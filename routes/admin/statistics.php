<?php

use App\Http\Controllers\Admin\StatisticsDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Statistics Routes
|--------------------------------------------------------------------------
| RINOMINATO: statistic.php → statistics.php per coerenza naming
| Controller: StatisticsDashboardController
|*/

Route::prefix('statistics')->name('statistics.')->group(function () {
    Route::get('/', [StatisticsDashboardController::class, 'index'])->name('dashboard');
    Route::get('/disponibilita', [StatisticsDashboardController::class, 'disponibilita'])->name('disponibilita');
    Route::get('/assegnazioni', [StatisticsDashboardController::class, 'assegnazioni'])->name('assegnazioni');
    Route::get('/tornei', [StatisticsDashboardController::class, 'tornei'])->name('tornei');
    Route::get('/arbitri', [StatisticsDashboardController::class, 'arbitri'])->name('arbitri');
    Route::get('/zone', [StatisticsDashboardController::class, 'zone'])->name('zone');
    Route::get('/performance', [StatisticsDashboardController::class, 'performance'])->name('performance');
    Route::get('/export', [StatisticsDashboardController::class, 'exportCsv'])->name('export');
    Route::get('/api/{type}', [StatisticsDashboardController::class, 'apiStats'])->name('api');
});
