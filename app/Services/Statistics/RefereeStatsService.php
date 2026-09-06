<?php

namespace App\Services\Statistics;

use App\Models\User;
use App\Traits\HasZoneVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RefereeStatsService
{
    use HasZoneVisibility;

    /**
     * Ottiene statistiche generali sugli arbitri.
     *
     * @return array<string, mixed>
     */
    public function getGeneralStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return [
            'total' => $query->count(),
            'active' => $query->clone()->where('is_active', true)->count(),
            'inactive' => $query->clone()->where('is_active', false)->count(),
        ];
    }

    /**
     * Ottiene arbitri per livello.
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function getByLevel(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->where('level', '<>', 'Archivio')
            ->selectRaw('level, COUNT(*) as totale')
            ->orderByRaw('FIELD(level, "Aspirante", "1_livello", "Regionale", "Nazionale", "Internazionale")')
            ->groupBy('level')
            ->pluck('totale', 'level');
    }

    /**
     * Ottiene arbitri per zona.
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function getByZone(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        // Solo per admin nazionali/super admin
        if (! $this->isNationalAdmin($user)) {
            return Collection::make([]);
        }

        return User::query()->where('user_type', '=', 'referee')
            ->where('level', '<>', 'Archivio')
            ->join('zones', 'users.zone_id', '=', 'zones.id')
            ->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name', 'asc')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }

    /**
     * Ottiene conteggio totale arbitri (escluso Archivio).
     */
    public function getTotal(?User $user = null): int
    {
        return $this->baseQuery($user)
            ->where('level', '<>', 'Archivio')
            ->count();
    }

    /**
     * Ottiene arbitri attivi nell'ultimo mese.
     */
    public function getRecentlyActive(?User $user = null): int
    {
        $user = $user ?? auth()->user();

        return $this->baseQuery($user)
            ->where('last_login_at', '>=', now()->subMonth())
            ->count();
    }

    /**
     * Ottiene arbitri con assegnazioni.
     */
    public function getWithAssignments(?User $user = null): int
    {
        $user = $user ?? auth()->user();

        return $this->baseQuery($user)
            ->has('assignments')
            ->count();
    }

    /**
     * Ottiene percentuale arbitri attivi.
     */
    public function getActivePercentage(?User $user = null): float
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);
        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $active = $query->clone()->where('is_active', true)->count();

        return round(($active / $total) * 100, 1);
    }

    /**
     * Ottiene statistiche attività arbitri.
     *
     * @return array<string, mixed>
     */
    public function getActivityStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user)->where('is_active', true);

        $referees = $query->withCount(['assignments', 'availabilities'])->get();

        return [
            'avg_assignments' => round((float) $referees->avg('assignments_count'), 2),
            'max_assignments' => $referees->max('assignments_count'),
            'min_assignments' => $referees->min('assignments_count'),
            'avg_availabilities' => round((float) $referees->avg('availabilities_count'), 2),
        ];
    }

    /**
     * Ottiene tasso disponibilità arbitri.
     */
    public function getAvailabilityRate(?User $user = null): float
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user)->where('is_active', true);

        $total = $query->count();
        if ($total === 0) {
            return 0;
        }

        $withAvailabilities = $query->clone()->has('availabilities')->count();

        return round(($withAvailabilities / $total) * 100, 1);
    }

    /**
     * Ottiene statistiche complete per la vista arbitri.
     *
     * @return array<string, mixed>
     */
    public function getFullStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        return [
            'totale_arbitri' => $this->getTotal($user),
            'per_livello' => $this->getByLevel($user),
            'per_zona' => $this->getByZone($user),
            'attivi_ultimo_mese' => $this->getRecentlyActive($user),
            'con_assegnazioni' => $this->getWithAssignments($user),
            'active_percentage' => $this->getActivePercentage($user),
            'activity' => $this->getActivityStats($user),
            'availability_rate' => $this->getAvailabilityRate($user),
        ];
    }

    /**
     * Query base con visibilità applicata.
     *
     * @return Builder<\App\Models\User>
     */
    protected function baseQuery(?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        $query = User::query()->where('user_type', '=', 'referee');

        return $this->applyUserVisibility($query, $user);
    }
}
