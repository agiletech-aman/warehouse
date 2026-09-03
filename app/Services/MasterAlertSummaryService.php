<?php

namespace App\Services;

use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MasterAlertSummaryService
{
    public const DASHBOARD_CACHE_VERSION_KEY = 'device_latest_status.dashboard.version';

    /**
     * The dashboard is a current-state view. Counts are calculated in SQL
     * against each sensor's latest row in the readings table.
     */
    public function fetchDashboard(array $filters): array
    {
        $cacheFilters = [
            'location' => $filters['location'] ?? null,
            'warehouseCode' => $filters['warehouseCode'] ?? null,
            'state' => $filters['state'] ?? null,
            'fromDate' => $filters['fromDate'] ?? null,
            'toDate' => $filters['toDate'] ?? null,
        ];
        ksort($cacheFilters);

        $version = (int) Cache::get(self::DASHBOARD_CACHE_VERSION_KEY, 1);
        $cacheKey = 'device_latest_status.dashboard.'.$version.'.'.sha1(json_encode($cacheFilters));

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($filters): array {
            return $this->buildDashboard($filters);
        });
    }

    public static function invalidateDashboardCache(): void
    {
        $key = self::DASHBOARD_CACHE_VERSION_KEY;
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
    }

    /**
     * This endpoint has historically represented historical alert/readings
     * counts. Keep that source and response contract unchanged.
     */
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

    private function buildDashboard(array $filters): array
    {
        $gas = $this->gasSql();

        $base = Reading::query()
            ->whereIn('id', Reading::latestIdsPerSensor())
            ->whereRaw("{$gas} IN (?, ?)", ['co2', 'ph3']);
        $this->applyFilters($base, $filters);

        $status = "LOWER(COALESCE(status, ''))";
        $severity = "CASE WHEN LOWER(COALESCE(level, '')) IN ('severe', 'critical') THEN LOWER(level) ELSE 'normal' END";

        $overall = (clone $base)
            ->selectRaw('COUNT(*) as total_iot_devices')
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' THEN 1 ELSE 0 END) as total_sensors_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' THEN 1 ELSE 0 END) as total_sensors_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$status} = 'online' THEN 1 ELSE 0 END) as total_online_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$status} <> 'online' THEN 1 ELSE 0 END) as total_offline_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$status} = 'online' THEN 1 ELSE 0 END) as total_online_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$status} <> 'online' THEN 1 ELSE 0 END) as total_offline_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'normal' THEN 1 ELSE 0 END) as total_normal_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'severe' THEN 1 ELSE 0 END) as total_severe_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'critical' THEN 1 ELSE 0 END) as total_critical_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'normal' THEN 1 ELSE 0 END) as total_normal_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'severe' THEN 1 ELSE 0 END) as total_severe_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'critical' THEN 1 ELSE 0 END) as total_critical_ph3")
            ->first();

        $locationRows = (clone $base)
            ->select(['region', 'warehouse'])
            ->selectRaw('COUNT(*) as total_iot_devices')
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' THEN 1 ELSE 0 END) as total_sensors_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' THEN 1 ELSE 0 END) as total_sensors_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$status} = 'online' THEN 1 ELSE 0 END) as online_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$status} <> 'online' THEN 1 ELSE 0 END) as offline_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$status} = 'online' THEN 1 ELSE 0 END) as online_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$status} <> 'online' THEN 1 ELSE 0 END) as offline_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'normal' THEN 1 ELSE 0 END) as normal_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'severe' THEN 1 ELSE 0 END) as severe_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND {$severity} = 'critical' THEN 1 ELSE 0 END) as critical_co2")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'normal' THEN 1 ELSE 0 END) as normal_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'severe' THEN 1 ELSE 0 END) as severe_ph3")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND {$severity} = 'critical' THEN 1 ELSE 0 END) as critical_ph3")
            ->groupBy('region', 'warehouse')
            ->orderBy('warehouse')
            ->get();

        $locationWise = [];
        foreach ($locationRows as $row) {
            $locationWise[$this->locationKey($row->warehouse, $row->region)] = [
                'state' => $row->region,
                'totalIotDevices' => (int) $row->total_iot_devices,
                'totalSensorsCO2' => (int) $row->total_sensors_co2,
                'totalSensorsPH3' => (int) $row->total_sensors_ph3,
                'onlineCO2' => (int) $row->online_co2,
                'offlineCO2' => (int) $row->offline_co2,
                'onlinePH3' => (int) $row->online_ph3,
                'offlinePH3' => (int) $row->offline_ph3,
                'normalCO2' => (int) $row->normal_co2,
                'severeCO2' => (int) $row->severe_co2,
                'criticalCO2' => (int) $row->critical_co2,
                'normalPH3' => (int) $row->normal_ph3,
                'severePH3' => (int) $row->severe_ph3,
                'criticalPH3' => (int) $row->critical_ph3,
                'locationName' => $row->warehouse,
            ];
        }
        ksort($locationWise);

        return [
            'overall' => [
                'totalIotDevices' => (int) ($overall->total_iot_devices ?? 0),
                'totalSensorsCO2' => (int) ($overall->total_sensors_co2 ?? 0),
                'totalSensorsPH3' => (int) ($overall->total_sensors_ph3 ?? 0),
                'totalOnlineCO2' => (int) ($overall->total_online_co2 ?? 0),
                'totalOfflineCO2' => (int) ($overall->total_offline_co2 ?? 0),
                'totalOnlinePH3' => (int) ($overall->total_online_ph3 ?? 0),
                'totalOfflinePH3' => (int) ($overall->total_offline_ph3 ?? 0),
                'totalNormalCO2' => (int) ($overall->total_normal_co2 ?? 0),
                'totalSevereCO2' => (int) ($overall->total_severe_co2 ?? 0),
                'totalCriticalCO2' => (int) ($overall->total_critical_co2 ?? 0),
                'totalNormalPH3' => (int) ($overall->total_normal_ph3 ?? 0),
                'totalSeverePH3' => (int) ($overall->total_severe_ph3 ?? 0),
                'totalCriticalPH3' => (int) ($overall->total_critical_ph3 ?? 0),
            ],
            'locationWise' => $locationWise,
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['location'])) {
            $query->whereRaw(
                'LOWER(TRIM(warehouse)) = ?',
                [strtolower(trim($filters['location']))]
            );
        }

        if (! empty($filters['warehouseCode'])) {
            $query->whereRaw(
                'LOWER(TRIM(warehouse_code)) = ?',
                [strtolower(trim($filters['warehouseCode']))]
            );
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

    private function gasSql(): string
    {
        return "LOWER(REPLACE(REPLACE(device_type, '₂', '2'), '₃', '3'))";
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
