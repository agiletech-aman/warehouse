<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Alert;
use App\Models\DeviceLatestStatus;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function summary(Request $request)
    {
        $filters = $this->extractFilters($request);

        $readingsBase = $this->buildReadingsQuery($filters);

        $totalReadings = (clone $readingsBase)->count();

        // Count devices from the same projection as the Devices page. Readings keep the status they
        // arrived with, while devices:mark-stale-offline only updates device_latest_status.
        $deviceCounts = $this->buildDeviceStatusQuery($filters)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) AS online")
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) AS offline")
            ->first();

        $totalDevices = (int) ($deviceCounts->total ?? 0);
        $onlineDevices = (int) ($deviceCounts->online ?? 0);
        $offlineDevices = (int) ($deviceCounts->offline ?? 0);

        $regionsCount = !empty($filters['region_code']) || !empty($filters['region_name'])
            ? 1
            : $this->distinctLocationCount($readingsBase, 'region_code', 'region');

        $warehousesCount = !empty($filters['warehouse_code']) || !empty($filters['warehouse_name'])
            ? 1
            : $this->distinctLocationCount($readingsBase, 'warehouse_code', 'warehouse');

        // Severe/Critical alerts counts: prefer Alert.type filters within date range.
        $alertsBase = $this->buildAlertsQuery($filters);
        $severeAlerts = (clone $alertsBase)->where('type', 'severe')->count();
        $criticalAlerts = (clone $alertsBase)->where('type', 'critical')->count();

        // Chart datasets
        [$readingsTrend, $alertsTrend, $levelDistribution, $regionDeviceCounts] = $this->buildCharts($filters);
        $onlineVsOffline = [
            'online' => $onlineDevices,
            'offline' => $offlineDevices,
        ];

        return response()->json([
            'success' => true,
            'stats' => [
                'total_readings' => $totalReadings,
                'total_devices' => $totalDevices,
                'online_devices' => $onlineDevices,
                'offline_devices' => $offlineDevices,
                'severe_alerts' => $severeAlerts,
                'critical_alerts' => $criticalAlerts,
                'regions_count' => $regionsCount,
                'warehouses_count' => $warehousesCount,
            ],
            'charts' => [
                'readings_trend' => $readingsTrend,
                'alerts_trend' => $alertsTrend,
                'online_vs_offline_devices' => $onlineVsOffline,
                'region_wise_device_count' => $regionDeviceCounts,
                'level_distribution' => $levelDistribution,
            ],
        ]);
    }

    public function data(Request $request)
    {
        $draw = (int) $request->query('draw', 1);
        $start = (int) $request->query('start', 0);
        $length = (int) $request->query('length', 10);
        if ($length <= 0) {
            $length = 10;
        }

        $filters = $this->extractFilters($request);

        $readingsBase = $this->buildReadingsQuery($filters);
        $this->applyDataTableSearch($readingsBase, $request);

        $recordsTotal = Reading::query()->count();
        $recordsFiltered = (clone $readingsBase)->count();

        $data = $readingsBase
            ->latest('recorded_at')
            ->offset($start)
            ->limit($length)
            ->get([
                'recorded_at',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
                'device_name',
                'sensor_device_id',
                'device_type',
                'device_ip',
                'reading_value',
                'unit',
                'level',
                'status',
            ])
            ->map(function ($r) {
                return [
                    'date_time' => $r->recorded_at ? $r->recorded_at->format('d M Y H:i:s') : '-',
                    'recorded_at_iso' => $r->recorded_at ? $r->recorded_at->toDateTimeString() : null,
                    'region' => $r->region ?: '-',
                    'region_code' => $r->region_code ?: '-',
                    'warehouse' => $r->warehouse ?: '-',
                    'warehouse_code' => $r->warehouse_code ?: '-',
                    'device_name' => $r->device_name ?: '-',
                    'device_code' => $r->sensor_device_id ?: '-',
                    'device_type' => $r->device_type ?: '-',
                    'device_ip' => $r->device_ip ?: '-',
                    'value' => $r->reading_value,
                    'unit' => $r->unit ?: '-',
                    'level' => Reading::normalizeLevel($r->reading_value, $r->level),
                    'status' => $r->status ?: 'offline',
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request, string $format)
    {
        $format = strtolower($format);
        if (!in_array($format, ['pdf', 'excel', 'csv'], true)) {
            abort(404);
        }

        $filters = $this->extractFilters($request);
        $filters['selected_cols'] = $request->query('selected_cols');

        if ($format === 'pdf') {

            // For PDF we render a simple HTML table from the same export query.
            $pdfData = [
                'filters' => $filters,
                'rows' => $this->buildReadingsQuery($filters)
                    ->latest('recorded_at')
                    ->get([
                        'recorded_at', 'region', 'region_code', 'warehouse', 'warehouse_code',
                        'device_name', 'sensor_device_id', 'device_type', 'device_ip',
                        'reading_value', 'unit', 'level', 'status'
                    ]),
            ];

            $pdf = PDF::loadView('reports.exports.pdf', $pdfData)->setPaper('a4', 'landscape');
            return $pdf->download('reports_' . date('Ymd_His') . '.pdf');
        }

        // Excel + CSV via maatwebsite/excel
        $export = new ReportExport($filters);
        $fileName = 'reports_' . date('Ymd_His');

        if ($format === 'excel') {
            return Excel::download($export, $fileName . '.xlsx');
        }

        // CSV
        return Excel::download($export, $fileName . '.csv', 
            \Maatwebsite\Excel\Excel::CSV);
    }

    private function extractFilters(Request $request): array
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $reportType = (string) $request->query('report_type', 'reading');

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'region_id' => $request->query('region_id'),
            'region_code' => $request->query('region_code'),
            'region_name' => $request->query('region_name'),

            'warehouse_id' => $request->query('warehouse_id'),
            'warehouse_code' => $request->query('warehouse_code'),
            'warehouse_name' => $request->query('warehouse_name'),

            'device_type' => $request->query('device_type'),
            'device_code' => $request->query('device_code'), 
            'device_name' => $request->query('device_name'),

            'status' => $request->query('status'),
            'level' => $request->query('level'),

            'current_only' => $request->boolean('current_only'),

            'report_type' => $reportType,
        ];

        return $filters;
    }

    private function buildReadingsQuery(array $filters)
    {
        $q = Reading::query();

        // Date range
        if (!empty($filters['from_date'])) {
            $q->where('recorded_at', '>=', Carbon::parse($filters['from_date'])->format('Y-m-d') . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $q->where('recorded_at', '<=', Carbon::parse($filters['to_date'])->format('Y-m-d') . ' 23:59:59');
        }

        // Unified filters
        if (!empty($filters['region_code'])) {
            $q->whereIn('region_code', $this->splitMultiValue($filters['region_code']));
        }
        if (!empty($filters['region_name'])) {
            $q->where('region', $filters['region_name']);
        }

        if (!empty($filters['warehouse_code'])) {
            $q->whereIn('warehouse_code', $this->splitMultiValue($filters['warehouse_code']));
        }
        if (!empty($filters['warehouse_name'])) {
            $q->where('warehouse', $filters['warehouse_name']);
        }

        if (!empty($filters['device_type'])) {
            $q->where('device_type', $filters['device_type']);
        }

        if (!empty($filters['device_code'])) {
            $q->where('sensor_device_id', $filters['device_code']);
        }
        if (!empty($filters['device_name'])) {
            $q->where('device_name', $filters['device_name']);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['level'])) {
            $q->whereIn('level', $this->splitMultiValue($filters['level']));
        }

        if (!empty($filters['current_only'])) {
            $q->whereIn('id', $this->latestDeviceReadingIds());
        }

        // Report type adjustments (filters only)
        $reportType = strtolower((string) ($filters['report_type'] ?? 'reading'));

        switch ($reportType) {
            case 'alert':
                // readings that have alerts -> handled by distinct reading_id mapping via alerts query
                $q->whereIn('id', $this->alertReadingIds($filters));
                break;
            case 'severe_alert':
                $q->where('level', 'severe');
                $q->whereIn('id', $this->alertReadingIds($filters, 'severe'));
                break;
            case 'critical_alert':
                $q->where('level', 'critical');
                $q->whereIn('id', $this->alertReadingIds($filters, 'critical'));
                break;
            case 'offline_device':
                // Offline devices in your schema are mostly stored in devices.status; we approximate by readings.status=offline.
                $q->where('status', 'offline');
                break;
            default:
                // other report types map to reading filters already handled
                break;
        }

        return $q;
    }

    /**
     * @return array<int, string>
     */
    private function splitMultiValue($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $values), fn ($v) => $v !== ''));
    }

    private function applyDataTableSearch($query, Request $request): void
    {
        $value = trim((string) data_get($request->query('search', []), 'value', ''));

        if ($value === '') {
            return;
        }

        $query->where(function ($q) use ($value) {
            $q->where('recorded_at', 'like', '%' . $value . '%')
                ->orWhere('region', 'like', '%' . $value . '%')
                ->orWhere('region_code', 'like', '%' . $value . '%')
                ->orWhere('warehouse', 'like', '%' . $value . '%')
                ->orWhere('warehouse_code', 'like', '%' . $value . '%')
                ->orWhere('device_name', 'like', '%' . $value . '%')
                ->orWhere('sensor_device_id', 'like', '%' . $value . '%')
                ->orWhere('device_type', 'like', '%' . $value . '%')
                ->orWhere('device_ip', 'like', '%' . $value . '%')
                ->orWhere('reading_value', 'like', '%' . $value . '%')
                ->orWhere('unit', 'like', '%' . $value . '%')
                ->orWhere('level', 'like', '%' . $value . '%')
                ->orWhere('status', 'like', '%' . $value . '%');
        });
    }

    private function buildAlertsQuery(array $filters)
    {
        $q = Alert::query()->whereNull('deleted_at');

        // Date range by created_at
        if (!empty($filters['from_date'])) {
            $q->where('created_at', '>=', Carbon::parse($filters['from_date'])->format('Y-m-d') . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $q->where('created_at', '<=', Carbon::parse($filters['to_date'])->format('Y-m-d') . ' 23:59:59');
        }

        if (!empty($filters['device_code'])) {
            $q->where('device_id', $filters['device_code']);
        }

        // Optional: region/warehouse via reading_id if present
        // (kept minimal; table still unified reading-based)

        return $q;
    }

    private function alertReadingIds(array $filters, ?string $type = null): array
    {
        $alerts = Alert::query();

        if (!empty($filters['from_date'])) {
            $alerts->where('created_at', '>=', Carbon::parse($filters['from_date'])->format('Y-m-d') . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $alerts->where('created_at', '<=', Carbon::parse($filters['to_date'])->format('Y-m-d') . ' 23:59:59');
        }

        if ($type) {
            $alerts->where('type', $type);
        }

        if (!empty($filters['device_code'])) {
            $alerts->where('device_id', $filters['device_code']);
        }

        return $alerts->whereNotNull('reading_id')->pluck('reading_id')->unique()->values()->all();
    }

    /**
     * Report filters applied to device_latest_status, where each row is already a device's latest reading.
     */
    private function buildDeviceStatusQuery(array $filters)
    {
        $q = DeviceLatestStatus::query();

        if (!empty($filters['from_date'])) {
            $q->where('recorded_at', '>=', Carbon::parse($filters['from_date'])->format('Y-m-d') . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $q->where('recorded_at', '<=', Carbon::parse($filters['to_date'])->format('Y-m-d') . ' 23:59:59');
        }

        if (!empty($filters['region_code'])) {
            $q->whereIn('region_code', $this->splitMultiValue($filters['region_code']));
        }
        if (!empty($filters['region_name'])) {
            $q->where('region', $filters['region_name']);
        }

        if (!empty($filters['warehouse_code'])) {
            $q->whereIn('warehouse_code', $this->splitMultiValue($filters['warehouse_code']));
        }
        if (!empty($filters['warehouse_name'])) {
            $q->where('warehouse', $filters['warehouse_name']);
        }

        if (!empty($filters['device_type'])) {
            $q->where('device_type', $filters['device_type']);
        }
        if (!empty($filters['device_code'])) {
            $q->where('sensor_device_id', $filters['device_code']);
        }
        if (!empty($filters['device_name'])) {
            $q->where('device_name', $filters['device_name']);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['level'])) {
            $q->whereIn('level', $this->splitMultiValue($filters['level']));
        }

        switch (strtolower((string) ($filters['report_type'] ?? 'reading'))) {
            case 'severe_alert':
                $q->where('level', 'severe');
                break;
            case 'critical_alert':
                $q->where('level', 'critical');
                break;
            case 'offline_device':
                $q->where('status', 'offline');
                break;
        }

        return $q;
    }

    private function latestDeviceReadingIds()
    {
        return Reading::latestIdsPerSensor();
    }

    private function distinctLocationCount($query, string $codeColumn, string $nameColumn): int
    {
        $locations = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM({$codeColumn}), ''), NULLIF(TRIM({$nameColumn}), '')) AS location_key")
            ->distinct();

        return DB::query()
            ->fromSub($locations, 'locations')
            ->whereNotNull('location_key')
            ->count();
    }

    private function buildCharts(array $filters): array
    {
        // Bucket size: choose by report_type
        $reportType = strtolower((string) ($filters['report_type'] ?? 'reading'));

        $from = !empty($filters['from_date']) ? Carbon::parse($filters['from_date']) : null;
        $to = !empty($filters['to_date']) ? Carbon::parse($filters['to_date']) : null;
        $bucket = match ($reportType) {
            'daily_report', 'custom_date_range_report' => 'day',
            'weekly_report' => 'week',
            'monthly_report' => 'month',
            default => 'day',
        };

        $readingsBase = $this->buildReadingsQuery($filters);

        $labels = [];
        $readingsCounts = [];
        $alertsCounts = [];

        // For trend charts, restrict to manageable range for performance.
        if ($from && $to && $from->diffInDays($to) > 120) {
            $from = $to->copy()->subDays(120);
            $filters['from_date'] = $from->toDateString();
            $filters['to_date'] = $to->toDateString();
            $readingsBase = $this->buildReadingsQuery($filters);
        }

        // Aggregate per day in SQL (DATE() works on MySQL and SQLite), then roll up to the bucket in PHP.
        // Loading every row here exhausted memory on large tables when no date range was given.
        $bucketCounts = $this->trendCounts($readingsBase, 'recorded_at', $bucket);
        $labels = array_keys($bucketCounts);
        $readingsCounts = array_values($bucketCounts);

        // Alerts trend
        $alertsBase = $this->buildAlertsQuery($filters);
        $alertBucketCounts = $this->trendCounts($alertsBase, 'created_at', $bucket);
        $alertsCounts = array_values($alertBucketCounts);

        // Level distribution
        $levelDist = [
            'normal' => (clone $readingsBase)->where('level', 'normal')->count(),
            'severe' => (clone $readingsBase)->where('level', 'severe')->count(),
            'critical' => (clone $readingsBase)->where('level', 'critical')->count(),
        ];

        // Region wise device count (best-effort from readings: distinct sensor_device_id per region)
        $regionDeviceCount = (clone $readingsBase)
            ->toBase()
            ->selectRaw("COALESCE(NULLIF(region_code, ''), NULLIF(region, ''), 'unknown') AS region_key")
            ->selectRaw("COUNT(DISTINCT NULLIF(sensor_device_id, '')) AS device_count")
            ->groupBy('region_key')
            ->get()
            ->map(fn ($r) => ['region' => $r->region_key, 'count' => (int) $r->device_count])
            ->all();

        return [
            [$labels, $readingsCounts],
            [array_keys($alertBucketCounts), $alertsCounts],
            $levelDist,
            $regionDeviceCount,
        ];
    }

    /**
     * @return array<string, int> bucket label => count, sorted by label
     */
    private function trendCounts($query, string $column, string $bucket): array
    {
        $days = (clone $query)
            ->toBase()
            ->whereNotNull($column)
            ->selectRaw("DATE({$column}) AS day, COUNT(*) AS total")
            ->groupBy('day')
            ->get();

        $counts = [];
        foreach ($days as $row) {
            $dt = Carbon::parse($row->day);
            $key = match ($bucket) {
                'week' => $dt->format('o-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->total;
        }

        ksort($counts);

        return $counts;
    }
}
