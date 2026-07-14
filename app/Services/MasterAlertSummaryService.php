<?php

namespace App\Services;

use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MasterAlertSummaryService
{
    public function fetchDashboard(array $filters): array
    {
        $base = Reading::query();
        $this->applyFilters($base, $filters);

        $latestReadingIds = (clone $base)
            ->whereNotNull('sensor_device_id')
            ->selectRaw('MAX(id)')
            ->groupBy('sensor_device_id');

        $latestDevices = Reading::query()
            ->whereIn('id', $latestReadingIds)
            ->get([
                'sensor_device_id',
                'device_type',
                'status',
                'region',
                'warehouse',
            ]);

        $levelCounts = (clone $base)
            ->selectRaw('region, warehouse, LOWER(device_type) as gas, LOWER(level) as severity, COUNT(*) as aggregate')
            ->groupBy('region', 'warehouse', 'gas', 'severity')
            ->get();

        return [
            'overall' => $this->liveOverall($latestDevices, $levelCounts),
            'locationWise' => $this->liveLocationWise($latestDevices, $levelCounts),
        ];
    }

    public function fetchSummary(): array
    {
        $overall = [];

        foreach (['CO2' => 'co2', 'PH3' => 'ph3'] as $prefix => $deviceType) {
            $query = Reading::query()->whereRaw('LOWER(device_type) = ?', [$deviceType]);

            $overall["totalNormal{$prefix}"] = (clone $query)->where('level', 'normal')->count();
            $overall["totalSevere{$prefix}"] = (clone $query)->where('level', 'severe')->count();
            $overall["totalCritical{$prefix}"] = (clone $query)->where('level', 'critical')->count();
        }

        return ['overall' => $overall];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['location'])) {
            $query->whereRaw('LOWER(TRIM(warehouse)) = ?', [strtolower(trim($filters['location']))]);
        }

        if (! empty($filters['state'])) {
            $query->whereRaw('LOWER(TRIM(region)) = ?', [strtolower(trim($filters['state']))]);
        }

        if (! empty($filters['fromDate'])) {
            $query->where('recorded_at', '>=', $this->filterDate($filters['fromDate'], false));
        }

        if (! empty($filters['toDate'])) {
            $query->where('recorded_at', '<=', $this->filterDate($filters['toDate'], true));
        }
    }

    private function liveOverall($latestDevices, $levelCounts): array
    {
        return [
            'totalIotDevices' => $latestDevices->count(),
            'totalOnlineCO2' => $this->deviceStatusCount($latestDevices, 'co2', 'online'),
            'totalOfflineCO2' => $this->deviceStatusCount($latestDevices, 'co2', 'offline'),
            'totalOnlinePH3' => $this->deviceStatusCount($latestDevices, 'ph3', 'online'),
            'totalOfflinePH3' => $this->deviceStatusCount($latestDevices, 'ph3', 'offline'),
            'totalNormalCO2' => $this->levelCount($levelCounts, 'co2', 'normal'),
            'totalSevereCO2' => $this->levelCount($levelCounts, 'co2', 'severe'),
            'totalCriticalCO2' => $this->levelCount($levelCounts, 'co2', 'critical'),
            'totalNormalPH3' => $this->levelCount($levelCounts, 'ph3', 'normal'),
            'totalSeverePH3' => $this->levelCount($levelCounts, 'ph3', 'severe'),
            'totalCriticalPH3' => $this->levelCount($levelCounts, 'ph3', 'critical'),
        ];
    }

    private function liveLocationWise($latestDevices, $levelCounts): array
    {
        $locations = [];

        foreach ($levelCounts as $count) {
            $key = $this->locationKey($count->warehouse, $count->region);
            $this->initializeLocation($locations, $key, $count->warehouse, $count->region);

            $gas = strtoupper((string) $count->gas);
            $severity = strtolower((string) $count->severity);
            $field = $severity.$gas;

            if (array_key_exists($field, $locations[$key])) {
                $locations[$key][$field] += (int) $count->aggregate;
            }
        }

        foreach ($latestDevices as $device) {
            $key = $this->locationKey($device->warehouse, $device->region);
            $this->initializeLocation($locations, $key, $device->warehouse, $device->region);
            $locations[$key]['totalIotDevices']++;

            $gas = strtoupper(strtolower((string) $device->device_type));
            $status = strtolower((string) $device->status) === 'online' ? 'online' : 'offline';
            $field = $status.$gas;

            if (array_key_exists($field, $locations[$key])) {
                $locations[$key][$field]++;
            }
        }

        ksort($locations);

        return $locations;
    }

    private function initializeLocation(array &$locations, string $key, ?string $location, ?string $state): void
    {
        $locations[$key] ??= [
            'state' => $state,
            'totalIotDevices' => 0,
            'onlineCO2' => 0,
            'offlineCO2' => 0,
            'onlinePH3' => 0,
            'offlinePH3' => 0,
            'normalCO2' => 0,
            'severeCO2' => 0,
            'criticalCO2' => 0,
            'normalPH3' => 0,
            'severePH3' => 0,
            'criticalPH3' => 0,
            'locationName' => $location,
        ];
    }

    private function deviceStatusCount($devices, string $gas, string $status): int
    {
        return $devices->filter(fn (Reading $reading) => strtolower((string) $reading->device_type) === $gas
            && (strtolower((string) $reading->status) === 'online' ? 'online' : 'offline') === $status)->count();
    }

    private function levelCount($counts, string $gas, string $severity): int
    {
        return (int) $counts
            ->filter(fn ($row) => $row->gas === $gas && $row->severity === $severity)
            ->sum('aggregate');
    }

    private function locationKey(?string $location, ?string $state): string
    {
        return strtoupper(trim($location ?: 'UNKNOWN').'-'.trim($state ?: 'UNKNOWN'));
    }

    private function filterDate(string $date, bool $endOfDay): Carbon
    {
        $value = Carbon::parse($date);

        if (! str_contains($date, 'T') && ! str_contains($date, ':')) {
            $endOfDay ? $value->endOfDay() : $value->startOfDay();
        }

        return $value->setTimezone(config('app.timezone'));
    }
}
