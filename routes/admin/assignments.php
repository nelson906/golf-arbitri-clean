<?php

use App\Http\Controllers\Admin\AssignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Assignment Routes
|--------------------------------------------------------------------------
| Gestione completa assegnazioni arbitri ai tornei
| Separate dalle tournament routes per maggiore modularità
*/
Route::resource('assignments', AssignmentController::class);

Route::prefix('assignments')->name('assignments.')->group(function () {

    // CRUD Base
    Route::get('/', [AssignmentController::class, 'index'])->name('index');
    Route::get('/create', [AssignmentController::class, 'create'])->name('create');
    Route::post('/', [AssignmentController::class, 'store'])->name('store');
    Route::get('/{assignment}', [AssignmentController::class, 'show'])->name('show');
    Route::get('/{assignment}/edit', [AssignmentController::class, 'edit'])->name('edit');
    Route::get('/{tournament}/assign', [AssignmentController::class, 'assignReferees'])->name('assign-referees');
    Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('update');
    Route::delete('/{assignment}', [AssignmentController::class, 'destroy'])->name('destroy');

    // Status Management
    Route::post('/{assignment}/confirm', [AssignmentController::class, 'confirm'])->name('confirm');
    Route::post('/{assignment}/unconfirm', [AssignmentController::class, 'unconfirm'])->name('unconfirm');

    // Bulk Operations
    Route::post('/bulk-assign', [AssignmentController::class, 'bulkAssign'])->name('bulk-assign');
    Route::post('/bulk-confirm', [AssignmentController::class, 'bulkConfirm'])->name('bulk-confirm');
    Route::post('/bulk-delete', [AssignmentController::class, 'bulkDelete'])->name('bulk-delete');
    Route::post('/bulk-action', [AssignmentController::class, 'bulkAction'])->name('bulk-action');

    // Auto-Assignment Features
    Route::prefix('auto')->name('auto.')->group(function () {
        Route::get('/setup/{tournament}', [AssignmentController::class, 'autoAssignSetup'])->name('setup');
        Route::post('/execute/{tournament}', [AssignmentController::class, 'autoAssign'])->name('execute');
        Route::get('/preview/{tournament}', [AssignmentController::class, 'autoAssignPreview'])->name('preview');
        Route::post('/batch-tournaments', [AssignmentController::class, 'batchAutoAssign'])->name('batch');
    });

    // Advanced Assignment Tools
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/', [AssignmentController::class, 'tools'])->name('index');
        Route::get('/availability-match', [AssignmentController::class, 'availabilityMatch'])->name('availability-match');
        Route::get('/referee-workload', [AssignmentController::class, 'refereeWorkload'])->name('referee-workload');
        Route::get('/conflict-detector', [AssignmentController::class, 'conflictDetector'])->name('conflict-detector');
        Route::get('/optimal-assignment', [AssignmentController::class, 'optimalAssignment'])->name('optimal-assignment');
    });

    // Export/Import
    Route::get('/export', [AssignmentController::class, 'export'])->name('export');
    Route::post('/import', [AssignmentController::class, 'import'])->name('import');
    Route::get('/export-template', [AssignmentController::class, 'exportTemplate'])->name('export-template');

    // Calendar & Schedule Views
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/', [AssignmentController::class, 'calendar'])->name('index');
        Route::get('/referee/{user}', [AssignmentController::class, 'refereeCalendar'])->name('referee');
        Route::get('/tournament/{tournament}', [AssignmentController::class, 'tournamentCalendar'])->name('tournament');
        Route::get('/zone/{zone}', [AssignmentController::class, 'zoneCalendar'])->name('zone');
        Route::get('/data', [AssignmentController::class, 'calendarData'])->name('data');
    });

    // Statistics & Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AssignmentController::class, 'reports'])->name('index');
        Route::get('/by-referee', [AssignmentController::class, 'reportByReferee'])->name('by-referee');
        Route::get('/by-tournament', [AssignmentController::class, 'reportByTournament'])->name('by-tournament');
        Route::get('/by-zone', [AssignmentController::class, 'reportByZone'])->name('by-zone');
        Route::get('/by-role', [AssignmentController::class, 'reportByRole'])->name('by-role');
        Route::get('/confirmation-rate', [AssignmentController::class, 'confirmationRate'])->name('confirmation-rate');
        Route::get('/workload-distribution', [AssignmentController::class, 'workloadDistribution'])->name('workload-distribution');
    });

    // Communication related to assignments
    Route::prefix('communications')->name('communications.')->group(function () {
        Route::get('/', [AssignmentController::class, 'communications'])->name('index');
        Route::post('/notify-assignments', [AssignmentController::class, 'notifyAssignments'])->name('notify');
        Route::post('/send-reminders', [AssignmentController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/request-confirmation', [AssignmentController::class, 'requestConfirmation'])->name('request-confirmation');

        // Assignment-specific notifications
        Route::post('/{assignment}/notify', [AssignmentController::class, 'notifyAssignment'])->name('notify-single');
        Route::post('/{assignment}/resend-notification', [AssignmentController::class, 'resendNotification'])
            ->name('resend-notification');
    });

    // Document Generation for assignments
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/{assignment}/letter', [AssignmentController::class, 'assignmentLetter'])->name('letter');
        Route::get('/{assignment}/certificate', [AssignmentController::class, 'assignmentCertificate'])->name('certificate');
        Route::get('/batch-letters', [AssignmentController::class, 'batchAssignmentLetters'])->name('batch-letters');
        Route::get('/tournament/{tournament}/all-letters', [AssignmentController::class, 'tournamentAllLetters'])
            ->name('tournament-all-letters');
    });

    // Search & Filter
    Route::get('/search', [AssignmentController::class, 'search'])->name('search');
    Route::get('/filter-options', [AssignmentController::class, 'filterOptions'])->name('filter-options');
    Route::get('/referee-suggestions', [AssignmentController::class, 'refereeSuggestions'])->name('referee-suggestions');
});

