<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReadingController;

use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\AlertController;


Route::post('/readings', [ReadingController::class, 'store']);

// Reading filters + severity counts (single API)
Route::get('/readings/summary', [ReadingController::class, 'indexWithSummary']);
Route::get('/regions', [RegionController::class, 'index']);
Route::get('/warehouses', [WarehouseController::class, 'index']);
Route::get('/devices', [DeviceController::class, 'index']);

// Alerts: active alerts count + all alerts
Route::get('/alerts', [AlertController::class, 'index']);





