<?php

use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\TournamentTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Tournament Routes
|--------------------------------------------------------------------------
| Gestione completa tornei per admin (Zone Admin, National Admin, Super Admin)
| Tutte le routes sono prefissate con 'admin.' nel name
|*/

Route::prefix('tournaments')->name('tournaments.')->group(function () {
    // Calendar View (must be defined before resource to avoid being caught by /{tournament})
    Route::get('/calendar', [TournamentController::class, 'calendar'])->name('calendar');

    // CRUD Base using resource
    Route::resource('/', TournamentController::class)->parameters(['' => 'tournament']);

    // Status Management
    Route::post('/{tournament}/status', [TournamentController::class, 'changeStatus'])->name('change-status');

    // Tournament-specific sub-routes
    Route::prefix('{tournament}')->group(function () {

        // Availability management (only index exists)
        Route::get('/availabilities', [TournamentController::class, 'availabilities'])->name('availabilities.index');

        // Notification form route
        Route::get('/assignment-form', [NotificationController::class, 'showAssignmentForm'])
            ->name('show-assignment-form');

        // Route per notifiche gare nazionali (senza allegati)
        Route::post('/send-national-notification', [NotificationController::class, 'sendNationalNotification'])
            ->name('send-national-notification');
    });
});

// Tournament notification routes - outside the tournament prefix
Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
    Route::post('/send-assignment-with-convocation', [NotificationController::class, 'sendAssignmentWithConvocation'])
        ->name('send-assignment-with-convocation');
});

// Tournament Types Management (fully functional)
Route::prefix('tournament-management')->name('tournament-management.')->group(function () {

    Route::resource('types', TournamentTypeController::class)->except(['show']);
    Route::post('types/{tournamentType}/toggle-active', [TournamentTypeController::class, 'toggleActive'])
        ->name('types.toggle-active');
});
