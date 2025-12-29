<?php

use App\Http\Controllers\Admin\CareerHistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Career History Routes
|--------------------------------------------------------------------------
| Gestione storico carriera arbitri
|*/

Route::prefix('career-history')->name('career-history.')->group(function () {
    // Lista arbitri
    Route::get('/', [CareerHistoryController::class, 'index'])->name('index');

    // Archiviazione anno
    Route::get('/archive', [CareerHistoryController::class, 'archiveForm'])->name('archive-form');
    Route::post('/archive', [CareerHistoryController::class, 'processArchive'])->name('process-archive');
    Route::get('/preview-year', [CareerHistoryController::class, 'previewYear'])->name('preview-year');

    // Storico singolo arbitro
    Route::get('/{user}', [CareerHistoryController::class, 'show'])->name('show');
    Route::get('/{user}/year/{year}', [CareerHistoryController::class, 'editYear'])->name('edit-year');

    // Gestione tornei nello storico
    Route::post('/{user}/add-tournament', [CareerHistoryController::class, 'addTournament'])->name('add-tournament');
    Route::post('/{user}/add-multiple-tournaments', [CareerHistoryController::class, 'addMultipleTournaments'])->name('add-multiple-tournaments');
    Route::post('/{user}/remove-tournament', [CareerHistoryController::class, 'removeTournament'])->name('remove-tournament');
});
