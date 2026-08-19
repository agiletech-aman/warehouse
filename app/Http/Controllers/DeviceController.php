<?php

namespace App\Http\Controllers;

use App\Exports\DevicesExport;
use App\Exports\DeviceDetailedSummaryExport;
use App\Models\Device;
use App\Models\DeviceLatestStatus;
use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DeviceController extends Controller
{
    private function applyReadingDeviceFilters($query, Request $request, bool $includeStatus = true)
    {
        $selectedRegion = trim((string) $request->query('region_code', ''));
        $selectedWarehouse = trim((string) $request->query('warehouse_code', ''));
        $selectedStatus = strtolower(trim((string) $request->query('status', '')));

        if (!in_array($selectedStatus, ['online', 'offline'], true)) {
            $selectedStatus = '';
        }

        if ($selectedRegion !== '') {
            $query->where(function ($query) use ($selectedRegion) {
                $query->where('region_code', $selectedRegion)
                    ->orWhere('region', $selectedRegion);
            });
        }

        if ($selectedWarehouse !== '') {
            $query->where(function ($query) use ($selectedWarehouse) {
                $query->where('warehouse_code', $selectedWarehouse)
                    ->orWhere('warehouse', $selectedWarehouse);
            });
        }

        if ($includeStatus && $selectedStatus !== '') {
            $query->where('status', $selectedStatus);
        }

        return [$selectedRegion, $selectedWarehouse, $selectedStatus];
    }

    public function index(Request $request)
    {
        $selectedRegion = trim((string) $request->query('region_code', ''));
        $selectedWarehouse = trim((string) $request->query('warehouse_code', ''));
        $selectedStatus = strtolower(trim((string) $request->query('status', '')));

        if (!in_array($selectedStatus, ['online', 'offline'], true)) {
            $selectedStatus = '';
        }

        $regions = DeviceLatestStatus::query()
            ->where(function ($query) {
                $query->whereNotNull('region_code')->orWhereNotNull('region');
            })
            ->select('region_code', 'region')
            ->distinct()
            ->orderBy('region')
            ->get();

        $warehouses = DeviceLatestStatus::query()
            ->where(function ($query) {
                $query->whereNotNull('warehouse_code')->orWhereNotNull('warehouse');
            })
            ->select('warehouse_code', 'warehouse')
            ->distinct()
            ->orderBy('warehouse')
            ->get();

        $deviceCountsQuery = DeviceLatestStatus::query();
        $this->applyReadingDeviceFilters($deviceCountsQuery, $request);

        $summaryCounts = (clone $deviceCountsQuery)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) as online")
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) as offline")
            ->first();

        $deviceCounts = $this->countsFromAggregate($summaryCounts);

        // A warehouse is active when it has received at least one reading in the last 24 hours.
        // Region and warehouse filters apply, while the device status filter is intentionally ignored.
        $activeWarehouseReadings = DeviceLatestStatus::query()
            ->where('recorded_at', '>=', now()->subDay());
        $this->applyReadingDeviceFilters($activeWarehouseReadings, $request, false);

        $activeWarehouseCount = $activeWarehouseReadings
            ->where(function ($query) {
                $query->whereNotNull('warehouse_code')->orWhereNotNull('warehouse');
            })
            ->select(['warehouse', 'warehouse_code'])
            ->groupBy('warehouse', 'warehouse_code')
            ->get()
            ->count();

        $gas = "LOWER(REPLACE(REPLACE(device_type, '₂', '2'), '₃', '3'))";
        $typeCounts = (clone $deviceCountsQuery)
            ->selectRaw("{$gas} as gas")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) as online")
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) as offline")
            ->groupByRaw($gas)
            ->get()
            ->keyBy(fn ($row) => strtoupper((string) $row->gas));

        $deviceTypeCounts = collect(['CO2', 'PH3'])
            ->mapWithKeys(fn (string $type) => [
                $type => $this->countsFromAggregate($typeCounts->get($type)),
            ])
            ->all();

        $warehouseDeviceCounts = (clone $deviceCountsQuery)
            ->select(['warehouse', 'warehouse_code', 'region', 'region_code'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) as online")
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) as offline")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' THEN 1 ELSE 0 END) as co2_total")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) as co2_online")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'co2' AND LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) as co2_offline")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' THEN 1 ELSE 0 END) as ph3_total")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND LOWER(COALESCE(status, '')) = 'online' THEN 1 ELSE 0 END) as ph3_online")
            ->selectRaw("SUM(CASE WHEN {$gas} = 'ph3' AND LOWER(COALESCE(status, '')) = 'offline' THEN 1 ELSE 0 END) as ph3_offline")
            ->groupBy('warehouse', 'warehouse_code', 'region', 'region_code')
            ->orderBy('warehouse')
            ->get()
            ->map(function ($warehouse) {
                $warehouseName = trim((string) $warehouse->warehouse);
                $warehouseCode = trim((string) $warehouse->warehouse_code);
                $regionName = trim((string) $warehouse->region);
                $regionCode = trim((string) $warehouse->region_code);

                return [
                    'name' => $warehouseName !== '' ? $warehouseName : ($warehouseCode !== '' ? $warehouseCode : 'Unassigned'),
                    'code' => $warehouseCode,
                    'region_name' => $regionName !== '' ? $regionName : ($regionCode !== '' ? $regionCode : '-'),
                    'region_code' => $regionCode,
                    'overall' => $this->countsFromAggregate($warehouse),
                    'CO2' => [
                        'total' => (int) $warehouse->co2_total,
                        'online' => (int) $warehouse->co2_online,
                        'offline' => (int) $warehouse->co2_offline,
                    ],
                    'PH3' => [
                        'total' => (int) $warehouse->ph3_total,
                        'online' => (int) $warehouse->ph3_online,
                        'offline' => (int) $warehouse->ph3_offline,
                    ],
                ];
            })
            ->values();

        return view('devices.index', compact(
            'regions',
            'warehouses',
            'selectedRegion',
            'selectedWarehouse',
            'selectedStatus',
            'deviceCounts',
            'activeWarehouseCount',
            'deviceTypeCounts',
            'warehouseDeviceCounts'
        ));
    }

    public function data(Request $request)
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length <= 0 || $length > 100) {
            $length = 10;
        }

        $allDevices = DeviceLatestStatus::query();
        $recordsTotal = (clone $allDevices)->count();

        $query = DeviceLatestStatus::query();
        $this->applyReadingDeviceFilters($query, $request);
        $this->applyDataTableSearch($query, $request);

        $recordsFiltered = (clone $query)->count();

        $devices = $query
            ->latest('recorded_at')
            ->orderBy('sensor_device_id')
            ->offset($start)
            ->limit($length)
            ->get([
                'sensor_device_id',
                'device_name',
                'device_type',
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
            ])
            ->map(function (DeviceLatestStatus $reading) {
                return [
                    'code' => $reading->sensor_device_id ?: '-',
                    'name' => $reading->device_name ?: '-',
                    'region' => $reading->region ?: ($reading->region_code ?: '-'),
                    'region_code' => $reading->region_code,
                    'warehouse' => $reading->warehouse ?: ($reading->warehouse_code ?: '-'),
                    'warehouse_code' => $reading->warehouse_code,
                    'type' => $reading->device_type ?: '-',
                    'location' => $this->joinLocationParts($reading->godown, $reading->compartment),
                    'value' => $reading->reading_value,
                    'unit' => $reading->unit,
                    'recorded_at' => $reading->recorded_at?->format('d M Y H:i:s') ?: '-',
                    'level' => Reading::normalizeLevel($reading->reading_value, $reading->level),
                    'status' => $reading->status ?: 'offline',
                    'delete_url' => route('devices.reading-destroy', ['reading' => $reading->sensor_device_id]),
                    'delete_label' => $reading->device_name ?: ($reading->sensor_device_id ?: 'this device'),
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $devices,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new DevicesExport($request->only(['region_code', 'warehouse_code', 'status', 'search'])),
            'devices-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function detailedSummaryExport(Request $request)
    {
        return Excel::download(
            new DeviceDetailedSummaryExport(
                $request->only(['region_code', 'warehouse_code', 'status'])
            ),
            'device-detailed-summary-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }

    public function create()
    {
        $warehouses = Warehouse::latest()->get();

        return view('devices.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'device_code' => 'required|string|max:20|unique:devices,device_code',
            'device_name' => 'required|string|max:100',
            'device_type' => 'nullable|string|max:50',
            'model_no' => 'nullable|string|max:50',
            'serial_no' => 'nullable|string|max:100|unique:devices,serial_no',
            'mac_address' => 'nullable|string|max:50',
            'ip_address' => 'nullable|ip|max:50',
            'firmware_version' => 'nullable|string|max:50',
            'installation_date' => 'nullable|date',
            'last_seen_at' => 'nullable|date',
            'status' => 'required|in:online,offline,maintenance',
        ]);

        Device::create($request->only([
            'warehouse_id',
            'device_code',
            'device_name',
            'device_type',
            'model_no',
            'serial_no',
            'mac_address',
            'ip_address',
            'firmware_version',
            'installation_date',
            'last_seen_at',
            'status',
        ]));

        return redirect()->route('devices.index')->with('success', 'Device created successfully.');
    }

    public function show(Device $device)
    {
        $device->load('warehouse');

        return view('devices.show', compact('device'));
    }

    public function edit(Device $device)
    {
        $warehouses = Warehouse::latest()->get();

        return view('devices.edit', compact('device', 'warehouses'));
    }

    public function update(Request $request, Device $device)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'device_code' => 'required|string|max:20|unique:devices,device_code,' . $device->id,
            'device_name' => 'required|string|max:100',
            'device_type' => 'nullable|string|max:50',
            'model_no' => 'nullable|string|max:50',
            'serial_no' => 'nullable|string|max:100|unique:devices,serial_no,' . $device->id,
            'mac_address' => 'nullable|string|max:50',
            'ip_address' => 'nullable|ip|max:50',
            'firmware_version' => 'nullable|string|max:50',
            'installation_date' => 'nullable|date',
            'last_seen_at' => 'nullable|date',
            'status' => 'required|in:online,offline,maintenance',
        ]);

        $device->update($request->only([
            'warehouse_id',
            'device_code',
            'device_name',
            'device_type',
            'model_no',
            'serial_no',
            'mac_address',
            'ip_address',
            'firmware_version',
            'installation_date',
            'last_seen_at',
            'status',
        ]));

        return redirect()->route('devices.index')->with('success', 'Device updated successfully.');
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device deleted successfully.');
    }

    public function destroyReadingDevice(string $reading)
    {
        $historicalReading = Reading::query()
            ->where('sensor_device_id', $reading)
            ->orWhere('id', $reading)
            ->firstOrFail();

        $sensorDeviceId = $historicalReading->sensor_device_id;
        $deletedReadings = Reading::query()
            ->where('sensor_device_id', $sensorDeviceId ?: $historicalReading->id)
            ->delete();

        if ($sensorDeviceId) {
            DeviceLatestStatus::query()->whereKey($sensorDeviceId)->delete();
        }

        return redirect()->back()->with('success', $deletedReadings . ' reading(s) deleted successfully.');
    }

    private function applyDataTableSearch($query, Request $request): void
    {
        $value = trim((string) data_get($request->query('search', []), 'value', ''));

        if ($value === '') {
            return;
        }

        $query->where(function ($query) use ($value) {
            $query->where('sensor_device_id', 'like', '%' . $value . '%')
                ->orWhere('device_name', 'like', '%' . $value . '%')
                ->orWhere('device_type', 'like', '%' . $value . '%')
                ->orWhere('region', 'like', '%' . $value . '%')
                ->orWhere('region_code', 'like', '%' . $value . '%')
                ->orWhere('warehouse', 'like', '%' . $value . '%')
                ->orWhere('warehouse_code', 'like', '%' . $value . '%')
                ->orWhere('godown', 'like', '%' . $value . '%')
                ->orWhere('compartment', 'like', '%' . $value . '%')
                ->orWhere('reading_value', 'like', '%' . $value . '%')
                ->orWhere('unit', 'like', '%' . $value . '%')
                ->orWhere('level', 'like', '%' . $value . '%')
                ->orWhere('status', 'like', '%' . $value . '%');
        });
    }

    private function joinLocationParts(?string $first, ?string $second): string
    {
        $parts = array_values(array_filter([
            trim((string) $first),
            trim((string) $second),
        ], fn ($part) => $part !== ''));

        return $parts ? implode(' / ', $parts) : '-';
    }

    private function countsFromAggregate($aggregate): array
    {
        return [
            'total' => (int) ($aggregate->total ?? 0),
            'online' => (int) ($aggregate->online ?? 0),
            'offline' => (int) ($aggregate->offline ?? 0),
        ];
    }
}
