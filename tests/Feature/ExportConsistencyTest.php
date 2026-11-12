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

        foreach ([['G1', 'C1'], ['G1', 'C2'], ['G2', 'C3']] as [$godown, $compartment]) {
            $reading = Reading::create([
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
                $reading->only(['device_name', 'reading_value', 'unit', 'level', 'status', 'godown', 'compartment', 'recorded_at'])
            );
        }

        $this->withSession($session)->getJson('/admin/reports/data?godown=G1')
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonPath('data.0.godown', 'G1');

        $this->withSession($session)->getJson('/admin/reports/data?godown=G1&compartment=C2')
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.compartment', 'C2');

        $this->withSession($session)->getJson('/admin/reports/locations?godown=G1')
            ->assertOk()
            ->assertJsonPath('godowns', ['G1', 'G2'])
            ->assertJsonPath('compartments', ['C1', 'C2']);

        $rows = (new ReportExport(['compartment' => 'C3']))->query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('G2', $rows->first()->godown);
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
