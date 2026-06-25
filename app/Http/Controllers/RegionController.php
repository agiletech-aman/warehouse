<?php

namespace App\Http\Controllers;

use App\Exports\RegionsExport;
use App\Models\Reading;
use App\Models\Region;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RegionController extends Controller
{
    public function index()
    {
        $regions = Region::latest()->get();

        return view('regions.index', compact('regions'));
    }

    public function export()
    {
        return Excel::download(
            new RegionsExport(),
            'regions-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function create()
    {
        return view('regions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'region_code' => 'required|unique:regions,region_code',
            'region_name' => 'required',
            'status' => 'required|in:active,inactive',
            'manager_name' => 'nullable|string|max:100',
            'manager_email' => 'nullable|email|max:100',
            'manager_phone' => 'nullable|string|max:20',
        ]);

        Region::create([
            'region_code' => $request->region_code,
            'region_name' => $request->region_name,
            'status' => $request->status,
            'manager_name' => $request->manager_name,
            'manager_email' => $request->manager_email,
            'manager_phone' => $request->manager_phone,
        ]);

        return redirect()
                ->route('regions.index')
                ->with('success','Region Created Successfully');
    }

    public function show(Region $region)
    {
        return view('regions.show',compact('region'));
    }

    public function edit(Region $region)
    {
        return view('regions.edit',compact('region'));
    }

    public function update(Request $request, Region $region)
    {
        $request->validate([
            'region_code' => 'required|unique:regions,region_code,' . $region->id,
            'region_name' => 'required',
            'status' => 'required|in:active,inactive',
            'manager_name' => 'nullable|string|max:100',
            'manager_email' => 'nullable|email|max:100',
            'manager_phone' => 'nullable|string|max:20',
        ]);

        $region->update([
            'region_code' => $request->region_code,
            'region_name' => $request->region_name,
            'status' => $request->status,
            'manager_name' => $request->manager_name,
            'manager_email' => $request->manager_email,
            'manager_phone' => $request->manager_phone,
        ]);

        return redirect()
            ->route('regions.index')
            ->with('success','Region Updated Successfully');
    }

    public function destroy(Region $region)
    {
        $hasWarehouses = $region->warehouses()->exists();
        $hasReadings = Reading::where(function ($query) use ($region) {
            $query->where('region_code', $region->region_code)
                ->orWhere('region', $region->region_name);
        })->exists();

        if ($hasWarehouses || $hasReadings) {
            return redirect()
                ->route('regions.index')
                ->with('error', 'Region is in use and cannot be deleted.');
        }

        $region->delete();

        return redirect()
            ->route('regions.index')
            ->with('success','Region Deleted Successfully');
    }
}
