<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    private function latestReadingIds()
    {
        return Reading::latestIdsPerSensor();
    }

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

        $query = Reading::query()
            ->whereIn('id', $this->latestReadingIds());

        if ($status) {
            $statusMap = [
                'active' => 'online',
                'inactive' => 'offline',
            ];

            $query->where('status', $statusMap[$status] ?? $status);
        }

        if ($warehouseId) {
            $warehouse = Warehouse::find($warehouseId);

            if ($warehouse) {
                $query->where(function ($q) use ($warehouse) {
                    $q->where('warehouse_code', $warehouse->warehouse_code)
                        ->orWhere('warehouse', $warehouse->warehouse_name);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($warehouseCode) {
            $query->where('warehouse_code', $warehouseCode);
        }

        if ($warehouseName) {
            $query->where('warehouse', $warehouseName);
        }

        if ($regionId) {
            $region = Region::find($regionId);

            if ($region) {
                $query->where(function ($q) use ($region) {
                    $q->where('region_code', $region->region_code)
                        ->orWhere('region', $region->region_name);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($regionCode) {
            $query->where('region_code', $regionCode);
        }

        if ($regionName) {
            $query->where('region', $regionName);
        }

        if ($deviceCode) {
            $query->where('sensor_device_id', $deviceCode);
        }

        if ($deviceName) {
            $query->where('device_name', $deviceName);
        }

        $query->latest('id');

        $query->select([
            'id',
            'sensor_device_id',
            'device_name',
            'device_type',
            'device_ip',
            'region',
            'region_code',
            'warehouse',
            'warehouse_code',
            'godown',
            'compartment',
            'reading_value',
            'unit',
            'level',
            'status',
            'recorded_at',
            'created_at',
            'updated_at'
        ]);

        $devices = $query->paginate($perPage);
        $devices->getCollection()->transform(function (Reading $reading) use ($includeWarehouse, $includeRegion) {
            $row = [
                'id' => $reading->id,
                'reading_id' => $reading->id,
                'warehouse_id' => null,
                'device_code' => $reading->sensor_device_id,
                'device_name' => $reading->device_name,
                'device_type' => $reading->device_type,
                'ip_address' => $reading->device_ip,
                'device_ip' => $reading->device_ip,
                'region' => $reading->region,
                'region_code' => $reading->region_code,
                'warehouse' => $reading->warehouse,
                'warehouse_code' => $reading->warehouse_code,
                'godown' => $reading->godown,
                'compartment' => $reading->compartment,
                'latest_reading' => $reading->reading_value,
                'reading_value' => $reading->reading_value,
                'unit' => $reading->unit,
                'level' => $reading->reading_value === null ? 'unknown' : ($reading->level ?: 'normal'),
                'status' => $reading->status ?: 'offline',
                'recorded_at' => $reading->recorded_at,
                'created_at' => $reading->created_at,
                'updated_at' => $reading->updated_at,
            ];

            if ($includeWarehouse) {
                $row['warehouse_data'] = [
                    'warehouse_code' => $reading->warehouse_code,
                    'warehouse_name' => $reading->warehouse,
                ];
            }

            if ($includeRegion) {
                $row['region_data'] = [
                    'region_code' => $reading->region_code,
                    'region_name' => $reading->region,
                ];
            }

            return $row;
        });

        $latestDevices = Reading::query()->whereIn('id', $this->latestReadingIds());
        $activeCount = (clone $latestDevices)->where('status', 'online')->count();
        $inactiveCount = (clone $latestDevices)->where('status', 'offline')->count();

        return response()->json([
            'success' => true,
            'message' => 'Devices fetched successfully from readings',
            'count' => $devices->total(),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'online' => $activeCount,
            'offline' => $inactiveCount,
            'data' => $devices,
        ]);
    }
}

