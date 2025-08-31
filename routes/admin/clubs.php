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
        Route::get('/', [App\Http\Controllers\Admin\ClubController::class, 'index'])
            ->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\ClubController::class, 'create'])
            ->name('create');
        Route::post('/', [App\Http\Controllers\Admin\ClubController::class, 'store'])
            ->name('store');
        Route::get('/{club}', [App\Http\Controllers\Admin\ClubController::class, 'show'])
            ->name('show');
        Route::get('/{club}/edit', [App\Http\Controllers\Admin\ClubController::class, 'edit'])
            ->name('edit');
        Route::put('/{club}', [App\Http\Controllers\Admin\ClubController::class, 'update'])
            ->name('update');
                Route::post('/{club}/deactivate', action: [ClubController::class, 'deactivate'])->name('deactivate');

        Route::delete('/{club}', [App\Http\Controllers\Admin\ClubController::class, 'destroy'])
            ->name('destroy');
        Route::patch('/{club}/toggle-active', [App\Http\Controllers\Admin\ClubController::class, 'toggleActive'])
            ->name('toggle-active');
        Route::get('/export', [App\Http\Controllers\Admin\ClubController::class, 'export'])
            ->name('export');
    });

