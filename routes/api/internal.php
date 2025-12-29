<?php

/*
|--------------------------------------------------------------------------
| routes/api/internal.php - Internal AJAX API Routes
|--------------------------------------------------------------------------
| Routes per chiamate AJAX interne dell'applicazione
| Utilizzate da JavaScript frontend per operazioni dinamiche
*/

use Illuminate\Support\Facades\Route;

// Authentication required for all internal API
Route::middleware('auth')->group(function () {

    // Dashboard Data (inline closures - working)
    Route::prefix('dashboard')->group(function () {
        Route::get('/notifications/unread', function () {
            return response()->json(['count' => auth()->user()->unreadNotifications()->count()]);
        });
    });

    // Zone & System Data (inline closures - working)
    Route::prefix('system')->group(function () {
        Route::get('/zones', function () {
            return \App\Models\Zone::active()->get(['id', 'name', 'code']);
        });
        Route::get('/tournament-types', function () {
            return \App\Models\TournamentType::active()->get(['id', 'name', 'competence']);
        });
        Route::get('/current-user', function () {
            return auth()->user()->only(['id', 'name', 'user_type', 'level', 'zone_id']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Public Tournament API (no auth required for read-only)
|--------------------------------------------------------------------------
*/

Route::prefix('tournaments')->group(function () {

    // Public Read-Only Endpoints (inline closures - working)
    Route::get('/', function () {
        return \App\Models\Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('status', 'completed')
            ->where('start_date', '>=', now()->subMonths(6))
            ->paginate(20);
    });

    Route::get('/{tournament}', function (\App\Models\Tournament $tournament) {
        if (! in_array($tournament->status, ['completed', 'assigned'])) {
            abort(404);
        }

        return $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);
    });

    // Statistics (Public - inline closure)
    Route::get('/stats/summary', function () {
        $currentYear = now()->year;

        return response()->json([
            'tournaments_completed' => \App\Models\Tournament::where('status', 'completed')
                ->whereYear('start_date', $currentYear)->count(),
            'total_assignments' => \App\Models\Assignment::whereHas('tournament', function ($q) use ($currentYear) {
                $q->whereYear('start_date', $currentYear);
            })->count(),
            'zones_active' => \App\Models\Zone::active()->count(),
        ]);
    });
});
