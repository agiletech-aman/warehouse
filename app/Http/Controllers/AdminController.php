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
        $activeWarehouses = Warehouse::count();
        $totalRegions = Region::count();

        return view('admin.dashboard', compact('totalWarehouses', 'activeWarehouses', 'totalRegions'));
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