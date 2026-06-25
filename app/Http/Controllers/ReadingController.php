<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function index()
    {
        $readingCounts = [
            'total' => Reading::count(),
            'normal' => Reading::where(function ($query) {
                $query->where('level', 'normal')
                    ->orWhereNull('level')
                    ->orWhere('level', '');
            })->count(),
            'severe' => Reading::where('level', 'severe')->count(),
            'critical' => Reading::where('level', 'critical')->count(),
        ];

        $readings = Reading::with('device')
            ->latest('recorded_at')
            ->latest('id')
            ->get();

        return view('readings.index', compact('readings', 'readingCounts'));
    }

    public function create()
    {
        // UI: readings can still be manually linked to a device (optional).
        // Import removed since monitoring system store flow no longer resolves device FK.
        $devices = \App\Models\Device::latest()->get();

        return view('readings.create', compact('devices'));
    }

    public function show(Reading $reading)
    {
        $reading->load('device');

        return view('readings.show', compact('reading'));
    }

    public function edit(Reading $reading)
    {
        $devices = \App\Models\Device::latest()->get();

        return view('readings.edit', compact('reading', 'devices'));
    }

    public function update(Request $request, Reading $reading)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'reading_value' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'status' => 'required|in:normal,severe,critical',
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
