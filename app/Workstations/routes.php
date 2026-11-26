<?php

use App\Workstations\Http\Controllers\{AgentController, AuthController, DashboardController, EnvController,
    InstallController, ReleaseController, WorkstationController};
use App\Workstations\Http\Middleware\{AdminGate, PortalGuard};
use Illuminate\Support\Facades\Route;

// ── Agent API (stateless, no session/CSRF) ────────────────────────────────
Route::prefix('api/agent')->middleware('api')->group(function () {
    Route::get('bootstrap/{code}', [AgentController::class, 'bootstrap'])->middleware('throttle:wsm-bootstrap');
    Route::post('checkin', [AgentController::class, 'checkin'])->middleware('throttle:wsm-checkin');
    Route::get('releases/{release}/download', [AgentController::class, 'download'])->middleware('throttle:wsm-bootstrap');
});

// ── Admin portal ──────────────────────────────────────────────────────────
Route::prefix('workstations')->middleware(['web', PortalGuard::class])->name('wsm.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:wsm-login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(AdminGate::class)->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::post('approve-all', [DashboardController::class, 'approveAll'])->name('approve-all');

        Route::get('add', [InstallController::class, 'show'])->name('add');
        Route::post('add', [InstallController::class, 'create'])->name('add.create');

        Route::get('releases', [ReleaseController::class, 'index'])->name('releases');
        Route::post('releases', [ReleaseController::class, 'store'])->name('releases.store');
        Route::post('releases/{release}', [ReleaseController::class, 'action'])->name('releases.action');

        Route::get('settings', [EnvController::class, 'index'])->name('env');
        Route::post('settings', [EnvController::class, 'save'])->name('env.save');

        Route::get('ws/{wid}', [WorkstationController::class, 'show'])->name('show');
        Route::post('ws/{wid}', [WorkstationController::class, 'update'])->name('update');
    });
});
