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

// NOTA (audit 2026-07, fix G2): rimossa la "Public Tournament API" senza auth.
// GET /api/tournaments/{id} caricava assignments.user esponendo email/telefono
// degli arbitri a chiunque. Endpoint mai referenziati da views/JS.
