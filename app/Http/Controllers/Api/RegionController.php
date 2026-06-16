<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
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

        $includeWarehouses = $request->query('include_warehouses') == '1' || $request->query('include_warehouses') === true;

        $query = Region::query();

        if ($status) {
            $query->where('status', $status);
        }

        $query->latest('id');

        if ($includeWarehouses) {
            $query->with([
                'warehouses' => function ($q) {
                    $q->select([
                        'id',
                        'region_id',
                        'warehouse_code',
                        'warehouse_name',
                        'manager_name',
                        'manager_email',
                        'manager_phone',
                        'status',
                        'created_at',
                        'updated_at',
                    ]);
                },
            ])->select([
                'id',
                'region_code',
                'region_name',
                'manager_name',
                'manager_email',
                'manager_phone',
                'status',
                'created_at',
                'updated_at',
            ]);
        } else {
            $query->select([
                'id',
                'region_code',
                'region_name',
                'manager_name',
                'manager_email',
                'manager_phone',
                'status',
                'created_at',
                'updated_at',
            ]);
        }

        $regions = $query->paginate($perPage);

        $activeCount = Region::where('status', 'active')->count();
        $inactiveCount = Region::where('status', 'inactive')->count();

        return response()->json([
            'success' => true,
            'message' => 'Regions fetched successfully',
            'count' => $regions->total(),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'data' => $regions,
        ]);
    }
}

