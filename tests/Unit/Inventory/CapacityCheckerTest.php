<?php

namespace Tests\Unit\Inventory;

use App\Models\Category;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\ItemClassification;
use App\Models\Warehouse;
use App\Services\Inventory\CapacityChecker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CapacityCheckerTest extends TestCase
{
    use DatabaseTransactions;

    protected CapacityChecker $capacityChecker;
    protected Warehouse $warehouse;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capacityChecker = new CapacityChecker();

        $this->category = Category::create([
            'name' => 'Category Cap ' . uniqid(),
            'code' => 'CC' . rand(100, 999),
        ]);
    }

    /**
     * Test 4: Total volume melebihi limit -> Kelas C direduksi terlebih dahulu,
     * Kelas A terakhir; dan tidak ada SKU di bawah ROP + MOQ.
     */
    public function test_capacity_reduction_prioritizes_c_then_b_then_a_and_respects_lower_bounds(): void
    {
        // Kapasitas 115 m3, limit 85% = 97.75 m3
        $warehouse = Warehouse::create([
            'name'        => 'Wh-Capacity-Test-' . uniqid(),
            'capacity_m3' => 115.0,
        ]);

        $now = Carbon::now();

        // Buat SKU Kelas A, B, dan C (volume = 1.0 m3 tiap unit)
        // Masing-masing memiliki ROP = 20, MOQ = 10 -> lower bound = 30
        // Effective MAX awal = 40 (volume = 40 m3)
        // Total awal = 40 + 40 + 40 = 120 m3 > 97.75 m3 (kelebihan 22.25 m3)
        // Pemotongan:
        // C dapat dipotong 10 (40 -> 30). Sisa kelebihan = 12.25 m3.
        // B dapat dipotong 10 (40 -> 30). Sisa kelebihan = 2.25 m3.
        // A dipotong 3 (ceil of 2.25) -> 37.
        // Total baru = 30 + 30 + 37 = 97 <= 97.75 m3!

        $itemA = $this->createItemWithParam($warehouse, 'A', 20, 10, 40, $now);
        $itemB = $this->createItemWithParam($warehouse, 'B', 20, 10, 40, $now);
        $itemC = $this->createItemWithParam($warehouse, 'C', 20, 10, 40, $now);

        // Jalankan Capacity Checker
        $result = $this->capacityChecker->evaluateWarehouse($warehouse);

        $this->assertLessThanOrEqual(97.75, $result['total_volume']);
        $this->assertFalse($result['is_over_capacity']);

        // Refresh parameter dari database
        $paramA = $itemA->activeParameter()->first();
        $paramB = $itemB->activeParameter()->first();
        $paramC = $itemC->activeParameter()->first();

        // 1. Verifikasi Batas Bawah: Tidak ada SKU yang berada di bawah (ROP + MOQ) = 30
        $this->assertGreaterThanOrEqual(30, $paramA->effective_max);
        $this->assertGreaterThanOrEqual(30, $paramB->effective_max);
        $this->assertGreaterThanOrEqual(30, $paramC->effective_max);

        // 2. Verifikasi Urutan Prioritas Pemangkasan:
        // C terpangkas habis ke lower bound (30)
        $this->assertEquals(30, $paramC->effective_max, 'Kelas C harus terpangkas terlebih dahulu ke lower bound.');

        // B terpangkas habis ke lower bound (30)
        $this->assertEquals(30, $paramB->effective_max, 'Kelas B harus terpangkas berikutnya ke lower bound.');

        // A dipotong terakhir (dari 40 menjadi 37)
        $this->assertEquals(37, $paramA->effective_max, 'Kelas A dipangkas paling terakhir.');
    }

    /**
     * Test saat seluruh SKU telah mencapai batas bawah namun masih melebihi kapasitas:
     * Sistem tidak memaksa di bawah ROP+MOQ dan menandai alert WAREHOUSE_OVER_CAPACITY.
     */
    public function test_over_capacity_alert_when_all_skus_at_lower_bounds(): void
    {
        // Kapasitas sangat kecil: 100 m3 -> limit 85 m3
        // Namun sum of lower bounds = 30 + 30 + 30 = 90 m3 > 85 m3
        $warehouse = Warehouse::create([
            'name'        => 'Wh-Over-Cap-' . uniqid(),
            'capacity_m3' => 100.0,
        ]);

        $now = Carbon::now();

        $itemA = $this->createItemWithParam($warehouse, 'A', 20, 10, 40, $now);
        $itemB = $this->createItemWithParam($warehouse, 'B', 20, 10, 40, $now);
        $itemC = $this->createItemWithParam($warehouse, 'C', 20, 10, 40, $now);

        $result = $this->capacityChecker->evaluateWarehouse($warehouse);

        $this->assertTrue($result['is_over_capacity']);
        $this->assertEquals(90.0, $result['total_volume']);

        $paramA = $itemA->activeParameter()->first();
        $this->assertEquals(30, $paramA->effective_max);
        $this->assertStringContainsString('WAREHOUSE_OVER_CAPACITY', $paramA->flag_reason);
    }

    /**
     * Test jika kelebihan volume kecil, hanya Kelas C yang terpotong dan Kelas A & B tidak terpengaruh.
     */
    public function test_minor_excess_only_reduces_class_c(): void
    {
        // Kapasitas 120 m3, limit 85% = 102.0 m3
        $warehouse = Warehouse::create([
            'name'        => 'Wh-Minor-Test-' . uniqid(),
            'capacity_m3' => 120.0,
        ]);

        $now = Carbon::now();

        // Total volume = 40 + 40 + 40 = 120 m3. Kelebihan = 120 - 102 = 18 m3.
        // Kelas C memiliki kapasitas pemotongan 40 - 10 = 30 m3 (ROP=5, MOQ=5, Lower=10).
        // Sehingga kelebihan 18 m3 cukup dipotong HANYA dari Kelas C!
        $itemA = $this->createItemWithParam($warehouse, 'A', 5, 5, 40, $now);
        $itemB = $this->createItemWithParam($warehouse, 'B', 5, 5, 40, $now);
        $itemC = $this->createItemWithParam($warehouse, 'C', 5, 5, 40, $now);

        $result = $this->capacityChecker->evaluateWarehouse($warehouse);

        $this->assertLessThanOrEqual(102.0, $result['total_volume']);

        $paramA = $itemA->activeParameter()->first();
        $paramB = $itemB->activeParameter()->first();
        $paramC = $itemC->activeParameter()->first();

        // Kelas A dan B TIDAK tersentuh (tetap 40)
        $this->assertEquals(40, $paramA->effective_max, 'Kelas A tidak boleh dipotong jika C masih bisa mengompensasi.');
        $this->assertEquals(40, $paramB->effective_max, 'Kelas B tidak boleh dipotong jika C masih bisa mengompensasi.');

        // Kelas C berkurang dari 40
        $this->assertLessThan(40, $paramC->effective_max, 'Kelas C harus dipotong untuk menutupi kelebihan volume.');
        $this->assertGreaterThanOrEqual(10, $paramC->effective_max);
    }

    private function createItemWithParam(Warehouse $wh, string $abcClass, int $rop, int $moq, int $max, Carbon $now): Item
    {
        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $wh->id,
            'sku'                 => "SKU-CAP-{$abcClass}-" . uniqid(),
            'name'                => "Item {$abcClass}",
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 1.0,
            'moq'                 => $moq,
            'lot_size'            => 1,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        ItemClassification::create([
            'item_id'        => $item->id,
            'computed_at'    => $now,
            'abc_class'      => $abcClass,
            'xyz_class'      => 'X',
            'adi'            => 1.0,
            'cv2'            => 0.2,
            'demand_pattern' => 'smooth',
            'annual_value'   => 1000000,
        ]);

        InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now,
            'source'                         => 'ML',
            'proposed_ss'                    => 10,
            'proposed_rop'                   => $rop,
            'proposed_max'                   => $max,
            'effective_ss'                   => 10,
            'effective_rop'                  => $rop,
            'effective_max'                  => $max,
            'effective_max_before_capacity'  => $max,
            'status'                         => 'ACTIVE',
        ]);

        return $item;
    }
}
