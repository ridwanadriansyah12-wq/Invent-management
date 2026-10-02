<?php

namespace Tests\Unit\Inventory;

use App\Models\Category;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\Warehouse;
use App\Services\Inventory\OrderQuantitySanitizer;
use App\Services\Inventory\PurchaseRequisitionTrigger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseRequisitionTriggerTest extends TestCase
{
    use DatabaseTransactions;

    protected PurchaseRequisitionTrigger $trigger;
    protected Warehouse $warehouse;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trigger = new PurchaseRequisitionTrigger(new OrderQuantitySanitizer());

        $this->category = Category::create([
            'name' => 'Category PR ' . uniqid(),
            'code' => 'CPR' . rand(100, 999),
        ]);

        $this->warehouse = Warehouse::create([
            'name'        => 'Warehouse PR ' . uniqid(),
            'capacity_m3' => 500.0,
        ]);
    }

    /**
     * Test penerbitan PR ketika inventory_position <= effective_rop.
     */
    public function test_pr_triggered_when_stock_at_or_below_rop(): void
    {
        $now = Carbon::now();

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-TRIGGER-' . uniqid(),
            'name'                => 'Item Trigger',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.01,
            'stock_on_hand'       => 20, // Di bawah ROP (50)
            'moq'                 => 60,
            'lot_size'            => 12,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        $param = InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now,
            'source'                         => 'ML',
            'proposed_ss'                    => 15,
            'proposed_rop'                   => 50,
            'proposed_max'                   => 120,
            'effective_ss'                   => 15,
            'effective_rop'                  => 50,
            'effective_max'                  => 120,
            'effective_max_before_capacity'  => 120,
            'status'                         => 'ACTIVE',
        ]);

        $pr = $this->trigger->evaluateItem($item);

        $this->assertNotNull($pr);
        $this->assertEquals($item->id, $pr->item_id);
        $this->assertEquals($param->id, $pr->parameter_id);
        $this->assertEquals(20.0, $pr->inventory_position);
        // q_raw = 120 - 20 = 100
        $this->assertEquals(100.0, $pr->q_raw);
        // Sanitizer: MOQ=50, lot=12 -> ceil(100/12)*12 = 9*12 = 108. max(50, 108) = 108
        $this->assertEquals(108.0, $pr->q_final);
        $this->assertEquals('OPEN', $pr->status);
        $this->assertFalse($pr->volume_flag);
    }

    /**
     * Test tidak menerbitkan PR jika inventory_position > effective_rop.
     */
    public function test_no_pr_triggered_when_stock_above_rop(): void
    {
        $now = Carbon::now();

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-ABOVE-' . uniqid(),
            'name'                => 'Item Above ROP',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.01,
            'stock_on_hand'       => 80, // Di atas ROP (50)
            'moq'                 => 10,
            'lot_size'            => 1,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now,
            'source'                         => 'ML',
            'proposed_ss'                    => 15,
            'proposed_rop'                   => 50,
            'proposed_max'                   => 120,
            'effective_ss'                   => 15,
            'effective_rop'                  => 50,
            'effective_max'                  => 120,
            'effective_max_before_capacity'  => 120,
            'status'                         => 'ACTIVE',
        ]);

        $pr = $this->trigger->evaluateItem($item);
        $this->assertNull($pr);
    }

    /**
     * Invariant: Tidak membuat PR baru jika sudah ada PR berstatus OPEN untuk SKU tsb.
     */
    public function test_does_not_create_duplicate_open_pr(): void
    {
        $now = Carbon::now();

        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $this->warehouse->id,
            'sku'                 => 'SKU-DUP-' . uniqid(),
            'name'                => 'Item Duplicate Open PR',
            'unit'                => 'pcs',
            'unit_cost'           => 10000,
            'volume_m3'           => 0.01,
            'stock_on_hand'       => 10,
            'moq'                 => 10,
            'lot_size'            => 1,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        $param = InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now,
            'source'                         => 'ML',
            'proposed_ss'                    => 15,
            'proposed_rop'                   => 50,
            'proposed_max'                   => 120,
            'effective_ss'                   => 15,
            'effective_rop'                  => 50,
            'effective_max'                  => 120,
            'effective_max_before_capacity'  => 120,
            'status'                         => 'ACTIVE',
        ]);

        // Buat PR OPEN eksisting
        PurchaseRequisition::create([
            'item_id'                => $item->id,
            'parameter_id'           => $param->id,
            'inventory_position'     => 10,
            'effective_rop_snapshot' => 50,
            'effective_max_snapshot' => 120,
            'q_raw'                  => 110,
            'q_final'                => 110,
            'moq_snapshot'           => 10,
            'lot_size_snapshot'      => 1,
            'status'                 => 'OPEN',
        ]);

        $pr = $this->trigger->evaluateItem($item);
        $this->assertNull($pr, 'Harus mengembalikan null karena sudah ada PR OPEN.');
    }

    /**
     * Test volume flag saat pesanan membuat total proyeksi volume gudang melebihi limit.
     */
    public function test_pr_sets_volume_flag_when_exceeding_warehouse_capacity(): void
    {
        $now = Carbon::now();

        // Warehouse sangat kecil: 10 m3 -> limit 8.5 m3
        $tinyWarehouse = Warehouse::create([
            'name'        => 'Tiny WH ' . uniqid(),
            'capacity_m3' => 10.0,
        ]);

        // Item bervolume besar: 1 unit = 1 m3, stock = 0, ROP = 5, MAX = 15
        // q_raw = 15 -> volume = 15 m3 > 8.5 m3 limit!
        $item = Item::create([
            'category_id'         => $this->category->id,
            'warehouse_id'        => $tinyWarehouse->id,
            'sku'                 => 'SKU-HEAVY-' . uniqid(),
            'name'                => 'Item Heavy',
            'unit'                => 'pcs',
            'unit_cost'           => 100000,
            'volume_m3'           => 1.0,
            'stock_on_hand'       => 0,
            'moq'                 => 1,
            'lot_size'            => 1,
            'lead_time_days'      => 15,
            'first_movement_date' => $now->copy()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        $param = InventoryParameter::create([
            'item_id'                        => $item->id,
            'computed_at'                    => $now,
            'source'                         => 'ML',
            'proposed_ss'                    => 2,
            'proposed_rop'                   => 5,
            'proposed_max'                   => 15,
            'effective_ss'                   => 2,
            'effective_rop'                  => 5,
            'effective_max'                  => 15,
            'effective_max_before_capacity'  => 15,
            'status'                         => 'ACTIVE',
        ]);

        $pr = $this->trigger->evaluateItem($item);

        $this->assertNotNull($pr);
        $this->assertTrue($pr->volume_flag);
        $this->assertEquals('VOLUME_EXCEEDS_WAREHOUSE_CAPACITY', $pr->notes);
    }
}
