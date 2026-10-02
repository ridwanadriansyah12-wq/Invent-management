<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationGatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_full_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue(Gate::forUser($admin)->allows('admin-only'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($admin)->allows('run-pipeline'));
        $this->assertTrue(Gate::forUser($admin)->allows('review-parameters'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-pr'));
        $this->assertTrue(Gate::forUser($admin)->allows('record-movement'));
        $this->assertTrue(Gate::forUser($admin)->allows('view-inventory'));
    }

    public function test_approver_has_review_and_pr_permissions_but_not_settings(): void
    {
        $approver = User::factory()->create(['role' => 'approver']);

        $this->assertFalse(Gate::forUser($approver)->allows('admin-only'));
        $this->assertFalse(Gate::forUser($approver)->allows('manage-settings'));
        $this->assertFalse(Gate::forUser($approver)->allows('run-pipeline'));
        $this->assertTrue(Gate::forUser($approver)->allows('review-parameters'));
        $this->assertTrue(Gate::forUser($approver)->allows('manage-pr'));
        $this->assertTrue(Gate::forUser($approver)->allows('record-movement'));
        $this->assertTrue(Gate::forUser($approver)->allows('view-inventory'));
    }

    public function test_staff_has_movement_and_view_inventory_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertFalse(Gate::forUser($staff)->allows('admin-only'));
        $this->assertFalse(Gate::forUser($staff)->allows('manage-settings'));
        $this->assertFalse(Gate::forUser($staff)->allows('run-pipeline'));
        $this->assertFalse(Gate::forUser($staff)->allows('review-parameters'));
        $this->assertFalse(Gate::forUser($staff)->allows('manage-pr'));
        $this->assertTrue(Gate::forUser($staff)->allows('record-movement'));
        $this->assertTrue(Gate::forUser($staff)->allows('view-inventory'));
    }

    public function test_prism_create_admin_artisan_command(): void
    {
        $this->artisan('prism:create-admin', [
            '--name' => 'Super Admin',
            '--email' => 'admin@prism.local',
        ])
        ->expectsQuestion('Masukkan Password (minimal 8 karakter)', 'secret1234')
        ->expectsQuestion('Ulangi Password untuk Konfirmasi', 'secret1234')
        ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@prism.local',
            'role' => 'admin',
        ]);
    }
}
