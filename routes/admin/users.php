<?php

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


// NOTA (audit 2026-07): rimossi i gruppi vuoti 'validation' e 'mass-communication'
// (closure senza route — scheletro mai implementato).
