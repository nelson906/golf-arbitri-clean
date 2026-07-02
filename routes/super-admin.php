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

    // Tournament Types
    Route::resource('tournament-types', TournamentTypeController::class);
    Route::patch('tournament-types/{tournamentType}/toggle-active', [TournamentTypeController::class, 'toggleActive'])
        ->name('tournament-types.toggle-active');

    // Institutional Emails
    Route::resource('institutional-emails', \App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class)
        ->except(['show']);  // Show view non necessaria per gestione email
    Route::patch('institutional-emails/{institutionalEmail}/toggle-active', [\App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class, 'toggleActive'])
        ->name('institutional-emails.toggle-active');
    Route::get('institutional-emails-export', [\App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class, 'export'])
        ->name('institutional-emails.export');

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
        Route::post('clauses/reorder', 'reorder')->name('clauses.reorder');
        Route::get('clauses/{clause}/preview', 'preview')->name('clauses.preview');
    });

    // ===== MODULAR SUPER ADMIN ROUTES =====
    require __DIR__.'/super-admin/monitoring.php';
});
