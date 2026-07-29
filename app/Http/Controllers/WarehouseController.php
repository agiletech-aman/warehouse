<?php

namespace App\Http\Controllers;

use App\Exports\WarehousesExport;
use App\Models\Alert;
use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $activeOnly = $request->boolean('active');

        return view('warehouses.index', compact('activeOnly'));
    }

    public function data(Request $request)
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length <= 0 || $length > 100) {
            $length = 10;
        }

        $activeOnly = $request->boolean('active');
        $baseQuery = Warehouse::query()
            ->when($activeOnly, fn ($query) => $query->activeInLast24Hours());

        $recordsTotal = (clone $baseQuery)->count();
        $query = (clone $baseQuery)->with('region');
        $this->applyDataTableSearch($query, $request);
        $recordsFiltered = (clone $query)->count();

        $orderColumnIndex = (int) data_get($request->query('order', []), '0.column', 0);
        $orderDirection = strtolower((string) data_get($request->query('order', []), '0.dir', 'asc'));
        $orderDirection = $orderDirection === 'desc' ? 'desc' : 'asc';
        $orderColumns = [
            0 => 'warehouse_code',
            1 => 'warehouse_name',
            2 => 'region_uuid',
            3 => 'manager_name',
            4 => 'status',
        ];
        $orderColumn = $orderColumns[$orderColumnIndex] ?? 'warehouse_code';

        $warehouses = $query
            ->orderBy($orderColumn, $orderDirection)
            ->orderBy('id')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn (Warehouse $warehouse) => [
                'code' => $warehouse->warehouse_code ?: '-',
                'name' => $warehouse->warehouse_name ?: '-',
                'region' => $warehouse->region?->region_name ?: '-',
                'manager_name' => $warehouse->manager_name ?: '-',
                'manager_email' => $warehouse->manager_email,
                'manager_phone' => $warehouse->manager_phone,
                'status' => strtolower((string) ($warehouse->status ?: 'unknown')),
                'edit_url' => route('warehouses.edit', $warehouse),
                'delete_url' => route('warehouses.destroy', $warehouse),
                'delete_label' => $warehouse->warehouse_name ?: 'this warehouse',
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $warehouses,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new WarehousesExport(
                trim((string) $request->query('search', '')),
                $request->boolean('active')
            ),
            ($request->boolean('active') ? 'active-warehouses-' : 'warehouses-')
                .now()->format('Y-m-d-His').'.xlsx'
        );
    }

    public function create()
    {
        $regions = Region::latest()->get();

        return view('warehouses.create', compact('regions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'region_id' => 'required|exists:regions,id',
            'warehouse_code' => 'required|unique:warehouses,warehouse_code|max:20',
            'warehouse_name' => 'required|string|max:150',
            'manager_name' => 'required|string|max:100',
            'manager_email' => 'nullable|email|max:100',
            'manager_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,inactive',
        ]);

        Warehouse::create($request->only([
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
            'latitude',
            'longitude',
            'status',
        ]));

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse Created Successfully');
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load('region');

        return view('warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse)
    {
        $regions = Region::latest()->get();

        return view('warehouses.edit', compact('warehouse', 'regions'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'region_id' => 'required|exists:regions,id',
            'warehouse_code' => 'required|unique:warehouses,warehouse_code,'.$warehouse->id.'|max:20',
            'warehouse_name' => 'required|string|max:150',
            'manager_name' => 'required|string|max:100',
            'manager_email' => 'nullable|email|max:100',
            'manager_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouse->update($request->only([
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
            'latitude',
            'longitude',
            'status',
        ]));

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse Updated Successfully');
    }

    public function destroy(Warehouse $warehouse)
    {
        $hasDevices = $warehouse->devices()->exists();
        $hasReadings = Reading::where(function ($query) use ($warehouse) {
            $query->where('warehouse_code', $warehouse->warehouse_code)
                ->orWhere('warehouse', $warehouse->warehouse_name);
        })->exists();
        $hasAlerts = Alert::whereIn(
            'device_id',
            $warehouse->devices()->pluck('device_code')
        )->exists();

        if ($hasDevices || $hasReadings || $hasAlerts) {
            return redirect()
                ->route('warehouses.index')
                ->with('error', 'Warehouse is in use and cannot be deleted.');
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse Deleted Successfully');
    }

    private function applyDataTableSearch($query, Request $request): void
    {
        $value = trim((string) data_get($request->query('search', []), 'value', ''));

        if ($value === '') {
            return;
        }

        $like = '%' . $value . '%';
        $query->where(function ($query) use ($like) {
            $query->where('warehouse_code', 'like', $like)
                ->orWhere('warehouse_name', 'like', $like)
                ->orWhere('manager_name', 'like', $like)
                ->orWhere('manager_email', 'like', $like)
                ->orWhere('manager_phone', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhereHas('region', function ($regionQuery) use ($like) {
                    $regionQuery->where('region_code', 'like', $like)
                        ->orWhere('region_name', 'like', $like);
                });
        });
    }
}
