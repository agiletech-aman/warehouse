<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // Login Page
    public function showLogin()
    {
        // resources/views/auth/login.blade.php
        return view('auth.login');
    }

    // Handle Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()
                ->withInput()
                ->with('error', 'Invalid email or password');
        }

        session([
            'admin_id'   => $admin->id,
            'admin_name' => $admin->name,
            'admin_email'=> $admin->email,
        ]);

        return redirect()->route('admin.dashboard');
    }

    // Dashboard
    public function dashboard()
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        $totalWarehouses = Warehouse::count();

        // Active warehouses definition (as per requirement): warehouses that have any reading in last 24 hours.
        // IoT store flow keeps device_id nullable, so infer activity from readings -> warehouse_code/warehouse.
        $activeWarehouses = Warehouse::query()
            ->where(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('readings')
                        ->where('recorded_at', '>=', now()->subDay())
                        ->whereColumn('readings.warehouse_code', 'warehouses.warehouse_code');
                });
            })
            ->orWhere(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('readings')
                        ->where('recorded_at', '>=', now()->subDay())
                        ->whereColumn('readings.warehouse', 'warehouses.warehouse_name');
                });
            })
            ->distinct('warehouses.id')
            ->count('warehouses.id');


        $totalRegions = Region::count();

        // Device status from readings (latest status per sensor_device_id within last 24 hours)
        $onlineDevices = \App\Models\Reading::query()
            ->selectRaw('sensor_device_id')
            ->whereNotNull('sensor_device_id')
            ->where('recorded_at', '>=', now()->subDay())
            ->groupBy('sensor_device_id')
            ->havingRaw("MAX(CASE WHEN LOWER(status) = 'online' THEN 1 ELSE 0 END) = 1")
            ->count();

        $offlineDevices = \App\Models\Reading::query()
            ->selectRaw('sensor_device_id')
            ->whereNotNull('sensor_device_id')
            ->where('recorded_at', '>=', now()->subDay())
            ->groupBy('sensor_device_id')
            ->havingRaw("MAX(CASE WHEN LOWER(status) = 'offline' THEN 1 ELSE 0 END) = 1")
            ->count();


        $criticalActiveAlerts = \App\Models\Alert::where('active', true)
            ->where(function ($q) {
                $q->where('type', 'critical')
                    ->orWhere('type', 'severe')
                    ->orWhere('type', 'device_offline');
            })
            ->count();

        $last24hAlertsCount = \App\Models\Alert::where('created_at', '>=', now()->subDay())->count();

        $latestReadings = \App\Models\Reading::with('device')
            ->latest('recorded_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalWarehouses',
            'activeWarehouses',
            'totalRegions',
            'onlineDevices',
            'offlineDevices',
            'criticalActiveAlerts',
            'last24hAlertsCount',
            'latestReadings'
        ));
    }

    public function settings()
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.settings');
    }

    public function changePassword(Request $request)
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Admin::find(session('admin_id'));

        if (!$admin || !Hash::check($request->old_password, $admin->password)) {
            return back()->with('error', 'Your old password is incorrect.');
        }

        $admin->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('admin.settings')->with('success', 'Password changed successfully.');
    }

    // Logout
    public function logout()
    {
        session()->flush();

        return redirect()->route('admin.login')
            ->with('success', 'Logged out successfully');
    }
}