<?php

use App\Http\Controllers\Admin\AssignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Assignment Routes
|--------------------------------------------------------------------------
| Gestione completa assegnazioni arbitri ai tornei
| Separate dalle tournament routes per maggiore modularità
|*/
// Route::resource('assignments', AssignmentController::class); // Commentato perché le route sono definite manualmente sotto

Route::prefix('assignments')->name('assignments.')->group(function () {

        // CRUD standard
        Route::get('/', [App\Http\Controllers\Admin\AssignmentController::class, 'index'])
            ->name('index');

        Route::get('/create', [App\Http\Controllers\Admin\AssignmentController::class, 'create'])
            ->name('create');

        Route::post('/', [App\Http\Controllers\Admin\AssignmentController::class, 'store'])
            ->name('store');

        Route::get('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'show'])
            ->name('show');

        Route::get('/{assignment}/edit', [App\Http\Controllers\Admin\AssignmentController::class, 'edit'])
            ->name('edit');

        Route::put('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'update'])
            ->name('update');

        Route::delete('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'destroy'])
            ->name('destroy');

        // Route speciali per assegnazione arbitri
        Route::get('/tournament/{tournament}/assign-referees',
            [App\Http\Controllers\Admin\AssignmentController::class, 'assignReferees'])
            ->name('assign-referees');

        Route::post('/tournament/{tournament}/assign-referees',
            [App\Http\Controllers\Admin\AssignmentController::class, 'storeMultiple'])
            ->name('storeMultiple');

        Route::delete('/tournament/{tournament}/referee/{referee}',
            [App\Http\Controllers\Admin\AssignmentController::class, 'removeFromTournament'])
            ->name('removeFromTournament');

        // Azioni aggiuntive
        Route::post('/{assignment}/confirm',
            [App\Http\Controllers\Admin\AssignmentController::class, 'confirm'])
            ->name('confirm');

        Route::post('/{assignment}/cancel',
            [App\Http\Controllers\Admin\AssignmentController::class, 'cancel'])
            ->name('cancel');

        Route::post('/bulk-assign',
            [App\Http\Controllers\Admin\AssignmentController::class, 'bulkAssign'])
            ->name('bulk-assign');

        Route::get('/export',
            [App\Http\Controllers\Admin\AssignmentController::class, 'export'])
            ->name('export');
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
