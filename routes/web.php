<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\WarehouseController;


Route::get('/admin/login', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.post');

Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])
    ->name('admin.dashboard');

Route::post('/admin/logout', [AdminController::class, 'logout'])
    ->name('admin.logout');

Route::get('/admin/settings', [AdminController::class, 'settings'])
    ->name('admin.settings');

Route::post('/admin/change-password', [AdminController::class, 'changePassword'])
    ->name('admin.change-password');

Route::resource('regions', RegionController::class);
Route::resource('warehouses', WarehouseController::class);
Route::resource('devices', DeviceController::class);
Route::resource('readings', ReadingController::class);
Route::resource('alerts', AlertController::class);

