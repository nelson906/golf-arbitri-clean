<?php

use App\Http\Controllers\Admin\RefereeCareerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Referee Career Routes
|--------------------------------------------------------------------------
| Gestione curriculum e carriera arbitri
| Spostate da routes/web.php inline per organizzazione modulare
|*/

Route::prefix('referees')->name('referees.')->group(function () {
    Route::get('/curricula', [RefereeCareerController::class, 'curricula'])->name('curricula');
    Route::get('/{referee}/curriculum', [RefereeCareerController::class, 'curriculum'])->name('curriculum');
});
