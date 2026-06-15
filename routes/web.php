<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\WarehouseController;
use App\Models\Reading;
use App\Models\Region;
use App\Http\Controllers\ReportController;


Route::get('/', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('/', [AdminController::class, 'login'])->name('admin.login.post');

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

// Reports


Route::get('/admin/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/admin/reports/data', [ReportController::class, 'data'])->name('reports.data');
Route::get('/admin/reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
Route::get('/admin/reports/export/{format}', [ReportController::class, 'export'])->name('reports.export');


Route::get('/hierarchy', function () {
    $latestDeviceReadings = Reading::query()
        ->whereNotNull('sensor_device_id')
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->get()
        ->unique('sensor_device_id')
        ->values();

    $regionKey = fn ($code, $name) => strtolower(trim($code ?: $name ?: 'unknown'));
    $warehouseKey = fn ($code, $name) => strtolower(trim($code ?: $name ?: 'unknown'));

    $readingsByRegion = $latestDeviceReadings->groupBy(
        fn ($reading) => $regionKey($reading->region_code, $reading->region)
    );

    $regions = Region::with('warehouses')
        ->latest()
        ->get()
        ->map(function ($region) use ($readingsByRegion, $regionKey, $warehouseKey) {
            $currentRegionKey = $regionKey($region->region_code, $region->region_name);
            $regionReadings = $readingsByRegion->get($currentRegionKey, collect());
            $readingsByWarehouse = $regionReadings->groupBy(
                fn ($reading) => $warehouseKey($reading->warehouse_code, $reading->warehouse)
            );

            $warehouses = $region->warehouses
                ->map(function ($warehouse) use ($readingsByWarehouse, $warehouseKey) {
                    $currentWarehouseKey = $warehouseKey($warehouse->warehouse_code, $warehouse->warehouse_name);
                    $devices = $readingsByWarehouse->get($currentWarehouseKey, collect())->values();

                    return (object) [
                        'warehouse_code' => $warehouse->warehouse_code ?: '-',
                        'warehouse_name' => $warehouse->warehouse_name ?: 'Unknown Warehouse',
                        'manager_name' => $warehouse->manager_name,
                        'status' => $warehouse->status,
                        'devices_count' => $devices->count(),
                        'devices' => $devices,
                    ];
                });

            $knownWarehouseKeys = $region->warehouses
                ->map(fn ($warehouse) => $warehouseKey($warehouse->warehouse_code, $warehouse->warehouse_name));

            $readingOnlyWarehouses = $readingsByWarehouse
                ->reject(fn ($devices, $key) => $knownWarehouseKeys->contains($key))
                ->map(function ($devices) {
                    $first = $devices->first();

                    return (object) [
                        'warehouse_code' => $first->warehouse_code ?: '-',
                        'warehouse_name' => $first->warehouse ?: ($first->warehouse_code ?: 'Unknown Warehouse'),
                        'manager_name' => null,
                        'status' => 'active',
                        'devices_count' => $devices->count(),
                        'devices' => $devices->values(),
                    ];
                });

            $warehouses = $warehouses->concat($readingOnlyWarehouses)->values();

            return (object) [
                'region_code' => $region->region_code ?: '-',
                'region_name' => $region->region_name ?: 'Unknown Region',
                'status' => $region->status,
                'warehouses_count' => $warehouses->count(),
                'warehouses' => $warehouses,
            ];
        });

    $knownRegionKeys = $regions->map(fn ($region) => $regionKey($region->region_code, $region->region_name));

    $readingOnlyRegions = $readingsByRegion
        ->reject(fn ($readings, $key) => $knownRegionKeys->contains($key))
        ->map(function ($regionReadings) use ($warehouseKey) {
            $firstRegionReading = $regionReadings->first();
            $warehouses = $regionReadings
                ->groupBy(fn ($reading) => $warehouseKey($reading->warehouse_code, $reading->warehouse))
                ->map(function ($warehouseReadings) {
                    $firstWarehouseReading = $warehouseReadings->first();

                    return (object) [
                        'warehouse_code' => $firstWarehouseReading->warehouse_code ?: '-',
                        'warehouse_name' => $firstWarehouseReading->warehouse ?: ($firstWarehouseReading->warehouse_code ?: 'Unknown Warehouse'),
                        'manager_name' => null,
                        'status' => 'active',
                        'devices_count' => $warehouseReadings->count(),
                        'devices' => $warehouseReadings->values(),
                    ];
                })
                ->values();

            return (object) [
                'region_code' => $firstRegionReading->region_code ?: '-',
                'region_name' => $firstRegionReading->region ?: ($firstRegionReading->region_code ?: 'Unknown Region'),
                'status' => 'active',
                'warehouses_count' => $warehouses->count(),
                'warehouses' => $warehouses,
            ];
        });

    $regions = $regions
        ->concat($readingOnlyRegions)
        ->values();

    return view('hierarchy.index', compact('regions'));
})->name('hierarchy.index');
