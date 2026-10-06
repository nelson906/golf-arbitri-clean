<?php

use App\Http\Controllers\SuperAdmin\CacheManagementController;
use App\Http\Controllers\SuperAdmin\HealthCheckController;
use App\Http\Controllers\SuperAdmin\MonitoringController;
use App\Http\Controllers\SuperAdmin\SystemLogsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Monitoring Routes
|--------------------------------------------------------------------------
| Routes per monitoraggio sistema, accessibili solo a super_admin
|--------------------------------------------------------------------------
*/

Route::prefix('monitoring')->name('monitoring.')->group(function () {
    // Dashboard e metriche principali
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('dashboard');
    Route::get('/metrics', [MonitoringController::class, 'realtimeMetrics'])->name('metrics');
    Route::get('/performance', [MonitoringController::class, 'performanceMetrics'])->name('performance');

    // Health Check
    Route::get('/health', [HealthCheckController::class, 'index'])->name('health');

    // System Logs
    Route::get('/logs', [SystemLogsController::class, 'index'])->name('logs');

    // Cache Management
    Route::post('/clear-cache', [CacheManagementController::class, 'clear'])->name('clear-cache');
    Route::post('/optimize', [CacheManagementController::class, 'optimize'])->name('optimize');
});
