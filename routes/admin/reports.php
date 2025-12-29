<?php

/*
|--------------------------------------------------------------------------
| routes/admin/reports.php - Reporting Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\StatisticsDashboardController;
use Illuminate\Support\Facades\Route;

// REPORTS & STATISTICS
Route::prefix('reports')->name('reports.')->group(function () {

    // Main Dashboard
    Route::get('/', [StatisticsDashboardController::class, 'index'])->name('index');

    // Working routes that match actual controller methods
    Route::get('/disponibilita', [StatisticsDashboardController::class, 'disponibilita'])->name('disponibilita');
    Route::get('/assegnazioni', [StatisticsDashboardController::class, 'assegnazioni'])->name('assegnazioni');
    Route::get('/tornei', [StatisticsDashboardController::class, 'tornei'])->name('tornei');
    Route::get('/arbitri', [StatisticsDashboardController::class, 'arbitri'])->name('arbitri');
    Route::get('/zone', [StatisticsDashboardController::class, 'zone'])->name('zone');
    Route::get('/performance', [StatisticsDashboardController::class, 'performance'])->name('performance');
    Route::get('/export-csv', [StatisticsDashboardController::class, 'exportCsv'])->name('export-csv');
    Route::get('/api-stats/{type}', [StatisticsDashboardController::class, 'apiStats'])->name('api-stats');
});
