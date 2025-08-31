<?php

use App\Http\Controllers\Admin\MonitoringController;
use Illuminate\Support\Facades\Route;

        // ✅ MONITORING SYSTEM - ROUTES AGGIUNTE
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('/', [MonitoringController::class, 'dashboard'])->name('dashboard');
            Route::get('/health', [MonitoringController::class, 'healthCheck'])->name('health');
            Route::get('/metrics', [MonitoringController::class, 'realtimeMetrics'])->name('metrics');
            Route::get('/history', [MonitoringController::class, 'history'])->name('history');
            Route::get('/logs', [MonitoringController::class, 'systemLogs'])->name('logs');
            Route::get('/performance', [MonitoringController::class, 'performanceMetrics'])->name('performance');
            Route::post('/clear-cache', [MonitoringController::class, 'clearCache'])->name('clear-cache');
            Route::post('/optimize', [MonitoringController::class, 'optimize'])->name('optimize');
        });
