<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::with(['device', 'reading'])->latest()->paginate(10);

        return view('alerts.index', compact('alerts'));
    }

    public function create()
    {
        $devices = Device::latest()->get();
        $readings = Reading::latest()->get();

        return view('alerts.create', compact('devices', 'readings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_code',
            'reading_id' => 'nullable|exists:readings,id',
            'alert_type' => 'required|in:high_co2,high_phosphorus,device_offline',
            'alert_value' => 'required|numeric',
        ]);

        Alert::create($request->only(['device_id', 'reading_id', 'alert_type', 'alert_value']));

        return redirect()->route('alerts.index')->with('success', 'Alert created successfully.');
    }

    public function show(Alert $alert)
    {
        $alert->load(['device', 'reading']);

        return view('alerts.show', compact('alert'));
    }

    public function edit(Alert $alert)
    {
        $devices = Device::latest()->get();
        $readings = Reading::latest()->get();

        return view('alerts.edit', compact('alert', 'devices', 'readings'));
    }

    public function update(Request $request, Alert $alert)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_code',
            'reading_id' => 'nullable|exists:readings,id',
            'alert_type' => 'required|in:high_co2,high_phosphorus,device_offline',
            'alert_value' => 'required|numeric',
        ]);

        $alert->update($request->only(['device_id', 'reading_id', 'alert_type', 'alert_value']));

        return redirect()->route('alerts.index')->with('success', 'Alert updated successfully.');
    }

    public function destroy(Alert $alert)
    {
        $alert->delete();

        return redirect()->route('alerts.index')->with('success', 'Alert deleted successfully.');
    }
}
