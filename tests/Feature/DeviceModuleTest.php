<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_module_routes_are_available(): void
    {
        $session = $this->adminSession();

        $this->withSession($session)->get('/devices')
            ->assertStatus(200)
            ->assertSee('Devices');

        $this->withSession($session)->get('/devices/create')
            ->assertStatus(200)
            ->assertSee('Add Device');
    }

    public function test_devices_and_hierarchy_are_listed_from_readings(): void
    {
        $session = $this->adminSession();

        Reading::create([
            'key' => 'test-key',
            'sensor_device_id' => 'TEMP_192_168_1_101_1',
            'device_name' => 'Temperature Sensor 1',
            'device_type' => 'TEMP',
            'device_ip' => '192.168.1.101',
            'unit' => 'C',
            'port' => 502,
            'region' => 'MUMBAI',
            'region_code' => 'RE-MUM',
            'warehouse' => 'Warehouse A',
            'warehouse_code' => 'WH001',
            'godown' => 'Godown 1',
            'compartment' => 'Compartment A',
            'reading_value' => 28.5,
            'level' => 'severe',
            'status' => 'online',
            'recorded_at' => now(),
        ]);

        $this->withSession($session)->get('/devices')
            ->assertStatus(200)
            ->assertSee('Devices');

        $this->withSession($session)->getJson('/devices/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonFragment([
                'code' => 'TEMP_192_168_1_101_1',
                'warehouse' => 'Warehouse A',
                'region_code' => 'RE-MUM',
            ]);

        $this->withSession($session)->get('/hierarchy')
            ->assertStatus(200)
            ->assertSee('MUMBAI')
            ->assertSee('Warehouse A')
            ->assertSee('Temperature Sensor 1');
    }

    public function test_hierarchy_shows_regions_and_warehouses_without_devices(): void
    {
        $session = $this->adminSession();

        $region = Region::create([
            'region_code' => 'RE-DEL',
            'region_name' => 'DELHI',
            'status' => 'active',
        ]);

        Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => 'WH-DEL',
            'warehouse_name' => 'Delhi Warehouse',
            'manager_name' => 'Amit',
            'manager_email' => null,
            'manager_phone' => null,
            'status' => 'active',
        ]);

        $this->withSession($session)->get('/hierarchy')
            ->assertStatus(200)
            ->assertSee('DELHI')
            ->assertSee('Delhi Warehouse')
            ->assertSee('No devices found');
    }

    public function test_devices_data_endpoint_returns_only_the_requested_page(): void
    {
        $session = $this->adminSession();

        foreach (range(1, 12) as $index) {
            Reading::create([
                'sensor_device_id' => 'DEVICE-' . $index,
                'device_name' => 'Device ' . $index,
                'unit' => 'C',
                'reading_value' => 20 + $index,
                'level' => 'normal',
                'status' => 'online',
                'recorded_at' => now()->subMinutes($index),
            ]);
        }

        $this->withSession($session)
            ->getJson('/devices/data?draw=7&start=5&length=5')
            ->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 12)
            ->assertJsonCount(5, 'data');
    }

    public function test_devices_view_shows_the_latest_reading_timestamp(): void
    {
        $session = $this->adminSession();
        $recordedAt = now()->startOfSecond();

        Reading::create([
            'sensor_device_id' => 'DEVICE-TIME',
            'device_name' => 'Timestamp Device',
            'unit' => 'ppm',
            'reading_value' => 24,
            'level' => 'normal',
            'status' => 'online',
            'recorded_at' => $recordedAt,
        ]);

        $this->withSession($session)
            ->get('/devices')
            ->assertOk()
            ->assertSee('Latest Reading Time');

        $this->withSession($session)
            ->getJson('/devices/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('data.0.recorded_at', $recordedAt->format('d M Y H:i:s'));
    }

    public function test_device_summary_is_split_by_type_and_warehouse(): void
    {
        $session = $this->adminSession();

        $rows = [
            ['CO2-A', 'CO2', 'online', 'Warehouse A', 'WH-A'],
            ['CO2-B', 'CO₂', 'offline', 'Warehouse A', 'WH-A'],
            ['PH3-A', 'PH3', 'online', 'Warehouse B', 'WH-B'],
            ['PH3-B', 'PH₃', 'offline', 'Warehouse B', 'WH-B'],
        ];

        foreach ($rows as [$deviceId, $deviceType, $status, $warehouse, $warehouseCode]) {
            Reading::create([
                'sensor_device_id' => $deviceId,
                'device_name' => $deviceId,
                'device_type' => $deviceType,
                'region' => 'North Region',
                'region_code' => 'RE-NORTH',
                'warehouse' => $warehouse,
                'warehouse_code' => $warehouseCode,
                'reading_value' => 10,
                'unit' => 'ppm',
                'status' => $status,
                'recorded_at' => now(),
            ]);
        }

        $response = $this->withSession($session)->get('/devices');

        $response->assertOk()
            ->assertSee('Overall Summary')
            ->assertSee('View Detailed Summary')
            ->assertSee('Warehouse-wise Device Summary')
            ->assertViewHas('deviceCounts', [
                'total' => 4,
                'online' => 2,
                'offline' => 2,
            ])
            ->assertViewHas('activeWarehouseCount', 2)
            ->assertViewHas('deviceTypeCounts', function (array $counts) {
                return $counts['CO2'] === ['total' => 2, 'online' => 1, 'offline' => 1]
                    && $counts['PH3'] === ['total' => 2, 'online' => 1, 'offline' => 1];
            })
            ->assertViewHas('warehouseDeviceCounts', function ($warehouses) {
                return $warehouses->count() === 2
                    && $warehouses->firstWhere('code', 'WH-A')['CO2']['total'] === 2
                    && $warehouses->firstWhere('code', 'WH-B')['PH3']['total'] === 2
                    && $warehouses->firstWhere('code', 'WH-A')['region_code'] === 'RE-NORTH';
            });
    }
}
