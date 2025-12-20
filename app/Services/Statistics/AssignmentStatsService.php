<?php

namespace App\Services\Statistics;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use App\Traits\HasZoneVisibility;
use Illuminate\Support\Collection;

class AssignmentStatsService
{
    use HasZoneVisibility;

    /**
     * Ottiene statistiche generali sulle assegnazioni.
     */
    public function getGeneralStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return [
            'total' => $query->count(),
            'confirmed' => $query->clone()->where('is_confirmed', true)->count(),
            'pending' => $query->clone()->where('is_confirmed', false)->count(),
        ];
    }

    /**
     * Ottiene conteggio totale assegnazioni.
     */
    public function getTotal(?User $user = null): int
    {
        return $this->baseQuery($user)->count();
    }

    /**
     * Ottiene assegnazioni per ruolo.
     */
    public function getByRole(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->selectRaw('role, COUNT(*) as totale')
            ->orderByRaw('FIELD(role, "Direttore di Torneo", "Arbitro", "Osservatore")')
            ->groupBy('role')
            ->pluck('totale', 'role');
    }

    /**
     * Ottiene assegnazioni per zona.
     */
    public function getByZone(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        // Solo per admin nazionali/super admin
        if (! $this->isNationalAdmin($user)) {
            return Collection::make([]);
        }

        return Assignment::query()
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->join('zones', 'tournaments.zone_id', '=', 'zones.id')
            ->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name', 'asc')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }

    /**
     * Ottiene assegnazioni per livello arbitro.
     */
    public function getByLevel(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->join('users', 'assignments.user_id', '=', 'users.id')
            ->selectRaw('users.level, COUNT(*) as totale')
            ->groupBy('users.level')
            ->pluck('totale', 'level');
    }

    /**
     * Ottiene tornei con assegnazioni.
     */
    public function getTournamentsWithAssignments(?User $user = null): int
    {
        $user = $user ?? auth()->user();
        $query = Tournament::query()->has('assignments');

        return $this->applyTournamentVisibility($query, $user)->count();
    }

    /**
     * Ottiene media arbitri per torneo.
     */
    public function getAverageRefereesPerTournament(?User $user = null): float
    {
        $totalAssignments = $this->getTotal($user);
        $tournamentsWithAssignments = $this->getTournamentsWithAssignments($user);

        return $tournamentsWithAssignments > 0
            ? round($totalAssignments / $tournamentsWithAssignments, 2)
            : 0;
    }

    /**
     * Ottiene assegnazioni recenti (ultimi 30 giorni).
     */
    public function getRecent(int $days = 30, ?User $user = null): int
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->where('assigned_at', '>=', now()->subDays($days))->count();
    }

    /**
     * Ottiene statistiche carico di lavoro.
     */
    public function getWorkloadStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        $refereesQuery = User::query()
            ->where('user_type', '=', 'referee')
            ->where('is_active', '=', true)
            ->withCount('assignments');

        $this->applyUserVisibility($refereesQuery, $user);
        $referees = $refereesQuery->get();

        return [
            'avg_assignments' => round($referees->avg('assignments_count'), 2),
            'max_assignments' => $referees->max('assignments_count'),
            'min_assignments' => $referees->min('assignments_count'),
            'overloaded_referees' => $referees->where('assignments_count', '>', 10)->count(),
        ];
    }

    /**
     * Ottiene statistiche complete per la vista assegnazioni.
     */
    public function getFullStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $generalStats = $this->getGeneralStats($user);

        return [
            'total' => $generalStats['total'],
            'confirmed' => $generalStats['confirmed'],
            'pending' => $generalStats['pending'],
            'by_role' => $this->getByRole($user),
            'by_zone' => $this->getByZone($user),
            'by_level' => $this->getByLevel($user),
            'workload' => $this->getWorkloadStats($user),
            'totale_assegnazioni' => $generalStats['total'],
            'per_zona' => $this->getByZone($user),
            'tornei_assegnati' => $this->getTournamentsWithAssignments($user),
            'media_arbitri_torneo' => $this->getAverageRefereesPerTournament($user),
            'ultimi_30_giorni' => $this->getRecent(30, $user),
        ];
    }

    /**
     * Query base con visibilità applicata.
     */
    protected function baseQuery(?User $user = null)
    {
        $user = $user ?? auth()->user();
        $query = Assignment::query();

        return $this->applyTournamentRelationVisibility($query, $user);
    }
}
