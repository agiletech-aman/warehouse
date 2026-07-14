<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class MasterAlertService
{
    public function fetchAlerts(array $filters): array
    {
        $page = max(1, (int) ($filters['pageNumber'] ?? 1));
        $pageSize = max(1, (int) ($filters['pageSize'] ?? 20));
        $deviceTypeId = (int) ($filters['deviceTypeId'] ?? 30001);
        $deviceType = $deviceTypeId === 30002 ? 'ph3' : 'co2';

        $query = Reading::query()
            ->whereRaw('LOWER(device_type) = ?', [$deviceType]);

        $this->applyFilter($query, 'region', $filters['state'] ?? null);
        $this->applyFilter($query, 'warehouse', $filters['location'] ?? null);

        if (! empty($filters['alertType'])) {
            $query->whereRaw('UPPER(level) = ?', [strtoupper($filters['alertType'])]);
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
        $readings = $query
            ->latest('recorded_at')
            ->latest('id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $warehouses = Warehouse::query()
            ->whereIn('warehouse_name', $readings->pluck('warehouse')->filter()->unique())
            ->get(['warehouse_name', 'city'])
            ->keyBy('warehouse_name');

        $data = $readings->map(function (Reading $reading) use ($deviceTypeId, $warehouses) {
            $warehouse = $warehouses->get($reading->warehouse);

            return [
                'id' => $reading->id,
                'shadName' => $reading->godown,
                'columnName' => $reading->compartment,
                'locationName' => $reading->warehouse,
                'city' => $warehouse?->city ?: $reading->warehouse,
                'state' => $reading->region,
                'pinCode' => null,
                'deviceValue' => (float) $reading->reading_value,
                'deviceIp' => $reading->device_ip,
                'alertType' => strtoupper((string) $reading->level),
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
