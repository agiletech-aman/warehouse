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
