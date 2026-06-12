<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Region;
use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReadingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_module_routes_are_available(): void
    {
        $this->get('/readings')
            ->assertStatus(200)
            ->assertSee('Readings');

        $this->get('/readings/create')
            ->assertStatus(200)
            ->assertSee('Add Reading');
    }

    public function test_sensor_reading_sends_alert_by_warehouse(): void
    {
        Mail::fake();

        $region = Region::create([
            'region_code' => 'RE-AHM',
            'region_name' => 'AHMEDABAD',
            'status' => 'active',
        ]);

        Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => 'WH001',
            'warehouse_name' => 'ANAND',
            'manager_name' => 'Deepka Sharma',
            'manager_email' => 'manager@example.com',
            'manager_phone' => null,
            'status' => 'active',
        ]);

        $this->postJson('/api/readings', [
            'key' => 'test-key',
            'readings' => [
                [
                    'device_id' => 'TEMP_192_168_1_101_1',
                    'device_name' => 'Temperature Sensor',
                    'device_type' => 'Temperature',
                    'device_ip' => '192.168.1.101',
                    'unit' => 'C',
                    'port' => 1,
                    'region' => 'AHMEDABAD',
                    'region_code' => 'RE-AHM',
                    'warehouse' => 'ANAND',
                    'warehouse_code' => 'WH001',
                    'value' => 55,
                    'level' => 'critical',
                    'status' => 'online',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('alerting.attempts', 1)
            ->assertJsonPath('alerting.alerts_created', 1)
            ->assertJsonPath('alerting.alert_emails_sent', 1);

        $reading = Reading::first();

        $this->assertNotNull($reading);
        $this->assertDatabaseHas('alerts', [
            'device_id' => 'TEMP_192_168_1_101_1',
            'reading_id' => $reading->id,
            'type' => 'critical',
            'alert_type' => 'high_co2',
            'active' => true,
        ]);
        $this->assertSame(1, Alert::count());
    }
}
