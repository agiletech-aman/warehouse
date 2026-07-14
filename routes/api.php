<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\Master\AlertController as MasterAlertController;
use App\Http\Controllers\Api\Master\RegionController as MasterRegionController;
use App\Http\Controllers\Api\Master\WarehouseController as MasterWarehouseController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::post('/readings', [ReadingController::class, 'store']);

// Reading filters + severity counts (single API)
Route::get('/readings/summary', [ReadingController::class, 'indexWithSummary']);
Route::get('/regions', [RegionController::class, 'index']);
Route::get('/warehouses', [WarehouseController::class, 'index']);
Route::get('/master/regions', [MasterRegionController::class, 'regions']);
Route::get('/master/warehouses', [MasterWarehouseController::class, 'warehouses']);
Route::get('/devices', [DeviceController::class, 'index']);

// Alerts: active alerts count + all alerts
Route::get('/alerts', [AlertController::class, 'index']);
Route::get('/master-alerts', [MasterAlertController::class, 'alertsApi']);
Route::get('/master-alerts/summary', [MasterAlertController::class, 'summaryApi']);
Route::post('/master-alert-summary', [MasterAlertController::class, 'storeSummaryApi']);
Route::get('/master-alert-summary/dashboard', [MasterAlertController::class, 'dashboardApi']);
Route::get('/master-alerts/devices', [MasterAlertController::class, 'devicesApi']);
Route::get('/master-alerts/states', [MasterAlertController::class, 'statesApi']);
Route::get('/master-alerts/states/{state}/locations', [MasterWarehouseController::class, 'locationsByState']);
Route::get('/master-alerts/export', [MasterAlertController::class, 'exportExcel']);
Route::get('/master-alerts/regions/{regionId}/warehouses', [MasterWarehouseController::class, 'warehousesByRegion']);
Route::get('/master-alerts/warehouses/{warehouseId}/cities', [MasterWarehouseController::class, 'citiesByWarehouse']);
Route::get('/master-alerts/{id}', [MasterAlertController::class, 'alertDetailsApi']);
