<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Reading extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'sensor_device_id',

        // FK optional
        'device_id',
        'device_name',
        'device_type',
        'device_ip',

        'unit',
        'port',

        'region',
        'region_code',

        'warehouse',
        'warehouse_code',

        'godown',
        'compartment',

        'reading_value',
        'level',
        'status',

        'recorded_at'
    ];



    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public static function latestIdsPerSensor(bool $groupByDeviceType = false)
    {
        $typeMatch = $groupByDeviceType
            ? ' AND ((newer.device_type = candidate.device_type) OR (newer.device_type IS NULL AND candidate.device_type IS NULL))'
            : '';

        $query = DB::table('readings as candidate')
            ->selectRaw('MAX(candidate.id)')
            ->whereNull('candidate.deleted_at')
            ->whereNotNull('candidate.sensor_device_id')
            ->whereRaw(
                'candidate.recorded_at = (SELECT MAX(newer.recorded_at) FROM readings newer WHERE newer.deleted_at IS NULL AND newer.sensor_device_id = candidate.sensor_device_id' . $typeMatch . ')'
            )
            ->groupBy('candidate.sensor_device_id');

        if ($groupByDeviceType) {
            $query->groupBy('candidate.device_type');
        }

        return $query;
    }
}

