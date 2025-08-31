<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin User Routes (SCHEMA UNIFICATO)
|--------------------------------------------------------------------------
| Gestione unificata di tutti gli utenti: Referees, Admins, Super Admins
| Sostituisce le vecchie routes separate admin/referees.php + admin/admins.php
*/
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index'])
            ->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\UserController::class, 'create'])
            ->name('create');
        Route::post('/', [App\Http\Controllers\Admin\UserController::class, 'store'])
            ->name('store');
        Route::get('/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])
            ->name('show');
        Route::get('/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])
            ->name('edit');
        Route::put('/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])
            ->name('update');
        Route::delete('/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])
            ->name('destroy');
        Route::patch('/{user}/toggle-active', [App\Http\Controllers\Admin\UserController::class, 'toggleActive'])
            ->name('toggle-active');
    });

    // Alias per retrocompatibilità
    Route::prefix('referees')->name('referees.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index'])
            ->name('index');
        Route::get('/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])
            ->name('show');
    });

Route::prefix('admins')->name('admins.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users.index', ['user_type' => 'admin,national_admin,super_admin']);
    })->name('index');

    Route::get('/zone-admins', function () {
        return redirect()->route('admin.users.index', ['user_type' => 'admin']);
    })->name('zone');

    Route::get('/national-admins', function () {
        return redirect()->route('admin.users.index', ['user_type' => 'national_admin']);
    })->name('national');
});

// User validation & verification
Route::prefix('validation')->name('validation.')->group(function () {
    Route::get('/pending', [UserController::class, 'pendingValidation'])->name('pending');
    Route::post('/approve/{user}', [UserController::class, 'approveUser'])->name('approve');
    Route::post('/reject/{user}', [UserController::class, 'rejectUser'])->name('reject');
    Route::get('/documents/{user}', [UserController::class, 'validationDocuments'])->name('documents');
});

// Mass communication to users
Route::prefix('mass-communication')->name('mass-communication.')->group(function () {
    Route::get('/', [UserController::class, 'massCommunication'])->name('index');
    Route::post('/send', [UserController::class, 'sendMassCommunication'])->name('send');
    Route::get('/templates', [UserController::class, 'communicationTemplates'])->name('templates');
});
