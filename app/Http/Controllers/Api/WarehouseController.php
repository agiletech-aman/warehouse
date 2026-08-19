<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WarehouseController extends Controller
{
     public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $warehouse = Warehouse::with('region')
            ->whereRaw('LOWER(manager_email) = ?', [
                Str::lower($validated['email'])
            ])
            ->first();

        if (!$warehouse || !Hash::check($validated['password'], $warehouse->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'warehouse' => [
                'id' => $warehouse->id,
                'frs_id' => $warehouse->frs_id,
                'nms_id' => $warehouse->nms_id,
                'lynk_id'=> $warehouse->lynk_id,

                'region_id' => $warehouse->region_id,
                'region_frs_id' => $warehouse->region_frs_id,
                'region_nms_id' => $warehouse->region_nms_id,

                'warehouse_code' => $warehouse->warehouse_code,
                'warehouse_name' => $warehouse->warehouse_name,

                'manager_name' => $warehouse->manager_name,
                'manager_email' => $warehouse->manager_email,
                'manager_phone' => $warehouse->manager_phone,

                'address' => $warehouse->address,   
                'status' => $warehouse->status,

                'region' => $warehouse->region ? [
                    'id' => $warehouse->region->id,
                    'frs_id' => $warehouse->region->frs_id,
                    'nms_id' => $warehouse->region->nms_id,
                    'region_name' => $warehouse->region->region_name,
                    'region_code' => $warehouse->region->region_code,
                ] : null,
            ],
        ]);
    }
    
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 10));
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $status = $request->query('status');
        if ($status !== null && !in_array($status, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Use active or inactive.',
            ], 422);
        }

        $includeRegion = $request->query('include_region') == '1' || $request->query('include_region') === true;
        $includeDevices = $request->query('include_devices') == '1' || $request->query('include_devices') === true;

        $regionId = $request->query('region_id');
        $regionCode = $request->query('region_code');
        $regionName = $request->query('region_name');

        $query = Warehouse::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($regionId) {
            $query->where('region_id', $regionId);
        }

        if ($regionCode) {
            $query->whereHas('region', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            });
        }

        if ($regionName) {
            $query->whereHas('region', function ($q) use ($regionName) {
                $q->where('region_name', $regionName);
            });
        }

        if ($includeRegion) {
            $query->with([
                'region' => function ($q) {
                    $q->select(['id', 'region_code', 'region_name', 'status']);
                }
            ]);
        }

        if ($includeDevices) {
            $query->with([
                'devices' => function ($q) {
                    $q->select([
                        'id',
                        'warehouse_id',
                        'device_code',
                        'device_name',
                        'device_type',
                        'ip_address',
                        'status',
                    ]);
                }
            ]);
        }

        $query->latest('id');

        // API output (do not send city/state/country/latitude/longitude)
        $query->select([
            'id',
            'region_id',
            'warehouse_code',
            'warehouse_name',
            'manager_name',
            'manager_email',
            'manager_phone',
            'address',
            'city',
            'state',
            'country',
            'status',
            'created_at',
            'updated_at',
        ]);


        $warehouses = $query->paginate($perPage);

$activeCount = Warehouse::where('status', 'active')->count();
        $inactiveCount = Warehouse::where('status', 'inactive')->count();

return response()->json([
            'success' => true,
            'message' => 'Warehouses fetched successfully',
            'count' => $warehouses->total(),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'data' => $warehouses,
        ]);
    }
}