// Assignment Validation & Quality Control
Route::prefix('assignment-validation')->name('assignment-validation.')->group(function () {
    Route::get('/', [AssignmentController::class, 'validation'])->name('index');
    Route::get('/conflicts', [AssignmentController::class, 'validationConflicts'])->name('conflicts');
    Route::get('/missing-requirements', [AssignmentController::class, 'missingRequirements'])->name('missing-requirements');
    Route::get('/overassigned-referees', [AssignmentController::class, 'overassignedReferees'])->name('overassigned');
    Route::get('/underassigned-referees', [AssignmentController::class, 'underassignedReferees'])->name('underassigned');
    Route::post('/fix-conflicts', [AssignmentController::class, 'fixConflicts'])->name('fix-conflicts');
});

// Historical data and archive
Route::prefix('history')->name('history.')->group(function () {
    Route::get('/', [AssignmentController::class, 'history'])->name('index');
    Route::get('/referee/{user}', [AssignmentController::class, 'refereeHistory'])->name('referee');
    Route::get('/tournament/{tournament}', [AssignmentController::class, 'tournamentHistory'])->name('tournament');
    Route::get('/changes', [AssignmentController::class, 'changeHistory'])->name('changes');
    Route::get('/export-history', [AssignmentController::class, 'exportHistory'])->name('export');
});

// Assignment Templates for recurring patterns
Route::prefix('templates')->name('templates.')->group(function () {
    Route::get('/', [AssignmentController::class, 'templates'])->name('index');
    Route::get('/create', [AssignmentController::class, 'createTemplate'])->name('create');
    Route::post('/', [AssignmentController::class, 'storeTemplate'])->name('store');
    Route::post('/{template}/apply', [AssignmentController::class, 'applyTemplate'])->name('apply');
    Route::delete('/{template}', [AssignmentController::class, 'destroyTemplate'])->name('destroy');
});
