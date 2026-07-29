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
        return view('alerts.index');
    }

    public function data(Request $request)
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length <= 0 || $length > 100) {
            $length = 10;
        }

        $query = Alert::query()->with([
            'device:id,device_code,device_name',
            'reading:id,device_name,reading_value',
        ]);

        $this->applyDataTableSearch($query, $request);

        $recordsTotal = Alert::query()->count();
        $recordsFiltered = (clone $query)->count();

        $orderColumnIndex = (int) data_get($request->query('order', []), '0.column', 4);
        $orderDirection = strtolower((string) data_get($request->query('order', []), '0.dir', 'desc'));
        $orderDirection = $orderDirection === 'asc' ? 'asc' : 'desc';
        $orderColumns = [
            0 => 'device_id',
            1 => 'type',
            2 => 'message',
            3 => 'alert_value',
            4 => 'created_at',
            5 => 'last_email_at',
        ];
        $orderColumn = $orderColumns[$orderColumnIndex] ?? 'created_at';

        $alerts = $query
            ->orderBy($orderColumn, $orderDirection)
            ->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function (Alert $alert) {
                $type = strtolower((string) ($alert->type ?: $alert->alert_type ?: 'alert'));
                $isOffline = str_contains($type, 'offline')
                    || str_contains(strtolower((string) $alert->message), 'offline');

                return [
                    'device' => $alert->device?->device_name
                        ?: $alert->reading?->device_name
                        ?: $alert->device_id,
                    'type' => $isOffline ? 'unknown' : $type,
                    'message' => $alert->message
                        ?: str_replace('_', ' ', ucfirst($alert->type ?: $alert->alert_type ?: 'Alert')),
                    'value' => $isOffline
                        ? 'N/A'
                        : ($alert->alert_value ?? $alert->reading?->reading_value ?? '-'),
                    'triggered_at' => $alert->created_at?->format('d M Y H:i:s') ?: '-',
                    'mail_status' => $alert->last_email_at ? 'sent' : 'pending',
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $alerts,
        ]);
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

    private function applyDataTableSearch($query, Request $request): void
    {
        $value = trim((string) data_get($request->query('search', []), 'value', ''));

        if ($value === '') {
            return;
        }

        $query->where(function ($q) use ($value) {
            $like = '%' . $value . '%';

            $q->where('device_id', 'like', $like)
                ->orWhere('type', 'like', $like)
                ->orWhere('alert_type', 'like', $like)
                ->orWhere('message', 'like', $like)
                ->orWhere('alert_value', 'like', $like)
                ->orWhere('created_at', 'like', $like)
                ->orWhereHas('device', fn ($device) => $device
                    ->where('device_name', 'like', $like)
                    ->orWhere('device_code', 'like', $like))
                ->orWhereHas('reading', fn ($reading) => $reading
                    ->where('device_name', 'like', $like));
        });
    }
}
