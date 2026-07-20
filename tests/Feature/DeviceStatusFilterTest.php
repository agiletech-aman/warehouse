<?php

namespace Tests\Feature;

use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_can_be_filtered_by_online_status(): void
    {
        $this->createReading('DEVICE-ONLINE', 'online');
        $this->createReading('DEVICE-OFFLINE', 'offline');

        $response = $this->withSession(['admin_id' => 1])
            ->getJson('/devices/data?status=online&draw=1&start=0&length=10');

        $response->assertOk()
            ->assertJsonFragment(['code' => 'DEVICE-ONLINE'])
            ->assertJsonMissing(['code' => 'DEVICE-OFFLINE']);
    }

    public function test_devices_can_be_filtered_by_offline_status(): void
    {
        $this->createReading('DEVICE-ONLINE', 'online');
        $this->createReading('DEVICE-OFFLINE', 'offline');

        $response = $this->withSession(['admin_id' => 1])
            ->getJson('/devices/data?status=offline&draw=1&start=0&length=10');

        $response->assertOk()
            ->assertJsonFragment(['code' => 'DEVICE-OFFLINE'])
            ->assertJsonMissing(['code' => 'DEVICE-ONLINE']);
    }

    public function test_latest_device_status_uses_recorded_time_not_insertion_order(): void
    {
        Reading::create([
            'sensor_device_id' => 'DEVICE-ORDERED',
            'device_name' => 'Newest Reading',
            'reading_value' => 20,
            'unit' => 'C',
            'status' => 'online',
            'recorded_at' => now(),
        ]);

        Reading::create([
            'sensor_device_id' => 'DEVICE-ORDERED',
            'device_name' => 'Late-arriving Old Reading',
            'reading_value' => 18,
            'unit' => 'C',
            'status' => 'offline',
            'recorded_at' => now()->subDay(),
        ]);

        $this->withSession(['admin_id' => 1])
            ->getJson('/devices/data?status=online&draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Newest Reading'])
            ->assertJsonMissing(['name' => 'Late-arriving Old Reading']);
    }

    private function createReading(string $deviceId, string $status): void
    {
        Reading::create([
            'sensor_device_id' => $deviceId,
            'device_name' => $deviceId,
            'device_type' => 'TEMP',
            'unit' => 'C',
            'region' => 'North',
            'region_code' => 'NORTH',
            'warehouse' => 'Warehouse A',
            'warehouse_code' => 'WH-A',
            'reading_value' => 20,
            'level' => 'normal',
            'status' => $status,
            'recorded_at' => now(),
        ]);
    }
}
