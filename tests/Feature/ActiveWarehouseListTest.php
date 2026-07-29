<?php

namespace Tests\Feature;

use App\Exports\WarehousesExport;
use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveWarehouseListTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_active_card_opens_only_warehouses_with_recent_readings(): void
    {
        $region = Region::factory()->create();
        $activeByCode = $this->createWarehouse($region, 'WH-ACTIVE-1', 'Active By Code');
        $activeByName = $this->createWarehouse($region, 'WH-ACTIVE-2', 'Active By Name');
        $stale = $this->createWarehouse($region, 'WH-STALE', 'Stale Warehouse');

        $this->createReading([
            'warehouse_code' => $activeByCode->warehouse_code,
            'warehouse' => 'Different Name',
            'recorded_at' => now()->subHour(),
        ]);
        $this->createReading([
            'warehouse_code' => 'DIFFERENT-CODE',
            'warehouse' => $activeByName->warehouse_name,
            'recorded_at' => now()->subHours(2),
        ]);
        $this->createReading([
            'warehouse_code' => $stale->warehouse_code,
            'warehouse' => $stale->warehouse_name,
            'recorded_at' => now()->subDays(2),
        ]);

        $dashboard = $this->withSession($this->adminSession())->get('/admin/dashboard');

        $dashboard->assertOk()
            ->assertSee(route('warehouses.index', ['active' => 1]), false);

        $this->withSession($this->adminSession())
            ->get('/warehouses?active=1')
            ->assertOk()
            ->assertSeeText('Active Warehouses')
            ->assertSee(route('warehouses.export', ['active' => 1]), false);

        $this->withSession($this->adminSession())
            ->getJson('/warehouses/data?active=1&draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => $activeByCode->warehouse_name])
            ->assertJsonFragment(['name' => $activeByName->warehouse_name])
            ->assertJsonMissing(['name' => $stale->warehouse_name]);
    }

    public function test_active_warehouse_export_uses_the_same_recent_reading_filter(): void
    {
        $region = Region::factory()->create();
        $active = $this->createWarehouse($region, 'WH-EXPORT-1', 'Export Active');
        $inactive = $this->createWarehouse($region, 'WH-EXPORT-2', 'Export Inactive');

        $this->createReading([
            'warehouse_code' => $active->warehouse_code,
            'warehouse' => $active->warehouse_name,
            'recorded_at' => now()->subMinutes(30),
        ]);

        $exportedNames = (new WarehousesExport('', true))
            ->query()
            ->pluck('warehouse_name');

        $this->assertTrue($exportedNames->contains($active->warehouse_name));
        $this->assertFalse($exportedNames->contains($inactive->warehouse_name));
    }

    public function test_warehouse_data_endpoint_returns_only_the_requested_page(): void
    {
        $region = Region::factory()->create();
        $this->createWarehouse($region, 'WH-PAGE-1', 'Page Warehouse 1');
        $this->createWarehouse($region, 'WH-PAGE-2', 'Page Warehouse 2');
        $this->createWarehouse($region, 'WH-PAGE-3', 'Page Warehouse 3');

        $this->withSession($this->adminSession())
            ->getJson('/warehouses/data?draw=7&start=0&length=2')
            ->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(2, 'data');
    }

    private function createWarehouse(Region $region, string $code, string $name): Warehouse
    {
        return Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => $code,
            'warehouse_name' => $name,
            'manager_name' => 'Test Manager',
            'status' => 'active',
        ]);
    }

    private function createReading(array $attributes): Reading
    {
        return Reading::create(array_merge([
            'sensor_device_id' => 'SENSOR-' . fake()->unique()->numerify('####'),
            'device_name' => 'Test Sensor',
            'reading_value' => '10',
            'unit' => 'ppm',
            'level' => 'normal',
            'status' => 'online',
        ], $attributes));
    }
}
