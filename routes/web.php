<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - CORE MINIMAL
|--------------------------------------------------------------------------
|
| Core routes minime + redirect intelligenti basati sui ruoli.
| Le routes specifiche sono in moduli separati in routes/admin/ e routes/referee/
|
*/

// Public routes
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Dashboard principale - redirect intelligente basato su ruolo
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Profile management (tutti gli utenti)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::prefix('tournaments')->name('tournaments.')->group(function () {
        Route::get('tournaments', function () {
            return view('admin.placeholder', ['title' => 'Tournaments']);
        })->name('index');
        Route::get('/calendar/view', [TournamentController::class, 'calendar'])->name('calendar');
        Route::get('/calendar/data', [TournamentController::class, 'calendarData'])->name('calendar-data');
    });
    Route::get(uri: 'reports', action: function () {
        return view('admin.placeholder', ['title' => 'Reports']);
    })->name('reports.dashboard');
});


// Authentication routes (Laravel Breeze/standard)
require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES - Middleware: admin_or_superadmin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin_or_superadmin'])->group(function () {
    // Dashboard Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });
        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        // Placeholder routes
        // Route::get('tournaments', function () {
        //     return view('admin.placeholder', ['title' => 'Tournaments']);
        // })->name('tournaments.index');

        Route::get('tournaments/create', function () {
            return view('admin.placeholder', ['title' => 'Create Tournament']);
        })->name('tournaments.create');

        // Route::get('assignments', action: function () {
        //     return view('admin.placeholder', ['title' => 'Assignments']);
        // })->name('assignments.index');

        Route::get(uri: 'communications', action: function () {
            return view('admin.placeholder', ['title' => 'Communications']);
        })->name('communications.index');

        Route::get('letterheads', action: function () {
            return view('admin.placeholder', ['title' => 'Letterheads']);
        })->name('letterheads.index');

        // Route::get('clubs', action: function () {
        //     return view('admin.placeholder', ['title' => 'Clubs']);
        // })->name('clubs.index');

        Route::get(uri: 'letter-templates', action: function () {
            return view('admin.placeholder', ['title' => 'Templates']);
        })->name('letter-templates.index');

        Route::get(uri: 'tournament-notifications', action: function () {
            return view('admin.placeholder', ['title' => 'Tournament-notifications']);
        })->name('tournament-notifications.index');

        Route::get(uri: 'documents', action: function () {
            return view('admin.placeholder', ['title' => 'Documents']);
        })->name('documents.index');

        Route::get(uri: 'settings', action: function () {
            return view('admin.placeholder', ['title' => 'Settings']);
        })->name('settings');



        // Quick stats API
        Route::get('/quick-stats', [App\Http\Controllers\Admin\DashboardController::class, 'quickStats'])
            ->name('quick-stats');

        // Load modular admin routes
        require __DIR__ . '/admin/tournaments.php';
        require __DIR__ . '/admin/users.php';
        require __DIR__ . '/admin/assignments.php';
        require __DIR__ . '/admin/dashboard.php';
        require __DIR__ . '/admin/statistic.php';
        require __DIR__ . '/admin/monitoring.php';
        require __DIR__ . '/admin/clubs.php';
        // require __DIR__.'/admin/notifications.php';
        require __DIR__ . '/admin/reports.php';
        // require __DIR__.'/referee/dashboard.php';   // Se non esiste

    });
});

/*
|--------------------------------------------------------------------------
| REFEREE ROUTES - Middleware: referee_or_admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->group(function () {
    // Dashboard Referee
    Route::prefix('referee')->name('referee.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('referee.dashboard');
        });
    });

    // Load modular referee routes
    // require __DIR__.'/referee/dashboard.php';
    // require __DIR__.'/referee/availability.php';
    // require __DIR__.'/referee/tournaments.php';
});

/*
|--------------------------------------------------------------------------
| API ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('api')->name('api.')->group(function () {
    // Internal API (for AJAX calls)
    require __DIR__ . '/api/internal.php';

    // Versioned API
    Route::prefix('v1')->name('v1.')->group(function () {
        require __DIR__ . '/api/v1/tournaments.php';
        require __DIR__ . '/api/v1/notifications.php';
    });
});

/*
|--------------------------------------------------------------------------
| DEVELOPMENT/DEBUG ROUTES - Solo in ambiente di sviluppo
|--------------------------------------------------------------------------
*/
if (app()->environment(['local', 'staging'])) {
    Route::prefix('dev')->name('dev.')->group(function () {
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


// Referee routes compatibility - redirect to users
Route::redirect('/admin/referees', '/admin/users?user_type=referee');
