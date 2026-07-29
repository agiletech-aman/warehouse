<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
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

    public static function normalizeLevel(mixed $readingValue, mixed $level): string
    {
        if ($readingValue === null || $readingValue === '' || !is_numeric($readingValue)) {
            return 'unknown';
        }

        $normalizedLevel = strtolower(trim((string) $level));

        return match ($normalizedLevel) {
            'warn', 'warning' => 'severe',
            'crit' => 'critical',
            '' => 'unknown',
            default => $normalizedLevel,
        };
    }

    public static function latestIdsPerSensor(bool $groupByDeviceType = false)
    {
        $latestTimestamps = DB::table('readings as latest_source')
            ->select('latest_source.sensor_device_id')
            ->selectRaw('MAX(latest_source.recorded_at) as latest_recorded_at')
            ->whereNull('latest_source.deleted_at')
            ->whereNotNull('latest_source.sensor_device_id')
            ->groupBy('latest_source.sensor_device_id');

        if ($groupByDeviceType) {
            $latestTimestamps
                ->addSelect('latest_source.device_type')
                ->groupBy('latest_source.device_type');
        }

        $query = DB::table('readings as candidate')
            ->joinSub($latestTimestamps, 'latest', function (JoinClause $join) use ($groupByDeviceType) {
                $join->on('latest.sensor_device_id', '=', 'candidate.sensor_device_id')
                    ->on('latest.latest_recorded_at', '=', 'candidate.recorded_at');

                if ($groupByDeviceType) {
                    $join->where(function (JoinClause $typeJoin) {
                        $typeJoin->whereColumn('latest.device_type', 'candidate.device_type')
                            ->orWhere(function (JoinClause $nullTypeJoin) {
                                $nullTypeJoin->whereNull('latest.device_type')
                                    ->whereNull('candidate.device_type');
                            });
                    });
                }
            })
            ->selectRaw('MAX(candidate.id)')
            ->whereNull('candidate.deleted_at')
            ->whereNotNull('candidate.sensor_device_id')
            ->groupBy('candidate.sensor_device_id');

        if ($groupByDeviceType) {
            $query->groupBy('candidate.device_type');
        }

        return $query;
    }
}
