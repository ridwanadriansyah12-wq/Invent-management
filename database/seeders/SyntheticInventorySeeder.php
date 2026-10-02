<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryDefault;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SyntheticInventorySeeder — Menghasilkan dataset sintetis untuk validasi & pengujian:
 * 1. SKU Smooth (demand teratur, CV2 rendah, ADI < 1.32)
 * 2. SKU Intermittent (demand berjeda, ADI >= 1.32, CV2 < 0.49)
 * 3. SKU Lumpy (demand sporadis & ukuran lonjakan besar, ADI >= 1.32, CV2 >= 0.49)
 * 4. SKU Censored / Stockout (periode kehabisan barang opening_stock = 0 & issued = 0)
 * 5. SKU Baru umur < 30 hari (untuk menguji jalur STATIC_CATEGORY)
 * 6. SKU ROP Melonjak > 50% (untuk menguji jalur PENDING_REVIEW & Clamping)
 */
class SyntheticInventorySeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::parse('2026-09-24');

        // 1. Gudang Sintetis
        $warehouse = Warehouse::firstOrCreate(
            ['name' => 'Gudang Pusat Karawang'],
            ['capacity_m3' => 1500.0]
        );

        // User Sistem untuk audit log pergerakan stok
        $systemUser = \App\Models\User::firstOrCreate(
            ['email' => 'system@inventory.local'],
            [
                'name'      => 'System Automated Bot',
                'password'  => bcrypt('secret123'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
        $userId = $systemUser->id;

        // 2. Kategori Sintetis & Default
        $catElectronics = Category::firstOrCreate(
            ['name' => 'Elektronik & Kelistrikan']
        );
        CategoryDefault::updateOrCreate(
            ['category_id' => $catElectronics->id],
            [
                'avg_daily_demand' => 8.0,
                'safety_days'      => 7.0,
                'source'           => 'historical',
            ]
        );

        $catFasteners = Category::firstOrCreate(
            ['name' => 'Baut, Mur & Fastener']
        );
        CategoryDefault::updateOrCreate(
            ['category_id' => $catFasteners->id],
            [
                'avg_daily_demand' => 50.0,
                'safety_days'      => 10.0,
                'source'           => 'historical',
            ]
        );

        // ── 1. SKU Smooth (Demand Stabil Harian) ──────────────────────────────
        $itemSmooth = Item::updateOrCreate(
            ['sku' => 'SKU-SMOOTH-01'],
            [
                'name'                => 'Relay 12V 40A Heavy Duty',
                'category_id'         => $catElectronics->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'pcs',
                'unit_cost'           => 25000,
                'volume_m3'           => 0.002,
                'stock_on_hand'       => 150,
                'moq'                 => 50,
                'lot_size'            => 10,
                'lead_time_days'      => 14,
                'lead_time_std_days'  => 1.0,
                'first_movement_date' => $today->copy()->subDays(120)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsSmooth($itemSmooth, $today, 120, $userId);

        // ── 2. SKU Intermittent (Permintaan Berjeda, Ukuran Mirip) ────────────
        $itemIntermittent = Item::updateOrCreate(
            ['sku' => 'SKU-INTERMITTENT-01'],
            [
                'name'                => 'Sensor Proximity M18 PNP',
                'category_id'         => $catElectronics->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'pcs',
                'unit_cost'           => 120000,
                'volume_m3'           => 0.003,
                'stock_on_hand'       => 40,
                'moq'                 => 10,
                'lot_size'            => 5,
                'lead_time_days'      => 21,
                'lead_time_std_days'  => 2.0,
                'first_movement_date' => $today->copy()->subDays(120)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsIntermittent($itemIntermittent, $today, 120, $userId);

        // ── 3. SKU Lumpy (Permintaan Sporadis & Ukuran Berfluktuasi) ───────────
        $itemLumpy = Item::updateOrCreate(
            ['sku' => 'SKU-LUMPY-01'],
            [
                'name'                => 'Inverter Variable Frequency Drive 5.5kW',
                'category_id'         => $catElectronics->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'unit',
                'unit_cost'           => 4500000,
                'volume_m3'           => 0.045,
                'stock_on_hand'       => 8,
                'moq'                 => 4,
                'lot_size'            => 2,
                'lead_time_days'      => 30,
                'lead_time_std_days'  => 5.0,
                'first_movement_date' => $today->copy()->subDays(120)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsLumpy($itemLumpy, $today, 120, $userId);

        // ── 4. SKU Stockout / Censored (Mengalami Periode Kosong) ─────────────
        $itemCensored = Item::updateOrCreate(
            ['sku' => 'SKU-CENSORED-01'],
            [
                'name'                => 'Baut Baja M10 x 50 Grade 8.8',
                'category_id'         => $catFasteners->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'box',
                'unit_cost'           => 85000,
                'volume_m3'           => 0.005,
                'stock_on_hand'       => 0, // Sedang kosong
                'moq'                 => 20,
                'lot_size'            => 5,
                'lead_time_days'      => 10,
                'lead_time_std_days'  => 1.0,
                'first_movement_date' => $today->copy()->subDays(90)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsCensored($itemCensored, $today, 90, $userId);

        // ── 5. SKU Baru (Umur < 30 Hari -> Wajib STATIC_CATEGORY) ─────────────
        $itemNew = Item::updateOrCreate(
            ['sku' => 'SKU-NEW-01'],
            [
                'name'                => 'Terminal Block Din Rail 4mm (Baru Rilis)',
                'category_id'         => $catElectronics->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'pcs',
                'unit_cost'           => 7500,
                'volume_m3'           => 0.0005,
                'stock_on_hand'       => 200,
                'moq'                 => 100,
                'lot_size'            => 50,
                'lead_time_days'      => 15,
                'lead_time_std_days'  => 0.0,
                'first_movement_date' => $today->copy()->subDays(12)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsNew($itemNew, $today, 12, $userId);

        // ── 6. SKU ROP Spike > 50% (Memicu PENDING_REVIEW & Clamping) ─────────
        $itemSpike = Item::updateOrCreate(
            ['sku' => 'SKU-SPIKE-01'],
            [
                'name'                => 'MCB 3-Phase 32A Schneider',
                'category_id'         => $catElectronics->id,
                'warehouse_id'        => $warehouse->id,
                'unit'                => 'pcs',
                'unit_cost'           => 185000,
                'volume_m3'           => 0.004,
                'stock_on_hand'       => 15,
                'moq'                 => 12,
                'lot_size'            => 6,
                'lead_time_days'      => 14,
                'lead_time_std_days'  => 2.0,
                'first_movement_date' => $today->copy()->subDays(90)->toDateString(),
                'is_active'           => true,
            ]
        );
        $this->seedMovementsSpike($itemSpike, $today, 90, $userId);
    }

    private function seedMovementsSmooth(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 1500,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            $qty = 8 + ($i % 5);
            StockMovement::create([
                'item_id'       => $item->id,
                'user_id'       => $userId,
                'movement_date' => $today->copy()->subDays($i)->toDateString(),
                'type'          => 'OUT',
                'reason'        => 'ISSUE',
                'qty'           => $qty,
            ]);
        }
    }

    private function seedMovementsIntermittent(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 300,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            if ($i % 3 === 0) {
                StockMovement::create([
                    'item_id'       => $item->id,
                    'user_id'       => $userId,
                    'movement_date' => $today->copy()->subDays($i)->toDateString(),
                    'type'          => 'OUT',
                    'reason'        => 'ISSUE',
                    'qty'           => 5,
                ]);
            }
        }
    }

    private function seedMovementsLumpy(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 50,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            if ($i % 7 === 0) {
                $qty = ($i % 14 === 0) ? 6 : 2;
                StockMovement::create([
                    'item_id'       => $item->id,
                    'user_id'       => $userId,
                    'movement_date' => $today->copy()->subDays($i)->toDateString(),
                    'type'          => 'OUT',
                    'reason'        => 'ISSUE',
                    'qty'           => $qty,
                ]);
            }
        }
    }

    private function seedMovementsCensored(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 200,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            if ($i > 10) {
                StockMovement::create([
                    'item_id'       => $item->id,
                    'user_id'       => $userId,
                    'movement_date' => $today->copy()->subDays($i)->toDateString(),
                    'type'          => 'OUT',
                    'reason'        => 'ISSUE',
                    'qty'           => 2.5,
                ]);
            }
        }
    }

    private function seedMovementsNew(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 300,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            StockMovement::create([
                'item_id'       => $item->id,
                'user_id'       => $userId,
                'movement_date' => $today->copy()->subDays($i)->toDateString(),
                'type'          => 'OUT',
                'reason'        => 'ISSUE',
                'qty'           => 5,
            ]);
        }
    }

    private function seedMovementsSpike(Item $item, Carbon $today, int $days, int $userId): void
    {
        StockMovement::where('item_id', $item->id)->delete();
        StockMovement::create([
            'item_id'       => $item->id,
            'user_id'       => $userId,
            'movement_date' => $today->copy()->subDays($days)->toDateString(),
            'type'          => 'IN',
            'reason'        => 'RECEIPT',
            'qty'           => 500,
        ]);

        for ($i = $days - 1; $i >= 0; $i--) {
            $qty = ($i <= 5) ? 25 : 3;
            StockMovement::create([
                'item_id'       => $item->id,
                'user_id'       => $userId,
                'movement_date' => $today->copy()->subDays($i)->toDateString(),
                'type'          => 'OUT',
                'reason'        => 'ISSUE',
                'qty'           => $qty,
            ]);
        }
    }
}
