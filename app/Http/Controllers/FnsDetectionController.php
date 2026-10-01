<?php

namespace App\Http\Controllers;

use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FnsDetectionController extends Controller
{
    public function index(): View
    {
        return view('fns-detections.index');
    }

    public function index02(): View
    {
        return view('fns-detections.index02');
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length < 1 || $length > 100) {
            $length = 10;
        }

        $query = FnsDetection::query()->filter([
            'search' => data_get($request->query('search', []), 'value', ''),
        ]);
        $recordsTotal = FnsDetection::query()->count();
        $recordsFiltered = (clone $query)->count();

        $detections = $query
            ->latest('detected_at')
            ->latest('id')
            ->offset($start)
            ->limit($length)
            ->get();
        $warehouseNames = $this->warehouseNamesFor($detections->pluck('warehouse_code'));
        $warehouseNamesByCameraIp = $this->warehouseNamesForCameraIps($detections->pluck('camera_ip'));

        $detections = $detections->map(fn (FnsDetection $detection) => [
                'id' => $detection->id,
                'name' => $detection->camera_name ?: '-',
                'camera_ip' => $detection->camera_ip ?: '-',
                'warehouse_code' => $detection->warehouse_code ?: '-',
                'warehouse_name' => $warehouseNames->get($detection->warehouse_code)
                    ?: $warehouseNamesByCameraIp->get($detection->camera_ip)
                    ?: '-',
                'location' => $this->joinParts($detection->godown, $detection->compartment),
                'detection_type' => $detection->detection_type,
                'confidence' => round($detection->confidence * 100, 2),
                'snapshot_path' => $detection->snapshot_path ?: '-',
                'snapshot_url' => $detection->snapshot_url,
                'bounding_box' => $detection->bounding_box ?: '-',
                'detected_at' => $detection->detected_at?->format('d M Y H:i:s') ?: '-',
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $detections,
        ]);
    }

    public function data02(Request $request): JsonResponse
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length < 1 || $length > 100) {
            $length = 10;
        }

        $query = FnsDetection02::query()->filter([
            'search' => data_get($request->query('search', []), 'value', ''),
        ]);
        $recordsTotal = FnsDetection02::query()->count();
        $recordsFiltered = (clone $query)->count();

        $detections = $query
            ->latest('detected_at')
            ->latest('id')
            ->offset($start)
            ->limit($length)
            ->get();
        $warehouseNames = $this->warehouseNamesFor($detections->pluck('warehouse_code'));
        $warehouseNamesByCameraIp = $this->warehouseNamesForCameraIps($detections->pluck('camera_ip'));

        $detections = $detections->map(fn (FnsDetection02 $detection) => [
                'id' => $detection->id,
                'name' => $detection->camera_name ?: '-',
                'camera_ip' => $detection->camera_ip ?: '-',
                'warehouse_code' => $detection->warehouse_code ?: '-',
                'warehouse_name' => $warehouseNames->get($detection->warehouse_code)
                    ?: $warehouseNamesByCameraIp->get($detection->camera_ip)
                    ?: '-',
                'location' => $this->joinParts($detection->godown, $detection->compartment),
                'detection_type' => $detection->detection_type,
                'confidence' => round($detection->confidence * 100, 2),
                'snapshot_path' => $detection->snapshot_path ?: '-',
                'snapshot_url' => $detection->snapshot_url,
                'bounding_box' => $detection->bounding_box ?: '-',
                'detected_at' => $detection->detected_at?->format('d M Y H:i:s') ?: '-',
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $detections,
        ]);
    }

    private function warehouseNamesFor($warehouseCodes)
    {
        $codes = $warehouseCodes->filter(fn ($code) => filled($code))->unique()->values();

        return $codes->isEmpty()
            ? collect()
            : Warehouse::withTrashed()->whereIn('warehouse_code', $codes)->pluck('warehouse_name', 'warehouse_code');
    }

    private function warehouseNamesForCameraIps($cameraIps)
    {
        $ips = $cameraIps->filter(fn ($ip) => filled($ip))->unique()->values();

        if ($ips->isEmpty() || ! \Illuminate\Support\Facades\Schema::hasTable('devices')) {
            return collect();
        }

        return Warehouse::withTrashed()
            ->join('devices', 'devices.warehouse_id', '=', 'warehouses.id')
            ->whereIn('devices.ip_address', $ips)
            ->pluck('warehouses.warehouse_name', 'devices.ip_address');
    }

    private function joinParts(?string $godown, ?string $compartment): string
    {
        $godown = trim((string) $godown);
        $compartment = trim((string) $compartment);

        $parts = array_values(array_filter([$godown, $compartment], fn (string $part) => $part !== ''));

        return $parts ? implode(' / ', $parts) : '-';
    }
}
