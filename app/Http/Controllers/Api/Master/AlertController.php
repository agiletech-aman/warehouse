<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\MasterAlertSummary;
use App\Models\Region;
use App\Models\Warehouse;
use App\Services\MasterAlertService;
use App\Services\MasterAlertSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlertController extends Controller
{
    private const DEVICE_CO2 = 30000;

    private const DEVICE_PH3 = 30001;

    public function alertsApi(Request $request, MasterAlertService $alertService): JsonResponse
    {
        $response = $this->getAlerts($request, $alertService, null, true);

        return response()->json([
            'totalCount' => $response['totalCount'],
            'pageNumber' => $response['pageNumber'],
            'pageSize' => $response['pageSize'],
            'data' => $response['data'],
        ]);
    }

    public function summaryApi(Request $request, MasterAlertSummaryService $summaryService): JsonResponse
    {
        return response()->json($this->getSummary($request, $summaryService));
    }

    public function storeSummaryApi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machineId' => 'required|string|max:100',
            'totalIotDevices' => 'required|integer|min:0',
            'onlineCO2' => 'required|integer|min:0',
            'offlineCO2' => 'required|integer|min:0',
            'onlinePH3' => 'required|integer|min:0',
            'offlinePH3' => 'required|integer|min:0',
            'normalCO2' => 'required|integer|min:0',
            'severeCO2' => 'required|integer|min:0',
            'criticalCO2' => 'required|integer|min:0',
            'normalPH3' => 'required|integer|min:0',
            'severePH3' => 'required|integer|min:0',
            'criticalPH3' => 'required|integer|min:0',
            'shadName' => 'nullable|string|max:150',
            'columnName' => 'nullable|string|max:150',
            'locationName' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'pinCode' => 'nullable|string|max:20',
            'snapshotTime' => 'required|date',
        ]);

        $snapshotTime = Carbon::parse($validated['snapshotTime']);

        $summary = MasterAlertSummary::create([
            'machine_id' => $validated['machineId'],
            'total_iot_devices' => $validated['totalIotDevices'],
            'online_co2' => $validated['onlineCO2'],
            'offline_co2' => $validated['offlineCO2'],
            'online_ph3' => $validated['onlinePH3'],
            'offline_ph3' => $validated['offlinePH3'],
            'normal_co2' => $validated['normalCO2'],
            'severe_co2' => $validated['severeCO2'],
            'critical_co2' => $validated['criticalCO2'],
            'normal_ph3' => $validated['normalPH3'],
            'severe_ph3' => $validated['severePH3'],
            'critical_ph3' => $validated['criticalPH3'],
            'shad_name' => $validated['shadName'] ?? null,
            'column_name' => $validated['columnName'] ?? null,
            'location_name' => $validated['locationName'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'pin_code' => $validated['pinCode'] ?? null,
            'snapshot_time' => $snapshotTime->copy()->setTimezone(config('app.timezone')),
        ]);

        return response()->json([
            'machineId' => $summary->machine_id,
            'totalIotDevices' => $summary->total_iot_devices,
            'onlineCO2' => $summary->online_co2,
            'offlineCO2' => $summary->offline_co2,
            'onlinePH3' => $summary->online_ph3,
            'offlinePH3' => $summary->offline_ph3,
            'normalCO2' => $summary->normal_co2,
            'severeCO2' => $summary->severe_co2,
            'criticalCO2' => $summary->critical_co2,
            'normalPH3' => $summary->normal_ph3,
            'severePH3' => $summary->severe_ph3,
            'criticalPH3' => $summary->critical_ph3,
            'shadName' => $summary->shad_name,
            'columnName' => $summary->column_name,
            'locationName' => $summary->location_name,
            'state' => $summary->state,
            'city' => $summary->city,
            'pinCode' => $summary->pin_code,
            'snapshotTime' => $snapshotTime->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ]);
    }

    public function dashboardApi(
        Request $request,
        MasterAlertSummaryService $summaryService
    ): JsonResponse {
        $validated = $request->validate([
            'location' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:100',
            'fromDate' => 'nullable|date',
            'toDate' => 'nullable|date|after_or_equal:fromDate',
        ]);

        return response()->json($summaryService->fetchDashboard($validated));
    }

    public function statesApi(): JsonResponse
    {
        $states = Region::query()
            ->whereNotNull('frs_id')
            ->orderBy('region_name')
            ->get(['id', 'frs_id', 'nms_id', 'region_name'])
            ->filter(fn (Region $region) => ctype_digit((string) $region->frs_id))
            ->map(fn (Region $region) => [
                'base_id' => $region->id,
                'frs_id' => $region->frs_id,
                'nms_id' => $region->nms_id,
                'name' => Str::title(strtolower(trim($region->region_name))),
            ])
            ->unique('name')
            ->values();

        return response()->json($states);
    }

    public function devicesApi(
        Request $request,
        MasterAlertService $alertService,
        ?string $warehouseNmsId = null
    ): JsonResponse
    {
        $warehouseNmsId ??= $request->get(
            'warehouseNmsId',
            $request->get(
                'warehouse_nms_id',
                $request->get('nms_id', $request->get('warehouseId', $request->get('warehouse_id')))
            )
        );
        $warehouse = null;

        if ($warehouseNmsId !== null && $warehouseNmsId !== '') {
            $warehouse = Warehouse::query()
                ->where('nms_id', (string) $warehouseNmsId)
                ->first();

            if (! $warehouse) {
                return response()->json(['message' => 'Warehouse not found.'], 404);
            }
        }

        $deviceTypeId = $request->get('deviceTypeId');
        if ($deviceTypeId !== null && ! in_array((int) $deviceTypeId, [self::DEVICE_CO2, self::DEVICE_PH3], true)) {
            return response()->json([
                'message' => 'Invalid deviceTypeId. Use 30000 for CO2 or 30001 for PH3.',
            ], 422);
        }

        $page = max(1, (int) $request->get('pageNumber', $request->get('page', 1)));
        $pageSize = max(1, min((int) $request->get('pageSize', 20), 500));

        $response = $alertService->fetchDevices([
            'pageNumber' => $page,
            'pageSize' => $pageSize,
            'deviceTypeId' => $deviceTypeId !== null ? (int) $deviceTypeId : null,
            'warehouseName' => $warehouse?->warehouse_name,
            'warehouseCode' => $warehouse?->warehouse_code,
            'accessibleWarehouseNames' => $this->accessibleWarehouseNames(),
        ]);

        return response()->json([
            'data' => $response['data'],
            'totalCount' => $response['totalCount'],
            'pageNumber' => $response['pageNumber'],
            'pageSize' => $response['pageSize'],
            'totalPages' => $response['totalPages'],
            'warehouseNmsId' => $warehouse?->nms_id ?? 'N/A',
            'warehouseId' => $warehouse?->nms_id ?? 'N/A',
            'deviceTypeId' => $deviceTypeId !== null ? (int) $deviceTypeId : 'N/A',
            'gasType' => $deviceTypeId === null
                ? 'ALL'
                : ((int) $deviceTypeId === self::DEVICE_PH3 ? 'PH3' : 'CO2'),
        ]);
    }

    public function alertDetailsApi(
        Request $request,
        MasterAlertService $alertService,
        string $id
    ): JsonResponse {
        $request->merge(['page' => 1, 'pageSize' => 10000]);
        $response = $this->getAlerts($request, $alertService, 10000);

        $alert = collect($response['data'] ?? [])->first(
            fn (array $item) => (string) ($item['id'] ?? $item['alertId'] ?? '') === $id
        );

        if (! $alert) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        return response()->json([
            'data' => $alert,
            'deviceTypeId' => $response['deviceTypeId'],
            'gasType' => $response['gasType'],
        ]);
    }

    public function exportExcel(Request $request, MasterAlertService $alertService): StreamedResponse
    {
        $response = $this->getAlerts($request, $alertService, 10000);
        $gasLabel = $response['deviceTypeId'] === self::DEVICE_PH3 ? 'PH3' : 'CO2';

        return response()->streamDownload(function () use ($response) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'id', 'shadName', 'columnName', 'locationName', 'city', 'state',
                'pinCode', 'deviceValue', 'deviceIp', 'alertType', 'recordTime',
                'regDate', 'deviceTypeId', 'deviceStatus',
            ]);

            foreach ($response['data'] ?? [] as $alert) {
                fputcsv($stream, [
                    $alert['id'] ?? '',
                    $alert['shadName'] ?? '',
                    $alert['columnName'] ?? '',
                    $alert['locationName'] ?? '',
                    $alert['city'] ?? '',
                    $alert['state'] ?? '',
                    $alert['pinCode'] ?? '',
                    $alert['deviceValue'] ?? '',
                    $alert['deviceIp'] ?? '',
                    $alert['alertType'] ?? '',
                    $alert['recordTime'] ?? '',
                    $alert['regDate'] ?? '',
                    $alert['deviceTypeId'] ?? '',
                    $alert['deviceStatus'] ?? '',
                ]);
            }

            fclose($stream);
        }, "master-alerts-{$gasLabel}.csv", ['Content-Type' => 'text/csv']);
    }

    private function getAlerts(
        Request $request,
        MasterAlertService $alertService,
        ?int $customPageSize = null,
        bool $allDeviceTypes = false,
        bool $allRecords = false
    ): array {
        $deviceTypeId = $allDeviceTypes ? null : $this->deviceTypeId($request);
        $page = max(1, (int) $request->get('pageNumber', $request->get('page', 1)));
        $pageSize = $customPageSize ?? max(1, min((int) $request->get('pageSize', 20), 500));

        $response = $alertService->fetchAlerts([
            'deviceTypeId' => $deviceTypeId,
            'pageNumber' => $page,
            'pageSize' => $pageSize,
            'state' => $request->query('state', $request->query('region')),
            'location' => $request->query('location', $request->query('warehouse')),
            'alertType' => $request->query('alertType', $request->query('alert_type')),
            'device' => $request->get('device'),
            'fromDate' => $request->query('fromDate', $request->query('from_date')),
            'toDate' => $request->query('toDate', $request->query('to_date')),
            'showNormal' => $request->boolean('showNormal') ? 1 : null,
            'allRecords' => $allRecords,
        ]);

        $warehouseNames = $this->accessibleWarehouseNames();
        if ($warehouseNames !== null) {
            $response['data'] = collect($response['data'] ?? [])
                ->filter(fn (array $alert) => in_array($alert['locationName'] ?? null, $warehouseNames, true))
                ->values()
                ->all();
        }

        $response['deviceTypeId'] = $deviceTypeId;
        $response['gasType'] = $deviceTypeId === null
            ? 'ALL'
            : ($deviceTypeId === self::DEVICE_PH3 ? 'PH3' : 'CO2');
        $response['pageNumber'] = $allRecords ? 1 : $page;
        $response['pageSize'] = $allRecords ? $response['totalCount'] : $pageSize;

        return $response;
    }

    private function getSummary(Request $request, MasterAlertSummaryService $summaryService): array
    {
        $deviceTypeId = $this->deviceTypeId($request);
        $response = $summaryService->fetchSummary();
        $overall = $response['overall'] ?? [];
        $prefix = $deviceTypeId === self::DEVICE_PH3 ? 'PH3' : 'CO2';

        $response['selectedSummary'] = [
            'deviceTypeId' => $deviceTypeId,
            'gasType' => $prefix,
            'normal' => $overall["totalNormal{$prefix}"] ?? 0,
            'severe' => $overall["totalSevere{$prefix}"] ?? 0,
            'critical' => $overall["totalCritical{$prefix}"] ?? 0,
        ];

        return $response;
    }

    private function deviceTypeId(Request $request): int
    {
        $deviceTypeId = (int) $request->get('deviceTypeId');

        return in_array($deviceTypeId, [self::DEVICE_CO2, self::DEVICE_PH3], true)
            ? $deviceTypeId
            : self::DEVICE_CO2;
    }

    private function accessibleWarehouseNames(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'getAccessibleWarehouseNames')) {
            return null;
        }

        return collect($user->getAccessibleWarehouseNames())->filter()->values()->all();
    }
}
