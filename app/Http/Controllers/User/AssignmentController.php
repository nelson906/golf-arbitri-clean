<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * "Le Mie Assegnazioni" — tutte le designazioni dell'arbitro nella stagione
 * corrente (decisione 2026-10-03, P16). Le stagioni archiviate restano nel
 * Curriculum.
 */
class AssignmentController extends Controller
{
    public function index(): View
    {
        $user = $this->authUser();

        $assignments = $user->assignments()
            ->with(['tournament.club.zone', 'tournament.tournamentType'])
            ->whereHas('tournament')
            ->get()
            ->sortBy(fn ($a) => $a->tournament->start_date)
            ->values();

        $today = now()->startOfDay();

        $upcoming = $assignments
            ->filter(fn ($a) => ($a->tournament->end_date ?? $a->tournament->start_date) >= $today)
            ->values();

        $past = $assignments
            ->filter(fn ($a) => ($a->tournament->end_date ?? $a->tournament->start_date) < $today)
            ->sortByDesc(fn ($a) => $a->tournament->start_date)
            ->values();

        return view('user.assignments.index', compact('upcoming', 'past'));
    }
}
