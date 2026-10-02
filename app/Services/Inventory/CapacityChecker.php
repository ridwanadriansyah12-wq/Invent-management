<?php

namespace App\Services\Inventory;

use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CapacityChecker — Tahap 6: Pengecekan dan Penyesuaian Kapasitas Gudang.
 *
 * Logika:
 * 1. total_volume = SUM(effective_max_i * volume_m3_i) untuk semua SKU aktif di gudang.
 * 2. limit = warehouses.capacity_m3 * max_warehouse_utilization.
 * 3. Jika total_volume <= limit -> Selesai.
 * 4. Jika melebihi -> Kurangi effective_max secara proporsional berurutan:
 *    Kelas C -> Kelas B -> Kelas A.
 *    Batas bawah per SKU: effective_max >= effective_rop + moq.
 * 5. Jika seluruh SKU sudah di batas bawah dan masih melebihi -> Jangan paksa,
 *    tandai alert 'WAREHOUSE_OVER_CAPACITY' pada flag_reason.
 */
use App\Models\SystemAlert;
use App\Services\Settings\SettingsService;

class CapacityChecker
{
    public function __construct(
        protected ?SettingsService $settings = null
    ) {
        $this->settings = $this->settings ?? app(SettingsService::class);
    }
    /**
     * Jalankan evaluasi kapasitas untuk semua gudang atau gudang tertentu.
     *
     * @param Warehouse|null $warehouse Jika null, proses semua gudang aktif
     * @return array<int, array{warehouse_id: int, total_volume: float, limit: float, is_over_capacity: bool, skus_adjusted: int}>
     */
    public function checkAndAdjustAll(?Warehouse $warehouse = null): array
    {
        $warehouses = $warehouse ? collect([$warehouse]) : Warehouse::all();
        $results = [];

        foreach ($warehouses as $wh) {
            $results[$wh->id] = $this->evaluateWarehouse($wh);
        }

        return $results;
    }

