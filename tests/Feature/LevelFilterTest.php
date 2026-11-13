<?php

namespace Tests\Feature;

use App\Models\DeviceLatestStatus;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelFilterTest extends TestCase
{
    use RefreshDatabase;

    private function reading(string $sensorId, $value, ?string $level): void
    {
        Reading::create([
            'key' => 'test-key',
            'sensor_device_id' => $sensorId,
            'device_name' => $sensorId,
            'device_type' => 'PH3',
            'device_ip' => '172.22.41.24',
            'unit' => 'ppm',
            'port' => 502,
            'region' => 'MUMBAI',
            'region_code' => 'RE-MUM',
            'warehouse' => 'Warehouse A',
            'warehouse_code' => 'WH001',
            'godown' => 'Godown 1',
            'compartment' => 'Compartment A',
            'reading_value' => $value,
            'level' => $level,
            'status' => 'online',
            'recorded_at' => now(),
        ]);
    }

    private function filtered($query, array $levels): array
    {
        Reading::applyLevelFilter($query, $levels);

        return $query->orderBy('sensor_device_id')->pluck('sensor_device_id')->all();
    }

    public function test_level_filter_supports_unknown_and_matches_normalized_levels(): void
    {
        $this->reading('A_NORMAL', 10, 'normal');
        $this->reading('B_WARN', 50, 'warning');
        $this->reading('C_CRITICAL', 90, 'critical');
        $this->reading('D_NO_LEVEL', 5, null);

        $this->assertSame(['D_NO_LEVEL'], $this->filtered(Reading::query(), ['unknown']));
        $this->assertSame(['A_NORMAL'], $this->filtered(Reading::query(), ['normal']));
        $this->assertSame(['B_WARN'], $this->filtered(Reading::query(), ['severe']));
        $this->assertSame(['C_CRITICAL', 'D_NO_LEVEL'], $this->filtered(Reading::query(), ['critical', 'unknown']));
    }

    public function test_device_without_reading_value_is_unknown(): void
    {
        $this->reading('A_NORMAL', 10, 'normal');
        $this->reading('B_NO_VALUE', 20, 'normal');
        DeviceLatestStatus::query()->whereKey('B_NO_VALUE')->update(['reading_value' => null]);

        $this->assertSame(['B_NO_VALUE'], $this->filtered(DeviceLatestStatus::query(), ['unknown']));
        $this->assertSame(['A_NORMAL'], $this->filtered(DeviceLatestStatus::query(), ['normal']));
    }
}
