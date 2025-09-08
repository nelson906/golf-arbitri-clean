<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TournamentTypeController;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('super-admin')->name('super-admin.')->middleware(['auth', 'super_admin'])->group(function () {
    
    // Users Management
    Route::get('/users', function() {
        return view('admin.placeholder', ['title' => 'Gestione Utenti Sistema']);
    })->name('users.index');
    
    // Tournament Types
    Route::resource('tournament-types', TournamentTypeController::class);
    Route::patch('tournament-types/{tournamentType}/toggle-active', [TournamentTypeController::class, 'toggleActive'])
        ->name('tournament-types.toggle-active');
    
    // Institutional Emails
    Route::resource('institutional-emails', \App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class);
    Route::patch('institutional-emails/{institutionalEmail}/toggle-active', [\App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class, 'toggleActive'])
        ->name('institutional-emails.toggle-active');
    Route::get('institutional-emails-export', [\App\Http\Controllers\SuperAdmin\InstitutionalEmailController::class, 'export'])
        ->name('institutional-emails.export');
    
    // Settings
    Route::get('/settings', function() {
        return view('admin.placeholder', ['title' => 'Impostazioni Sistema']);
    })->name('settings.index');
    
    // System Logs
    Route::get('/system/logs', function() {
        return view('admin.placeholder', ['title' => 'Logs Sistema']);
    })->name('system.logs');
});
