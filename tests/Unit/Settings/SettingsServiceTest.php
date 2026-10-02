<?php

namespace Tests\Unit\Settings;

use App\Models\AppSetting;
use App\Models\SettingAudit;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(SettingsService::class);
    }

    public function test_get_fallback_to_config(): void
    {
        $clampPct = $this->settings->get('clamp_pct');
        $this->assertEquals(0.50, $clampPct);
    }

    public function test_update_many_stores_in_db_and_records_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->settings->updateMany([
            'clamp_pct' => 0.40,
            'lead_time_days_default' => 20,
        ], $admin->id);

        $this->assertEquals(0.40, $this->settings->get('clamp_pct'));
        $this->assertEquals(20, $this->settings->get('lead_time_days_default'));

        $this->assertDatabaseHas('app_settings', [
            'key' => 'clamp_pct',
            'updated_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('setting_audits', [
            'user_id' => $admin->id,
            'setting_key' => 'clamp_pct',
        ]);
    }

    public function test_validation_rejects_out_of_range_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // max_warehouse_utilization must be <= 1.0 and > 0
        $this->settings->updateMany([
            'max_warehouse_utilization' => 1.5,
        ]);
    }

    public function test_snapshot_returns_complete_operational_parameters(): void
    {
        $snapshot = $this->settings->getSnapshot();

        $this->assertArrayHasKey('clamp_pct', $snapshot);
        $this->assertArrayHasKey('max_warehouse_utilization', $snapshot);
        $this->assertArrayHasKey('service_level', $snapshot);
        $this->assertArrayHasKey('target_cover_days', $snapshot);
    }
}
