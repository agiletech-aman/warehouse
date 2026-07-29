<?php

namespace Tests\Feature;

use App\Exports\DeviceDetailedSummaryExport;
use App\Exports\ReportExport;
use App\Exports\WarehousesExport;
use App\Models\Alert;
use App\Models\Reading;
use App\Models\Warehouse;
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

    public function test_warehouse_export_includes_co2_and_ph3_device_counts(): void
    {
        foreach ([
            ['CO2-1', 'CO₂'],
            ['CO2-2', 'CO2'],
            ['PH3-1', 'PH₃'],
            ['TEMP-1', 'TEMP'],
        ] as [$sensorId, $deviceType]) {
            Reading::create([
                'sensor_device_id' => $sensorId,
                'device_type' => $deviceType,
                'region' => 'Chennai',
                'region_code' => 'RE-CHE',
                'warehouse' => 'Warehouse A',
                'warehouse_code' => 'WH-A',
                'reading_value' => 10,
                'unit' => 'ppm',
                'status' => 'online',
                'recorded_at' => now(),
            ]);
        }

        $warehouse = new Warehouse([
            'warehouse_code' => 'WH-A',
            'warehouse_name' => 'Warehouse A',
            'status' => 'active',
        ]);
        $export = new WarehousesExport();
        $mapped = $export->map($warehouse);

        $this->assertCount(count($export->headings()), $mapped);
        $this->assertSame(2, $mapped[13]);
        $this->assertSame(1, $mapped[14]);
    }

    public function test_device_detailed_summary_export_contains_warehouse_counts(): void
    {
        foreach ([
            ['CO2-ONLINE', 'CO2', 'online'],
            ['CO2-OFFLINE', 'CO₂', 'offline'],
            ['PH3-ONLINE', 'PH3', 'online'],
        ] as [$sensorId, $deviceType, $status]) {
            Reading::create([
                'sensor_device_id' => $sensorId,
                'device_type' => $deviceType,
                'region' => 'Chennai',
                'region_code' => 'RE-CHE',
                'warehouse' => 'Warehouse A',
                'warehouse_code' => 'WH-A',
                'reading_value' => 10,
                'unit' => 'ppm',
                'status' => $status,
                'recorded_at' => now(),
            ]);
        }

        $export = new DeviceDetailedSummaryExport();
        $row = $export->collection()->first();

        $this->assertCount(count($export->headings()), $row);
        $this->assertSame(
            ['Warehouse A', 'WH-A', 'Chennai', 'RE-CHE', 3, 2, 1, 2, 1, 1, 1, 1, 0],
            $row
        );
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
