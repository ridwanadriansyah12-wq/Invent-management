<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\MonthlyUsage;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MLInventoryEngine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────────────────
        User::insert([
            [
                'name'       => 'IT Administrator',
                'email'      => 'admin@rop.com',
                'password'   => Hash::make('password'),
                'role'       => 'it',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Staff Procurement',
                'email'      => 'procurement@rop.com',
                'password'   => Hash::make('password'),
                'role'       => 'procurement',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Staff Gudang',
                'email'      => 'gudang@rop.com',
                'password'   => Hash::make('password'),
                'role'       => 'gudang',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // ── Categories ─────────────────────────────────────────────────────
        $catOffice   = Category::create(['name' => 'Alat Tulis Kantor', 'description' => 'Perlengkapan administrasi']);
        $catCleaning = Category::create(['name' => 'Kebersihan',        'description' => 'Perlengkapan kebersihan']);
        $catElectric = Category::create(['name' => 'Elektronik',        'description' => 'Peralatan elektronik']);
        $catSafety   = Category::create(['name' => 'Safety & K3',       'description' => 'Alat pelindung diri']);

        // ── Suppliers ──────────────────────────────────────────────────────
        $sup1 = Supplier::create(['name' => 'PT. Sumber Barang Utama', 'contact_person' => 'Budi', 'phone' => '0812-1234-5678', 'email' => 'budi@sbu.co.id']);
        $sup2 = Supplier::create(['name' => 'CV. Maju Bersama',        'contact_person' => 'Sari', 'phone' => '0813-9876-5432', 'email' => 'sari@maju.co.id']);

        // ── Items ──────────────────────────────────────────────────────────
        $items = [
            ['category_id' => $catOffice->id,   'supplier_id' => $sup1->id, 'code' => 'ATK-001', 'name' => 'Kertas HVS A4 80gr',        'unit' => 'rim',  'stock_on_hand' => 45,  'lead_time_days' => 5,  'coverage_period' => 30],
            ['category_id' => $catOffice->id,   'supplier_id' => $sup1->id, 'code' => 'ATK-002', 'name' => 'Pulpen Ballpoint Hitam',     'unit' => 'lusin','stock_on_hand' => 8,   'lead_time_days' => 3,  'coverage_period' => 30],
            ['category_id' => $catOffice->id,   'supplier_id' => $sup2->id, 'code' => 'ATK-003', 'name' => 'Map Snelhechter',             'unit' => 'pcs',  'stock_on_hand' => 120, 'lead_time_days' => 7,  'coverage_period' => 60],
            ['category_id' => $catCleaning->id, 'supplier_id' => $sup2->id, 'code' => 'KBR-001', 'name' => 'Sabun Cuci Tangan Botol',    'unit' => 'botol','stock_on_hand' => 5,   'lead_time_days' => 4,  'coverage_period' => 30],
            ['category_id' => $catCleaning->id, 'supplier_id' => $sup2->id, 'code' => 'KBR-002', 'name' => 'Tisu Tissue Box 200 lembar', 'unit' => 'box',  'stock_on_hand' => 0,   'lead_time_days' => 3,  'coverage_period' => 30],
            ['category_id' => $catElectric->id, 'supplier_id' => $sup1->id, 'code' => 'ELK-001', 'name' => 'Baterai AA Alkaline',         'unit' => 'pack', 'stock_on_hand' => 22,  'lead_time_days' => 7,  'coverage_period' => 30],
            ['category_id' => $catSafety->id,   'supplier_id' => $sup1->id, 'code' => 'K3-001',  'name' => 'Masker N95',                  'unit' => 'box',  'stock_on_hand' => 3,   'lead_time_days' => 10, 'coverage_period' => 30],
        ];

        foreach ($items as $data) {
            Item::create($data);
        }

        // ── Seed Monthly Usage (simulate 12 months of historical data) ───
        $allItems = Item::all();
        $ml       = new MLInventoryEngine();

        foreach ($allItems as $item) {
            // Generate 12 months of random usage data
            for ($m = 11; $m >= 0; $m--) {
                $date      = Carbon::now()->subMonths($m);
                $baseUsage = rand(10, 80);
                $totalOut  = $baseUsage + rand(-5, 5); // some variation

                MonthlyUsage::create([
                    'item_id'       => $item->id,
                    'year'          => $date->year,
                    'month'         => $date->month,
                    'total_out'     => max(0, $totalOut),
                    'max_daily_out' => round($totalOut / 25, 2),
                ]);
            }

            // Run ML Engine to compute SS, ROP, Max
            $ml->recalculate($item->fresh());
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('📧 Akun yang tersedia:');
        $this->command->info('   IT Admin    : admin@rop.com / password');
        $this->command->info('   Procurement : procurement@rop.com / password');
        $this->command->info('   Gudang      : gudang@rop.com / password');
    }
}
