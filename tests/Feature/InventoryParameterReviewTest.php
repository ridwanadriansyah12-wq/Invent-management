<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryParameterReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Item $item;
    protected InventoryParameter $pendingParam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role'      => 'approver',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Review Cat ' . uniqid(),
            'code' => 'RC' . rand(100, 999),
        ]);

        $warehouse = Warehouse::create([
            'name'        => 'Review Wh ' . uniqid(),
            'capacity_m3' => 500.0,
        ]);

        $this->item = Item::create([
            'category_id'         => $category->id,
            'warehouse_id'        => $warehouse->id,
            'sku'                 => 'SKU-REV-' . uniqid(),
            'name'                => 'Item Review',
            'unit'                => 'pcs',
            'unit_cost'           => 50000,
            'volume_m3'           => 0.02,
            'moq'                 => 10,
            'lot_size'            => 5,
            'lead_time_days'      => 15,
            'first_movement_date' => Carbon::now()->subDays(60)->toDateString(),
            'is_active'           => true,
        ]);

        $this->pendingParam = InventoryParameter::create([
            'item_id'                        => $this->item->id,
            'computed_at'                    => Carbon::now(),
            'source'                         => 'ML',
            'proposed_ss'                    => 40,
            'proposed_rop'                   => 200,
            'proposed_max'                   => 500,
            'effective_ss'                   => 30, // Clamped
            'effective_rop'                  => 150, // Clamped (1.5x baseline)
            'effective_max'                  => 375, // Clamped
            'effective_max_before_capacity'  => 375,
            'status'                         => 'PENDING_REVIEW',
            'flag_reason'                    => 'ROP_DEVIATION_GT_50PCT',
        ]);
    }

    public function test_get_pending_reviews_list(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/inventory/parameters/pending');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.data'));
    }

    public function test_approve_pending_parameter(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/v1/inventory/parameters/{$this->pendingParam->id}/approve", [
            'notes' => 'Approved by procurement team for promotion season.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->pendingParam->refresh();
        $this->assertEquals('APPROVED', $this->pendingParam->status);
        $this->assertEquals(200, $this->pendingParam->effective_rop); // Restored to proposed
        $this->assertEquals(40, $this->pendingParam->effective_ss);
        $this->assertEquals(500, $this->pendingParam->effective_max);
        $this->assertNotNull($this->pendingParam->reviewed_at);
        $this->assertEquals($this->user->id, $this->pendingParam->reviewed_by);
    }

    public function test_reject_pending_parameter(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/v1/inventory/parameters/{$this->pendingParam->id}/reject", [
            'notes' => 'Rejected. Demand spike was an anomaly.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->pendingParam->refresh();
        $this->assertEquals('REJECTED', $this->pendingParam->status);
        $this->assertEquals(150, $this->pendingParam->effective_rop); // Retains clamped value
        $this->assertNotNull($this->pendingParam->reviewed_at);
        $this->assertEquals($this->user->id, $this->pendingParam->reviewed_by);
    }
}
