<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MasterAlertService
{
    private const DEVICE_CO2 = 30000;

    private const DEVICE_PH3 = 30001;

    private const ALERT_TYPES = ['normal', 'severe', 'critical'];

    public function fetchDevices(array $filters): array
    {
        $page = max(1, (int) ($filters['pageNumber'] ?? 1));
        $pageSize = max(1, min((int) ($filters['pageSize'] ?? 20), 500));

        $query = Reading::query()
            ->whereIn('id', Reading::latestIdsPerSensor())
            ->whereRaw('LOWER(device_type) IN (?, ?)', ['co2', 'ph3']);

        if (isset($filters['deviceTypeId'])) {
            $deviceType = (int) $filters['deviceTypeId'] === self::DEVICE_PH3 ? 'ph3' : 'co2';
            $query->whereRaw('LOWER(device_type) = ?', [$deviceType]);
        }

        if (! empty($filters['warehouseName']) || ! empty($filters['warehouseCode'])) {
            $query->where(function (Builder $query) use ($filters) {
                if (! empty($filters['warehouseName'])) {
                    $query->where('warehouse', $filters['warehouseName']);
                }

                if (! empty($filters['warehouseCode'])) {
                    $method = ! empty($filters['warehouseName']) ? 'orWhere' : 'where';
                    $query->{$method}('warehouse_code', $filters['warehouseCode']);
                }
            });
        }

        if (is_array($filters['accessibleWarehouseNames'] ?? null)) {
            $warehouseNames = array_values(array_filter($filters['accessibleWarehouseNames']));
            $warehouseNames === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('warehouse', $warehouseNames);
        }

        $total = (clone $query)->count();

        $readings = $query
            ->latest('recorded_at')
            ->orderBy('sensor_device_id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get([
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
                'reading_value',
                'unit',
                'level',
                'status',
                'recorded_at',
            ]);

        $data = $readings->map(function (Reading $reading) {
            $location = collect([$reading->godown, $reading->compartment])
                ->filter(fn ($part) => $part !== null && $part !== '')
                ->implode(' / ');

            return [
                // Current-status rows are keyed by sensor id, not a historical
                // reading id. Keep the legacy response key with that stable id.
                'id' => $reading->sensor_device_id,
                'code' => $reading->sensor_device_id ?? 'N/A',
                'name' => $reading->device_name ?? 'N/A',
                'region' => $reading->region ?? 'N/A',
                'regionCode' => $reading->region_code ?? 'N/A',
                'warehouse' => $reading->warehouse ?? 'N/A',
                'warehouseCode' => $reading->warehouse_code ?? 'N/A',
                'type' => $reading->device_type
                    ? strtoupper((string) $reading->device_type)
                    : 'N/A',
                'location' => $location !== '' ? $location : 'N/A',
                'latestReading' => is_numeric($reading->reading_value)
                    ? (float) $reading->reading_value
                    : 'N/A',
                'unit' => $reading->unit ?? 'N/A',
                'latestReadingTime' => $reading->recorded_at?->format('Y-m-d\TH:i:s.v') ?? 'N/A',
                'level' => Reading::normalizeLevel($reading->reading_value, $reading->level),
                'status' => strtolower((string) ($reading->status ?: 'offline')),
                'deviceIp' => $reading->device_ip ?? 'N/A',
            ];
        })->values()->all();

        return [
            'totalCount' => $total,
            'pageNumber' => $page,
            'pageSize' => $pageSize,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
            'data' => $data,
        ];
    }

    public function fetchAlerts(array $filters): array
    {
        $page = max(1, (int) ($filters['pageNumber'] ?? 1));
        $pageSize = max(1, (int) ($filters['pageSize'] ?? 20));
        $deviceTypeId = isset($filters['deviceTypeId']) ? (int) $filters['deviceTypeId'] : null;

        $query = Reading::query()
            ->whereRaw('LOWER(device_type) IN (?, ?)', ['co2', 'ph3']);

        if ($deviceTypeId !== null) {
            $deviceType = $deviceTypeId === self::DEVICE_PH3 ? 'ph3' : 'co2';
            $query->whereRaw('LOWER(device_type) = ?', [$deviceType]);
        }

        $this->applyRegionBaseIdFilter(
            $query,
            $this->filterValue($filters['state'] ?? null)
        );
        $this->applyWarehouseBaseIdFilter(
            $query,
            $this->filterValue($filters['location'] ?? null)
        );

        $alertType = strtolower((string) $this->filterValue($filters['alertType'] ?? null));
        if (in_array($alertType, self::ALERT_TYPES, true)) {
            if ($alertType === 'normal') {
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("LOWER(COALESCE(level, '')) NOT IN (?, ?)", ['severe', 'critical']));
            } else {
                $query->whereRaw('LOWER(level) = ?', [$alertType]);
            }
        }

        if (! empty($filters['device'])) {
            $device = $filters['device'];
            $query->where(fn (Builder $query) => $query
                ->where('sensor_device_id', 'like', "%{$device}%")
                ->orWhere('device_name', 'like', "%{$device}%"));
        }

        if (! empty($filters['fromDate'])) {
            $query->where(
                'recorded_at',
                '>=',
                $this->filterDate((string) $filters['fromDate'], false)
            );
        }

        if (! empty($filters['toDate'])) {
            $query->where(
                'recorded_at',
                '<=',
                $this->filterDate((string) $filters['toDate'], true)
            );
        }

        if (is_array($filters['accessibleWarehouseNames'] ?? null)) {
            $warehouseNames = array_values(array_filter($filters['accessibleWarehouseNames']));
            $warehouseNames === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('warehouse', $warehouseNames);
        }

        $total = (clone $query)->count();
        $query->latest('recorded_at')->latest('id');

        if (! ($filters['allRecords'] ?? false)) {
            $query->offset(($page - 1) * $pageSize)->limit($pageSize);
        } else {
            $page = 1;
            $pageSize = $total;
        }

        $readings = $query->get();

        $warehouseCodes = $readings->pluck('warehouse_code')->filter()->unique()->values();
        $warehouseNames = $readings->pluck('warehouse')->filter()->unique()->values();

        $warehouses = Warehouse::query()
            ->whereIn('warehouse_code', $warehouseCodes)
            ->orWhereIn('warehouse_name', $warehouseNames)
            ->get(['id', 'frs_id', 'nms_id', 'warehouse_code', 'warehouse_name', 'city']);

        $warehousesByCode = $warehouses->keyBy(
            fn (Warehouse $warehouse) => strtoupper(trim((string) $warehouse->warehouse_code))
        );
        $warehousesByName = $warehouses->keyBy(
            fn (Warehouse $warehouse) => strtolower(trim((string) $warehouse->warehouse_name))
        );

        $regionCodes = $readings->pluck('region_code')->filter()->unique()->values();
        $regionNames = $readings->pluck('region')->filter()->unique()->values();

        $regions = Region::query()
            ->whereIn('region_code', $regionCodes)
            ->orWhereIn('region_name', $regionNames)
            ->get(['id', 'frs_id', 'nms_id', 'region_code', 'region_name']);

        $regionsByCode = $regions->keyBy(
            fn (Region $region) => strtoupper(trim((string) $region->region_code))
        );
        $regionsByName = $regions->keyBy(
            fn (Region $region) => strtolower(trim((string) $region->region_name))
        );

        $data = $readings->map(function (Reading $reading) use (
            $warehousesByCode,
            $warehousesByName,
            $regionsByCode,
            $regionsByName
        ) {
            $warehouse = $warehousesByCode->get(
                strtoupper(trim((string) $reading->warehouse_code))
            ) ?? $warehousesByName->get(
                strtolower(trim((string) $reading->warehouse))
            );
            $region = $regionsByCode->get(
                strtoupper(trim((string) $reading->region_code))
            ) ?? $regionsByName->get(
                strtolower(trim((string) $reading->region))
            );
            $hasReading = is_numeric($reading->reading_value);
            $normalizedLevel = Reading::normalizeLevel(
                $reading->reading_value,
                $reading->level
            );
            $alertType = ! $hasReading
                ? 'unknown'
                : (in_array($normalizedLevel, self::ALERT_TYPES, true) ? $normalizedLevel : 'normal');
            $deviceTypeId = strtolower((string) $reading->device_type) === 'ph3'
                ? self::DEVICE_PH3
                : self::DEVICE_CO2;

            return [
                'id' => $reading->id,
                'base_id' => $warehouse?->id,
                'frs_id' => $warehouse?->frs_id,
                'nms_id' => $warehouse?->nms_id,
                'region_base_id' => $region?->id,
                'region_frs_id' => $region?->frs_id,
                'region_nms_id' => $region?->nms_id,
                'deviceCode' => $reading->sensor_device_id,
                'deviceName' => $reading->device_name,
                'deviceType' => $reading->device_type,
                'shadName' => $reading->godown,
                'columnName' => $reading->compartment,
                'locationName' => $reading->warehouse,
                'location' => $reading->warehouse,
                'regionCode' => $reading->region_code,
                'warehouseCode' => $reading->warehouse_code,
                'city' => $warehouse?->city ?: $reading->warehouse,
                'state' => $reading->region,
                'pinCode' => null,
                'deviceValue' => $hasReading
                    ? (float) $reading->reading_value
                    : 'N/A',
                'unit' => $reading->unit,
                'deviceIp' => $reading->device_ip,
                'alertType' => strtoupper($alertType),
                'recordTime' => $reading->recorded_at?->format('Y-m-d\TH:i:s.v'),
                'regDate' => $reading->created_at?->format('Y-m-d\TH:i:s.v'),
                'deviceTypeId' => $deviceTypeId,
                'deviceStatus' => ucfirst(strtolower((string) $reading->status)),
            ];
        })->values()->all();

        return [
            'totalCount' => $total,
            'pageNumber' => $page,
            'pageSize' => $pageSize,
            'data' => $data,
        ];
    }

    private function applyFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $query->whereRaw(
                "LOWER(TRIM({$column})) = ?",
                [strtolower(trim((string) $value))]
            );
        }
    }

    private function applyRegionBaseIdFilter(Builder $query, ?string $baseId): void
    {
        if ($baseId === null) {
            return;
        }

        $region = ctype_digit($baseId)
            ? Region::query()->find((int) $baseId, ['region_code', 'region_name'])
            : null;

        if (! $region) {
            $query->whereRaw('1 = 0');

            return;
        }

        $region->region_code
            ? $this->applyFilter($query, 'region_code', $region->region_code)
            : $this->applyFilter($query, 'region', $region->region_name);
    }

    private function applyWarehouseBaseIdFilter(Builder $query, ?string $baseId): void
    {
        if ($baseId === null) {
            return;
        }

        $warehouse = ctype_digit($baseId)
            ? Warehouse::query()->find((int) $baseId, ['warehouse_code', 'warehouse_name'])
            : null;

        if (! $warehouse) {
            $query->whereRaw('1 = 0');

            return;
        }

        $warehouse->warehouse_code
            ? $this->applyFilter($query, 'warehouse_code', $warehouse->warehouse_code)
            : $this->applyFilter($query, 'warehouse', $warehouse->warehouse_name);
    }

    private function filterValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return in_array(strtolower($value), [
            'all',
            'all states',
            'all locations',
            'all alert types',
        ], true) ? null : $value;
    }

    private function filterDate(string $date, bool $endOfDay): Carbon
    {
        $date = trim($date);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            $value = Carbon::createFromFormat('!d/m/Y', $date);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $value = Carbon::createFromFormat('!Y-m-d', $date);
        } else {
            $value = Carbon::parse($date);
        }

        return $endOfDay ? $value->endOfDay() : $value->startOfDay();
    }
}
