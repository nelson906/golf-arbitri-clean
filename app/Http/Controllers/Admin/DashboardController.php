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
        $isZoneScoped = in_array($user->user_type, ['admin']) && $user->zone_id;

        $usersQuery = User::query();
        $refereesQuery = User::where('user_type', 'referee');
        $tournamentsQuery = Tournament::query();
        $assignmentsQuery = Assignment::with(['user', 'tournament']);

        if ($isZoneScoped) {
            $usersQuery->where('zone_id', $user->zone_id);
            $refereesQuery->where('zone_id', $user->zone_id);
            $tournamentsQuery->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
            $assignmentsQuery->whereHas('tournament.club', fn ($q) => $q->where('zone_id', $user->zone_id));
        }

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
