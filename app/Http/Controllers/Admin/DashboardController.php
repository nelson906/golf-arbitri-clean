<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefereeLevel;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentVisibility;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->authUser();

        $refereesQuery = User::where('user_type', 'referee');
        // Tornei e assegnazioni: regola unica di visibilita' (D10), che conta
        // anche i tornei senza circolo (T.B.A.) della zona.
        $tournamentsQuery = TournamentVisibility::apply(Tournament::query(), $user);
        $assignmentsQuery = TournamentVisibility::applyViaRelation(Assignment::query(), $user);
        $clubsQuery = Club::query();

        if ($user->isZoneAdmin() && $user->zone_id) {
            $refereesQuery->where('zone_id', $user->zone_id);
            $clubsQuery->where('zone_id', $user->zone_id);
        } elseif ($user->user_type === UserType::NationalAdmin) {
            // CRC: solo arbitri di livello nazionale; vede tutti i circoli
            $refereesQuery->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]);
        }
        // super_admin: no filters, sees everything

        $stats = [
            'total_tournaments' => $tournamentsQuery->count(),
            'total_referees' => $refereesQuery->count(),
            'total_assignments' => $assignmentsQuery->count(),
            'total_clubs' => $clubsQuery->count(),
            'recent_assignments' => TournamentVisibility::applyViaRelation(Assignment::with(['user', 'tournament']), $user)
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
