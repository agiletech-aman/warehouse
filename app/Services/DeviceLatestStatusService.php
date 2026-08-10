<?php

namespace App\Services;

use App\Models\DeviceLatestStatus;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeviceLatestStatusService
{
    private const TABLE = 'device_latest_status';

    /** @var list<string> */
    private const STATUS_COLUMNS = [
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
        'created_at',
        'updated_at',
    ];

    /**
     * Copy a historical row into the current-status projection.  Empty sensor
     * identifiers are intentionally historical-only: they cannot be a stable
     * primary key for a current device.
     */
    public function upsertFromReading(Reading $reading): bool
    {
        return $this->upsert($this->attributesFromReading($reading));
    }

    /**
     * Insert a current status or update it only when the incoming timestamp is
     * at least as recent as the stored timestamp.  The native statements make
     * the comparison atomic under concurrent ingestion.
     */
    public function upsert(array $attributes): bool
    {
        $attributes = $this->normalizeAttributes($attributes);

        if ($attributes === null) {
            return false;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $affected = match ($driver) {
            'sqlite', 'pgsql' => $this->upsertWithConflictClause($attributes, $driver),
            'mysql', 'mariadb' => $this->upsertWithMysqlClause($attributes),
            default => $this->upsertWithLock($attributes),
        };

        if ($affected > 0) {
            // Dashboard responses are versioned; advancing the version makes
            // every cached filter variant stale without flushing unrelated cache.
            $this->invalidateDashboardCache();
        }

        return $affected > 0;
    }

    /** @return array<string, mixed> */
    public function attributesFromReading(Reading $reading): array
    {
        return [
            'sensor_device_id' => $reading->sensor_device_id,
            'device_type' => $reading->device_type,
            'device_name' => $reading->device_name,
            'status' => $reading->status ?? 'online',
            'level' => $reading->level,
            'reading_value' => $reading->reading_value,
            'unit' => $reading->unit,
            'device_ip' => $reading->device_ip,
            'port' => $reading->port,
            'region' => $reading->region,
            'region_code' => $reading->region_code,
            'warehouse' => $reading->warehouse,
            'warehouse_code' => $reading->warehouse_code,
            'godown' => $reading->godown,
            'compartment' => $reading->compartment,
            // A database default is not hydrated onto a just-created model.
            // Using now() mirrors readings.recorded_at's default in that case.
            'recorded_at' => $reading->recorded_at ?? now(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function upsertWithConflictClause(array $attributes, string $driver): int
    {
        $quote = $driver === 'pgsql' ? '"' : '"';
        $columnList = implode(', ', array_map(fn (string $column) => $quote.$column.$quote, self::STATUS_COLUMNS));
        $placeholders = implode(', ', array_fill(0, count(self::STATUS_COLUMNS), '?'));
        $updates = implode(', ', array_map(
            fn (string $column) => $quote.$column.$quote.' = excluded.'.$quote.$column.$quote,
            $this->updateColumns()
        ));

        $sql = 'INSERT INTO '.$quote.self::TABLE.$quote.' ('.$columnList.') VALUES ('.$placeholders.') '
            .'ON CONFLICT ('.$quote.'sensor_device_id'.$quote.') DO UPDATE SET '.$updates.' '
            .'WHERE excluded.'.$quote.'recorded_at'.$quote.' >= '
            .$quote.self::TABLE.$quote.'.'.$quote.'recorded_at'.$quote;

        return DB::affectingStatement($sql, $this->bindings($attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function upsertWithMysqlClause(array $attributes): int
    {
        $quote = '`';
        $columnList = implode(', ', array_map(fn (string $column) => $quote.$column.$quote, self::STATUS_COLUMNS));
        $placeholders = implode(', ', array_fill(0, count(self::STATUS_COLUMNS), '?'));
        $updates = implode(', ', array_map(
            fn (string $column) => $quote.$column.$quote
                .' = IF(VALUES('.$quote.'recorded_at'.$quote.') >= '.$quote.'recorded_at'.$quote.', '
                .'VALUES('.$quote.$column.$quote.'), '.$quote.$column.$quote.')',
            $this->updateColumns()
        ));

        $sql = 'INSERT INTO '.$quote.self::TABLE.$quote.' ('.$columnList.') VALUES ('.$placeholders.') '
            .'ON DUPLICATE KEY UPDATE '.$updates;

        return DB::affectingStatement($sql, $this->bindings($attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function upsertWithLock(array $attributes): int
    {
        return DB::transaction(function () use ($attributes): int {
            $current = DeviceLatestStatus::query()
                ->whereKey($attributes['sensor_device_id'])
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                try {
                    DeviceLatestStatus::query()->create($this->withoutTimestamps($attributes));

                    return 1;
                } catch (QueryException $exception) {
                    // Another transaction created the row after the first lock.
                    // Re-read it under lock and apply the same timestamp rule.
                    $current = DeviceLatestStatus::query()
                        ->whereKey($attributes['sensor_device_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($current === null) {
                        throw $exception;
                    }
                }
            }

            if ($current->recorded_at !== null && $current->recorded_at->gt($attributes['recorded_at'])) {
                return 0;
            }

            $current->fill($this->withoutTimestamps($attributes));
            $current->updated_at = $attributes['updated_at'];
            $current->save();

            return 1;
        });
    }

    /** @return list<string> */
    private function updateColumns(): array
    {
        return array_values(array_filter(
            self::STATUS_COLUMNS,
            fn (string $column) => $column !== 'sensor_device_id' && $column !== 'created_at'
        ));
    }

    /** @param array<string, mixed> $attributes */
    private function normalizeAttributes(array $attributes): ?array
    {
        $sensorId = trim((string) ($attributes['sensor_device_id'] ?? ''));
        if ($sensorId === '') {
            return null;
        }

        $timestamp = $attributes['recorded_at'] ?? now();
        if (! $timestamp instanceof CarbonInterface) {
            $timestamp = now()->parse($timestamp);
        }

        $now = now();

        return [
            'sensor_device_id' => $sensorId,
            'device_type' => $attributes['device_type'] ?? null,
            'device_name' => $attributes['device_name'] ?? null,
            'status' => strtolower((string) ($attributes['status'] ?? 'online')) ?: 'online',
            'level' => $attributes['level'] ?? null,
            'reading_value' => $attributes['reading_value'] ?? null,
            'unit' => $attributes['unit'] ?? null,
            'device_ip' => $attributes['device_ip'] ?? null,
            'port' => $attributes['port'] ?? null,
            'region' => $attributes['region'] ?? null,
            'region_code' => $attributes['region_code'] ?? null,
            'warehouse' => $attributes['warehouse'] ?? null,
            'warehouse_code' => $attributes['warehouse_code'] ?? null,
            'godown' => $attributes['godown'] ?? null,
            'compartment' => $attributes['compartment'] ?? null,
            'recorded_at' => $timestamp,
            'created_at' => $attributes['created_at'] ?? $now,
            'updated_at' => $now,
        ];
    }

    /** @param array<string, mixed> $attributes @return list<mixed> */
    private function bindings(array $attributes): array
    {
        $dateFormat = DB::connection()->getQueryGrammar()->getDateFormat();

        return array_map(function (string $column) use ($attributes, $dateFormat) {
            $value = $attributes[$column];

            return $value instanceof CarbonInterface ? $value->format($dateFormat) : $value;
        }, self::STATUS_COLUMNS);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function withoutTimestamps(array $attributes): array
    {
        unset($attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }

    private function invalidateDashboardCache(): void
    {
        $key = MasterAlertSummaryService::DASHBOARD_CACHE_VERSION_KEY;
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
    }
}
