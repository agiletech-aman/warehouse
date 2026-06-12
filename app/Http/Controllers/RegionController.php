<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index()
    {
        $regions = Region::latest()->paginate(10);

        return view('regions.index', compact('regions'));
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
        ]);

        Region::create([
            'region_code' => $request->region_code,
            'region_name' => $request->region_name,
            'status' => $request->status,
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
            'region_name'=>'required',
            'status' => 'required|in:active,inactive',
        ]);

        $region->update([
            'region_code' => $request->region_code,
            'region_name'=>$request->region_name,
            'status'=>$request->status
        ]);

        return redirect()
            ->route('regions.index')
            ->with('success','Region Updated Successfully');
    }

    public function destroy(Region $region)
    {
        $region->delete();

        return redirect()
            ->route('regions.index')
            ->with('success','Region Deleted Successfully');
    }
}