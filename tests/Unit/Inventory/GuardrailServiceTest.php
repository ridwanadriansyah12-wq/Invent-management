<?php

namespace Tests\Unit\Inventory;

use App\Models\Category;
use App\Models\CategoryDefault;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\GuardrailService;
use App\Services\Inventory\ParameterCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GuardrailServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected GuardrailService $guardrailService;
    protected Category $category;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardrailService = new GuardrailService(new ParameterCalculator());

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'code' => 'CAT' . rand(100, 999),
        ]);

        CategoryDefault::create([
            'category_id'      => $this->category->id,
            'avg_daily_demand' => 5.0,
            'safety_days'      => 7.0,
            'source'           => 'manual',
        ]);

        $this->warehouse = Warehouse::create([
            'name'        => 'Test Warehouse ' . uniqid(),
            'capacity_m3' => 1000.0,
        ]);
    }

    /**
     * Test 5.1: SKU berumur 29 hari (< 30 hari) diarahkan ke STATIC_CATEGORY.
     */
    public function test_sku_age_29_days_routes_to_static_category(): void
    {
        $now = Carbon::parse('2026-09-24 10:00:00');

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-AGE-29-' . uniqid(),
            'name'                => 'Item Age 29',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.05,
            'moq'                 => 10,
            'lot_size'            => 5,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(29)->toDateString(),
            'is_active'           => true,
        ]);

        $mlForecast = [
            'mu_daily'    => 20.0,
            'sigma_daily' => 5.0,
        ];

        $param = $this->guardrailService->processItem($item, $mlForecast, $now);

        $this->assertEquals('STATIC_CATEGORY', $param->source);
        $this->assertEquals('ACTIVE', $param->status);
        // avg_daily = 5.0, safety_days = 7.0, LT = 15
        // SS = ceil(5 * 7) = 35
        // ROP = ceil(5 * 15 + 35) = 110
        $this->assertEquals(35, $param->effective_ss);
        $this->assertEquals(110, $param->effective_rop);
    }

    /**
     * Test 5.2: SKU berumur 30 hari (>= 30 hari) menggunakan output ML.
     */
    public function test_sku_age_30_days_routes_to_ml(): void
    {
        $now = Carbon::parse('2026-09-24 10:00:00');

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-AGE-30-' . uniqid(),
            'name'                => 'Item Age 30',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.05,
            'moq'                 => 10,
            'lot_size'            => 5,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(30)->toDateString(),
            'is_active'           => true,
        ]);

        // Baseline statis: CategoryDefault avg=5, safety=7, LT=15 -> ROP=110
        // ML: mu=5.0, sigma=1.0, LT=15, Class C (Z≈1.2816)
        // variance = 15*1 = 15, sqrt(15)≈3.873, SS=ceil(1.2816*3.873)=5, ROP=ceil(5*15+5)=80
        // lower = 0.5 * 110 = 55, upper = 1.5 * 110 = 165
        // 80 berada di dalam [55, 165] -> ACTIVE
        $mlForecast = [
            'mu_daily'    => 5.0,
            'sigma_daily' => 1.0,
        ];

        $param = $this->guardrailService->processItem($item, $mlForecast, $now);

        $this->assertEquals('ML', $param->source);
        $this->assertEquals('ACTIVE', $param->status);
        $this->assertEquals(80, $param->effective_rop);
    }

    /**
     * Test 5.3: Clamping saat ROP melonjak > 50% (1.6x baseline):
     * Status PENDING_REVIEW, effective_rop = 1.5x baseline, flag = ROP_DEVIATION_GT_50PCT.
     */
    public function test_rop_spike_gt_50pct_is_clamped_to_150pct(): void
    {
        $now = Carbon::parse('2026-09-24 10:00:00');

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-SPIKE-' . uniqid(),
            'name'                => 'Item Spike',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.05,
            'moq'                 => 10,
            'lot_size'            => 5,
            'lead_time_days'      => 10,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        // Buat baseline historis ROP = 100
        InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now->copy()->subDays(5),
            'source'                         => 'ML',
            'proposed_ss'                    => 20,
            'proposed_rop'                   => 100,
            'proposed_max'                   => 250,
            'effective_ss'                   => 20,
            'effective_rop'                  => 100,
            'effective_max'                  => 250,
            'effective_max_before_capacity'  => 250,
            'status'                         => 'ACTIVE',
        ]);

        // ML menghasilkan usulan ROP = 160 (1.6x baseline)
        // mu_daily = 14, sigma = 2, LT = 10 -> ROP ≈ 140 + SS ≈ 160
        $mlForecast = [
            'mu_daily'    => 14.5,
            'sigma_daily' => 3.0,
        ];

        $param = $this->guardrailService->processItem($item, $mlForecast, $now);

        // Baseline = 100, Upper = 1.5 * 100 = 150
        $this->assertEquals('PENDING_REVIEW', $param->status);
        $this->assertEquals('ROP_DEVIATION_GT_50PCT', $param->flag_reason);
        $this->assertEquals(150, $param->effective_rop);
        $this->assertGreaterThan(150, $param->proposed_rop);
    }

    /**
     * Test 5.4: Clamping saat ROP anjlok (0.4x baseline):
     * Status PENDING_REVIEW, effective_rop = 0.5x baseline, flag = ROP_DEVIATION_GT_50PCT.
     */
    public function test_rop_drop_lt_50pct_is_clamped_to_50pct(): void
    {
        $now = Carbon::parse('2026-09-24 10:00:00');

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-DROP-' . uniqid(),
            'name'                => 'Item Drop',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.05,
            'moq'                 => 10,
            'lot_size'            => 5,
            'lead_time_days'      => 10,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        // Buat baseline historis ROP = 100
        InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now->copy()->subDays(5),
            'source'                         => 'ML',
            'proposed_ss'                    => 20,
            'proposed_rop'                   => 100,
            'proposed_max'                   => 250,
            'effective_ss'                   => 20,
            'effective_rop'                  => 100,
            'effective_max'                  => 250,
            'effective_max_before_capacity'  => 250,
            'status'                         => 'ACTIVE',
        ]);

        // ML menghasilkan usulan ROP = 40 (0.4x baseline)
        $mlForecast = [
            'mu_daily'    => 2.5,
            'sigma_daily' => 1.0,
        ];

        $param = $this->guardrailService->processItem($item, $mlForecast, $now);

        // Baseline = 100, Lower = 0.5 * 100 = 50
        $this->assertEquals('PENDING_REVIEW', $param->status);
        $this->assertEquals('ROP_DEVIATION_GT_50PCT', $param->flag_reason);
        $this->assertEquals(50, $param->effective_rop);
        $this->assertLessThan(50, $param->proposed_rop);
    }
}
