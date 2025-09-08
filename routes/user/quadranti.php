<?php

use App\Http\Controllers\User\QuadrantiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Quadranti Routes
|--------------------------------------------------------------------------
|
| Routes per il simulatore tempi di partenza (quadranti) lato utente
|
*/

Route::prefix('quadranti')->name('quadranti.')->group(function () {
    // Vista principale del simulatore
    Route::get('/', [QuadrantiController::class, 'index'])->name('index');
    
    // Upload file Excel con nomi giocatori
    Route::post('/upload-excel', [QuadrantiController::class, 'uploadExcel'])->name('upload-excel');
    
    // API per ottenere dati effemeridi (alba/tramonto)
    Route::post('/coordinates', [QuadrantiController::class, 'getCoordinates'])->name('coordinates');
});
