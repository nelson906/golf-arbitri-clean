<?php

use App\Http\Controllers\Admin\CacheManagementController;
use App\Http\Controllers\Admin\HealthCheckController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\SystemLogsController;
use Illuminate\Support\Facades\Route;

// ✅ MONITORING SYSTEM - ROUTES
Route::prefix('monitoring')->name('monitoring.')->group(function () {
    // Dashboard e metriche principali
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('dashboard');
    Route::get('/metrics', [MonitoringController::class, 'realtimeMetrics'])->name('metrics');
    Route::get('/history', [MonitoringController::class, 'history'])->name('history');
    Route::get('/performance', [MonitoringController::class, 'performanceMetrics'])->name('performance');

    // Health Check
    Route::get('/health', [HealthCheckController::class, 'index'])->name('health');
    Route::get('/health/{component}', [HealthCheckController::class, 'check'])->name('health.check');
    Route::get('/status', [HealthCheckController::class, 'status'])->name('status');

    // System Logs
    Route::get('/logs', [SystemLogsController::class, 'index'])->name('logs');
    Route::get('/logs/level/{level}', [SystemLogsController::class, 'byLevel'])->name('logs.level');
    Route::get('/logs/stats', [SystemLogsController::class, 'stats'])->name('logs.stats');

    // Cache Management
    Route::post('/clear-cache', [CacheManagementController::class, 'clear'])->name('clear-cache');
    Route::post('/optimize', [CacheManagementController::class, 'optimize'])->name('optimize');
    Route::get('/cache-stats', [CacheManagementController::class, 'stats'])->name('cache-stats');

    // API endpoints
    Route::get('/api/{type}', [MonitoringController::class, 'apiMetrics'])->name('api');
});