    /**
     * Evaluasi kapasitas untuk satu gudang.
     */
    public function evaluateWarehouse(Warehouse $warehouse): array
    {
        $maxUtilization = (float) $this->settings->get('max_warehouse_utilization', 0.85);
        $limitM3 = (float) $warehouse->capacity_m3 * $maxUtilization;

        // Ambil semua item aktif pada gudang ini beserta activeParameter & classification
        $items = Item::with(['activeParameter', 'classification'])
            ->where('warehouse_id', $warehouse->id)
            ->where('is_active', true)
            ->get();

        // Filter item yang memiliki activeParameter dan volume_m3 valid
        /** @var Collection<int, array{item: Item, param: InventoryParameter, lower_bound: int, volume_m3: float, abc: string}> $skuData */
        $skuData = collect();

        foreach ($items as $item) {
            /** @var InventoryParameter|null $param */
            $param = $item->activeParameter;
            if (!$param || $param->effective_max <= 0) {
                continue;
            }

            $volumeM3 = (float) ($item->volume_m3 > 0 ? $item->volume_m3 : 0.001);
            $moq = (int) max(1, $item->moq ?? 1);
            $lowerBound = (int) ($param->effective_rop + $moq);
            $abcClass = strtoupper($item->classification?->abc_class ?? 'C');

            // Simpan snapshot sebelum reduksi kapasitas jika belum tersimpan
            if ($param->effective_max_before_capacity === null) {
                $param->effective_max_before_capacity = $param->effective_max;
            }

            $skuData->push([
                'item'        => $item,
                'param'       => $param,
                'lower_bound' => $lowerBound,
                'volume_m3'   => $volumeM3,
                'abc'         => $abcClass,
            ]);
        }

        $totalVolume = $this->calculateTotalVolume($skuData);

        if ($totalVolume <= $limitM3) {
            return [
                'warehouse_id'      => $warehouse->id,
                'total_volume'      => $totalVolume,
                'limit'             => $limitM3,
                'is_over_capacity'  => false,
                'skus_adjusted'     => 0,
            ];
        }

        // Jalankan pemotongan berjenjang C -> B -> A
        $adjustedCount = 0;
        $tiers = ['C', 'B', 'A'];

        foreach ($tiers as $tier) {
            $excessVolume = $totalVolume - $limitM3;
            if ($excessVolume <= 1e-6) {
                break;
            }

            $tierSkus = $skuData->where('abc', $tier);
            if ($tierSkus->isEmpty()) {
                continue;
            }

            // Hitung total kapasitas yang bisa dipangkas di tier ini
            $tierReducibleVolume = 0.0;
            foreach ($tierSkus as $entry) {
                $reducibleUnits = max(0, $entry['param']->effective_max - $entry['lower_bound']);
                $tierReducibleVolume += $reducibleUnits * $entry['volume_m3'];
            }

            if ($tierReducibleVolume <= 1e-6) {
                continue; // Tier ini sudah di lower bound semua
            }

            if ($excessVolume >= $tierReducibleVolume) {
                // Pangkas seluruh SKU di tier ini sampai ke batas bawahnya
                foreach ($tierSkus as $entry) {
                    $param = $entry['param'];
                    if ($param->effective_max > $entry['lower_bound']) {
                        $param->effective_max = $entry['lower_bound'];
                        $adjustedCount++;
                    }
                }
            } else {
                // Pangkas proporsional di tier ini
                $ratio = $excessVolume / $tierReducibleVolume;

                foreach ($tierSkus as $entry) {
                    $param = $entry['param'];
                    $reducibleUnits = max(0, $param->effective_max - $entry['lower_bound']);
                    if ($reducibleUnits <= 0) {
                        continue;
                    }

                    $unitCut = (int) ceil($reducibleUnits * $ratio);
                    $newMax = max($entry['lower_bound'], $param->effective_max - $unitCut);

                    if ($newMax !== $param->effective_max) {
                        $param->effective_max = $newMax;
                        $adjustedCount++;
                    }
                }
            }

            $totalVolume = $this->calculateTotalVolume($skuData);
        }

        // Periksa apakah setelah semua SKU diturunkan ke lower bound masih meluap
        $isOverCapacity = $totalVolume > $limitM3;

        DB::transaction(function () use ($skuData, $isOverCapacity) {
            foreach ($skuData as $entry) {
                $param = $entry['param'];
                $attributes = [
                    'effective_max'                 => $param->effective_max,
                    'effective_max_before_capacity' => $param->effective_max_before_capacity,
                ];

                if ($isOverCapacity) {
                    $attributes['flag_reason'] = $param->flag_reason
                        ? $param->flag_reason . '; WAREHOUSE_OVER_CAPACITY'
                        : 'WAREHOUSE_OVER_CAPACITY';
                }

                $param->update($attributes);
            }
        });

        if ($isOverCapacity) {
            Log::warning("CapacityChecker: Warehouse {$warehouse->name} (ID: {$warehouse->id}) over capacity after tier reductions. Volume: {$totalVolume} m3, Limit: {$limitM3} m3.");

            SystemAlert::firstOrCreate(
                [
                    'type'         => 'WAREHOUSE_OVER_CAPACITY',
                    'warehouse_id' => $warehouse->id,
                    'resolved_at'  => null,
                ],
                [
                    'message'      => "Kapasitas gudang {$warehouse->name} melebihi batas utilisasi maksimum ({$totalVolume} m³ / limit {$limitM3} m³).",
                ]
            );
        }

        return [
            'warehouse_id'      => $warehouse->id,
            'total_volume'      => $totalVolume,
            'limit'             => $limitM3,
            'is_over_capacity'  => $isOverCapacity,
            'skus_adjusted'     => $adjustedCount,
        ];
    }

    /**
     * Hitung total volume persediaan dari kumpulan SKU data.
     */
    protected function calculateTotalVolume(Collection $skuData): float
    {
        $sum = 0.0;
        foreach ($skuData as $entry) {
            $sum += ((float) $entry['param']->effective_max) * $entry['volume_m3'];
        }
        return $sum;
    }
}
