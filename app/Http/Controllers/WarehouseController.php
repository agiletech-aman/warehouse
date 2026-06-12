<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::with('region')
            ->latest()
            ->paginate(10);


        return view('warehouses.index', compact('warehouses'));
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
            'warehouse_code' => 'required|unique:warehouses,warehouse_code,' . $warehouse->id . '|max:20',
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
        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse Deleted Successfully');
    }
}
