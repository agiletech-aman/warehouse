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
        $this->assertSame('N/A', $mapped[9]);
        $this->assertSame('unknown', $mapped[11]);
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
