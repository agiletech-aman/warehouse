<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Reading;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function index()
    {
        $readings = Reading::with('device')->latest()->paginate(10);

        return view('readings.index', compact('readings'));
    }

    public function create()
    {
        $devices = Device::latest()->get();

        return view('readings.create', compact('devices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'reading_value' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'status' => 'required|in:normal,warning,critical',
            'recorded_at' => 'nullable|date',
        ]);

        Reading::create($request->only([
            'device_id',
            'reading_value',
            'unit',
            'status',
            'recorded_at',
        ]));

        return redirect()->route('readings.index')->with('success', 'Reading created successfully.');
    }

    public function show(Reading $reading)
    {
        $reading->load('device');

        return view('readings.show', compact('reading'));
    }

    public function edit(Reading $reading)
    {
        $devices = Device::latest()->get();

        return view('readings.edit', compact('reading', 'devices'));
    }

    public function update(Request $request, Reading $reading)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'reading_value' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'status' => 'required|in:normal,warning,critical',
            'recorded_at' => 'nullable|date',
        ]);

        $reading->update($request->only([
            'device_id',
            'reading_value',
            'unit',
            'status',
            'recorded_at',
        ]));

        return redirect()->route('readings.index')->with('success', 'Reading updated successfully.');
    }

    public function destroy(Reading $reading)
    {
        $reading->delete();

        return redirect()->route('readings.index')->with('success', 'Reading deleted successfully.');
    }
}
