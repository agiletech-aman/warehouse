<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Alert;
use App\Models\DeviceLatestStatus;
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

        // One scan for all reading stats: this request has to finish inside PHP-FPM's 60s limit.
        $readingStats = $this->buildReadingsQuery($filters)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw($this->distinctLocationSql('region_code', 'region') . ' AS regions')
            ->selectRaw($this->distinctLocationSql('warehouse_code', 'warehouse') . ' AS warehouses')
            ->first();

        $totalReadings = (int) ($readingStats->total ?? 0);

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
            : (int) ($readingStats->regions ?? 0);

        $warehousesCount = !empty($filters['warehouse_code']) || !empty($filters['warehouse_name'])
            ? 1
            : (int) ($readingStats->warehouses ?? 0);

        // Severe/Critical alerts counts: prefer Alert.type filters within date range.
        $alertsBase = $this->buildAlertsQuery($filters);
        $severeAlerts = (clone $alertsBase)->where('type', 'severe')->count();
        $criticalAlerts = (clone $alertsBase)->where('type', 'critical')->count();

        $charts = [
            'online_vs_offline_devices' => [
                'online' => $onlineDevices,
                'offline' => $offlineDevices,
            ],
        ];

        // The trend charts rescan readings several times and the reports page does not use them, so they are opt-in.
        if ($request->boolean('include_charts')) {
            [$readingsTrend, $alertsTrend, $levelDistribution, $regionDeviceCounts] = $this->buildCharts($filters);

            $charts += [
                'readings_trend' => $readingsTrend,
                'alerts_trend' => $alertsTrend,
                'region_wise_device_count' => $regionDeviceCounts,
                'level_distribution' => $levelDistribution,
            ];
        }

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
            'charts' => $charts,
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
                'godown',
                'compartment',
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
                    'godown' => $r->godown ?: '-',
                    'compartment' => $r->compartment ?: '-',
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

    /**
     * Chart data for the filtered readings: average / highest / lowest value per gas type
     * per time bucket, plus each gas's Normal / Severe ranges. Bucket size follows
     * the date range (hourly up to 7 days, daily up to 92 days, monthly beyond).
     * Without a date range the chart covers the last 7 days.
     */
    public function chart(Request $request)
    {
        $filters = $this->extractFilters($request);

        $to = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : now()->endOfDay();
        $from = !empty($filters['from_date'])
            ? Carbon::parse($filters['from_date'])->startOfDay()
            : $to->copy()->subDays(6)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $filters['from_date'] = $from->toDateString();
        $filters['to_date'] = $to->toDateString();

        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $bucket = match (true) {
            $days <= 7 => 'hour',
            $days <= 92 => 'shift', // one Day and one Night point per date
            default => 'month',
        };

        $query = $this->buildReadingsQuery($filters);
        $isSqlite = $query->getConnection()->getDriverName() === 'sqlite';

        $dayStart = (int) ReportExport::DAY_START_HOUR;
        $nightStart = (int) ReportExport::NIGHT_START_HOUR;
        $hourSql = ReportExport::hourSql($query);

        // Shift bucket "YYYY-MM-DD D" / "YYYY-MM-DD N". A night runs 18:00 -> 06:00, so readings
        // after midnight belong to the previous date's night (date of recorded_at minus 6 hours).
        $shiftSuffixSql = "CASE WHEN {$hourSql} >= {$dayStart} AND {$hourSql} < {$nightStart} THEN ' D' ELSE ' N' END";

        $bucketSql = match ($bucket) {
            'hour' => $isSqlite ? "strftime('%Y-%m-%d %H:00', recorded_at)" : "DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00')",
            'shift' => $isSqlite
                ? "(strftime('%Y-%m-%d', recorded_at, '-{$dayStart} hours') || {$shiftSuffixSql})"
                : "CONCAT(DATE_FORMAT(DATE_SUB(recorded_at, INTERVAL {$dayStart} HOUR), '%Y-%m-%d'), {$shiftSuffixSql})",
            'month' => $isSqlite ? "strftime('%Y-%m', recorded_at)" : "DATE_FORMAT(recorded_at, '%Y-%m')",
        };

        $gasSql = "COALESCE(NULLIF(TRIM(device_type), ''), 'Other')";
        $errorMin = Reading::SENSOR_ERROR_MIN_VALUE;

        // Sensor error codes (e.g. 65534 / 65535) are not gas values; they would squash the
        // real values flat on the chart, so they are left out and only counted.
        $rows = (clone $query)
            ->toBase()
            ->whereNotNull('recorded_at')
            ->whereNotNull('reading_value')
            ->where('reading_value', '<', $errorMin)
            ->selectRaw("{$bucketSql} AS bucket")
            ->selectRaw("{$gasSql} AS gas")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('AVG(reading_value) AS avg_value')
            ->selectRaw('MAX(reading_value) AS max_value')
            ->selectRaw('MIN(reading_value) AS min_value')
            ->groupByRaw("{$bucketSql}, {$gasSql}")
            ->get();

        // Whole-period numbers per gas, plus how many error readings were left out.
        $overall = (clone $query)
            ->toBase()
            ->whereNotNull('recorded_at')
            ->whereNotNull('reading_value')
            ->selectRaw("{$gasSql} AS gas")
            ->selectRaw('AVG(CASE WHEN reading_value < ? THEN reading_value END) AS avg_value', [$errorMin])
            ->selectRaw('MAX(CASE WHEN reading_value < ? THEN reading_value END) AS max_value', [$errorMin])
            ->selectRaw('MIN(CASE WHEN reading_value < ? THEN reading_value END) AS min_value', [$errorMin])
            ->selectRaw('SUM(CASE WHEN reading_value < ? THEN 1 ELSE 0 END) AS valid_count', [$errorMin])
            ->selectRaw('SUM(CASE WHEN reading_value >= ? THEN 1 ELSE 0 END) AS error_count', [$errorMin])
            ->groupByRaw($gasSql)
            ->get()
            ->keyBy('gas');

        // Every bucket in the range, so gaps show as empty instead of being skipped.
        $labels = [];
        $timeOfDay = strtolower((string) ($filters['time_of_day'] ?? ''));
        $wantDay = $timeOfDay !== 'night';
        $wantNight = $timeOfDay !== 'day';

        if ($bucket === 'shift') {
            // The early hours of the first date belong to the night of the date before it.
            $cursor = $from->copy()->subDay()->startOfDay();
            while ($cursor->lessThanOrEqualTo($to) && count($labels) < 2000) {
                $date = $cursor->format('Y-m-d');
                $isLeadingNight = $cursor->lessThan($from);

                if ($wantDay && !$isLeadingNight) {
                    $labels[] = $date . ' D';
                }
                if ($wantNight) {
                    $labels[] = $date . ' N';
                }

                $cursor->addDay();
            }

            // Drop the leading night if it has no readings.
            $leading = $from->copy()->subDay()->format('Y-m-d') . ' N';
            if (($labels[0] ?? null) === $leading && !$rows->contains('bucket', $leading)) {
                array_shift($labels);
            }
        } else {
            $cursor = $bucket === 'hour' ? $from->copy()->startOfHour() : $from->copy()->startOfMonth();
            $format = $bucket === 'hour' ? 'Y-m-d H:00' : 'Y-m';

            while ($cursor->lessThanOrEqualTo($to) && count($labels) < 2000) {
                $hour = (int) $cursor->format('H');
                $isDayHour = $hour >= $dayStart && $hour < $nightStart;

                // Hourly buckets outside the selected shift can never have data; leave them out.
                if ($bucket !== 'hour' || ($isDayHour ? $wantDay : $wantNight)) {
                    $labels[] = $cursor->format($format);
                }

                $bucket === 'hour' ? $cursor->addHour() : $cursor->addMonth();
            }
        }

        $round = fn ($v) => $v === null ? null : round((float) $v, 4);
        $thresholds = Reading::levelThresholds();

        $byGas = $rows->groupBy('gas');

        $series = $overall
            ->map(function ($stats, $gas) use ($byGas, $labels, $round, $thresholds) {
                $indexed = $byGas->get($gas, collect())->keyBy('bucket');
                $key = strtoupper(str_replace([' ', '-', '_', '₂', '₃'], ['', '', '', '2', '3'], (string) $gas));
                $gasThresholds = $thresholds[$key] ?? null;

                $avg = $round($stats->avg_value);
                $max = $round($stats->max_value);
                $min = $round($stats->min_value);

                return [
                    'gas' => (string) $gas,
                    'unit' => null,
                    'avg' => array_map(fn ($l) => $round($indexed->get($l)?->avg_value), $labels),
                    'max' => array_map(fn ($l) => $round($indexed->get($l)?->max_value), $labels),
                    'min' => array_map(fn ($l) => $round($indexed->get($l)?->min_value), $labels),
                    'thresholds' => $gasThresholds,
                    'summary' => [
                        'avg' => $avg,
                        'max' => $max,
                        'min' => $min,
                        'avg_level' => $avg === null ? null : Reading::levelFromValue($gas, $avg),
                        'max_level' => $max === null ? null : Reading::levelFromValue($gas, $max),
                        'min_level' => $min === null ? null : Reading::levelFromValue($gas, $min),
                        'readings' => (int) $stats->valid_count,
                        'error_readings' => (int) $stats->error_count,
                    ],
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        // Unit per gas from one sample row (cheap; units are fixed per gas type).
        foreach ($series as &$item) {
            $item['unit'] = (clone $query)
                ->where('device_type', $item['gas'])
                ->whereNotNull('unit')
                ->where('unit', '!=', '')
                ->value('unit');
        }
        unset($item);

        return response()->json([
            'success' => true,
            'bucket' => $bucket,
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date'],
            'time_of_day' => ReportExport::describeTimeOfDay($filters['time_of_day'] ?? null),
            'sensor_error_min_value' => $errorMin,
            'labels' => $labels,
            'series' => $series,
        ]);
    }

    /**
     * Godown / compartment options for the selected warehouse(s), grouped by godown:
     * [{godown: 'G1', compartments: ['C1', 'C2']}]. Nothing is returned until a warehouse
     * is selected. Read from device_latest_status (one row per sensor) so the readings
     * history table is never scanned for distinct values.
     */
    public function locations(Request $request)
    {
        $warehouseCodes = $this->splitMultiValue($request->query('warehouse_code', ''));

        if ($warehouseCodes === []) {
            return response()->json(['success' => true, 'groups' => []]);
        }

        $pairs = DeviceLatestStatus::query()
            ->whereIn('warehouse_code', $warehouseCodes)
            ->where(function ($q) {
                $q->where(fn ($q) => $q->whereNotNull('godown')->where('godown', '!=', ''))
                    ->orWhere(fn ($q) => $q->whereNotNull('compartment')->where('compartment', '!=', ''));
            })
            ->toBase()
            ->select(['godown', 'compartment'])
            ->distinct()
            ->get();

        $groups = $pairs
            ->groupBy(fn ($row) => (string) $row->godown)
            ->map(fn ($rows, $godown) => [
                'godown' => (string) $godown,
                'compartments' => $rows->pluck('compartment')
                    ->filter(fn ($c) => $c !== null && $c !== '')
                    ->map(fn ($c) => (string) $c)
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            ->sortBy('godown', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return response()->json(['success' => true, 'groups' => $groups]);
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
                        'godown', 'compartment', 'device_name', 'sensor_device_id', 'device_type', 'device_ip',
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
            return \App\Exports\ReportStreamExport::download($export, $fileName);
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

            // JSON list of [godown, compartment|null] pairs from the combined filter.
            'locations' => $request->query('locations'),

            'device_type' => $request->query('device_type'),
            'device_code' => $request->query('device_code'), 
            'device_name' => $request->query('device_name'),

            'status' => $request->query('status'),
            'level' => $request->query('level'),

            'current_only' => $request->boolean('current_only'),

            // all | day | night
            'time_of_day' => $request->query('time_of_day'),

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

        ReportExport::applyTimeOfDayFilter($q, $filters['time_of_day'] ?? null);

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

        ReportExport::applyLocationFilter($q, $filters['locations'] ?? null);

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
                ->orWhere('godown', 'like', '%' . $value . '%')
                ->orWhere('compartment', 'like', '%' . $value . '%')
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

        ReportExport::applyLocationFilter($q, $filters['locations'] ?? null);

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
        return Reading::latestIdsPerSensor()->get()->flatMap(fn ($r) => array_values((array) $r))->all();
    }

    private function distinctLocationSql(string $codeColumn, string $nameColumn): string
    {
        return "COUNT(DISTINCT COALESCE(NULLIF(TRIM({$codeColumn}), ''), NULLIF(TRIM({$nameColumn}), '')))";
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
