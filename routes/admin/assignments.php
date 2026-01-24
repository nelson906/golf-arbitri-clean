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

    // Route statiche PRIMA delle route con parametri dinamici
    Route::get('/', [App\Http\Controllers\Admin\AssignmentController::class, 'index'])
        ->name('index');

    Route::get('/create', [App\Http\Controllers\Admin\AssignmentController::class, 'create'])
        ->name('create');

    Route::post('/', [App\Http\Controllers\Admin\AssignmentController::class, 'store'])
        ->name('store');

    Route::get('/export', [App\Http\Controllers\Admin\AssignmentController::class, 'export'])
        ->name('export');

    Route::post('/bulk-assign', [App\Http\Controllers\Admin\AssignmentController::class, 'bulkAssign'])
        ->name('bulk-assign');

    // Route con prefisso /tournament/ (statiche rispetto a /{assignment})
    Route::get('/tournament/{tournament}/assign-referees', [App\Http\Controllers\Admin\AssignmentController::class, 'assignReferees'])
        ->name('assign-referees');

    Route::post('/tournament/{tournament}/assign-referees', [App\Http\Controllers\Admin\AssignmentController::class, 'storeMultiple'])
        ->name('storeMultiple');

    Route::delete('/tournament/{tournament}/referee/{referee}', [App\Http\Controllers\Admin\AssignmentController::class, 'removeFromTournament'])
        ->name('removeFromTournament');

    // Route CRUD con parametro dinamico {assignment} - DEVONO essere DOPO le route statiche
    Route::get('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'show'])
        ->name('show');

    Route::get('/{assignment}/edit', [App\Http\Controllers\Admin\AssignmentController::class, 'edit'])
        ->name('edit');

    Route::put('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'update'])
        ->name('update');

    Route::delete('/{assignment}', [App\Http\Controllers\Admin\AssignmentController::class, 'destroy'])
        ->name('destroy');

    Route::post('/{assignment}/confirm', [App\Http\Controllers\Admin\AssignmentController::class, 'confirm'])
        ->name('confirm');

    Route::post('/{assignment}/cancel', [App\Http\Controllers\Admin\AssignmentController::class, 'cancel'])
        ->name('cancel');
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

