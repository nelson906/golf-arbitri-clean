<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class DashboardController extends Controller
{
    /**
     * Display the referee dashboard.
     */
public function index(Request $request)
{
    $year = session('selected_year', date('Y'));
    $user = auth()->user();
    $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

    // USA I MODEL!
    // $stats = [
    //     'total_tournaments' => Tournament::count(),
    //     'open_tournaments' => Tournament::where('status', 'open')->count(),
    //     'completed_tournaments' => Tournament::where('status', 'completed')->count(),
    //     'total_assignments' => Assignment::count(),
    //     'pending_assignments' => Assignment::where('is_confirmed', false)->count(),
    //     'active_referees' => User::where('user_type', 'referee')
    //         ->where('is_active', true)
    //         ->when(!$isNationalAdmin, fn($q) => $q->where('zone_id', $user->zone_id))
    //         ->count(),
    // ];
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Get referee statistics (usando un try-catch per evitare errori)
        try {
            $stats = $user->referee_statistics;
        } catch (\Exception $e) {
            // Se il metodo non esiste, creiamo delle statistiche base
            $stats = (object) [
                'total_assignments' => $user->assignments()->count(),
                'assignments_this_year' => $user->assignments()
                    ->whereHas('tournament', function($q) {
                        $q->whereYear('start_date', now()->year);
                    })
                    ->count(),
                'confirmed_assignments' => $user->assignments()->where('is_confirmed', true)->count(),
                'pending_assignments' => $user->assignments()->where('is_confirmed', false)->count(),
            ];
        }

        // Upcoming assignments
        $upcomingAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.zone', 'tournament.tournamentType'])
            ->whereHas('tournament', function ($q) {
                $q->where('start_date', '>=', Carbon::today());
            })
            ->limit(5)
            ->get();

        // Recent assignments (last 3 months)
        $recentAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.zone'])
            ->whereHas('tournament', function ($q) {
                $q->where('end_date', '>=', Carbon::now()->subMonths(3))
                  ->where('end_date', '<', Carbon::today());
            })
            ->limit(5)
            ->get();

        // Tournaments open for availability
        $openTournamentsQuery = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('status', 'open')
            ->where('availability_deadline', '>=', Carbon::today());

        // Filter by zone for non-national referees
        if (!$isNationalReferee) {
            $openTournamentsQuery->where('zone_id', $user->zone_id);
        } else {
            // National referees see national tournaments from all zones
            $openTournamentsQuery->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                  ->orWhereHas('tournamentType', function ($q2) {
                      $q2->where('is_national', true);
                  });
            });
        }

        $openTournaments = $openTournamentsQuery
            ->orderBy('availability_deadline')
            ->limit(10)
            ->get();

        // Get availabilities that haven't been assigned yet
        $pendingAvailabilities = $user->availabilities()
            ->with(['tournament.club', 'tournament.zone'])
            ->whereDoesntHave('tournament.assignments', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereHas('tournament', function ($q) {
                $q->whereIn('status', ['open', 'closed'])
                  ->where('start_date', '>=', Carbon::today());
            })
            ->limit(5)
            ->get();

        // Monthly statistics (last 12 months) - semplificato
        $monthlyStats = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $count = $user->assignments()
                ->whereHas('tournament', function($q) use ($month) {
                    $q->where('start_date', 'like', $month . '%');
                })
                ->count();
            $monthlyStats[$month] = $count;
        }

        // Assignments by tournament type - semplificato
// Assignments by tournament type - semplificato
$assignmentsByCategory = [];
$assignments = $user->assignments()
    ->with('tournament.tournamentType')
    ->whereHas('tournament', function($q) {
        $q->whereYear('start_date', Carbon::now()->year);
    })
    ->get();

foreach ($assignments as $assignment) {
    if ($assignment->tournament && $assignment->tournament->tournamentType) {
        $typeName = $assignment->tournament->tournamentType->short_name ?? 'Altri';
        if (!isset($assignmentsByCategory[$typeName])) {
            $assignmentsByCategory[$typeName] = 0;
        }
        $assignmentsByCategory[$typeName]++;
    }
}
        // Calendar events for the next 3 months - semplificato
        $calendarEvents = [];
        $calendarAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.tournamentType'])
            ->whereHas('tournament', function ($q) {
                $q->whereBetween('start_date', [Carbon::today(), Carbon::today()->addMonths(3)]);
            })
            ->get();

        foreach ($calendarAssignments as $assignment) {
            $calendarEvents[] = [
                'id' => 'assignment-' . $assignment->id,
                'title' => $assignment->tournament->name,
                'start' => $assignment->tournament->start_date->format('Y-m-d'),
                'end' => $assignment->tournament->end_date ? $assignment->tournament->end_date->addDay()->format('Y-m-d') : $assignment->tournament->start_date->format('Y-m-d'),
                'color' => $assignment->is_confirmed ? '#10b981' : '#f59e0b',
                'textColor' => '#ffffff'
            ];
        }

        return view('referee.dashboard', compact(
            'user',
            'stats',
            'upcomingAssignments',
            'recentAssignments',
            'openTournaments',
            'pendingAvailabilities',
            'monthlyStats',
            'assignmentsByCategory',
            'calendarEvents'
        ));
}
}
