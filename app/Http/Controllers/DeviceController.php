<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::with('warehouse')->latest()->paginate(10);

        return view('devices.index', compact('devices'));
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
}
