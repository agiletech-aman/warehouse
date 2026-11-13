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
            ->assertSee('TEMP_192_168_1_101_1')
            ->assertSee('Warehouse A')
            ->assertSee('RE-MUM');

        $this->withSession($session)->get('/hierarchy')
            ->assertStatus(200)
            ->assertSee('MUMBAI')
            ->assertSee('Warehouse A')
            ->assertSee('Temperature Sensor 1');
    }

    public function test_multiple_devices_can_be_bulk_deleted(): void
    {
        $session = $this->adminSession();

        foreach (['DEV_A', 'DEV_B', 'DEV_KEEP'] as $sensorId) {
            Reading::create([
                'key' => 'test-key',
                'sensor_device_id' => $sensorId,
                'device_name' => $sensorId,
                'device_type' => 'CO2',
                'device_ip' => '192.168.1.10',
                'unit' => 'ppm',
                'port' => 502,
                'region' => 'MUMBAI',
                'region_code' => 'RE-MUM',
                'warehouse' => 'Warehouse A',
                'warehouse_code' => 'WH001',
                'godown' => 'Godown 1',
                'compartment' => 'Compartment A',
                'reading_value' => 400,
                'level' => 'normal',
                'status' => 'online',
                'recorded_at' => now(),
            ]);
        }

        $this->withSession($session)
            ->from('/devices')
            ->delete('/devices/bulk-destroy', ['device_ids' => ['DEV_A', 'DEV_B']])
            ->assertRedirect('/devices')
            ->assertSessionHas('success');

        $this->assertSoftDeleted('readings', ['sensor_device_id' => 'DEV_A']);
        $this->assertSoftDeleted('readings', ['sensor_device_id' => 'DEV_B']);
        $this->assertNotSoftDeleted('readings', ['sensor_device_id' => 'DEV_KEEP']);
        $this->assertDatabaseMissing('device_latest_status', ['sensor_device_id' => 'DEV_A']);
    }

    public function test_bulk_delete_requires_device_ids(): void
    {
        $this->withSession($this->adminSession())
            ->from('/devices')
            ->delete('/devices/bulk-destroy', [])
            ->assertSessionHasErrors('device_ids');
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
}
