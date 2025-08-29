<?php

/*
|--------------------------------------------------------------------------
| routes/api/internal.php - Internal AJAX API Routes
|--------------------------------------------------------------------------
| Routes per chiamate AJAX interne dell'applicazione
| Utilizzate da JavaScript frontend per operazioni dinamiche
*/

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\ClubController;
use App\Http\Controllers\Admin\AssignmentController;
use Illuminate\Support\Facades\Route;

// Authentication required for all internal API
Route::middleware('auth')->group(function () {

    // User/Referee Search & Autocomplete
    Route::prefix('users')->group(function () {
        Route::get('/search', [UserController::class, 'search']);
        Route::get('/referees/available/{tournament}', [UserController::class, 'availableReferees']);
        Route::get('/referees/by-level/{level}', [UserController::class, 'refereesByLevel']);
        Route::get('/referees/workload', [UserController::class, 'refereeWorkload']);
    });

    // Tournament Search & Data
    Route::prefix('tournaments')->group(function () {
        Route::get('/search', [TournamentController::class, 'search']);
        Route::get('/{tournament}/available-referees', [TournamentController::class, 'availableReferees']);
        Route::get('/{tournament}/assignments-data', [TournamentController::class, 'assignmentsData']);
        Route::get('/{tournament}/calendar-event', [TournamentController::class, 'calendarEvent']);
        Route::get('/upcoming', [TournamentController::class, 'upcomingTournaments']);
    });

    // Club Search & Data
    Route::prefix('clubs')->group(function () {
        Route::get('/search', [ClubController::class, 'search']);
        Route::get('/by-zone/{zone}', [ClubController::class, 'clubsByZone']);
    });

    // Assignment Tools
    Route::prefix('assignments')->group(function () {
        Route::get('/conflicts/{tournament}', [AssignmentController::class, 'checkConflicts']);
        Route::post('/preview-auto-assign/{tournament}', [AssignmentController::class, 'previewAutoAssign']);
        Route::get('/referee-suggestions', [AssignmentController::class, 'refereeSuggestions']);
    });

    // Dashboard Data
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats/{type}', function($type) {
            $controller = auth()->user()->is_referee ?
                'App\Http\Controllers\Referee\DashboardController' :
                'App\Http\Controllers\Admin\DashboardController';
            return app($controller)->quickStats(request()->merge(['type' => $type]));
        });
        Route::get('/notifications/unread', function() {
            return response()->json(['count' => auth()->user()->unreadNotifications()->count()]);
        });
    });

    // Zone & System Data
    Route::prefix('system')->group(function () {
        Route::get('/zones', function() {
            return \App\Models\Zone::active()->get(['id', 'name', 'code']);
        });
        Route::get('/tournament-types', function() {
            return \App\Models\TournamentType::active()->get(['id', 'name', 'competence']);
        });
        Route::get('/current-user', function() {
            return auth()->user()->only(['id', 'name', 'user_type', 'level', 'zone_id']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| routes/api/v1/tournaments.php - External Tournament API
|--------------------------------------------------------------------------
| API pubblica per accesso esterno ai dati dei tornei
*/

// Public Tournament API (no auth required for read-only)
Route::prefix('tournaments')->group(function () {

    // Public Read-Only Endpoints
    Route::get('/', function() {
        return \App\Models\Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('status', 'completed')
            ->where('start_date', '>=', now()->subMonths(6))
            ->paginate(20);
    });

    Route::get('/{tournament}', function(\App\Models\Tournament $tournament) {
        if (!in_array($tournament->status, ['completed', 'assigned'])) {
            abort(404);
        }
        return $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);
    });

    // Calendar Integration
    Route::get('/calendar/ical', [\App\Http\Controllers\Api\TournamentApiController::class, 'icalFeed']);
    Route::get('/calendar/json', [\App\Http\Controllers\Api\TournamentApiController::class, 'jsonCalendar']);

    // Statistics (Public)
    Route::get('/stats/summary', function() {
        $currentYear = now()->year;
        return response()->json([
            'tournaments_completed' => \App\Models\Tournament::where('status', 'completed')
                ->whereYear('start_date', $currentYear)->count(),
            'total_assignments' => \App\Models\Assignment::whereHas('tournament', function($q) use ($currentYear) {
                $q->whereYear('start_date', $currentYear);
            })->count(),
            'zones_active' => \App\Models\Zone::active()->count()
        ]);
    });
});

// Authenticated Tournament API
Route::middleware(['auth:sanctum'])->prefix('tournaments')->group(function () {

    // Referee-specific endpoints
    Route::middleware(['referee_or_admin'])->group(function () {
        Route::get('/my-assignments', [\App\Http\Controllers\Api\TournamentApiController::class, 'myAssignments']);
        Route::get('/my-availabilities', [\App\Http\Controllers\Api\TournamentApiController::class, 'myAvailabilities']);
        Route::post('/{tournament}/availability', [\App\Http\Controllers\Api\TournamentApiController::class, 'declareAvailability']);
        Route::delete('/{tournament}/availability', [\App\Http\Controllers\Api\TournamentApiController::class, 'withdrawAvailability']);
    });

    // Admin-specific endpoints
    Route::middleware(['admin_or_superadmin'])->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\TournamentApiController::class, 'store']);
        Route::put('/{tournament}', [\App\Http\Controllers\Api\TournamentApiController::class, 'update']);
        Route::post('/{tournament}/assignments', [\App\Http\Controllers\Api\TournamentApiController::class, 'createAssignment']);
        Route::delete('/assignments/{assignment}', [\App\Http\Controllers\Api\TournamentApiController::class, 'destroyAssignment']);
    });
});

/*
|--------------------------------------------------------------------------
| routes/api/v1/notifications.php - Notification API
|--------------------------------------------------------------------------
| API per gestione notifiche via external services
*/

// Webhook endpoints for notification delivery status
Route::post('/notifications/webhook/email-status', [\App\Http\Controllers\Api\NotificationApiController::class, 'emailWebhook']);
Route::post('/notifications/webhook/sms-status', [\App\Http\Controllers\Api\NotificationApiController::class, 'smsWebhook']);

// Public notification subscription (for clubs/external)
Route::post('/notifications/subscribe', [\App\Http\Controllers\Api\NotificationApiController::class, 'subscribe']);
Route::post('/notifications/unsubscribe', [\App\Http\Controllers\Api\NotificationApiController::class, 'unsubscribe']);

// Authenticated notification management
Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {

    // User notifications
    Route::get('/my-notifications', [\App\Http\Controllers\Api\NotificationApiController::class, 'myNotifications']);
    Route::post('/{notification}/mark-read', [\App\Http\Controllers\Api\NotificationApiController::class, 'markRead']);
    Route::post('/mark-all-read', [\App\Http\Controllers\Api\NotificationApiController::class, 'markAllRead']);

    // Admin notification management
    Route::middleware(['admin_or_superadmin'])->group(function () {
        Route::post('/send', [\App\Http\Controllers\Api\NotificationApiController::class, 'send']);
        Route::get('/delivery-status/{notification}', [\App\Http\Controllers\Api\NotificationApiController::class, 'deliveryStatus']);
        Route::post('/bulk-send', [\App\Http\Controllers\Api\NotificationApiController::class, 'bulkSend']);
    });
});

// Rate limiting for API routes
Route::middleware(['throttle:api'])->group(function () {
    // Apply rate limiting to sensitive endpoints
});
