<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\User\FedergolfController;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CORE APPLICATION ROUTES
|--------------------------------------------------------------------------
| Routes principali dell'applicazione: home, dashboard, profilo, tornei
| Organizzazione: Core → Admin → User → API → Development
|--------------------------------------------------------------------------
*/

// ===== CORE ROUTES =====
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Dashboard principale con redirect intelligente per ruolo
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
});

// Profile management (tutti gli utenti autenticati)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Tournaments (pubblici per tutti gli utenti autenticati)
Route::middleware(['auth'])->group(function () {
    Route::prefix('tournaments')->name('tournaments.')->group(function () {
        Route::get('/', [TournamentController::class, 'index'])->name('index');
        Route::get('/calendar/view', [TournamentController::class, 'calendar'])->name('calendar');
        Route::get('/{tournament}', [TournamentController::class, 'show'])->name('show');
    });

    // Reports placeholder
    Route::get('reports', function () {
        return view('admin.placeholder', ['title' => 'Reports']);
    })->name('reports.dashboard');
});
Route::middleware(['auth'])->prefix('user/federgolf')->group(function () {
    Route::post('/load-all', [FedergolfController::class, 'loadAllCompetitions']);
    Route::post('/iscritti', [FedergolfController::class, 'getIscritti']);
});

// Authentication routes
require __DIR__.'/auth.php';

// Super Admin routes
require __DIR__.'/super-admin.php';

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES SECTION
|--------------------------------------------------------------------------
| Tutte le routes amministrative con middleware admin_or_superadmin
| Organizzate per moduli in files separati
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin_or_superadmin'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        // Redirect root admin a dashboard
        Route::get('/', fn () => redirect()->route('admin.dashboard'));

        // Dashboard admin (route diretta)
        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Quick stats API (route diretta)
        Route::get('/quick-stats', [App\Http\Controllers\Admin\DashboardController::class, 'quickStats'])->name('quick-stats');

        // Settings placeholder (route diretta)
        Route::get('settings', function () {
            return view('admin.placeholder', ['title' => 'Settings']);
        })->name('settings');

        // ===== MODULAR ADMIN ROUTES =====
        require __DIR__.'/admin/tournaments.php';
        require __DIR__.'/admin/referee-career.php';      // SPOSTATO da inline
        require __DIR__.'/admin/users.php';
        require __DIR__.'/admin/assignments.php';
        require __DIR__.'/admin/statistics.php';           // RINOMINATO da statistic.php
        // monitoring.php spostato in super-admin/monitoring.php
        require __DIR__.'/admin/clubs.php';
        require __DIR__.'/admin/notifications.php';
        // require __DIR__.'/admin/reports.php';
        require __DIR__.'/admin/documents.php';
        require __DIR__.'/admin/communications.php';       // SPOSTATO da inline
        require __DIR__.'/admin/career-history.php';       // Gestione storico carriera arbitri
    });
});

/*
|--------------------------------------------------------------------------
| USER ROUTES SECTION
|--------------------------------------------------------------------------
| Routes per utenti/arbitri con middleware auth standard
| Ex-referee routes migrati a user per unificazione
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::prefix('user')->name('user.')->group(function () {
        // Redirect root user a availability
        Route::get('/', fn (): RedirectResponse => redirect()->route('user.availability.index'));

        // ===== MODULAR USER ROUTES =====
        require __DIR__.'/user/availability.php';
        require __DIR__.'/user/quadranti.php';
        require __DIR__.'/user/curriculum.php';
        require __DIR__.'/user/documents.php';
        require __DIR__.'/user/communications.php';
    });
});

/*
|--------------------------------------------------------------------------
| LEGACY COMPATIBILITY SECTION
|--------------------------------------------------------------------------
| Redirect per backward compatibility referee → user routes
| Mantenuti per non rompere link esistenti
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->group(function () {
    Route::prefix('referee')->name('referee.')->group(function () {
        // Redirect legacy referee dashboard a user
        Route::get('/', fn (): RedirectResponse => redirect()->route('user.availability.index'));
    });
});

// Referee dashboard route (outside prefix to avoid /referee/referee/dashboard)
require __DIR__.'/referee/dashboard.php';

// Redirect admin referees a users con filtro
Route::redirect('/admin/referees', '/admin/users?user_type=referee');

/*
|--------------------------------------------------------------------------
| API ROUTES SECTION
|--------------------------------------------------------------------------
| API interne per AJAX e API versionate per integrazioni esterne
|--------------------------------------------------------------------------
*/
Route::prefix('api')->name('api.')->group(function () {
    // Internal API per chiamate AJAX
    require __DIR__.'/api/internal.php';

    // Versioned API per integrazioni esterne
    Route::prefix('v1')->name('v1.')->group(function () {
        require __DIR__.'/api/v1/tournaments.php';
        require __DIR__.'/api/v1/notifications.php';
    });
});

require __DIR__.'/dev/view-preview.php';
if (app()->environment(['local', 'staging'])) {
    require __DIR__.'/dev/view-preview.php';
    require __DIR__.'/dev/view-test-all.php'; // ⚠️ AGGIUNGI
}
require __DIR__.'/maintenance.php';
