<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\CapacityChecker;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(
        protected CapacityChecker $capacityChecker,
        protected SettingsService $settings
    ) {}

    /**
     * Daftar gudang beserta pemantauan kapasitas dan utilisasi real-time.
     */
    public function index()
    {
        $maxUtilizationRate = (float) $this->settings->get('max_warehouse_utilization', 0.85);
        $warehouses = Warehouse::orderBy('name')->get();

        $warehouseData = $warehouses->map(function ($wh) use ($maxUtilizationRate) {
            $items = Item::with(['activeParameter', 'classification', 'category'])
                ->where('warehouse_id', $wh->id)
                ->where('is_active', true)
                ->get();

            $totalCapacity = (float) $wh->capacity_m3;
            $limitSafetyM3 = $totalCapacity * $maxUtilizationRate;

            $currentVolumeM3 = 0.0;
            $maxPlannedVolumeM3 = 0.0;
            $totalInventoryValue = 0.0;

            foreach ($items as $item) {
                $volPerUnit = (float) $item->volume_m3;
                $onHand = (float) $item->stock_on_hand;
                $unitCost = (float) $item->unit_cost;

                $currentVolumeM3 += ($onHand * $volPerUnit);
                $totalInventoryValue += ($onHand * $unitCost);

                if ($param = $item->activeParameter) {
                    $maxPlannedVolumeM3 += ((float) $param->effective_max * $volPerUnit);
                } else {
                    $maxPlannedVolumeM3 += ($onHand * $volPerUnit);
                }
            }

            $currentUtilPct = $totalCapacity > 0 ? ($currentVolumeM3 / $totalCapacity) * 100 : 0;
            $maxPlannedUtilPct = $totalCapacity > 0 ? ($maxPlannedVolumeM3 / $totalCapacity) * 100 : 0;

            // Status Kapasitas
            if ($currentUtilPct > 100) {
                $status = 'CRITICAL';
                $statusLabel = 'Over Capacity (>100%)';
                $statusColor = 'rose';
            } elseif ($currentUtilPct >= ($maxUtilizationRate * 100)) {
                $status = 'WARNING';
                $statusLabel = 'Mendekati Batas (≥' . round($maxUtilizationRate * 100) . '%)';
                $statusColor = 'amber';
            } else {
                $status = 'SAFE';
                $statusLabel = 'Aman (<' . round($maxUtilizationRate * 100) . '%)';
                $statusColor = 'emerald';
            }

            // Top 5 SKU Pemakan Volume Terbesar
            $topConsumers = $items->sortByDesc(fn($i) => (float)$i->stock_on_hand * (float)$i->volume_m3)->take(5);

            return [
                'model'                  => $wh,
                'total_capacity'         => $totalCapacity,
                'limit_safety_m3'        => $limitSafetyM3,
                'current_volume_m3'      => $currentVolumeM3,
                'current_util_pct'       => $currentUtilPct,
                'max_planned_volume_m3'  => $maxPlannedVolumeM3,
                'max_planned_util_pct'   => $maxPlannedUtilPct,
                'total_inventory_value'  => $totalInventoryValue,
                'active_skus_count'      => $items->count(),
                'status'                 => $status,
                'status_label'           => $statusLabel,
                'status_color'           => $statusColor,
                'top_consumers'          => $topConsumers,
            ];
        });

        // Global summary
        $totalSystemCapacity = $warehouseData->sum('total_capacity');
        $totalSystemUsed = $warehouseData->sum('current_volume_m3');
        $totalSystemPlannedMax = $warehouseData->sum('max_planned_volume_m3');
        $systemUtilPct = $totalSystemCapacity > 0 ? ($totalSystemUsed / $totalSystemCapacity) * 100 : 0;

        return view('warehouses.index', compact(
            'warehouseData',
            'totalSystemCapacity',
            'totalSystemUsed',
            'totalSystemPlannedMax',
            'systemUtilPct',
            'maxUtilizationRate'
        ));
    }

    /**
     * Detail gudang: daftar seluruh SKU, utilisasi m3, dan simulasi reduksi kapasitas.
     */
    public function show(Warehouse $warehouse)
    {
        $maxUtilizationRate = (float) $this->settings->get('max_warehouse_utilization', 0.85);
        $totalCapacity = (float) $warehouse->capacity_m3;
        $limitSafetyM3 = $totalCapacity * $maxUtilizationRate;

        $items = Item::with(['activeParameter', 'classification', 'category'])
            ->where('warehouse_id', $warehouse->id)
            ->where('is_active', true)
            ->get();

        $currentVolumeM3 = 0.0;
        $maxPlannedVolumeM3 = 0.0;
        $totalInventoryValue = 0.0;

        $skuBreakdown = $items->map(function ($item) {
            $volPerUnit = (float) $item->volume_m3;
            $onHand = (float) $item->stock_on_hand;
            $unitCost = (float) $item->unit_cost;
            $effectiveMax = (float) ($item->activeParameter?->effective_max ?? $onHand);
            $effectiveRop = (float) ($item->activeParameter?->effective_rop ?? 0);

            $usedM3 = $onHand * $volPerUnit;
            $maxM3 = $effectiveMax * $volPerUnit;
            $invVal = $onHand * $unitCost;

            return [
                'item'          => $item,
                'on_hand'       => $onHand,
                'vol_per_unit'  => $volPerUnit,
                'used_m3'       => $usedM3,
                'effective_rop' => $effectiveRop,
                'effective_max' => $effectiveMax,
                'max_m3'        => $maxM3,
                'inv_val'       => $invVal,
                'abc_class'     => $item->classification?->abc_class ?? 'C',
            ];
        })->sortByDesc('used_m3');

        $currentVolumeM3 = $skuBreakdown->sum('used_m3');
        $maxPlannedVolumeM3 = $skuBreakdown->sum('max_m3');
        $totalInventoryValue = $skuBreakdown->sum('inv_val');

        $currentUtilPct = $totalCapacity > 0 ? ($currentVolumeM3 / $totalCapacity) * 100 : 0;
        $maxPlannedUtilPct = $totalCapacity > 0 ? ($maxPlannedVolumeM3 / $totalCapacity) * 100 : 0;

        // Jalankan evaluasi kapasitas (dry-run / preview status dari service)
        $evaluation = $this->capacityChecker->evaluateWarehouse($warehouse);

        return view('warehouses.show', compact(
            'warehouse',
            'totalCapacity',
            'limitSafetyM3',
            'currentVolumeM3',
            'currentUtilPct',
            'maxPlannedVolumeM3',
            'maxPlannedUtilPct',
            'totalInventoryValue',
            'skuBreakdown',
            'evaluation',
            'maxUtilizationRate'
        ));
    }

    /**
     * Tambah gudang baru (Admin only).
     */
    public function store(Request $request)
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:warehouses,name'],
            'capacity_m3' => ['required', 'numeric', 'gt:0'],
        ], [
            'name.required'        => 'Nama gudang wajib diisi.',
            'name.unique'          => 'Nama gudang sudah terdaftar.',
            'capacity_m3.required' => 'Kapasitas fisik gudang (m³) wajib diisi.',
            'capacity_m3.gt'       => 'Kapasitas fisik harus lebih besar dari 0 m³.',
        ]);

        Warehouse::create($validated);

        return back()->with('success', "Gudang '{$validated['name']}' berhasil ditambahkan dengan kapasitas {$validated['capacity_m3']} m³.");
    }

    /**
     * Perbarui kapasitas atau nama gudang (Admin only).
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', "unique:warehouses,name,{$warehouse->id}"],
            'capacity_m3' => ['required', 'numeric', 'gt:0'],
        ], [
            'name.required'        => 'Nama gudang wajib diisi.',
            'name.unique'          => 'Nama gudang sudah digunakan.',
            'capacity_m3.required' => 'Kapasitas gudang (m³) wajib diisi.',
            'capacity_m3.gt'       => 'Kapasitas gudang harus lebih besar dari 0 m³.',
        ]);

        $oldCap = $warehouse->capacity_m3;
        $warehouse->update($validated);

        return back()->with('success', "Kapasitas gudang '{$warehouse->name}' berhasil diperbarui dari {$oldCap} m³ menjadi {$warehouse->capacity_m3} m³.");
    }

    /**
     * Picu evaluasi penyesuaian kapasitas (CapacityChecker) on-demand.
     */
    public function triggerCheck(Warehouse $warehouse)
    {
        $this->authorize('manage-settings');

        try {
            $result = $this->capacityChecker->evaluateWarehouse($warehouse);

            if ($result['is_over_capacity']) {
                return back()->with('error', "PERINGATAN OVER CAPACITY: Total proyeksi volume MAX di {$warehouse->name} tetap melebihi limit aman kendati seluruh SKU sudah dipotong hingga batas minimum ROP + MOQ!");
            }

            if ($result['skus_adjusted'] > 0) {
                return back()->with('warning', "Penyesuaian Kapasitas Selesai: {$result['skus_adjusted']} SKU kelas C/B/A berhasil dipangkas MAX efektifnya agar muat dalam batas kapasitas 85%.");
            }

            return back()->with('success', "Evaluasi Kapasitas Selesai: Total volume rencana MAX di {$warehouse->name} aman dan berada di bawah limit kapasitas!");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal mengevaluasi kapasitas: " . $e->getMessage());
        }
    }
}
