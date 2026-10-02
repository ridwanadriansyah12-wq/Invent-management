<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PurchaseRequisitionTrigger — Tahap 7: Pemicu Penerbitan Purchase Requisition (PR).
 *
 * Logika Harian:
 * 1. inventory_position = on_hand_stock + SUM(q_final PR berstatus OPEN/ORDERED yang belum diterima).
 * 2. Jika inventory_position <= effective_rop DAN belum ada PR OPEN untuk SKU tersebut:
 *    q_raw = effective_max - inventory_position.
 *    q_final = OrderQuantitySanitizer::sanitize(q_raw, moq, lot_size).
 * 3. Proyeksi Volume Cek:
 *    (q_final * volume_m3) + total_volume_berjalan <= kapasitas_gudang_limit.
 *    Jika melebihi: volume_flag = true, sertakan catatan review (tidak dibuang diam-diam).
 * 4. Simpan record purchase_requisitions.
 */
use App\Models\SystemAlert;
use App\Services\Settings\SettingsService;

class PurchaseRequisitionTrigger
{
    public function __construct(
        protected OrderQuantitySanitizer $sanitizer,
        protected ?SettingsService $settings = null
    ) {
        $this->settings = $this->settings ?? app(SettingsService::class);
    }

    /**
     * Jalankan evaluasi PR untuk semua item aktif atau item tertentu.
     *
     * @param Item|null $targetItem Jika null, proses seluruh item aktif
     * @return Collection<int, PurchaseRequisition> Daftar PR yang berhasil diterbitkan
     */
    public function evaluateAndTrigger(?Item $targetItem = null): Collection
    {
        $items = $targetItem
            ? collect([$targetItem])
            : Item::with(['activeParameter', 'warehouse'])
                ->where('is_active', true)
                ->get();

        $generatedPrs = collect();

        foreach ($items as $item) {
            $pr = $this->evaluateItem($item);
            if ($pr) {
                $generatedPrs->push($pr);
            }
        }

        return $generatedPrs;
    }

    /**
     * Evaluasi satu item untuk penerbitan PR.
     */
    public function evaluateItem(Item $item): ?PurchaseRequisition
    {
        $param = $item->activeParameter;

        if (!$param || $param->effective_rop <= 0) {
            return null;
        }

        // Cek apakah sudah ada PR berstatus OPEN untuk SKU ini (hanya boleh satu PR OPEN)
        $hasOpenPr = PurchaseRequisition::where('item_id', $item->id)
            ->where('status', 'OPEN')
            ->exists();

        if ($hasOpenPr) {
            return null;
        }

        // Hitung inventory_position = stock_on_hand + SUM(q_final PR OPEN / ORDERED)
        $outstandingPrQty = (float) PurchaseRequisition::where('item_id', $item->id)
            ->whereIn('status', ['OPEN', 'ORDERED'])
            ->sum('q_final');

        $inventoryPosition = (float) $item->stock_on_hand + $outstandingPrQty;

        // Trigger condition: inventory_position <= effective_rop
        if ($inventoryPosition > $param->effective_rop) {
            return null;
        }

        // q_raw = effective_max - inventory_position
        $qRaw = max(0.0, $param->effective_max - $inventoryPosition);

        $moq = (float) max(1, $item->moq ?? 1);
        $lotSize = (float) max(1, $item->lot_size ?? 1);

        // Sanitasi: Final Q = max( MOQ , ceil( q_raw / LotSize ) * LotSize )
        $qFinal = $this->sanitizer->sanitize($qRaw, $moq, $lotSize);

        if ($qFinal <= 0) {
            return null;
        }

        // Cek proyeksi volume gudang
        $warehouse = $item->warehouse;
        $volumeFlag = false;
        $needsReview = false;
        $flagReason = null;
        $notes = null;

        if ($warehouse) {
            $isOverLimit = $this->checkProjectedWarehouseCapacity($warehouse, $item, $qFinal);
            if ($isOverLimit) {
                $volumeFlag = true;
                $needsReview = true;
                $flagReason = 'VOLUME_EXCEEDS_CAPACITY';
                $notes = 'VOLUME_EXCEEDS_WAREHOUSE_CAPACITY';
                Log::warning("PurchaseRequisitionTrigger: PR for item {$item->sku} exceeds warehouse capacity limit.");

                SystemAlert::firstOrCreate(
                    [
                        'type'         => 'PR_CAPACITY_FLAG',
                        'item_id'      => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'resolved_at'  => null,
                    ],
                    [
                        'message'      => "PR SKU {$item->sku} diproyeksikan melebihi kapasitas gudang {$warehouse->name}.",
                    ]
                );
            }
        }

        return DB::transaction(function () use (
            $item, $param, $inventoryPosition, $qRaw, $qFinal, $moq, $lotSize, $volumeFlag, $needsReview, $flagReason, $notes
        ) {
            return PurchaseRequisition::create([
                'item_id'                => $item->id,
                'parameter_id'           => $param->id,
                'inventory_position'     => $inventoryPosition,
                'effective_rop_snapshot' => $param->effective_rop,
                'effective_max_snapshot' => $param->effective_max,
                'q_raw'                  => $qRaw,
                'q_final'                => $qFinal,
                'moq_snapshot'           => $moq,
                'lot_size_snapshot'      => $lotSize,
                'status'                 => 'OPEN',
                'volume_flag'            => $volumeFlag,
                'needs_review'           => $needsReview,
                'flag_reason'            => $flagReason,
                'notes'                  => $notes,
            ]);
        });
    }

    /**
     * Periksa apakah penambahan qFinal pada item membuat proyeksi volume gudang melebihi limit.
     */
    protected function checkProjectedWarehouseCapacity(Warehouse $warehouse, Item $candidateItem, int $qFinal): bool
    {
        $maxUtilization = (float) $this->settings->get('max_warehouse_utilization', 0.85);
        $capacityLimitM3 = (float) $warehouse->capacity_m3 * $maxUtilization;

        // Hitung total volume fisik saat ini di gudang + outstanding PR
        $itemsInWh = Item::where('warehouse_id', $warehouse->id)->get();
        $currentProjectedVolume = 0.0;

        foreach ($itemsInWh as $it) {
            $vol = (float) ($it->volume_m3 > 0 ? $it->volume_m3 : 0.001);
            $outstanding = (float) PurchaseRequisition::where('item_id', $it->id)
                ->whereIn('status', ['OPEN', 'ORDERED'])
                ->sum('q_final');
            $currentProjectedVolume += ((float) $it->stock_on_hand + $outstanding) * $vol;
        }

        $candidateVolumeM3 = (float) ($candidateItem->volume_m3 > 0 ? $candidateItem->volume_m3 : 0.001);
        $additionalVolume = $qFinal * $candidateVolumeM3;

        return ($currentProjectedVolume + $additionalVolume) > $capacityLimitM3;
    }
}
