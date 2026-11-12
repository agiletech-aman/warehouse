<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Admin;
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

    public function test_critical_readings_from_same_ip_collapse_into_one_row_within_five_minutes(): void
    {
        Mail::fake();

        $payload = fn (string $level, float $value) => [
            'key' => 'test-key',
            'readings' => [[
                'device_id' => 'CO2_192_168_1_50_1',
                'device_name' => 'CO2 Sensor',
                'device_type' => 'CO2',
                'device_ip' => '192.168.1.50',
                'unit' => 'ppm',
                'value' => $value,
                'level' => $level,
                'status' => 'online',
            ]],
        ];

        $this->postJson('/api/readings', $payload('critical', 1800))->assertOk();
        $this->postJson('/api/readings', $payload('severe', 1000))->assertOk();
        $this->postJson('/api/readings', $payload('critical', 1900))->assertOk();

        $this->assertSame(1, Reading::count());

        $reading = Reading::first();
        $this->assertSame('critical', $reading->level);
        $this->assertEquals(1900, (float) $reading->reading_value);
    }

    public function test_critical_readings_from_same_ip_after_five_minutes_create_a_new_row(): void
    {
        Mail::fake();

        $payload = fn (float $value) => [
            'key' => 'test-key',
            'readings' => [[
                'device_id' => 'CO2_192_168_1_51_1',
                'device_name' => 'CO2 Sensor',
                'device_type' => 'CO2',
                'device_ip' => '192.168.1.51',
                'unit' => 'ppm',
                'value' => $value,
                'level' => 'critical',
                'status' => 'online',
            ]],
        ];

        $this->postJson('/api/readings', $payload(1800))->assertOk();

        Reading::first()->update(['recorded_at' => now()->subMinutes(6)]);

        $this->postJson('/api/readings', $payload(1900))->assertOk();

        $this->assertSame(2, Reading::count());
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: string}>
     */
    public static function gasLevelThresholds(): array
    {
        return [
            'ph3 zero' => ['PH3', 0, 'normal'],
            'ph3 normal edge' => ['PH3', 0.3, 'normal'],
            'ph3 severe start' => ['PH3', 0.31, 'severe'],
            'ph3 severe edge' => ['PH3', 0.75, 'severe'],
            'ph3 critical' => ['PH3', 0.76, 'critical'],
            'co2 normal edge' => ['CO2', 800, 'normal'],
            'co2 severe start' => ['CO2', 801, 'severe'],
            'co2 severe edge' => ['CO2', 1700, 'severe'],
            'co2 critical' => ['CO2', 1701, 'critical'],
            'subscript type' => ['CO₂', 1701, 'critical'],
        ];
    }

    /**
     * @dataProvider gasLevelThresholds
     */
    public function test_gas_level_is_derived_from_value(string $type, float $value, string $expected): void
    {
        $this->assertSame($expected, Reading::levelFromValue($type, $value));
    }

    public function test_admin_can_edit_level_ranges_and_they_are_used_everywhere(): void
    {
        $admin = Admin::factory()->create();
        $session = ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'admin_email' => $admin->email];

        $this->withSession($session)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Edit Ranges');

        $this->withSession($session)->put('/admin/level-thresholds', [
            'thresholds' => [
                'PH3' => ['normal' => 0.5, 'severe' => 1],
                'CO2' => ['normal' => 1000, 'severe' => 2000],
            ],
        ])->assertRedirect('/admin/dashboard');

        $this->assertSame('normal', Reading::levelFromValue('PH3', 0.5));
        $this->assertSame('severe', Reading::levelFromValue('PH3', 0.9));
        $this->assertSame('critical', Reading::levelFromValue('PH3', 1.1));
        $this->assertSame('severe', Reading::levelFromValue('CO2', 1500));

        $this->getJson('/api/level-thresholds')
            ->assertOk()
            ->assertJsonPath('data.1.gas', 'CO2')
            ->assertJsonPath('data.1.severe.max', 2000);
    }

    public function test_level_ranges_reject_severe_not_above_normal(): void
    {
        $admin = Admin::factory()->create();
        $session = ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'admin_email' => $admin->email];

        $this->withSession($session)->put('/admin/level-thresholds', [
            'thresholds' => [
                'PH3' => ['normal' => 1, 'severe' => 0.5],
                'CO2' => ['normal' => 800, 'severe' => 1700],
            ],
        ])->assertSessionHasErrorsIn('levelThresholds', ['thresholds.PH3.severe']);

        $this->assertSame('critical', Reading::levelFromValue('PH3', 0.8));
    }

    public function test_level_from_value_ignores_other_types_and_missing_values(): void
    {
        $this->assertNull(Reading::levelFromValue('Temperature', 55));
        $this->assertNull(Reading::levelFromValue('CO2', null));
        $this->assertNull(Reading::levelFromValue('PH3', ''));
    }

    public function test_stored_gas_level_follows_value_not_device_level(): void
    {
        Mail::fake();

        $this->postJson('/api/readings', [
            'key' => 'test-key',
            'readings' => [
                [
                    'device_id' => 'PH3_192_168_1_60_1',
                    'device_type' => 'PH3',
                    'device_ip' => '192.168.1.60',
                    'unit' => 'ppm',
                    'value' => 0.5,
                    'level' => 'normal',
                    'status' => 'online',
                ],
                [
                    'device_id' => 'CO2_192_168_1_61_1',
                    'device_type' => 'CO2',
                    'device_ip' => '192.168.1.61',
                    'unit' => 'ppm',
                    'value' => 400,
                    'level' => 'critical',
                    'status' => 'online',
                ],
            ],
        ])->assertOk();

        $this->assertSame('severe', Reading::where('sensor_device_id', 'PH3_192_168_1_60_1')->value('level'));
        $this->assertSame('normal', Reading::where('sensor_device_id', 'CO2_192_168_1_61_1')->value('level'));
    }

    public function test_sensor_reading_rejects_an_invalid_api_key(): void
    {
        $this->postJson('/api/readings', [
            'key' => 'wrong-key',
            'readings' => [[
                'device_id' => 'DEVICE-1',
                'value' => 10,
            ]],
        ])->assertUnauthorized();

        $this->assertDatabaseCount('readings', 0);
    }
}
