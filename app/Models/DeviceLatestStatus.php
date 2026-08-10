<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeviceLatestStatus extends Model
{
    protected $table = 'device_latest_status';

    protected $primaryKey = 'sensor_device_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sensor_device_id',
        'device_type',
        'device_name',
        'status',
        'level',
        'reading_value',
        'unit',
        'device_ip',
        'port',
        'region',
        'region_code',
        'warehouse',
        'warehouse_code',
        'godown',
        'compartment',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Restrict a query to the two monitoring gases while accepting the unicode
     * subscript values emitted by older devices.
     */
    public function scopeMonitoringGases(Builder $query): Builder
    {
        return $query->whereIn('device_type', self::monitoringGasValues());
    }

    public function scopeDeviceTypeId(Builder $query, int $deviceTypeId): Builder
    {
        return $query->whereIn(
            'device_type',
            $deviceTypeId === 30001 ? self::ph3Values() : self::co2Values()
        );
    }

    public static function monitoringGasValues(): array
    {
        return [...self::co2Values(), ...self::ph3Values()];
    }

    public static function co2Values(): array
    {
        return ['co2', 'CO2', 'Co2', 'cO2', 'CO₂', 'co₂'];
    }

    public static function ph3Values(): array
    {
        return ['ph3', 'PH3', 'Ph3', 'pH3', 'PH₃', 'ph₃'];
    }
}
