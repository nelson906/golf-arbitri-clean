<?php

use App\Http\Controllers\Admin\CommunicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Communications Routes
|--------------------------------------------------------------------------
| Gestione comunicazioni sistema per admin
| Spostate da routes/web.php inline per organizzazione modulare
|*/

Route::prefix('communications')->name('communications.')->group(function () {
    Route::get('/', [CommunicationController::class, 'index'])->name('index');
    Route::get('/create', [CommunicationController::class, 'create'])->name('create');
    Route::post('/', [CommunicationController::class, 'store'])->name('store');
    Route::get('/{communication}', [CommunicationController::class, 'show'])->name('show');
    Route::delete('/{communication}', [CommunicationController::class, 'destroy'])->name('destroy');
    Route::patch('/{communication}/publish', [CommunicationController::class, 'publish'])->name('publish');
    Route::patch('/{communication}/expire', [CommunicationController::class, 'expire'])->name('expire');
});
