<?php

namespace Tests\Feature;

use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_summary_counts_devices_from_their_latest_readings(): void
    {
        $this->createReading('DEVICE-1', 'offline', now()->subMinute());
        $this->createReading('DEVICE-1', 'online', now());
        $this->createReading('DEVICE-2', 'offline', now());

        $response = $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary');

        $response->assertOk()
            ->assertJsonPath('stats.total_devices', 2)
            ->assertJsonPath('stats.online_devices', 1)
            ->assertJsonPath('stats.offline_devices', 1)
            ->assertJsonPath('charts.online_vs_offline_devices.online', 1)
            ->assertJsonPath('charts.online_vs_offline_devices.offline', 1);
    }

    public function test_report_summary_uses_current_device_status_for_stale_devices(): void
    {
        $this->createReading('DEVICE-1', 'online', now()->subHours(2));
        $this->createReading('DEVICE-2', 'online', now());

        $this->artisan('devices:mark-stale-offline', ['--minutes' => 30])->assertSuccessful();

        // Current Data Only -> live status from device_latest_status.
        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary?current_only=1')
            ->assertOk()
            ->assertJsonPath('stats.total_devices', 2)
            ->assertJsonPath('stats.online_devices', 1)
            ->assertJsonPath('stats.offline_devices', 1);

        // Without it -> counted from readings history, which still says online.
        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('stats.total_devices', 2)
            ->assertJsonPath('stats.online_devices', 2)
            ->assertJsonPath('stats.offline_devices', 0);
    }

    public function test_report_summary_device_count_follows_reading_filters_without_current_only(): void
    {
        $this->createReading('DEVICE-1', 'online', now()->subMinute());
        $this->createReading('DEVICE-1', 'offline', now());
        Reading::query()->where('sensor_device_id', 'DEVICE-1')->where('status', 'offline')
            ->update(['level' => 'unknown']);
        $this->createReading('DEVICE-2', 'online', now());

        // Unknown filter: only DEVICE-1's unknown reading matches -> 1 device, offline.
        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary?level=unknown')
            ->assertOk()
            ->assertJsonPath('stats.total_readings', 1)
            ->assertJsonPath('stats.total_devices', 1)
            ->assertJsonPath('stats.online_devices', 0)
            ->assertJsonPath('stats.offline_devices', 1);
    }

    public function test_report_summary_counts_readings_and_distinct_locations_in_one_response(): void
    {
        $this->createReading('DEVICE-1', 'online', now());
        $this->createReading('DEVICE-2', 'online', now());

        Reading::create([
            'sensor_device_id' => 'DEVICE-3',
            'device_name' => 'DEVICE-3',
            'device_type' => 'temperature',
            'region' => 'South',
            'region_code' => '',
            'warehouse' => 'Warehouse B',
            'warehouse_code' => null,
            'reading_value' => 20,
            'unit' => 'C',
            'level' => 'normal',
            'status' => 'online',
            'recorded_at' => now(),
        ]);

        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('stats.total_readings', 3)
            ->assertJsonPath('stats.regions_count', 2)
            ->assertJsonPath('stats.warehouses_count', 2);
    }

    public function test_report_summary_builds_trend_charts_only_when_requested(): void
    {
        $this->createReading('DEVICE-1', 'online', now());

        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary')
            ->assertOk()
            ->assertJsonMissingPath('charts.readings_trend')
            ->assertJsonPath('charts.online_vs_offline_devices.online', 1);

        $this->withSession(['admin_id' => 1])
            ->getJson('/admin/reports/summary?include_charts=1')
            ->assertOk()
            ->assertJsonPath('charts.level_distribution.normal', 1)
            ->assertJsonPath('charts.region_wise_device_count.0.region', 'NORTH')
            ->assertJsonPath('charts.online_vs_offline_devices.online', 1);
    }

    private function createReading(string $deviceId, string $status, $recordedAt): void
    {
        Reading::create([
            'sensor_device_id' => $deviceId,
            'device_name' => $deviceId,
            'device_type' => 'temperature',
            'region' => 'North',
            'region_code' => 'NORTH',
            'warehouse' => 'Warehouse A',
            'warehouse_code' => 'WH-A',
            'reading_value' => 20,
            'unit' => 'C',
            'level' => 'normal',
            'status' => $status,
            'recorded_at' => $recordedAt,
        ]);
    }
}
