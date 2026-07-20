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
}
