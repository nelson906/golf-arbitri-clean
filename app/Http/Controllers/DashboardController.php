<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Redirect based on user type
        switch ($user->user_type) {
            case 'super_admin':
            case 'national_admin':
                return $this->adminDashboard($user);
            case 'admin':
                return $this->zoneDashboard($user);
            case 'referee':
                return $this->refereeDashboard($user);
            default:
                abort(403);
        }
    }

    private function adminDashboard($user)
    {
        $stats = [
            'total_users' => User::count(),
            'active_referees' => User::referees()->active()->count(),
            'total_tournaments' => Tournament::count(),
            'active_tournaments' => Tournament::whereIn('status', ['open', 'assigned'])->count(),
            'pending_assignments' => Assignment::whereHas('tournament', function($q) {
                $q->where('status', 'open');
            })->count(),
        ];

        $recentTournaments = Tournament::with(['club', 'zone', 'tournamentType'])
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentTournaments'));
    }

    private function zoneDashboard($user)
    {
        $stats = [
            'zone_referees' => User::referees()->inZone($user->zone_id)->active()->count(),
            'zone_tournaments' => Tournament::inZone($user->zone_id)->count(),
            'pending_tournaments' => Tournament::inZone($user->zone_id)
                ->where('status', 'open')
                ->count(),
        ];

        $zoneTournaments = Tournament::with(['club', 'tournamentType'])
            ->inZone($user->zone_id)
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.zone-dashboard', compact('stats', 'zoneTournaments'));
    }

    private function refereeDashboard($user)
    {
        $user->load(['assignments.tournament', 'availabilities.tournament']);

        $stats = [
            'total_assignments' => $user->assignments->count(),
            'current_year_assignments' => $user->assignments()
                ->whereHas('tournament', function($q) {
                    $q->whereYear('start_date', date('Y'));
                })->count(),
            'pending_tournaments' => Tournament::where('status', 'open')
                ->where('availability_deadline', '>', now())
                ->count(),
        ];

        $upcomingAssignments = $user->assignments()
            ->with('tournament.club')
            ->whereHas('tournament', function($q) {
                $q->where('start_date', '>=', now())
                  ->where('status', '!=', 'cancelled');
            })
            ->orderBy(function($query) {
                $query->select('start_date')
                      ->from('tournaments')
                      ->whereColumn('tournaments.id', 'assignments.tournament_id');
            })
            ->limit(5)
            ->get();

        return view('referee.dashboard', compact('stats', 'upcomingAssignments'));
    }
}