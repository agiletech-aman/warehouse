<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 10));
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $status = $request->query('status');
        if (
            $status !== null &&
            !in_array($status, ['active', 'inactive', 'online', 'offline'], true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Use active/inactive or online/offline.',
            ], 422);
        }

        $includeWarehouse = $request->query('include_warehouse') == '1' || $request->query('include_warehouse') === true;
        $includeRegion = $request->query('include_region') == '1' || $request->query('include_region') === true;

        $regionId = $request->query('region_id');
        $regionCode = $request->query('region_code');
        $regionName = $request->query('region_name');

        $warehouseId = $request->query('warehouse_id');
        $warehouseCode = $request->query('warehouse_code');
        $warehouseName = $request->query('warehouse_name');

        $deviceCode = $request->query('device_code');
        $deviceName = $request->query('device_name');

        $query = Device::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($warehouseCode) {
            $query->whereHas('warehouse', function ($q) use ($warehouseCode) {
                $q->where('warehouse_code', $warehouseCode);
            });
        }

        if ($warehouseName) {
            $query->whereHas('warehouse', function ($q) use ($warehouseName) {
                $q->where('warehouse_name', $warehouseName);
            });
        }

        if ($regionId || $regionCode || $regionName) {
            $query->whereHas('warehouse.region', function ($q) use ($regionId, $regionCode, $regionName) {
                if ($regionId) {
                    $q->where('id', $regionId);
                }
                if ($regionCode) {
                    $q->where('region_code', $regionCode);
                }
                if ($regionName) {
                    $q->where('region_name', $regionName);
                }
            });
        }

        if ($deviceCode) {
            $query->where('device_code', $deviceCode);
        }

        if ($deviceName) {
            $query->where('device_name', $deviceName);
        }

        if ($includeWarehouse) {
            $query->with([
                'warehouse' => function ($q) {
                    $q->select(['id', 'region_id', 'warehouse_code', 'warehouse_name', 'status']);
                }
            ]);
        }

        if ($includeRegion) {
            $query->with([
                'warehouse.region' => function ($q) {
                    $q->select(['id', 'region_code', 'region_name', 'status']);
                }
            ]);
        }

        $query->latest('id');

        $query->select([
            'id',
            'warehouse_id',
            'device_code',
            'device_name',
            'device_type',
            'ip_address',
            'status',
            'created_at',
            'updated_at'
        ]);

$devices = $query->paginate($perPage);

$activeCount = Device::where('status', 'active')->count();
        $inactiveCount = Device::where('status', 'inactive')->count();

        return response()->json([
            'success' => true,
            'message' => 'Devices fetched successfully',
            'count' => $devices->total(),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'data' => $devices,
        ]);
    }
}

