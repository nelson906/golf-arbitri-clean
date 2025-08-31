<?php

/*
|--------------------------------------------------------------------------
| routes/admin/clubs.php - Club Management Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\ClubController;
use Illuminate\Support\Facades\Route;

// CLUBS MANAGEMENT
Route::prefix('clubs')->name('clubs.')->group(function () {

    // CRUD Base
    Route::get('/', [ClubController::class, 'index'])->name('index');
    Route::get('/create', [ClubController::class, 'create'])->name('create');
    Route::post('/', [ClubController::class, 'store'])->name('store');
    Route::get('/{club}', [ClubController::class, 'show'])->name('show');
    Route::get('/{club}/edit', [ClubController::class, 'edit'])->name('edit');
    Route::put('/{club}', [ClubController::class, 'update'])->name('update');
    Route::delete('/{club}', [ClubController::class, 'destroy'])->name('destroy');

    // Status Management
    Route::post('/{club}/toggle-active', [ClubController::class, 'toggleActive'])->name('toggle-active');
    Route::post('/{club}/deactivate', action: [ClubController::class, 'deactivate'])->name('deactivate');

    // Bulk Operations
    Route::post('/bulk-action', [ClubController::class, 'bulkAction'])->name('bulk-action');

    // Export/Import
    Route::get('/export', [ClubController::class, 'export'])->name('export');
    Route::post('/import', [ClubController::class, 'import'])->name('import');
    Route::get('/import-template', [ClubController::class, 'importTemplate'])->name('import-template');

    // Club-specific sub-routes
    Route::prefix('{club}')->group(function () {
        Route::get('/tournaments', [ClubController::class, 'tournaments'])->name('tournaments');
        Route::get('/statistics', [ClubController::class, 'statistics'])->name('statistics');
        Route::get('/communications', [ClubController::class, 'communications'])->name('communications');
        Route::post('/contact', [ClubController::class, 'contact'])->name('contact');
    });

    // Search & Filter
    Route::get('/search', [ClubController::class, 'search'])->name('search');
    Route::get('/filter-options', [ClubController::class, 'filterOptions'])->name('filter-options');
});

