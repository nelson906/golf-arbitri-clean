<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $refereesQuery = User::where('user_type', 'referee');
        $tournamentsQuery = Tournament::query();
        $assignmentsQuery = Assignment::query();
        $clubsQuery = Club::query();

        if ($user->user_type === 'admin' && $user->zone_id) {
            // Zone admin: only own zone (all tournaments of clubs in the zone)
            $refereesQuery->where('zone_id', $user->zone_id);
            $tournamentsQuery->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
            $assignmentsQuery->whereHas('tournament.club', fn ($q) => $q->where('zone_id', $user->zone_id));
            $clubsQuery->where('zone_id', $user->zone_id);
        } elseif ($user->user_type === 'national_admin') {
            // National admin: only national tournaments and national-level referees
            $refereesQuery->whereIn('level', [User::LEVEL_NAZIONALE, User::LEVEL_INTERNAZIONALE]);
            $tournamentsQuery->whereHas('tournamentType', fn ($q) => $q->where('is_national', true));
            $assignmentsQuery->whereHas('tournament.tournamentType', fn ($q) => $q->where('is_national', true));
            // National admin sees all clubs (national tournaments span all clubs)
        }
        // super_admin: no filters, sees everything

        $stats = [
            'total_tournaments' => $tournamentsQuery->count(),
            'total_referees' => $refereesQuery->count(),
            'total_assignments' => $assignmentsQuery->count(),
            'total_clubs' => $clubsQuery->count(),
            'recent_assignments' => Assignment::with(['user', 'tournament'])
                ->when($user->user_type === 'admin' && $user->zone_id, fn ($q) => $q->whereHas('tournament.club', fn ($sub) => $sub->where('zone_id', $user->zone_id)))
                ->when($user->user_type === 'national_admin', fn ($q) => $q->whereHas('tournament.tournamentType', fn ($sub) => $sub->where('is_national', true)))
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
