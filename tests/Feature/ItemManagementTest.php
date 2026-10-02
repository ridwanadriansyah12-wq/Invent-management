<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $approver;
    protected User $staff;
    protected Category $category;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->approver = User::factory()->create(['role' => 'approver']);
        $this->staff = User::factory()->create(['role' => 'staff']);

        $this->category = Category::create(['name' => 'Bahan Baku']);
        $this->warehouse = Warehouse::create(['name' => 'Gudang Utama', 'capacity_m3' => 1000]);
    }

    public function test_staff_and_approver_cannot_create_or_update_sku_returns_403(): void
    {
        $payload = [
            'sku'                => 'TEST-001',
            'name'               => 'Test Item 1',
            'category_id'        => $this->category->id,
            'warehouse_id'       => $this->warehouse->id,
            'unit'               => 'pcs',
            'stock_on_hand'      => 10,
            'unit_cost'          => 50000,
            'volume_m3'          => 0.05,
            'moq'                => 10,
            'lot_size'           => 5,
            'lead_time_days'     => 14,
            'lead_time_std_days' => 1.5,
        ];

        // 1. Staff mencoba membuat SKU -> 403 Forbidden
        $this->actingAs($this->staff)
            ->post('/items', $payload)
            ->assertStatus(403);

        // 2. Approver mencoba membuat SKU -> 403 Forbidden
        $this->actingAs($this->approver)
            ->post('/items', $payload)
            ->assertStatus(403);

        // 3. Staff membuka halaman form create -> 403 Forbidden
        $this->actingAs($this->staff)
            ->get('/items/create')
            ->assertStatus(403);

        // 4. Approver membuka halaman form create -> 403 Forbidden
        $this->actingAs($this->approver)
            ->get('/items/create')
            ->assertStatus(403);

        // Buat item via DB langsung untuk tes edit/update
        $item = Item::create(array_merge($payload, ['sku' => 'EXIST-001', 'is_active' => true]));

        // 5. Staff mencoba update SKU -> 403 Forbidden
        $this->actingAs($this->staff)
            ->put("/items/{$item->id}", $payload)
            ->assertStatus(403);

        // 6. Approver mencoba update SKU -> 403 Forbidden
        $this->actingAs($this->approver)
            ->put("/items/{$item->id}", $payload)
            ->assertStatus(403);
    }

    public function test_admin_can_create_and_update_sku(): void
    {
        $payload = [
            'sku'                => 'ADM-SKU-001',
            'name'               => 'Aluminium Plate 5mm',
            'category_id'        => $this->category->id,
            'warehouse_id'       => $this->warehouse->id,
            'unit'               => 'sheet',
            'stock_on_hand'      => 25,
            'unit_cost'          => 120000,
            'volume_m3'          => 0.02,
            'moq'                => 20,
            'lot_size'           => 10,
            'lead_time_days'     => 15,
            'lead_time_std_days' => 2.0,
        ];

        $response = $this->actingAs($this->admin)
            ->post('/items', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'sku'  => 'ADM-SKU-001',
            'name' => 'Aluminium Plate 5mm',
            'moq'  => 20,
        ]);

        $item = Item::where('sku', 'ADM-SKU-001')->firstOrFail();

        // Update oleh admin
        $updatePayload = array_merge($payload, [
            'name' => 'Aluminium Plate 5mm Anodized',
            'moq'  => 30,
        ]);

        $this->actingAs($this->admin)
            ->put("/items/{$item->id}", $updatePayload)
            ->assertRedirect();

        $this->assertDatabaseHas('items', [
            'id'   => $item->id,
            'name' => 'Aluminium Plate 5mm Anodized',
            'moq'  => 30,
        ]);
    }

    public function test_form_rejects_moq_that_is_not_multiple_of_lot_size(): void
    {
        $payload = [
            'sku'                => 'LOT-ERR-001',
            'name'               => 'Item Lot Error',
            'category_id'        => $this->category->id,
            'warehouse_id'       => $this->warehouse->id,
            'unit'               => 'pcs',
            'stock_on_hand'      => 10,
            'unit_cost'          => 50000,
            'volume_m3'          => 0.05,
            'moq'                => 15, // Bukan kelipatan dari 10!
            'lot_size'           => 10,
            'lead_time_days'     => 14,
            'lead_time_std_days' => 0.0,
        ];

        $response = $this->actingAs($this->admin)
            ->from('/items/create')
            ->post('/items', $payload);

        $response->assertSessionHasErrors(['moq']);
        $this->assertDatabaseMissing('items', ['sku' => 'LOT-ERR-001']);
    }

    public function test_form_does_not_permit_direct_rop_ss_max_manipulation(): void
    {
        $payload = [
            'sku'                => 'IMMUTABLE-001',
            'name'               => 'Immutable Params Item',
            'category_id'        => $this->category->id,
            'warehouse_id'       => $this->warehouse->id,
            'unit'               => 'pcs',
            'stock_on_hand'      => 10,
            'unit_cost'          => 50000,
            'volume_m3'          => 0.05,
            'moq'                => 20,
            'lot_size'           => 10,
            'lead_time_days'     => 14,
            'lead_time_std_days' => 0.0,
            'rop'                => 99999, // Parameter ilegal dari form
            'effective_rop'      => 99999,
            'proposed_rop'       => 99999,
            'safety_stock'       => 88888,
            'max_stock'          => 77777,
        ];

        $this->actingAs($this->admin)
            ->post('/items', $payload);

        $item = Item::where('sku', 'IMMUTABLE-001')->firstOrFail();

        // Parameter ROP/SS/MAX tidak tersimpan di inventory_parameters lewat form ini
        $this->assertNull($item->activeParameter);
    }

    public function test_dashboard_renders_without_error_when_tables_are_empty(): void
    {
        $response = $this->actingAs($this->staff)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Ringkasan Persediaan Terpadu');
        $response->assertSee('Total SKU Aktif');
        $response->assertSee('0');
    }

    public function test_dashboard_5_critical_skus_sorted_by_inventory_position_ratio_ascending(): void
    {
        // Item A: on_hand = 10, ROP = 100 -> ratio = 0.10
        $itemA = Item::create([
            'sku' => 'CRIT-A', 'name' => 'Kritis Sekali',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 10, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);
        InventoryParameter::create([
            'item_id' => $itemA->id, 'status' => 'ACTIVE', 'source' => 'ML',
            'proposed_rop' => 100, 'effective_rop' => 100, 'effective_ss' => 20, 'effective_max' => 200,
        ]);

        // Item B: on_hand = 50, ROP = 100 -> ratio = 0.50
        $itemB = Item::create([
            'sku' => 'CRIT-B', 'name' => 'Kritis Sedang',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 50, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);
        InventoryParameter::create([
            'item_id' => $itemB->id, 'status' => 'ACTIVE', 'source' => 'ML',
            'proposed_rop' => 100, 'effective_rop' => 100, 'effective_ss' => 20, 'effective_max' => 200,
        ]);

        // Item C: on_hand = 90, ROP = 100 -> ratio = 0.90
        $itemC = Item::create([
            'sku' => 'CRIT-C', 'name' => 'Mendekati ROP',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 90, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);
        InventoryParameter::create([
            'item_id' => $itemC->id, 'status' => 'ACTIVE', 'source' => 'ML',
            'proposed_rop' => 100, 'effective_rop' => 100, 'effective_ss' => 20, 'effective_max' => 200,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertStatus(200);

        /** @var \Illuminate\Database\Eloquent\Collection $criticalSkus */
        $criticalSkus = $response->viewData('criticalSkus');
        $this->assertCount(3, $criticalSkus);
        $this->assertEquals('CRIT-A', $criticalSkus[0]->sku);
        $this->assertEquals('CRIT-B', $criticalSkus[1]->sku);
        $this->assertEquals('CRIT-C', $criticalSkus[2]->sku);
    }

    public function test_sku_detail_page_renders_with_or_without_data(): void
    {
        $item = Item::create([
            'sku' => 'EMPTY-SKU', 'name' => 'SKU Kosong Tanpa Parameter',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 0, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->staff)->get("/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('EMPTY-SKU');
        $response->assertSee('1. Ringkasan & Master Data', false);
    }

    public function test_sku_filter_below_rop_works(): void
    {
        // SKU 1: On hand 10, ROP 100 (Below ROP)
        $itemBelow = Item::create([
            'sku' => 'BELOW-01', 'name' => 'Barang Di Bawah ROP',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 10, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);
        InventoryParameter::create([
            'item_id' => $itemBelow->id, 'status' => 'ACTIVE', 'source' => 'ML',
            'proposed_rop' => 100, 'effective_rop' => 100, 'effective_ss' => 20, 'effective_max' => 200,
        ]);

        // SKU 2: On hand 150, ROP 100 (Above ROP)
        $itemAbove = Item::create([
            'sku' => 'ABOVE-01', 'name' => 'Barang Aman Di Atas ROP',
            'category_id' => $this->category->id, 'warehouse_id' => $this->warehouse->id,
            'unit' => 'pcs', 'stock_on_hand' => 150, 'unit_cost' => 1000,
            'volume_m3' => 0.01, 'moq' => 10, 'lot_size' => 10, 'lead_time_days' => 15,
            'is_active' => true,
        ]);
        InventoryParameter::create([
            'item_id' => $itemAbove->id, 'status' => 'ACTIVE', 'source' => 'ML',
            'proposed_rop' => 100, 'effective_rop' => 100, 'effective_ss' => 20, 'effective_max' => 200,
        ]);

        $response = $this->actingAs($this->staff)->get('/items?below_rop=1');

        $response->assertStatus(200);
        $response->assertSee('BELOW-01');
        $response->assertDontSee('ABOVE-01');
    }
}
