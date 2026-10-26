<?php

use App\Fleet\Http\AdminAuth;
use App\Fleet\Http\Controllers\{AdminController, AgentController};
use Illuminate\Support\Facades\Route;

// Agent API (no session/CSRF) — /api/fleet/*
Route::prefix('api/fleet')->middleware('api')->group(function () {
    Route::post('checkin', [AgentController::class, 'checkin']);
    Route::get('releases/{release}/download', [AgentController::class, 'download']);
    Route::get('bootstrap/{code}', [AgentController::class, 'bootstrap']);
});

// Dashboard — /fleet
Route::prefix('fleet')->middleware(['web', AdminAuth::class])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('fleet.index');
    Route::post('bulk-approve', [AdminController::class, 'bulkApprove'])->name('fleet.bulk');
    Route::get('ws/{wid}', [AdminController::class, 'show'])->name('fleet.ws');
    Route::post('ws/{wid}', [AdminController::class, 'update'])->name('fleet.ws.update');
    Route::get('releases', [AdminController::class, 'releases'])->name('fleet.releases');
    Route::post('releases', [AdminController::class, 'storeRelease'])->name('fleet.releases.store');
    Route::post('releases/{release}', [AdminController::class, 'releaseAction'])->name('fleet.releases.action');
    Route::get('env', [AdminController::class, 'env'])->name('fleet.env');
    Route::post('env', [AdminController::class, 'envSave'])->name('fleet.env.save');
});
