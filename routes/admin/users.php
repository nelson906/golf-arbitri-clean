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
Route::resource('users', controller: UserController::class);
Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

Route::prefix('users')->name('users.')->group(function () {

    // CRUD Base
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

    // User Status Management
    Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
    Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
    Route::post('/{user}/resend-verification', [UserController::class, 'resendVerification'])
        ->name('resend-verification');

    // Bulk Operations
    Route::post('/bulk-action', [UserController::class, 'bulkAction'])->name('bulk-action');
    Route::post('/bulk-import', [UserController::class, 'bulkImport'])->name('bulk-import');

    // Export/Import
    Route::get('/export', [UserController::class, 'export'])->name('export');
    Route::get('/import-template', [UserController::class, 'importTemplate'])->name('import-template');

    // User-specific sub-routes
    Route::prefix('{user}')->group(function () {

        // Profile & Career (especially for referees)
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [UserController::class, 'profile'])->name('show');
            Route::get('/edit', [UserController::class, 'editProfile'])->name('edit');
            Route::put('/', [UserController::class, 'updateProfile'])->name('update');
            Route::post('/photo', [UserController::class, 'updatePhoto'])->name('photo');
        });

        // Career History (for referees)
        Route::prefix('career')->name('career.')->group(function () {
            Route::get('/', [UserController::class, 'career'])->name('show');
            Route::get('/assignments', [UserController::class, 'careerAssignments'])->name('assignments');
            Route::get('/availabilities', [UserController::class, 'careerAvailabilities'])->name('availabilities');
            Route::get('/statistics', [UserController::class, 'careerStatistics'])->name('statistics');
            Route::get('/export', [UserController::class, 'exportCareer'])->name('export');

            // Level progression tracking
            Route::get('/levels', [UserController::class, 'levelHistory'])->name('levels');
            Route::post('/levels', [UserController::class, 'addLevelChange'])->name('add-level');
        });

        // Communication History
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [UserController::class, 'communications'])->name('index');
            Route::get('/sent', [UserController::class, 'sentCommunications'])->name('sent');
            Route::get('/received', [UserController::class, 'receivedCommunications'])->name('received');
        });

        // User impersonation (Super Admin only)
        Route::post('/impersonate', [UserController::class, 'impersonate'])
            ->name('impersonate')
            ->middleware('super_admin');
    });

    // Search & Filter
    Route::get('/search', [UserController::class, 'search'])->name('search');
    Route::get('/filter-options', [UserController::class, 'filterOptions'])->name('filter-options');

    // Referee-specific mass operations
    Route::prefix('referee-management')->name('referee-management.')->group(function () {
        Route::get('/', [UserController::class, 'refereeManagement'])->name('index');
        Route::post('/mass-level-change', [UserController::class, 'massLevelChange'])->name('mass-level-change');
        Route::post('/mass-zone-transfer', [UserController::class, 'massZoneTransfer'])->name('mass-zone-transfer');
        Route::post('/mass-archive', [UserController::class, 'massArchive'])->name('mass-archive');
        Route::get('/qualification-report', [UserController::class, 'qualificationReport'])
            ->name('qualification-report');
    });

    // Statistics & Analytics
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [UserController::class, 'analytics'])->name('index');
        Route::get('/activity', [UserController::class, 'activityAnalytics'])->name('activity');
        Route::get('/distribution', [UserController::class, 'distributionAnalytics'])->name('distribution');
        Route::get('/performance', [UserController::class, 'performanceAnalytics'])->name('performance');
        Route::get('/level-progression', [UserController::class, 'levelProgression'])->name('level-progression');
    });
});

// Role-specific shortcut routes
Route::prefix('referees')->name('referees.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users.index', ['user_type' => 'referee']);
    })->name('index');

    Route::get('/active', function () {
        return redirect()->route('admin.users.index', ['user_type' => 'referee', 'is_active' => 1]);
    })->name('active');

    Route::get('/by-level/{level}', function ($level) {
        return redirect()->route('admin.users.index', ['user_type' => 'referee', 'level' => $level]);
    })->name('by-level');

    Route::get('/by-zone/{zone}', function ($zone) {
        return redirect()->route('admin.users.index', ['user_type' => 'referee', 'zone_id' => $zone]);
    })->name('by-zone');
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
