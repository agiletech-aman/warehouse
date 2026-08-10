<?php

namespace Tests\Feature;

use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAlertSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_matches_unified_dashboard_contract(): void
    {
        $this->createReading('CO2-1', 'co2', null, 'online', 1);
        $this->createReading('CO2-1', 'co2', 'critical', 'offline', 2);
        $this->createReading('CO2-2', 'co2', '', 'online', 3);
        $this->createReading('PH3-1', 'ph3', 'severe', 'online', 4);
        $this->createReading('OTHER-1', 'temperature', 'critical', 'online', 5);

        $response = $this->getJson('/api/master-alert-summary/dashboard');

        $response->assertOk()
            ->assertJsonPath('overall.totalIotDevices', 3)
            ->assertJsonPath('overall.totalSensorsCO2', 2)
            ->assertJsonPath('overall.totalSensorsPH3', 1)
            ->assertJsonPath('overall.totalOnlineCO2', 1)
            ->assertJsonPath('overall.totalOfflineCO2', 1)
            ->assertJsonPath('overall.totalOnlinePH3', 1)
            ->assertJsonPath('overall.totalOfflinePH3', 0)
            ->assertJsonPath('overall.totalNormalCO2', 1)
            ->assertJsonPath('overall.totalCriticalCO2', 1)
            ->assertJsonPath('overall.totalSeverePH3', 1)
            ->assertJsonPath('locationWise.TEST WAREHOUSE-TEST STATE.totalSensorsCO2', 2)
            ->assertJsonPath('locationWise.TEST WAREHOUSE-TEST STATE.totalSensorsPH3', 1);
    }

    private function createReading(
        string $sensorId,
        string $deviceType,
        ?string $level,
        string $status,
        int $offset
    ): Reading {
        return Reading::create([
            'sensor_device_id' => $sensorId,
            'device_name' => $sensorId,
            'device_type' => $deviceType,
            'reading_value' => 10,
            'unit' => 'ppm',
            'region' => 'Test State',
            'warehouse' => 'Test Warehouse',
            'level' => $level,
            'status' => $status,
            'recorded_at' => now()->addSeconds($offset),
        ]);
    }
}
