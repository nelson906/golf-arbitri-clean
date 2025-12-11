<?php

use App\Http\Controllers\User\CommunicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Communications Routes
|--------------------------------------------------------------------------
| Visualizzazione comunicazioni per utenti/arbitri
|--------------------------------------------------------------------------
*/

Route::prefix('communications')->name('communications.')->group(function () {
    Route::get('/', [CommunicationController::class, 'index'])->name('index');
    Route::get('/{communication}', [CommunicationController::class, 'show'])->name('show');
});
