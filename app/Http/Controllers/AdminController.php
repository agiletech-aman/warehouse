<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Reading;
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
            'admin_email' => $admin->email,
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

        // Activity is inferred from warehouse readings because device_id may be nullable.
        $activeWarehouses = Warehouse::activeInLast24Hours()->count();


        $totalRegions = Region::count();

        // Count each sensor once, using the same latest reading shown on the Devices page.
        $latestDeviceReadingIds = Reading::latestIdsPerSensor();

        $deviceStatusCounts = Reading::query()
            ->whereIn('id', clone $latestDeviceReadingIds)
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'online' THEN 1 ELSE 0 END) AS online_count")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'offline' THEN 1 ELSE 0 END) AS offline_count")
            ->first();

        $onlineDevices = (int) ($deviceStatusCounts->online_count ?? 0);
        $offlineDevices = (int) ($deviceStatusCounts->offline_count ?? 0);


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

        $routing = \App\Models\EmailRouting::all()
            ->keyBy(fn($r) => $r->device_type . '_' . $r->level);

        $ccEmails = \App\Models\CcEmail::orderBy('email')->get();

        return view('admin.settings', compact('routing', 'ccEmails'));
    }

    public function storeCcEmail(Request $request)
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'email' => 'required|email|unique:cc_emails,email',
        ]);

        \App\Models\CcEmail::create([
            'email'  => $request->email,
            'status' => $request->boolean('status', true),
        ]);

        return back()->with('success', 'CC email added successfully.');
    }

    public function updateCcEmail(Request $request, $id)
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        $cc = \App\Models\CcEmail::findOrFail($id);

        $request->validate([
            'email' => 'required|email|unique:cc_emails,email,' . $cc->id,
        ]);

        $cc->update(['email' => $request->email]);

        return back()->with('success', 'CC email updated successfully.');
    }

    public function destroyCcEmail($id)
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        \App\Models\CcEmail::findOrFail($id)->delete();

        return back()->with('success', 'CC email deleted successfully.');
    }

    public function toggleCcEmail($id)
    {
        if (!session()->has('admin_id')) {
            return redirect()->route('admin.login');
        }

        $cc = \App\Models\CcEmail::findOrFail($id);
        $cc->update(['status' => !$cc->status]);

        return back()->with('success', 'CC email status updated.');
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
