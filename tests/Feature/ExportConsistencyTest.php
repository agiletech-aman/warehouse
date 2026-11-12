<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Models\Alert;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_export_keeps_columns_aligned_when_values_are_missing(): void
    {
        $export = new ReportExport();
        $reading = new Reading([
            'sensor_device_id' => 'DEVICE-1',
            'device_name' => 'Device 1',
            'reading_value' => null,
            'status' => 'offline',
        ]);

        $mapped = $export->map($reading);

        $this->assertCount(count($export->headings()), $mapped);
        $this->assertSame('N/A', $mapped[array_search('value', ReportExport::COLUMNS, true)]);
        $this->assertSame('unknown', $mapped[array_search('level', ReportExport::COLUMNS, true)]);
        $this->assertSame('-', $mapped[array_search('godown', ReportExport::COLUMNS, true)]);
    }

    public function test_report_shows_and_filters_by_godown_and_compartment(): void
    {
        $admin = \App\Models\Admin::factory()->create();
        $session = ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'admin_email' => $admin->email];

        foreach ([['G1', 'C1', 'W1'], ['G1', 'C2', 'W1'], ['G2', 'C3', 'W1'], ['G9', 'C9', 'W2']] as [$godown, $compartment, $warehouse]) {
            $reading = Reading::create([
                'warehouse_code' => $warehouse,
                'sensor_device_id' => "DEV-$godown-$compartment",
                'device_name' => "Device $godown $compartment",
                'reading_value' => 10,
                'unit' => 'ppm',
                'level' => 'normal',
                'status' => 'online',
                'godown' => $godown,
                'compartment' => $compartment,
                'recorded_at' => now(),
            ]);
            \App\Models\DeviceLatestStatus::query()->updateOrCreate(
                ['sensor_device_id' => $reading->sensor_device_id],
                $reading->only(['warehouse_code', 'device_name', 'reading_value', 'unit', 'level', 'status', 'godown', 'compartment', 'recorded_at'])
            );
        }

        // Whole godown G1
        $this->withSession($session)->getJson('/admin/reports/data?' . http_build_query(['locations' => json_encode([['G1', null]])]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonPath('data.0.godown', 'G1');

        // G1/C2 together with G2/C3 must not mix into G1/C3 or G2/C2
        $this->withSession($session)->getJson('/admin/reports/data?' . http_build_query(['locations' => json_encode([['G1', 'C2'], ['G2', 'C3']])]))
            ->assertJsonPath('recordsFiltered', 2);

        // No warehouse selected -> no options
        $this->withSession($session)->getJson('/admin/reports/locations')
            ->assertOk()
            ->assertJsonPath('groups', []);

        // Only the selected warehouse's godowns / compartments
        $this->withSession($session)->getJson('/admin/reports/locations?warehouse_code=W1')
            ->assertOk()
            ->assertJsonPath('groups', [
                ['godown' => 'G1', 'compartments' => ['C1', 'C2']],
                ['godown' => 'G2', 'compartments' => ['C3']],
            ]);

        $rows = (new ReportExport(['locations' => json_encode([['G2', 'C3']])]))->query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('G2', $rows->first()->godown);

        $this->assertSame('G1, G2 / C3', ReportExport::describeLocations(json_encode([['G1', null], ['G2', 'C3']])));
    }

    public function test_report_chart_and_table_follow_day_night_and_date_range(): void
    {
        $admin = \App\Models\Admin::factory()->create();
        $session = ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'admin_email' => $admin->email];

        $make = fn (string $at, float $value, string $level, string $type = 'CO2') => Reading::create([
            'sensor_device_id' => 'DEV-' . $type,
            'device_type' => $type,
            'reading_value' => $value,
            'unit' => 'ppm',
            'level' => $level,
            'status' => 'online',
            'recorded_at' => $at,
        ]);

        $make('2026-11-10 09:00:00', 500, 'normal');   // day
        $make('2026-11-10 15:30:00', 900, 'severe');   // day
        $make('2026-11-10 22:00:00', 2000, 'critical'); // night
        $make('2026-11-11 03:00:00', 0.5, 'severe', 'PH3'); // night
        $make('2026-11-20 10:00:00', 100, 'normal');   // outside range
        $make('2026-11-10 11:00:00', 65534, 'critical'); // sensor error code, day

        $range = ['from_date' => '2026-11-10', 'to_date' => '2026-11-11'];

        // Table respects day / night
        // (the table still lists the sensor-error reading; only the chart leaves it out)
        $this->withSession($session)->getJson('/admin/reports/data?' . http_build_query($range + ['time_of_day' => 'day']))
            ->assertJsonPath('recordsFiltered', 3);
        $this->withSession($session)->getJson('/admin/reports/data?' . http_build_query($range + ['time_of_day' => 'night']))
            ->assertJsonPath('recordsFiltered', 2);

        // 2-day range -> hourly buckets; day shift only keeps 06:00-17:59 hours
        $day = $this->withSession($session)->getJson('/admin/reports/chart?' . http_build_query($range + ['time_of_day' => 'day']))
            ->assertOk()
            ->assertJsonPath('bucket', 'hour')
            ->json();

        $this->assertCount(24, $day['labels']);
        $this->assertSame('2026-11-10 06:00', $day['labels'][0]);
        $this->assertArrayNotHasKey('levels', $day);
        $this->assertSame(['CO2'], array_column($day['series'], 'gas'));
        $this->assertEquals(500, $day['series'][0]['avg'][array_search('2026-11-10 09:00', $day['labels'], true)]);
        $this->assertEquals(900, $day['series'][0]['max'][array_search('2026-11-10 15:00', $day['labels'], true)]);
        $this->assertLessThan(1700, max(array_filter($day['series'][0]['max'], fn ($v) => $v !== null)));
        $this->assertNull($day['series'][0]['max'][array_search('2026-11-10 11:00', $day['labels'], true)]);
        $this->assertEquals(700, $day['series'][0]['summary']['avg']);
        $this->assertEquals(900, $day['series'][0]['summary']['max']);
        $this->assertSame('severe', $day['series'][0]['summary']['max_level']);
        $this->assertSame(2, $day['series'][0]['summary']['readings']);
        $this->assertSame(1, $day['series'][0]['summary']['error_readings']);
        $this->assertEquals(['normal' => 800, 'severe' => 1700], $day['series'][0]['thresholds']);

        // Night: both gases, critical CO2 at 22:00
        $night = $this->withSession($session)->getJson('/admin/reports/chart?' . http_build_query($range + ['time_of_day' => 'night']))->json();
        $this->assertSame(['CO2', 'PH3'], array_column($night['series'], 'gas'));
        $this->assertEquals(2000, $night['series'][0]['max'][array_search('2026-11-10 22:00', $night['labels'], true)]);
        $this->assertEquals(0.5, $night['series'][1]['avg'][array_search('2026-11-11 03:00', $night['labels'], true)]);

        // Longer range -> daily buckets
        $daily = $this->withSession($session)->getJson('/admin/reports/chart?from_date=2026-11-01&to_date=2026-11-30')
            ->assertJsonPath('bucket', 'day')
            ->json();
        $this->assertCount(30, $daily['labels']);
        $co2 = collect($daily['series'])->firstWhere('gas', 'CO2');
        $this->assertEquals(2000, $co2['max'][array_search('2026-11-10', $daily['labels'], true)]);
        $this->assertNull($co2['avg'][array_search('2026-11-15', $daily['labels'], true)]);

        // Export uses the same day / night filter
        $this->assertCount(2, (new ReportExport($range + ['time_of_day' => 'night']))->query()->get());
    }

    public function test_severe_alert_export_uses_the_same_linked_alert_rule_as_reports(): void
    {
        $linked = $this->createSevereReading('LINKED');
        $this->createSevereReading('UNLINKED');

        Alert::create([
            'device_id' => 'LINKED',
            'reading_id' => $linked->id,
            'alert_type' => 'high_phosphorus',
            'alert_value' => 50,
            'type' => 'severe',
            'message' => 'Severe alert',
            'active' => true,
        ]);

        $rows = (new ReportExport(['report_type' => 'severe_alert']))->query()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('LINKED', $rows->first()->sensor_device_id);
    }

    private function createSevereReading(string $deviceId): Reading
    {
        return Reading::create([
            'sensor_device_id' => $deviceId,
            'device_name' => $deviceId,
            'reading_value' => 50,
            'unit' => 'ppm',
            'level' => 'severe',
            'status' => 'online',
            'recorded_at' => now(),
        ]);
    }
}
