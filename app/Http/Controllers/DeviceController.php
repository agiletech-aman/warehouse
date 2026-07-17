<?php

namespace App\Http\Controllers;

use App\Exports\DevicesExport;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DeviceController extends Controller
{
    private function latestReadingIds()
    {
        return Reading::latestIdsPerSensor();
    }

    private function applyReadingDeviceFilters($query, Request $request)
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

        if ($selectedStatus !== '') {
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

        $regions = Reading::whereIn('id', $this->latestReadingIds())
            ->select('region_code', 'region')
            ->orderBy('region')
            ->get()
            ->filter(fn ($region) => $region->region_code || $region->region)
            ->unique(fn ($region) => $region->region_code ?: $region->region)
            ->values();

        $warehouses = Reading::whereIn('id', $this->latestReadingIds())
            ->select('warehouse_code', 'warehouse')
            ->orderBy('warehouse')
            ->get()
            ->filter(fn ($warehouse) => $warehouse->warehouse_code || $warehouse->warehouse)
            ->unique(fn ($warehouse) => $warehouse->warehouse_code ?: $warehouse->warehouse)
            ->values();

        $devicesQuery = Reading::whereIn('id', $this->latestReadingIds());
        $this->applyReadingDeviceFilters($devicesQuery, $request);

        $deviceCountsQuery = Reading::whereIn('id', $this->latestReadingIds());
        $this->applyReadingDeviceFilters($deviceCountsQuery, $request);

        $deviceCounts = [
            'total' => (clone $deviceCountsQuery)->count(),
            'online' => (clone $deviceCountsQuery)->where('status', 'online')->count(),
            'offline' => (clone $deviceCountsQuery)->where('status', 'offline')->count(),
        ];

        $devices = $devicesQuery
            ->latest('recorded_at')
            ->latest('id')
            ->get();

        return view('devices.index', compact(
            'devices',
            'regions',
            'warehouses',
            'selectedRegion',
            'selectedWarehouse',
            'selectedStatus',
            'deviceCounts'
        ));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new DevicesExport($request->only(['region_code', 'warehouse_code', 'status', 'search'])),
            'devices-' . now()->format('Y-m-d-His') . '.xlsx'
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

    public function destroyReadingDevice(Reading $reading)
    {
        $deletedReadings = Reading::where(function ($query) use ($reading) {
            if ($reading->sensor_device_id) {
                $query->where('sensor_device_id', $reading->sensor_device_id);
            } else {
                $query->where('id', $reading->id);
            }
        })->delete();

        return redirect()->back()->with('success', $deletedReadings . ' reading(s) deleted successfully.');
    }
}
