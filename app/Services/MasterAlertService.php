<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class MasterAlertService
{
    private const DEVICE_CO2 = 30000;

    private const DEVICE_PH3 = 30001;

    private const ALERT_TYPES = ['normal', 'severe', 'critical'];

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

        $this->applyFilter($query, 'region', $filters['state'] ?? null);
        $this->applyFilter($query, 'warehouse', $filters['location'] ?? null);

        $alertType = strtolower((string) ($filters['alertType'] ?? ''));
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
            $query->where('recorded_at', '>=', $filters['fromDate'].' 00:00:00');
        }

        if (! empty($filters['toDate'])) {
            $query->where('recorded_at', '<=', $filters['toDate'].' 23:59:59');
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

        $warehouses = Warehouse::query()
            ->whereIn('warehouse_name', $readings->pluck('warehouse')->filter()->unique())
            ->get(['warehouse_name', 'city'])
            ->keyBy('warehouse_name');

        $data = $readings->map(function (Reading $reading) use ($warehouses) {
            $warehouse = $warehouses->get($reading->warehouse);
            $alertType = strtolower((string) $reading->level);
            $alertType = in_array($alertType, self::ALERT_TYPES, true) ? $alertType : 'normal';
            $deviceTypeId = strtolower((string) $reading->device_type) === 'ph3'
                ? self::DEVICE_PH3
                : self::DEVICE_CO2;

            return [
                'id' => $reading->id,
                'shadName' => $reading->godown,
                'columnName' => $reading->compartment,
                'locationName' => $reading->warehouse,
                'location' => $reading->warehouse,
                'city' => $warehouse?->city ?: $reading->warehouse,
                'state' => $reading->region,
                'pinCode' => null,
                'deviceValue' => (float) $reading->reading_value,
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
            $query->where($column, $value);
        }
    }
}
