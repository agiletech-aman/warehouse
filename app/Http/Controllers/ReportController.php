<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        // Devices counts (best-effort from readings)
        $devicesQuery = Device::query();
        if (!empty($filters['device_type'])) {
            $devicesQuery->where('device_type', $filters['device_type']);
        }

        // Apply region/warehouse constraints if provided
        $this->applyRegionWarehouseToDevices($devicesQuery, $filters);

        if (!empty($filters['status'])) {
            // status here is online/offline; map to devices.status active/inactive isn't consistent.
            // We'll map online->active and offline->inactive as a reasonable proxy.
            $map = [
                'online' => 'active',
                'offline' => 'inactive',
            ];
            $devicesStatus = $map[$filters['status']] ?? null;
            if ($devicesStatus) {
                $devicesQuery->where('status', $devicesStatus);
            }
        }

        $totalDevices = (clone $devicesQuery)->count();
        $onlineDevices = (clone $devicesQuery)->where('status', 'active')->count();
        $offlineDevices = (clone $devicesQuery)->where('status', 'inactive')->count();

        $regionsCount = !empty($filters['region_code']) || !empty($filters['region_name'])
            ? 1
            : (clone $readingsBase)->distinct('region_code')->count();

        $warehousesCount = !empty($filters['warehouse_code']) || !empty($filters['warehouse_name'])
            ? 1
            : (clone $readingsBase)->distinct('warehouse_code')->count();

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
                    'level' => $r->level ?: 'normal',
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

        $selectedCols = $filters['selected_cols'] ?? null;

        if ($format === 'pdf') {

            // For PDF we render a simple HTML table from the same export query.
            $pdfData = [
                'filters' => $filters,
                'rows' => $this->buildReadingsQuery($filters)
                    ->latest('recorded_at')
                    ->limit(5000)
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
            $q->where('region_code', $filters['region_code']);
        }
        if (!empty($filters['region_name'])) {
            $q->where('region', $filters['region_name']);
        }

        if (!empty($filters['warehouse_code'])) {
            $q->where('warehouse_code', $filters['warehouse_code']);
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
            $q->where('level', $filters['level']);
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

    private function applyRegionWarehouseToDevices($devicesQuery, array $filters): void
    {
        // best-effort using warehouses relation is available through device.warehouse()
        if (!empty($filters['warehouse_code'])) {
            $devicesQuery->whereHas('warehouse', function ($q) use ($filters) {
                $q->where('warehouse_code', $filters['warehouse_code']);
            });
        }

        if (!empty($filters['region_code'])) {
            $devicesQuery->whereHas('warehouse.region', function ($q) use ($filters) {
                $q->where('region_code', $filters['region_code']);
            });
        }
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

        // Build readings trend
        $readingsTrend = (clone $readingsBase)
            ->selectRaw('DATE(recorded_at) as d, YEAR(recorded_at) as y')
            ->get();

        // Instead of relying on SQL date-part portability, do in PHP bucketing.
        $rows = (clone $readingsBase)
            ->select(['recorded_at'])
            ->get();

        $bucketCounts = [];
        foreach ($rows as $row) {
            $dt = $row->recorded_at;
            if (!$dt) continue;
            $key = match ($bucket) {
                'week' => $dt->format('o-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };
            $bucketCounts[$key] = ($bucketCounts[$key] ?? 0) + 1;
        }

        ksort($bucketCounts);
        $labels = array_keys($bucketCounts);
        $readingsCounts = array_values($bucketCounts);

        // Alerts trend
        $alertsBase = $this->buildAlertsQuery($filters);
        $alertRows = (clone $alertsBase)->select(['created_at', 'type'])->get();
        $alertBucketCounts = [];
        foreach ($alertRows as $ar) {
            if (!$ar->created_at) continue;
            $dt = $ar->created_at;
            $key = match ($bucket) {
                'week' => $dt->format('o-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };
            $alertBucketCounts[$key] = ($alertBucketCounts[$key] ?? 0) + 1;
        }
        ksort($alertBucketCounts);
        $alertsCounts = array_values($alertBucketCounts);

        // Level distribution
        $levelDist = [
            'normal' => (clone $readingsBase)->where('level', 'normal')->count(),
            'severe' => (clone $readingsBase)->where('level', 'severe')->count(),
            'critical' => (clone $readingsBase)->where('level', 'critical')->count(),
        ];

        // Region wise device count (best-effort from readings: distinct sensor_device_id per region)
        $regionWise = (clone $readingsBase)
            ->select(['region_code', 'region', 'sensor_device_id'])
            ->get();

        $regionDeviceSets = [];
        foreach ($regionWise as $rr) {
            $key = ($rr->region_code ?: $rr->region) ?: 'unknown';
            if (!isset($regionDeviceSets[$key])) {
                $regionDeviceSets[$key] = [];
            }
            if ($rr->sensor_device_id) {
                $regionDeviceSets[$key][$rr->sensor_device_id] = true;
            }
        }
        $regionDeviceCount = [];
        foreach ($regionDeviceSets as $key => $set) {
            $regionDeviceCount[] = ['region' => $key, 'count' => count($set)];
        }

        return [
            [$labels, $readingsCounts],
            [array_keys($alertBucketCounts ?? []), $alertsCounts],
            $levelDist,
            $regionDeviceCount,
        ];
    }
}

