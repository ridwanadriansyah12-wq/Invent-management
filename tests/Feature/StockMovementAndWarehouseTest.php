<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementAndWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Warehouse $warehouse;
    protected Category $category;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.local',
            'password' => bcrypt('secret123'),
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Gudang Utama Test',
            'capacity_m3' => 100.0,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Uji',
        ]);

        $this->item = Item::create([
            'sku' => 'TEST-001',
            'name' => 'Barang Uji Logistik',
            'category_id' => $this->category->id,
            'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs',
            'stock_on_hand' => 50.0,
            'unit_cost' => 10000.0,
            'volume_m3' => 0.05,
            'moq' => 10,
            'lot_size' => 5,
            'lead_time_days' => 15,
            'is_active' => true,
        ]);
    }

    public function test_stock_movement_ledger_index_renders_successfully(): void
    {
        StockMovement::create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'type' => 'IN',
            'reason' => 'RECEIPT',
            'qty' => 20.0,
            'stock_before' => 30.0,
            'stock_after' => 50.0,
            'reference_no' => 'PO-999',
            'movement_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->staff)->get(route('stock-movements.index'));
        $response->assertStatus(200);
        $response->assertSee('TEST-001');
        $response->assertSee('PO-999');
        $response->assertSee('Pergerakan Stok (Stock Ledger)');
    }

    public function test_stock_movement_create_form_renders_properly(): void
    {
        $response = $this->actingAs($this->staff)->get(route('stock-movements.create'));
        $response->assertStatus(200);
        $response->assertSee('Catat Pergerakan Stok');
        $response->assertSee('TEST-001');
    }

    public function test_stock_movement_store_inbound_increases_stock_and_records_ledger(): void
    {
        $response = $this->actingAs($this->staff)->post(route('stock-movements.store'), [
            'item_id' => $this->item->id,
            'reason' => 'RECEIPT',
            'qty' => 25.0,
            'movement_date' => now()->toDateString(),
            'reference_no' => 'SJ-12345',
            'notes' => 'Penerimaan batch baru',
        ]);

        $response->assertRedirect(route('stock-movements.index'));

        $this->item->refresh();
        $this->assertEquals(75.0, (float)$this->item->stock_on_hand);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'type' => 'IN',
            'reason' => 'RECEIPT',
            'qty' => 25.0,
            'stock_before' => 50.0,
            'stock_after' => 75.0,
            'reference_no' => 'SJ-12345',
        ]);
    }

    public function test_stock_movement_store_outbound_decreases_stock_and_prevents_negative(): void
    {
        // 1. Sukses jika stok cukup
        $response = $this->actingAs($this->staff)->post(route('stock-movements.store'), [
            'item_id' => $this->item->id,
            'reason' => 'ISSUE',
            'qty' => 20.0,
            'movement_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('stock-movements.index'));
        $this->item->refresh();
        $this->assertEquals(30.0, (float)$this->item->stock_on_hand);

        // 2. Gagal jika pengeluaran melebihi sisa stok (30)
        $failResponse = $this->actingAs($this->staff)->post(route('stock-movements.store'), [
            'item_id' => $this->item->id,
            'reason' => 'ISSUE',
            'qty' => 100.0,
            'movement_date' => now()->toDateString(),
        ]);

        $failResponse->assertSessionHas('error');
        $this->item->refresh();
        $this->assertEquals(30.0, (float)$this->item->stock_on_hand);
    }

    public function test_warehouses_index_displays_capacity_and_utilization(): void
    {
        $response = $this->actingAs($this->staff)->get(route('warehouses.index'));
        $response->assertStatus(200);
        $response->assertSee('Gudang Utama Test');
        $response->assertSee('Kapasitas &amp; Utilisasi Gudang', false);
        // 50 pcs * 0.05 m³ = 2.5 m³
        $response->assertSee('2,5 m³', false);
    }

    public function test_warehouses_show_displays_sku_breakdown(): void
    {
        $response = $this->actingAs($this->staff)->get(route('warehouses.show', $this->warehouse));
        $response->assertStatus(200);
        $response->assertSee('Gudang Utama Test');
        $response->assertSee('TEST-001');
    }

    public function test_admin_can_update_warehouse_capacity(): void
    {
        $response = $this->actingAs($this->admin)->put(route('warehouses.update', $this->warehouse), [
            'name' => 'Gudang Utama Test Updated',
            'capacity_m3' => 250.0,
        ]);

        $response->assertSessionHas('success');
        $this->warehouse->refresh();
        $this->assertEquals(250.0, (float)$this->warehouse->capacity_m3);
        $this->assertEquals('Gudang Utama Test Updated', $this->warehouse->name);
    }

    public function test_inventory_health_diagnostics_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->staff)->get(route('inventory.health'));
        $response->assertStatus(200);
        $response->assertSee('Diagnostik Kesehatan Persediaan');
        $response->assertSee('Potensi Stockout');
        $response->assertSee('Kelebihan Stok (Overstock)');
        $response->assertSee('Barang Mati (Dead Stock)');
    }

    public function test_inventory_simulator_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->staff)->get(route('inventory.simulator', ['item_id' => $this->item->id]));
        $response->assertStatus(200);
        $response->assertSee('Simulator Kebijakan Persediaan');
        $response->assertSee('TEST-001');
        $response->assertSee('Safety Stock (SS)');
        $response->assertSee('Reorder Point (ROP)');
    }
}
