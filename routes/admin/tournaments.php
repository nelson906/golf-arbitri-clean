<?php

use App\Http\Controllers\Admin\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Tournament Routes
|--------------------------------------------------------------------------
| Gestione completa tornei per admin (Zone Admin, National Admin, Super Admin)
| Tutte le routes sono prefissate con 'admin.' nel name
|*/
Route::prefix('tournaments')->name('tournaments.')->group(function () {
    // CRUD Base using resource
    Route::resource('/', TournamentController::class)->parameters(['' => 'tournament']);

    // Status Management
    Route::post('/{tournament}/status', [TournamentController::class, 'changeStatus'])->name('change-status');

    // Calendar Views - removed, using the unified calendar in web.php

    // Bulk Operations
    Route::post('/bulk-action', [TournamentController::class, 'bulkAction'])->name('bulk-action');

    // Export/Import
    Route::get('/export', [TournamentController::class, 'export'])->name('export');
    Route::post('/import', [TournamentController::class, 'import'])->name('import');

    // Tournament-specific sub-routes
    Route::prefix('{tournament}')->group(function () {

        // Assignment management (nested in tournament context)
        Route::prefix('assignments')->name('assignments.')->group(function () {
            Route::get('/', [TournamentController::class, 'assignments'])->name('index');
            Route::get('/create', [TournamentController::class, 'createAssignment'])->name('create');
            Route::post('/', [TournamentController::class, 'storeAssignment'])->name('store');
            Route::delete('/{assignment}', [TournamentController::class, 'destroyAssignment'])->name('destroy');
            Route::post('/bulk-assign', [TournamentController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/auto-assign', [TournamentController::class, 'autoAssign'])->name('auto-assign');
        });

        // Availability management (nested in tournament context)
        Route::prefix('availabilities')->name('availabilities.')->group(function () {
            Route::get('/', [TournamentController::class, 'availabilities'])->name('index');
            Route::get('/export', [TournamentController::class, 'exportAvailabilities'])->name('export');
            Route::post('/remind', [TournamentController::class, 'sendAvailabilityReminder'])->name('remind');
        });

        // Communication management (nested in tournament context)
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [TournamentController::class, 'communications'])->name('index');
            Route::get('/create', [TournamentController::class, 'createCommunication'])->name('create');
            Route::post('/', [TournamentController::class, 'storeCommunication'])->name('store');
            Route::post('/send-assignment-letters', [TournamentController::class, 'sendAssignmentLetters'])
                ->name('send-assignment-letters');
        });
        
        // Notification form route
        Route::get('/assignment-form', [\App\Http\Controllers\Admin\NotificationController::class, 'showAssignmentForm'])
            ->name('show-assignment-form');

        // Documents & Reports (nested in tournament context)
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [TournamentController::class, 'documents'])->name('index');
            Route::get('/assignment-letter', [TournamentController::class, 'generateAssignmentLetter'])
                ->name('assignment-letter');
            Route::get('/convocation', [TournamentController::class, 'generateConvocation'])
                ->name('convocation');
            Route::get('/referee-list', [TournamentController::class, 'generateRefereeList'])
                ->name('referee-list');
        });

        // Clone/Duplicate functionality
        Route::post('/clone', [TournamentController::class, 'clone'])->name('clone');

        // History & Log
        Route::get('/history', [TournamentController::class, 'history'])->name('history');
    });

    // Search & Filter APIs
    Route::get('/search', [TournamentController::class, 'search'])->name('search');
    Route::get('/filter-options', [TournamentController::class, 'filterOptions'])->name('filter-options');

    // Statistics & Analytics
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [TournamentController::class, 'analytics'])->name('index');
        Route::get('/completion-rate', [TournamentController::class, 'completionRate'])->name('completion-rate');
        Route::get('/assignment-stats', [TournamentController::class, 'assignmentStats'])->name('assignment-stats');
        Route::get('/zone-distribution', [TournamentController::class, 'zoneDistribution'])->name('zone-distribution');
    });
});

// Tournament notification routes - outside the tournament prefix to avoid double tournament parameter
Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
    Route::post('/send-assignment', [\App\Http\Controllers\Admin\NotificationController::class, 'sendTournamentAssignment'])
        ->name('send-assignment');
    Route::post('/send-assignment-with-convocation', [\App\Http\Controllers\Admin\NotificationController::class, 'sendAssignmentWithConvocation'])
        ->name('send-assignment-with-convocation');
});

// Additional utility routes outside the tournaments prefix
Route::prefix('tournament-management')->name('tournament-management.')->group(function () {

    // Tournament Types Management
    Route::resource('types', App\Http\Controllers\Admin\TournamentTypeController::class)
        ->except(['show']);
    Route::post('types/{tournamentType}/toggle-active',
        [App\Http\Controllers\Admin\TournamentTypeController::class, 'toggleActive'])
        ->name('types.toggle-active');

    // Template Management for recurring tournaments
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TournamentController::class, 'templates'])->name('index');
        Route::get('/create', [TournamentController::class, 'createTemplate'])->name('create');
        Route::post('/', [TournamentController::class, 'storeTemplate'])->name('store');
        Route::post('/{template}/use', [TournamentController::class, 'useTemplate'])->name('use');
        Route::delete('/{template}', [TournamentController::class, 'destroyTemplate'])->name('destroy');
    });

    // Mass operations
    Route::prefix('mass')->name('mass.')->group(function () {
        Route::get('/', [TournamentController::class, 'massOperations'])->name('index');
        Route::post('/status-change', [TournamentController::class, 'massStatusChange'])->name('status-change');
        Route::post('/assign-referees', [TournamentController::class, 'massAssignReferees'])->name('assign-referees');
        Route::post('/send-notifications', [TournamentController::class, 'massSendNotifications'])
            ->name('send-notifications');
    });

    // Archive management
    Route::prefix('archive')->name('archive.')->group(function () {
        Route::get('/', [TournamentController::class, 'archive'])->name('index');
        Route::post('/tournaments', [TournamentController::class, 'archiveTournaments'])->name('tournaments');
        Route::post('/restore/{tournament}', [TournamentController::class, 'restore'])->name('restore');
    });
});
