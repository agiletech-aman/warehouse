<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_can_be_created_with_blank_manager_email(): void
    {
        $session = $this->adminSession();
        $region = \App\Models\Region::factory()->create();

        $response = $this->withSession($session)->post('/warehouses', [
            'region_id' => $region->id,
            'warehouse_code' => 'WH-TEST-1',
            'warehouse_name' => 'Main Warehouse',
            'manager_name' => 'John Doe',
            'manager_email' => '',
            'manager_phone' => '1234567890',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'latitude' => 12.3456789,
            'longitude' => 98.7654321,
            'status' => 'active',
        ]);

        $response->assertRedirect('/warehouses');
        $this->assertDatabaseHas('warehouses', [
            'warehouse_code' => 'WH-TEST-1',
            'warehouse_name' => 'Main Warehouse',
        ]);
    }
}
