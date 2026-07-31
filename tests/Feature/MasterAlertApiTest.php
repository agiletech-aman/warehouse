<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAlertApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_co2_alerts_use_device_type_30000_and_uppercase_alert_types(): void
    {
        foreach (['normal', 'severe', 'critical'] as $index => $level) {
            $this->createReading('co2', $level, $index);
        }

        $response = $this->getJson('/api/master-alerts?deviceTypeId=30000&pageSize=10');

        $response->assertOk()
            ->assertJsonPath('totalCount', 3)
            ->assertJsonPath('pageSize', 10)
            ->assertJsonPath('data.0.location', 'Test Warehouse')
            ->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing(
            ['NORMAL', 'SEVERE', 'CRITICAL'],
            collect($response->json('data'))->pluck('alertType')->all()
        );
        $this->assertSame(
            [30000],
            collect($response->json('data'))->pluck('deviceTypeId')->unique()->values()->all()
        );
    }

    public function test_master_alerts_combine_co2_and_ph3_with_row_specific_device_type_ids(): void
    {
        $this->createReading('ph3', 'severe');
        $this->createReading('co2', 'critical', 1);

        $response = $this->getJson('/api/master-alerts?deviceTypeId=30001&pageSize=10');

        $response->assertOk()
            ->assertJsonPath('totalCount', 2)
            ->assertJsonCount(2, 'data');

        $alertsByType = collect($response->json('data'))->keyBy('alertType');

        $this->assertSame(30001, $alertsByType->get('SEVERE')['deviceTypeId']);
        $this->assertSame(30000, $alertsByType->get('CRITICAL')['deviceTypeId']);
    }

    public function test_missing_level_is_returned_as_normal_instead_of_being_dropped(): void
    {
        $reading = $this->createReading('ph3', 'normal');
        $reading->update(['level' => null]);

        $this->getJson('/api/master-alerts?pageSize=10')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('data.0.alertType', 'NORMAL')
            ->assertJsonPath('data.0.deviceTypeId', 30001);
    }

    public function test_master_alerts_show_unknown_and_na_when_reading_is_missing(): void
    {
        $reading = $this->createReading('co2', 'normal');
        $reading->update([
            'reading_value' => '',
            'level' => null,
        ]);

        $this->getJson('/api/master-alerts?pageSize=10')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('data.0.deviceValue', 'N/A')
            ->assertJsonPath('data.0.alertType', 'UNKNOWN');

        $this->getJson('/api/master-alerts/devices?pageSize=10')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('data.0.latestReading', 'N/A')
            ->assertJsonPath('data.0.level', 'unknown');
    }

    public function test_master_alerts_are_paginated_twenty_records_at_a_time(): void
    {
        foreach (range(1, 25) as $offset) {
            $this->createReading($offset % 2 === 0 ? 'co2' : 'ph3', 'normal', $offset);
        }

        $this->getJson('/api/master-alerts?pageNumber=1')
            ->assertOk()
            ->assertJsonPath('totalCount', 25)
            ->assertJsonPath('pageNumber', 1)
            ->assertJsonPath('pageSize', 20)
            ->assertJsonCount(20, 'data');

        $this->getJson('/api/master-alerts?pageNumber=2')
            ->assertOk()
            ->assertJsonPath('totalCount', 25)
            ->assertJsonPath('pageNumber', 2)
            ->assertJsonPath('pageSize', 20)
            ->assertJsonCount(5, 'data');
    }

    public function test_master_alerts_can_be_filtered_with_url_query_parameters(): void
    {
        $region = Region::create([
            'frs_id' => '901',
            'nms_id' => 902,
            'region_code' => 'RE-BHU',
            'region_name' => 'BHUBANESWAR',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::create([
            'frs_id' => '903',
            'nms_id' => 904,
            'region_id' => $region->id,
            'warehouse_code' => 'WH-TEST',
            'warehouse_name' => 'TEST LOCATION',
            'manager_name' => 'Manager',
            'status' => 'active',
        ]);

        $matching = $this->createReading('co2', 'severe');
        $matching->update([
            'region' => 'BHUBANESWAR',
            'region_code' => 'RE-BHU',
            'warehouse' => 'TEST LOCATION',
            'warehouse_code' => 'WH-TEST',
            'recorded_at' => '2026-07-15 12:00:00',
        ]);

        $this->createReading('ph3', 'critical', 1)->update([
            'region' => 'BHUBANESWAR',
            'warehouse' => 'OTHER LOCATION',
            'recorded_at' => '2026-07-15 12:00:00',
        ]);

        $this->getJson(
            "/api/master-alerts?state={$region->id}"
            ."&location={$warehouse->id}"
            .'&alert_type=SEVERE'
            .'&from_date=15%2F07%2F2026'
            .'&to_date=15%2F07%2F2026'
        )
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.alertType', 'SEVERE');
    }

    public function test_locations_preserve_roman_numerals_in_warehouse_names(): void
    {
        $region = Region::create([
            'frs_id' => '911',
            'nms_id' => 912,
            'region_code' => 'RE-RAJ',
            'region_name' => 'RAJASTHAN',
            'status' => 'active',
        ]);
        Warehouse::create([
            'frs_id' => '913',
            'nms_id' => 914,
            'region_id' => $region->id,
            'warehouse_code' => 'WH-ROMAN',
            'warehouse_name' => 'HANUMANGARH -II',
            'manager_name' => 'Manager',
            'status' => 'active',
        ]);

        $this->getJson("/api/master-alerts/states/{$region->id}/locations")
            ->assertOk()
            ->assertJsonPath('0.base_id', 1)
            ->assertJsonPath('0.name', 'HANUMANGARH -II');
    }

    public function test_master_alert_devices_return_full_latest_device_data_with_ip(): void
    {
        $olderReading = $this->createReading('ph3', 'severe');
        $olderReading->update([
            'sensor_device_id' => 'PH3_192_168_1_10_1',
            'device_name' => 'PH3-12',
            'device_ip' => '192.168.1.10',
            'region' => 'CHANDIGARH',
            'region_code' => 'RE-CHA',
            'warehouse' => 'ROPAR(PEG)',
            'warehouse_code' => 'WH073',
            'godown' => 'G4',
            'compartment' => 'CA',
            'reading_value' => 8.5,
            'unit' => 'ppm',
            'recorded_at' => now()->subMinute(),
        ]);

        Reading::create(array_merge(
            $olderReading->only([
                'sensor_device_id',
                'device_name',
                'device_type',
                'device_ip',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
                'godown',
                'compartment',
                'unit',
            ]),
            [
                'reading_value' => 4.25,
                'level' => 'normal',
                'status' => 'online',
                'recorded_at' => now(),
            ]
        ));

        $this->getJson('/api/master-alerts/devices?deviceTypeId=30001')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('deviceTypeId', 30001)
            ->assertJsonPath('gasType', 'PH3')
            ->assertJsonPath('data.0.code', 'PH3_192_168_1_10_1')
            ->assertJsonPath('data.0.name', 'PH3-12')
            ->assertJsonPath('data.0.region', 'CHANDIGARH')
            ->assertJsonPath('data.0.regionCode', 'RE-CHA')
            ->assertJsonPath('data.0.warehouse', 'ROPAR(PEG)')
            ->assertJsonPath('data.0.warehouseCode', 'WH073')
            ->assertJsonPath('data.0.type', 'PH3')
            ->assertJsonPath('data.0.location', 'G4 / CA')
            ->assertJsonPath('data.0.latestReading', 4.25)
            ->assertJsonPath('data.0.unit', 'ppm')
            ->assertJsonPath('data.0.level', 'normal')
            ->assertJsonPath('data.0.status', 'online')
            ->assertJsonPath('data.0.deviceIp', '192.168.1.10')
            ->assertJsonStructure(['data' => [['latestReadingTime']]]);
    }

    public function test_master_alert_devices_can_be_filtered_by_warehouse_id(): void
    {
        $region = Region::create([
            'region_code' => 'RE-TEST',
            'region_name' => 'Test Region',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::create([
            'nms_id' => 9001,
            'region_id' => $region->id,
            'warehouse_code' => 'WH-ONE',
            'warehouse_name' => 'Warehouse One',
            'manager_name' => 'Manager',
            'status' => 'active',
        ]);

        $first = $this->createReading('co2', 'normal');
        $first->update([
            'warehouse' => 'Warehouse One',
            'warehouse_code' => 'WH-ONE',
        ]);
        $second = $this->createReading('ph3', 'normal', 1);
        $second->update([
            'warehouse' => 'Warehouse Two',
            'warehouse_code' => 'WH-TWO',
        ]);
        Reading::create(array_merge(
            $first->only([
                'sensor_device_id',
                'device_name',
                'device_type',
                'device_ip',
                'region',
                'region_code',
                'godown',
                'compartment',
                'reading_value',
                'unit',
                'level',
                'status',
            ]),
            [
                'warehouse' => 'Warehouse Two',
                'warehouse_code' => 'WH-TWO',
                'recorded_at' => now()->addSecond(),
            ]
        ));

        $this->getJson("/api/master-alerts/devices/{$warehouse->nms_id}")
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('pageNumber', 1)
            ->assertJsonPath('pageSize', 20)
            ->assertJsonPath('totalPages', 1)
            ->assertJsonPath('warehouseNmsId', $warehouse->nms_id)
            ->assertJsonPath('warehouseId', $warehouse->nms_id)
            ->assertJsonPath('data.0.warehouse', 'Warehouse One')
            ->assertJsonPath('data.0.code', $first->sensor_device_id)
            ->assertJsonCount(1, 'data');
    }

    public function test_master_alert_devices_return_all_types_with_pagination_without_warehouse_id(): void
    {
        $this->createReading('co2', 'normal');
        $this->createReading('ph3', 'normal', 1);
        $this->createReading('co2', 'normal', 2);

        $response = $this->getJson('/api/master-alerts/devices?pageNumber=2&pageSize=2');

        $response->assertOk()
            ->assertJsonPath('totalCount', 3)
            ->assertJsonPath('pageNumber', 2)
            ->assertJsonPath('pageSize', 2)
            ->assertJsonPath('totalPages', 2)
            ->assertJsonPath('deviceTypeId', 'N/A')
            ->assertJsonPath('warehouseNmsId', 'N/A')
            ->assertJsonPath('warehouseId', 'N/A')
            ->assertJsonPath('gasType', 'ALL')
            ->assertJsonCount(1, 'data');

        $this->assertEqualsCanonicalizing(
            ['CO2', 'PH3'],
            collect($this->getJson('/api/master-alerts/devices?pageSize=10')->json('data'))
                ->pluck('type')
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_master_alert_devices_do_not_return_null_values(): void
    {
        $this->createReading('co2', 'normal')->update([
            'device_ip' => null,
            'region' => null,
            'region_code' => null,
            'warehouse_code' => null,
            'godown' => null,
            'compartment' => null,
        ]);

        $response = $this->getJson('/api/master-alerts/devices');

        $response->assertOk()
            ->assertJsonPath('warehouseId', 'N/A')
            ->assertJsonPath('warehouseNmsId', 'N/A')
            ->assertJsonPath('deviceTypeId', 'N/A')
            ->assertJsonPath('data.0.deviceIp', 'N/A')
            ->assertJsonPath('data.0.region', 'N/A')
            ->assertJsonPath('data.0.regionCode', 'N/A')
            ->assertJsonPath('data.0.warehouseCode', 'N/A')
            ->assertJsonPath('data.0.location', 'N/A');

        $this->assertNotContains(null, $response->json('data.0'), true);
    }

    private function createReading(string $deviceType, string $level, int $offset = 0): Reading
    {
        return Reading::create([
            'sensor_device_id' => strtoupper($deviceType).'-'.$level.'-'.$offset,
            'device_name' => strtoupper($deviceType).' Sensor',
            'device_type' => $deviceType,
            'reading_value' => 10 + $offset,
            'unit' => 'ppm',
            'warehouse' => 'Test Warehouse',
            'level' => $level,
            'status' => 'online',
            'recorded_at' => now()->subSeconds($offset),
        ]);
    }
}
