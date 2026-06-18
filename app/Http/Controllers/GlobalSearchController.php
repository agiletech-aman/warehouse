<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['success' => true, 'results' => []]);
        }

        $limit = (int) $request->query('limit', 6);
        $limit = max(1, min($limit, 10));

        $term = $q;
        $like = '%' . $term . '%';

        $results = [];

        // Regions
        $regions = Region::query()
            ->where(function ($qq) use ($like) {
                $qq->where('region_code', 'like', $like)
                    ->orWhere('region_name', 'like', $like)
                    ->orWhere('manager_name', 'like', $like);
            })
            ->limit($limit)
            ->get(['id', 'region_code', 'region_name']);

        foreach ($regions as $r) {
            $results[] = [
                'type' => 'region',
                'label' => $r->region_code . ' - ' . $r->region_name,
                'route' => route('regions.index') . '?search=' . urlencode($r->region_code),
            ];
        }

        // Warehouses
        $warehouses = Warehouse::query()
            ->where(function ($qq) use ($like) {
                $qq->where('warehouse_code', 'like', $like)
                    ->orWhere('warehouse_name', 'like', $like)
                    ->orWhere('manager_name', 'like', $like);
            })
            ->limit($limit)
            ->get(['id', 'warehouse_code', 'warehouse_name']);

        foreach ($warehouses as $w) {
            $results[] = [
                'type' => 'warehouse',
                'label' => $w->warehouse_code . ' - ' . $w->warehouse_name,
                'route' => route('warehouses.index') . '?search=' . urlencode($w->warehouse_code),
            ];
        }

        // Devices
        $devices = Device::query()
            ->where(function ($qq) use ($like) {
                $qq->where('device_code', 'like', $like)
                    ->orWhere('device_name', 'like', $like)
                    ->orWhere('device_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like);
            })
            ->limit($limit)
            ->get(['id', 'device_code', 'device_name', 'device_type']);

        foreach ($devices as $d) {
            $results[] = [
                'type' => 'device',
                'label' => $d->device_code . ' - ' . ($d->device_name ?: $d->device_type ?: 'Device'),
                'route' => route('devices.index') . '?search=' . urlencode($d->device_code),
            ];
        }

        // Readings
        $readings = Reading::query()
            ->where(function ($qq) use ($like) {
                $qq->where('sensor_device_id', 'like', $like)
                    ->orWhere('device_name', 'like', $like)
                    ->orWhere('region', 'like', $like)
                    ->orWhere('warehouse', 'like', $like);
            })
            ->limit($limit)
            ->orderByDesc('recorded_at')
            ->get(['id', 'sensor_device_id', 'device_name', 'region', 'warehouse', 'recorded_at']);

        foreach ($readings as $r) {
            $results[] = [
                'type' => 'reading',
                'label' => ($r->sensor_device_id ?: '-') . ' - ' . ($r->device_name ?: 'Reading'),
                'route' => route('readings.index') . '?search=' . urlencode($r->sensor_device_id ?: ''),
            ];
        }

        // Alerts
        $alerts = Alert::query()
            ->where(function ($qq) use ($like) {
                $qq->where('type', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhere('reading_id', 'like', $like);
            })
            ->limit($limit)
            ->orderByDesc('created_at')
            ->get(['id', 'type', 'message', 'reading_id', 'created_at']);

        foreach ($alerts as $a) {
            $results[] = [
                'type' => 'alert',
                'label' => ($a->type ?: 'alert') . ' - ' . ($a->message ?: ('#' . $a->id)),
                'route' => route('alerts.index'),
            ];
        }

        // Reports (static)
        $results[] = [
            'type' => 'report',
            'label' => 'Reports',
            'route' => route('reports.index'),
        ];

        // keep unique by (type,label)
        $deduped = [];
        $seen = [];
        foreach ($results as $item) {
            $k = $item['type'] . '|' . $item['label'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $deduped[] = $item;
        }

        return response()->json(['success' => true, 'results' => array_slice($deduped, 0, 15)]);
    }
}

