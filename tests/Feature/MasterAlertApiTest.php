<?php

namespace Tests\Feature;

use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAlertApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_co2_alerts_use_device_type_30000_and_uppercase_alert_types(): void
    {
        foreach (['normal', 'severe', 'critical'] as $index => $level) {
            $this->createReading('co2', $level, $index);
        }

        $response = $this->getJson('/api/master-alerts?deviceTypeId=30000&pageSize=10');

        $response->assertOk()
            ->assertJsonPath('totalCount', 3)
            ->assertJsonPath('pageSize', 10)
            ->assertJsonPath('data.0.location', 'Test Warehouse')
            ->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing(
            ['NORMAL', 'SEVERE', 'CRITICAL'],
            collect($response->json('data'))->pluck('alertType')->all()
        );
        $this->assertSame(
            [30000],
            collect($response->json('data'))->pluck('deviceTypeId')->unique()->values()->all()
        );
    }

    public function test_master_alerts_combine_co2_and_ph3_with_row_specific_device_type_ids(): void
    {
        $this->createReading('ph3', 'severe');
        $this->createReading('co2', 'critical', 1);

        $response = $this->getJson('/api/master-alerts?deviceTypeId=30001&pageSize=10');

        $response->assertOk()
            ->assertJsonPath('totalCount', 2)
            ->assertJsonCount(2, 'data');

        $alertsByType = collect($response->json('data'))->keyBy('alertType');

        $this->assertSame(30001, $alertsByType->get('SEVERE')['deviceTypeId']);
        $this->assertSame(30000, $alertsByType->get('CRITICAL')['deviceTypeId']);
    }

    public function test_missing_level_is_returned_as_normal_instead_of_being_dropped(): void
    {
        $reading = $this->createReading('ph3', 'normal');
        $reading->update(['level' => null]);

        $this->getJson('/api/master-alerts?pageSize=10')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('data.0.alertType', 'NORMAL')
            ->assertJsonPath('data.0.deviceTypeId', 30001);
    }

    public function test_master_alerts_are_paginated_twenty_records_at_a_time(): void
    {
        foreach (range(1, 25) as $offset) {
            $this->createReading($offset % 2 === 0 ? 'co2' : 'ph3', 'normal', $offset);
        }

        $this->getJson('/api/master-alerts?pageNumber=1')
            ->assertOk()
            ->assertJsonPath('totalCount', 25)
            ->assertJsonPath('pageNumber', 1)
            ->assertJsonPath('pageSize', 20)
            ->assertJsonCount(20, 'data');

        $this->getJson('/api/master-alerts?pageNumber=2')
            ->assertOk()
            ->assertJsonPath('totalCount', 25)
            ->assertJsonPath('pageNumber', 2)
            ->assertJsonPath('pageSize', 20)
            ->assertJsonCount(5, 'data');
    }

    private function createReading(string $deviceType, string $level, int $offset = 0): Reading
    {
        return Reading::create([
            'sensor_device_id' => strtoupper($deviceType).'-'.$level.'-'.$offset,
            'device_name' => strtoupper($deviceType).' Sensor',
            'device_type' => $deviceType,
            'reading_value' => 10 + $offset,
            'unit' => 'ppm',
            'warehouse' => 'Test Warehouse',
            'level' => $level,
            'status' => 'online',
            'recorded_at' => now()->subSeconds($offset),
        ]);
    }
}
