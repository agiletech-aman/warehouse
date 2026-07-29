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
                    'level' => Reading::normalizeLevel($reading->reading_value, $reading->level),
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

    public function store(Request $request)
    {
        $validated = $this->validateReading($request);
        $validated = $this->addDeviceSnapshot($validated);
        $validated['recorded_at'] ??= now();

        Reading::create($validated);

        return redirect()->route('readings.index')->with('success', 'Reading created successfully.');
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
        $validated = $this->validateReading($request);
        $reading->update($this->addDeviceSnapshot($validated));

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

    private function validateReading(Request $request): array
    {
        return $request->validate([
            'device_id' => 'nullable|exists:devices,id',
            'sensor_device_id' => 'required|string|max:100',
            'reading_value' => 'nullable|numeric',
            'unit' => 'nullable|string|max:20',
            'level' => 'nullable|in:normal,severe,critical',
            'status' => 'required|in:online,offline',
            'recorded_at' => 'nullable|date',
        ]);
    }

    private function addDeviceSnapshot(array $data): array
    {
        if (empty($data['device_id'])) {
            return $data;
        }

        $device = Device::with('warehouse.region')->find($data['device_id']);
        if (!$device) {
            return $data;
        }

        $data['sensor_device_id'] = $data['sensor_device_id'] ?: $device->device_code;
        $data['device_name'] = $device->device_name;
        $data['device_type'] = $device->device_type;
        $data['device_ip'] = $device->ip_address;
        $data['warehouse'] = $device->warehouse?->warehouse_name;
        $data['warehouse_code'] = $device->warehouse?->warehouse_code;
        $data['region'] = $device->warehouse?->region?->region_name;
        $data['region_code'] = $device->warehouse?->region?->region_code;

        return $data;
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
