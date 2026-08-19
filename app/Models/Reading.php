<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Services\DeviceLatestStatusService;

class Reading extends Model
{
    use SoftDeletes;

    /** @var array<int, ?string> */
    private static array $originalSensorIds = [];

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

    protected static function booted(): void
    {
        static::updating(function (self $reading): void {
            self::$originalSensorIds[spl_object_id($reading)] = $reading->getOriginal('sensor_device_id');
        });

        // Keep the projection correct for every normal Eloquent write (web
        // forms, factories, imports), not only the ingestion endpoint.
        static::saved(function (self $reading): void {
            $latestStatus = app(DeviceLatestStatusService::class);
            $latestStatus->upsertFromReading($reading);

            $objectId = spl_object_id($reading);
            $originalSensorId = self::$originalSensorIds[$objectId] ?? null;
            unset(self::$originalSensorIds[$objectId]);

            if ($originalSensorId === null || $originalSensorId === $reading->sensor_device_id) {
                return;
            }

            // A corrected sensor id must not leave a phantom projection row.
            // Rebuild the old sensor from its remaining latest history, if any.
            $replacement = static::query()
                ->where('sensor_device_id', $originalSensorId)
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            if ($replacement) {
                $latestStatus->upsertFromReading($replacement);
            } else {
                DeviceLatestStatus::query()->whereKey($originalSensorId)->delete();
            }
        });
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

    public static function latestIdsPerSensor(
        bool $groupByDeviceType = false,
        ?string $warehouseName = null,
        ?string $warehouseCode = null
    ) {
        $applyWarehouseFilter = static function ($query, string $alias) use ($warehouseName, $warehouseCode): void {
            if (($warehouseName === null || $warehouseName === '')
                && ($warehouseCode === null || $warehouseCode === '')) {
                return;
            }

            $query->where(function ($query) use ($alias, $warehouseName, $warehouseCode) {
                if ($warehouseName !== null && $warehouseName !== '') {
                    $query->where("{$alias}.warehouse", $warehouseName);
                }

                if ($warehouseCode !== null && $warehouseCode !== '') {
                    $method = $warehouseName !== null && $warehouseName !== '' ? 'orWhere' : 'where';
                    $query->{$method}("{$alias}.warehouse_code", $warehouseCode);
                }
            });
        };

        $latestTimestamps = DB::table('readings as latest_source')
            ->select('latest_source.sensor_device_id')
            ->selectRaw('MAX(latest_source.recorded_at) as latest_recorded_at')
            ->whereNull('latest_source.deleted_at')
            ->whereNotNull('latest_source.sensor_device_id')
            ->groupBy('latest_source.sensor_device_id');

        $applyWarehouseFilter($latestTimestamps, 'latest_source');

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

        $applyWarehouseFilter($query, 'candidate');

        if ($groupByDeviceType) {
            $query->groupBy('candidate.device_type');
        }

        return $query;
    }
}
