<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_excludes_soft_deleted_warehouses_and_links_to_dashboard(): void
    {
        $admin = Admin::factory()->create();
        $region = Region::factory()->create();

        $warehouse = Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => 'WH-DEL-1',
            'warehouse_name' => 'Deleted Warehouse',
            'manager_name' => 'Jane Doe',
            'manager_email' => 'jane@example.com',
            'manager_phone' => '1234567890',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'latitude' => 12.3456789,
            'longitude' => 98.7654321,
            'status' => 'active',
        ]);

        $warehouse->delete();

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->get('/admin/dashboard')
            ->assertStatus(200)
            ->assertSee('admin/dashboard', false)
            ->assertSeeText('0');
    }
}
