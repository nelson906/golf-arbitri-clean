<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $usersQuery = User::query();
        $refereesQuery = User::where('user_type', 'referee');
        $tournamentsQuery = Tournament::query();
        $assignmentsQuery = Assignment::with(['user', 'tournament']);

        if ($user->user_type === 'admin' && $user->zone_id) {
            // Zone admin: only own zone (all tournaments of clubs in the zone)
            $usersQuery->where('zone_id', $user->zone_id);
            $refereesQuery->where('zone_id', $user->zone_id);
            $tournamentsQuery->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
            $assignmentsQuery->whereHas('tournament.club', fn ($q) => $q->where('zone_id', $user->zone_id));
        } elseif ($user->user_type === 'national_admin') {
            // National admin: only national tournaments and national-level referees
            $usersQuery->whereIn('level', [User::LEVEL_NAZIONALE, User::LEVEL_INTERNAZIONALE]);
            $refereesQuery->whereIn('level', [User::LEVEL_NAZIONALE, User::LEVEL_INTERNAZIONALE]);
            $tournamentsQuery->whereHas('tournamentType', fn ($q) => $q->where('is_national', true));
            $assignmentsQuery->whereHas('tournament.tournamentType', fn ($q) => $q->where('is_national', true));
        }
        // super_admin: no filters, sees everything

        $stats = [
            'total_users' => $usersQuery->count(),
            'total_referees' => $refereesQuery->count(),
            'total_tournaments' => $tournamentsQuery->count(),
            'recent_assignments' => $assignmentsQuery
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
