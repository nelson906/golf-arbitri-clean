<?php

use App\Http\Controllers\Admin\TournamentTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('super-admin')->name('super-admin.')->middleware(['auth', 'super_admin'])->group(function () {

    // Users Management — la gestione reale è quella unificata admin.users
    // (audit 2026-07: sostituito placeholder con redirect).
    Route::get('/users', fn () => redirect()->route('admin.users.index'))->name('users.index');

    // Zone (P18, 2026-10-03): elenco e modifica, nessuna cancellazione
    Route::resource('zones', \App\Http\Controllers\SuperAdmin\ZoneController::class)
        ->only(['index', 'edit', 'update']);

    // Caricamento completo comitati FIG (2026-10-05): riservato a un solo
    // account, controllo in FigImportController / App\Support\FigImportAccess
    Route::get('fig-import', [\App\Http\Controllers\SuperAdmin\FigImportController::class, 'index'])
        ->name('fig-import.index');
    Route::post('fig-import/block', [\App\Http\Controllers\SuperAdmin\FigImportController::class, 'block'])
        ->name('fig-import.block');

    // Tournament Types
    Route::resource('tournament-types', TournamentTypeController::class)->except(['show']);
    Route::patch('tournament-types/{tournamentType}/toggle-active', [TournamentTypeController::class, 'toggleActive'])
        ->name('tournament-types.toggle-active');

    // Institutional Emails
    Route::resource('institutional-emails', \App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class)
        ->except(['show']);  // Show view non necessaria per gestione email

    // NOTA (audit 2026-07): rimosso placeholder 'settings.index'
    // (view placeholder mai implementata; rimossi anche i link in navigation).

    // Notification Clauses
    Route::controller(\App\Http\Controllers\SuperAdmin\NotificationClauseController::class)->group(function () {
        Route::get('clauses', 'index')->name('clauses.index');
        Route::get('clauses/create', 'create')->name('clauses.create');
        Route::post('clauses', 'store')->name('clauses.store');
        Route::get('clauses/{clause}/edit', 'edit')->name('clauses.edit');
        Route::put('clauses/{clause}', 'update')->name('clauses.update');
        Route::delete('clauses/{clause}', 'destroy')->name('clauses.destroy');
        Route::post('clauses/{clause}/toggle-active', 'toggleActive')->name('clauses.toggle-active');
    });
});
