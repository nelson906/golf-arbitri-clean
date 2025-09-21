<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Application;

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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
        Route::get('/calendar/data', [TournamentController::class, 'calendarData'])->name('calendar-data');
        Route::get('/{tournament}', [TournamentController::class, 'show'])->name('show');
    });

    // Reports placeholder
    Route::get('reports', function () {
        return view('admin.placeholder', ['title' => 'Reports']);
    })->name('reports.dashboard');
});

// Authentication routes
require __DIR__ . '/auth.php';

// Super Admin routes
require __DIR__ . '/super-admin.php';

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
        Route::get('/', fn() => redirect()->route('admin.dashboard'));

        // Dashboard admin (route diretta)
        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Quick stats API (route diretta)
        Route::get('/quick-stats', [App\Http\Controllers\Admin\DashboardController::class, 'quickStats'])->name('quick-stats');

        // Settings placeholder (route diretta)
        Route::get('settings', function () {
            return view('admin.placeholder', ['title' => 'Settings']);
        })->name('settings');

        // ===== MODULAR ADMIN ROUTES =====
        require __DIR__ . '/admin/tournaments.php';
        require __DIR__ . '/admin/referee-career.php';      // SPOSTATO da inline
        require __DIR__ . '/admin/users.php';
        require __DIR__ . '/admin/assignments.php';
        require __DIR__ . '/admin/dashboard.php';
        require __DIR__ . '/admin/statistics.php';           // RINOMINATO da statistic.php
        require __DIR__ . '/admin/monitoring.php';
        require __DIR__ . '/admin/clubs.php';
        require __DIR__ . '/admin/notifications.php';
        require __DIR__ . '/admin/reports.php';
        require __DIR__ . '/admin/documents.php';
        require __DIR__ . '/admin/communications.php';       // SPOSTATO da inline
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
        Route::get('/', fn(): RedirectResponse => redirect()->route('user.availability.index'));

        // ===== MODULAR USER ROUTES =====
        require __DIR__ . '/user/availability.php';
        require __DIR__ . '/user/quadranti.php';
        require __DIR__ . '/user/curriculum.php';
        require __DIR__ . '/user/documents.php';
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
        Route::get('/', fn(): RedirectResponse => redirect()->route('user.availability.index'));
    });
});

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
    require __DIR__ . '/api/internal.php';

    // Versioned API per integrazioni esterne
    Route::prefix('v1')->name('v1.')->group(function () {
        require __DIR__ . '/api/v1/tournaments.php';
        require __DIR__ . '/api/v1/notifications.php';
    });
});

/*
|--------------------------------------------------------------------------
| DEVELOPMENT ROUTES SECTION
|--------------------------------------------------------------------------
| Routes di debug e sviluppo - attive solo in ambiente local/staging
|--------------------------------------------------------------------------
*/
if (app()->environment(['local', 'staging'])) {
    Route::prefix('dev')->name('dev.')->group(function () {
        // Debug routes list
        Route::get('/routes', function () {
            $routeCollection = Route::getRoutes();
            $routes = [];
            foreach ($routeCollection as $route) {
                $routes[] = [
                    'method' => implode('|', $route->methods()),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->gatherMiddleware()
                ];
            }
            return response()->json($routes);
        })->name('routes');

        // Debug user types
        Route::get('/user-types', function () {
            return response()->json([
                'current_user' => auth()->user()?->only(['id', 'name', 'user_type', 'level']),
                'user_types' => [
                    'super_admin' => 'Super Admin',
                    'national_admin' => 'National Admin (CRC)',
                    'admin' => 'Zone Admin',
                    'referee' => 'Referee'
                ]
            ]);
        })->name('user-types');
    });
}
