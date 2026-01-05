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
    Route::get('/history', [MonitoringController::class, 'history'])->name('history');
    Route::get('/performance', [MonitoringController::class, 'performanceMetrics'])->name('performance');

    // Health Check
    Route::get('/health', [HealthCheckController::class, 'index'])->name('health');
    Route::get('/health/{component}', [HealthCheckController::class, 'check'])->name('health.check');
    Route::get('/status', [HealthCheckController::class, 'status'])->name('status');
    Route::get('/uptime', [HealthCheckController::class, 'uptime'])->name('uptime');

    // System Logs
    Route::get('/logs', [SystemLogsController::class, 'index'])->name('logs');
    Route::get('/logs/level/{level}', [SystemLogsController::class, 'byLevel'])->name('logs.level');
    Route::get('/logs/stats', [SystemLogsController::class, 'stats'])->name('logs.stats');
    Route::get('/logs/errors/count', [SystemLogsController::class, 'errorCount'])->name('logs.error-count');

    // Cache Management
    Route::post('/clear-cache', [CacheManagementController::class, 'clear'])->name('clear-cache');
    Route::post('/optimize', [CacheManagementController::class, 'optimize'])->name('optimize');
    Route::get('/cache/stats', [CacheManagementController::class, 'stats'])->name('cache.stats');
    Route::post('/cache/clear-application', [CacheManagementController::class, 'clearApplication'])->name('cache.clear-application');
    Route::post('/cache/clear-views', [CacheManagementController::class, 'clearViews'])->name('cache.clear-views');

    // API endpoints per polling/AJAX
    Route::get('/api/{type}', [MonitoringController::class, 'apiMetrics'])->name('api');
});
