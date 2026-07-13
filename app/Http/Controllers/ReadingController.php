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

        return view('readings.index', compact('readingCounts'));
    }

    public function data(Request $request)
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length <= 0 || $length > 100) {
            $length = 10;
        }

        $query = Reading::query();
        $this->applyDataTableSearch($query, $request);

        $recordsTotal = Reading::query()->count();
        $recordsFiltered = (clone $query)->count();

        $readings = $query
            ->latest('recorded_at')
            ->latest('id')
            ->offset($start)
            ->limit($length)
            ->get([
                'id',
                'device_name',
                'device_type',
                'sensor_device_id',
                'reading_value',
                'unit',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
                'godown',
                'compartment',
                'level',
                'status',
                'recorded_at',
            ])
            ->map(function (Reading $reading) {
                return [
                    'device' => $reading->device_name ?: '-',
                    'type' => $reading->device_type ?: '-',
                    'sensor' => $reading->sensor_device_id ?: '-',
                    'value' => $reading->reading_value,
                    'unit' => $reading->unit ?: '-',
                    'region_warehouse' => $this->joinLocationParts(
                        $reading->region ?: $reading->region_code,
                        $reading->warehouse ?: $reading->warehouse_code
                    ),
                    'godown_compartment' => $this->joinLocationParts($reading->godown, $reading->compartment),
                    'level' => $reading->level ?: 'normal',
                    'status' => $reading->status ?: 'unknown',
                    'recorded_at' => $reading->recorded_at ? $reading->recorded_at->format('d M Y H:i:s') : '-',
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $readings,
        ]);
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

    private function applyDataTableSearch($query, Request $request): void
    {
        $value = trim((string) data_get($request->query('search', []), 'value', ''));

        if ($value === '') {
            return;
        }

        $query->where(function ($q) use ($value) {
            $q->where('recorded_at', 'like', '%' . $value . '%')
                ->orWhere('device_name', 'like', '%' . $value . '%')
                ->orWhere('device_type', 'like', '%' . $value . '%')
                ->orWhere('sensor_device_id', 'like', '%' . $value . '%')
                ->orWhere('reading_value', 'like', '%' . $value . '%')
                ->orWhere('unit', 'like', '%' . $value . '%')
                ->orWhere('region', 'like', '%' . $value . '%')
                ->orWhere('region_code', 'like', '%' . $value . '%')
                ->orWhere('warehouse', 'like', '%' . $value . '%')
                ->orWhere('warehouse_code', 'like', '%' . $value . '%')
                ->orWhere('godown', 'like', '%' . $value . '%')
                ->orWhere('compartment', 'like', '%' . $value . '%')
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
}
