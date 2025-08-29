<?php

/*
|--------------------------------------------------------------------------
| routes/admin/reports.php - Reporting Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Referee\DashboardController as RefereeDashboardController;
use App\Http\Controllers\Referee\AvailabilityController;
use App\Http\Controllers\Referee\TournamentController as RefereeTournamentController;
use Illuminate\Support\Facades\Route;

// REPORTS & STATISTICS
Route::prefix('reports')->name('reports.')->group(function () {

    // Main Dashboard
    Route::get('/', [StatisticsController::class, 'index'])->name('index');

    // Tournament Reports
    Route::prefix('tournaments')->name('tournaments.')->group(function () {
        Route::get('/', [StatisticsController::class, 'tournaments'])->name('index');
        Route::get('/completion-rate', [StatisticsController::class, 'tournamentCompletionRate'])->name('completion-rate');
        Route::get('/by-type', [StatisticsController::class, 'tournamentsByType'])->name('by-type');
        Route::get('/by-zone', [StatisticsController::class, 'tournamentsByZone'])->name('by-zone');
        Route::get('/calendar-analysis', [StatisticsController::class, 'calendarAnalysis'])->name('calendar-analysis');
    });

    // Referee Reports
    Route::prefix('referees')->name('referees.')->group(function () {
        Route::get('/', [StatisticsController::class, 'referees'])->name('index');
        Route::get('/activity', [StatisticsController::class, 'refereeActivity'])->name('activity');
        Route::get('/performance', [StatisticsController::class, 'refereePerformance'])->name('performance');
        Route::get('/level-distribution', [StatisticsController::class, 'refereeLevelDistribution'])->name('level-distribution');
        Route::get('/workload', [StatisticsController::class, 'refereeWorkload'])->name('workload');
        Route::get('/availability-trends', [StatisticsController::class, 'availabilityTrends'])->name('availability-trends');
    });

    // Assignment Reports
    Route::prefix('assignments')->name('assignments.')->group(function () {
        Route::get('/', [StatisticsController::class, 'assignments'])->name('index');
        Route::get('/efficiency', [StatisticsController::class, 'assignmentEfficiency'])->name('efficiency');
        Route::get('/role-distribution', [StatisticsController::class, 'roleDistribution'])->name('role-distribution');
        Route::get('/confirmation-rates', [StatisticsController::class, 'confirmationRates'])->name('confirmation-rates');
    });

    // Export Functionality
    Route::get('/export', [StatisticsController::class, 'export'])->name('export');
    Route::post('/custom-export', [StatisticsController::class, 'customExport'])->name('custom-export');
    Route::get('/scheduled-reports', [StatisticsController::class, 'scheduledReports'])->name('scheduled');
});

/*
|--------------------------------------------------------------------------
| routes/referee/dashboard.php - Referee Dashboard Routes
|--------------------------------------------------------------------------
*/

// REFEREE DASHBOARD
Route::prefix('referee')->name('referee.')->group(function () {

    // Main Dashboard
    Route::get('/dashboard', [RefereeDashboardController::class, 'index'])->name('dashboard');

    // Calendar Views
    Route::get('/calendar', [RefereeDashboardController::class, 'calendar'])->name('calendar');
    Route::get('/calendar-data', [RefereeDashboardController::class, 'calendarData'])->name('calendar-data');

    // Profile Management
    Route::get('/profile', [RefereeDashboardController::class, 'profile'])->name('profile');
    Route::get('/profile/edit', [RefereeDashboardController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [RefereeDashboardController::class, 'updateProfile'])->name('profile.update');

    // Quick Actions API
    Route::get('/quick-action', [RefereeDashboardController::class, 'quickAction'])->name('quick-action');

    // Personal Statistics
    Route::get('/statistics', [RefereeDashboardController::class, 'statistics'])->name('statistics');
    Route::get('/career-summary', [RefereeDashboardController::class, 'careerSummary'])->name('career-summary');
});

/*
|--------------------------------------------------------------------------
| routes/referee/availability.php - Availability Management Routes
|--------------------------------------------------------------------------
*/

// AVAILABILITY MANAGEMENT
Route::prefix('referee/availability')->name('referee.availability.')->group(function () {

    // Main Views
    Route::get('/', [AvailabilityController::class, 'index'])->name('index');
    Route::get('/calendar', [AvailabilityController::class, 'calendar'])->name('calendar');
    Route::get('/history', [AvailabilityController::class, 'history'])->name('history');

    // CRUD Operations
    Route::post('/', [AvailabilityController::class, 'store'])->name('store');
    Route::put('/{availability}', [AvailabilityController::class, 'update'])->name('update');
    Route::delete('/{availability}', [AvailabilityController::class, 'destroy'])->name('destroy');

    // Bulk Operations
    Route::post('/bulk-declare', [AvailabilityController::class, 'bulkDeclare'])->name('bulk-declare');
    Route::post('/bulk-withdraw', [AvailabilityController::class, 'bulkWithdraw'])->name('bulk-withdraw');

    // Quick Actions
    Route::post('/quick-declare/{tournament}', [AvailabilityController::class, 'quickDeclare'])->name('quick-declare');
    Route::delete('/quick-withdraw/{tournament}', [AvailabilityController::class, 'quickWithdraw'])->name('quick-withdraw');

    // Notifications & Reminders
    Route::get('/deadlines', [AvailabilityController::class, 'upcomingDeadlines'])->name('deadlines');
    Route::get('/recommendations', [AvailabilityController::class, 'recommendations'])->name('recommendations');
});

/*
|--------------------------------------------------------------------------
| routes/referee/tournaments.php - Referee Tournament Views
|--------------------------------------------------------------------------
*/

// REFEREE TOURNAMENT VIEWS (Read-Only)
Route::prefix('referee/tournaments')->name('referee.tournaments.')->group(function () {

    // Main Views
    Route::get('/', [RefereeTournamentController::class, 'index'])->name('index');
    Route::get('/{tournament}', [RefereeTournamentController::class, 'show'])->name('show');
    Route::get('/calendar/view', [RefereeTournamentController::class, 'calendar'])->name('calendar');

    // Personal Tournament Management
    Route::get('/my-tournaments', [RefereeTournamentController::class, 'myTournaments'])->name('my-tournaments');
    Route::get('/my-assignments', function () {
        return redirect()->route('referee.tournaments.my-tournaments', ['tab' => 'assignments']);
    })->name('my-assignments');
    Route::get('/my-availabilities', function () {
        return redirect()->route('referee.tournaments.my-tournaments', ['tab' => 'availabilities']);
    })->name('my-availabilities');

    // Search & Filter
    Route::get('/search', [RefereeTournamentController::class, 'search'])->name('search');

    // Quick availability declaration from tournament view
    Route::post('/{tournament}/declare-availability', [AvailabilityController::class, 'store'])
        ->name('declare-availability');
});
