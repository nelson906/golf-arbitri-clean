<?php

use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\DashboardController;
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
Route::middleware(['auth'])->group(function () {
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
        Route::get('/', [TournamentController::class, 'index'])->name('index');
        Route::get('/calendar/view', [TournamentController::class, 'calendar'])->name('calendar');
        Route::get('/calendar/data', [TournamentController::class, 'calendarData'])->name('calendar-data');
        Route::get('/{tournament}', [TournamentController::class, 'show'])->name('show');
    });
    Route::get(uri: 'reports', action: function () {
        return view('admin.placeholder', ['title' => 'Reports']);
    })->name('reports.dashboard');
});


// Authentication routes (Laravel Breeze/standard)
require __DIR__ . '/auth.php';

// Super Admin routes
require __DIR__ . '/super-admin.php';

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES - Middleware: admin_or_superadmin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin_or_superadmin'])->group(function () {
    // Dashboard Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn() => redirect()->route('admin.dashboard'));

        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Communications routes
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [CommunicationController::class, 'index'])->name('index');
            Route::get('/create', [CommunicationController::class, 'create'])->name('create');
            Route::post('/', [CommunicationController::class, 'store'])->name('store');
            Route::get('/{communication}', [CommunicationController::class, 'show'])->name('show');
            Route::delete('/{communication}', [CommunicationController::class, 'destroy'])->name('destroy');
            Route::patch('/{communication}/publish', [CommunicationController::class, 'publish'])->name('publish');
            Route::patch('/{communication}/expire', [CommunicationController::class, 'expire'])->name('expire');
        });


        // Letter Templates Management
        Route::prefix('letter-templates')->name('letter-templates.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\LetterTemplateController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\LetterTemplateController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\LetterTemplateController::class, 'store'])->name('store');
            Route::get('/{template}', [App\Http\Controllers\Admin\LetterTemplateController::class, 'show'])->name('show');
            Route::get('/{template}/edit', [App\Http\Controllers\Admin\LetterTemplateController::class, 'edit'])->name('edit');
            Route::put('/{template}', [App\Http\Controllers\Admin\LetterTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [App\Http\Controllers\Admin\LetterTemplateController::class, 'destroy'])->name('destroy');
            Route::post('/{template}/duplicate', [App\Http\Controllers\Admin\LetterTemplateController::class, 'duplicate'])->name('duplicate');
            Route::get('/{template}/preview', [App\Http\Controllers\Admin\LetterTemplateController::class, 'preview'])->name('preview');
            Route::post('/{template}/toggle-active', [App\Http\Controllers\Admin\LetterTemplateController::class, 'toggleActive'])->name('toggle-active');
        });

        Route::get(uri: 'settings', action: function () {
            return view('admin.placeholder', ['title' => 'Settings']);
        })->name('settings');

        // Referee Career routes
        Route::prefix('referees')->name('referees.')->group(function () {
            Route::get('/curricula', [\App\Http\Controllers\Admin\RefereeCareerController::class, 'curricula'])->name('curricula');
            Route::get('/{referee}/curriculum', [\App\Http\Controllers\Admin\RefereeCareerController::class, 'curriculum'])->name('curriculum');
        });


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
        require __DIR__ . '/admin/letterheads.php';
        require __DIR__ . '/admin/clubs.php';
        require __DIR__ . '/admin/notifications.php';
        require __DIR__ . '/admin/reports.php';
        require __DIR__ . '/admin/documents.php';


    });
});

/*
|--------------------------------------------------------------------------
| USER ROUTES (ex REFEREE) - Middleware: auth
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // User routes
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/', fn(): RedirectResponse => redirect()->route('user.availability.index'));

        // Load modular user routes
        require __DIR__.'/user/availability.php';
        require __DIR__.'/user/quadranti.php';
        require __DIR__.'/user/curriculum.php';
        require __DIR__.'/user/documents.php';
    });
});

/*
|--------------------------------------------------------------------------
| REFEREE ROUTES - Middleware: referee_or_admin (legacy compatibility)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->group(function () {
    // Legacy referee routes - redirect to user routes
    Route::prefix('referee')->name('referee.')->group(function () {
        Route::get('/', fn(): RedirectResponse => redirect()->route('user.availability.index'));
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
