<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Admin;
use App\Models\Region;
use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReadingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_module_routes_are_available(): void
    {
        $admin = Admin::factory()->create();
        $session = [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ];

        $this->withSession($session)->get('/readings')
            ->assertStatus(200)
            ->assertSee('Readings');

        $this->withSession($session)->get('/readings/create')
            ->assertStatus(200)
            ->assertSee('Add Reading');
    }

    public function test_readings_data_endpoint_returns_only_requested_page(): void
    {
        $admin = Admin::factory()->create();

        foreach (range(1, 3) as $index) {
            Reading::create([
                'sensor_device_id' => 'SENSOR-' . $index,
                'device_name' => 'Device ' . $index,
                'device_type' => 'Temperature',
                'reading_value' => 20 + $index,
                'unit' => 'C',
                'region' => 'North',
                'warehouse' => 'Warehouse ' . $index,
                'level' => 'normal',
                'status' => 'online',
                'recorded_at' => now()->subMinutes($index),
            ]);
        }

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->getJson('/readings/data?draw=7&start=0&length=2')
            ->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(2, 'data');
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

    public function test_sensor_reading_accepts_the_existing_payload_without_api_key_configuration(): void
    {
        $this->postJson('/api/readings', [
            'key' => 'wrong-key',
            'readings' => [[
                'device_id' => 'DEVICE-1',
                'value' => 10,
                'unit' => 'C',
                'level' => 'normal',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('readings', [
            'sensor_device_id' => 'DEVICE-1',
            'reading_value' => 10,
            'level' => 'normal',
        ]);
    }

    public function test_numeric_reading_without_level_is_accepted_and_logged_for_debugging(): void
    {
        Log::spy();

        $this->postJson('/api/readings', [
            'key' => 'test-key',
            'readings' => [[
                'device_id' => 'CO2_172_22_26_12_1',
                'value' => 695,
                'unit' => 'ppm',
                'status' => 'online',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('readings', [
            'sensor_device_id' => 'CO2_172_22_26_12_1',
            'reading_value' => 695,
            'level' => null,
            'status' => 'online',
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) =>
                str_contains($message, 'without a level')
                && $context['sensor_device_id'] === 'CO2_172_22_26_12_1'
                && (float) $context['reading_value'] === 695.0
            );
    }

    public function test_warn_level_is_mapped_to_severe_and_displayed_instead_of_unknown(): void
    {
        $this->postJson('/api/readings', [
            'key' => 'test-key',
            'readings' => [[
                'device_id' => 'CO2_172_22_26_12_1',
                'value' => 680,
                'unit' => 'ppm',
                'level' => 'WARN',
                'status' => 'online',
            ]],
        ])->assertOk();

        $reading = Reading::firstOrFail();

        $this->assertSame('severe', $reading->level);
        $this->assertSame('severe', Reading::normalizeLevel(680, 'WARN'));

        $admin = Admin::factory()->create();

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->getJson('/readings/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('data.0.level', 'severe');
    }

    public function test_zero_reading_keeps_the_level_received_by_the_api(): void
    {
        $this->postJson('/api/readings', [
            'key' => 'test-key',
            'readings' => [[
                'device_id' => 'PH3_192_168_19_26_1',
                'value' => 0,
                'unit' => 'ppm',
                'severity' => 'normal',
                'status' => 'offline',
            ]],
        ])->assertOk();

        $reading = Reading::firstOrFail();

        $this->assertSame(0.0, (float) $reading->reading_value);
        $this->assertSame('normal', $reading->level);
        $this->assertSame('normal', Reading::normalizeLevel($reading->reading_value, $reading->level));
        $this->assertDatabaseHas('alerts', [
            'device_id' => 'PH3_192_168_19_26_1',
            'type' => 'unknown',
            'alert_type' => 'device_offline',
            'message' => 'Device is OFFLINE',
            'active' => true,
        ]);

        $admin = Admin::factory()->create();

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->getJson('/readings/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('data.0.value', 0)
            ->assertJsonPath('data.0.level', 'normal');

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->getJson('/alerts/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'unknown')
            ->assertJsonPath('data.0.message', 'Device is OFFLINE');
    }
}
