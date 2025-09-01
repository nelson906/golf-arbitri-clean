<?php

use App\Http\Controllers\User\AvailabilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Availability Routes
|--------------------------------------------------------------------------
|
| Routes per la gestione delle disponibilità lato utente (ex referee)
|
*/

Route::prefix('availability')->name('availability.')->group(function () {
    // Lista disponibilità dichiarate
    Route::get('/', [AvailabilityController::class, 'index'])->name('index');
    
    // Vista tornei per dichiarare disponibilità
    Route::get('/tournaments', [AvailabilityController::class, 'tournaments'])->name('tournaments');
    
    // Salva/rimuovi disponibilità singola
    Route::post('/store', [AvailabilityController::class, 'store'])->name('store');
    
    // Salva disponibilità batch
    Route::post('/save-batch', [AvailabilityController::class, 'saveBatch'])->name('saveBatch');
    
    // Vista calendario
    Route::get('/calendar', [AvailabilityController::class, 'calendar'])->name('calendar');
});
