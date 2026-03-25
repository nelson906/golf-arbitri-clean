<?php

use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\QuadrantiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy Referee Routes (Compatibility Layer)
|--------------------------------------------------------------------------
| Queste route mantengono la compatibilità con i vecchi URL /referee/*.
| Devono essere protette dal middleware auth + referee_or_admin esattamente
| come le route /user/* equivalenti.
|
| FIX: aggiunto middleware auth — in precedenza mancante (rischio accesso
| non autenticato). Vedere test: RefereeDashboardAuthTest.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->group(function () {
    Route::get('/referee/dashboard', [DashboardController::class, 'index'])->name('referee.dashboard');

    // Quadranti (Starting Times Simulator)
    Route::prefix('/referee/quadranti')->name('referee.quadranti.')->group(function () {
        Route::get('/', [QuadrantiController::class, 'index'])->name('index');
        Route::post('/upload-excel', [QuadrantiController::class, 'uploadExcel'])->name('upload-excel');
    });
});
