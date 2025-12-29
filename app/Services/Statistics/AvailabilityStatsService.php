<?php

namespace App\Services\Statistics;

use App\Models\Availability;
use App\Models\User;
use App\Traits\HasZoneVisibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityStatsService
{
    use HasZoneVisibility;

    /**
     * Ottiene statistiche generali sulle disponibilità.
     */
    public function getGeneralStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return [
            'total' => $query->count(),
            'referees_with_availability' => Availability::query()->distinct()->count('user_id'),
            'tournaments_with_availability' => Availability::query()->distinct()->count('tournament_id'),
        ];
    }

    /**
     * Ottiene conteggio totale disponibilità.
     */
    public function getTotal(?User $user = null): int
    {
        return $this->baseQuery($user)->count();
    }

    /**
     * Ottiene disponibilità per zona.
     */
    public function getByZone(?string $month = null, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        // Solo per admin nazionali/super admin
        if (! $this->isNationalAdmin($user)) {
            return collect([]);
        }

        $query = Availability::query()
            ->join('tournaments', 'availabilities.tournament_id', '=', 'tournaments.id')
            ->join('zones', 'tournaments.zone_id', '=', 'zones.id');

        if ($month) {
            $query->whereMonth('tournaments.start_date', $month);
        }

        return $query->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }

    /**
     * Ottiene disponibilità per livello arbitro.
     */
    public function getByLevel(?string $month = null, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        $query = User::query()
            ->where('user_type', '=', 'referee')
            ->where('is_active', '=', true)
            ->withCount('availabilities');

        $this->applyUserVisibility($query, $user);

        return $query->select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->pluck('count', 'level');
    }

    /**
     * Ottiene disponibilità per mese (anno corrente).
     */
    public function getByMonth(?User $user = null): Collection
    {
        return Availability::selectRaw('MONTH(created_at) as mese, COUNT(*) as totale')
            ->whereYear('created_at', date('Y'))
            ->groupBy('mese')
            ->pluck('totale', 'mese');
    }

    /**
     * Ottiene tasso di conversione (disponibilità -> assegnazioni).
     */
    public function getConversionRate(?string $month = null, ?User $user = null): float
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        if ($month) {
            $query->whereHas('tournament', function ($q) use ($month) {
                $q->whereMonth('start_date', $month);
            });
        }

        $totalAvailabilities = $query->count();
        if ($totalAvailabilities === 0) {
            return 0;
        }

        // Conta quante disponibilità sono diventate assegnazioni
        $converted = $query->clone()
            ->whereHas('referee.assignments', function ($q) {
                $q->whereColumn('assignments.tournament_id', 'availabilities.tournament_id');
            })
            ->count();

        return round(($converted / $totalAvailabilities) * 100, 1);
    }

    /**
     * Ottiene classifica arbitri per disponibilità.
     */
    public function getRefereesRanking(
        ?User $user = null,
        string $sortBy = 'availabilities_count',
        string $sortDirection = 'desc'
    ): \Illuminate\Database\Eloquent\Collection {
        $user = $user ?? auth()->user();

        $query = User::query()
            ->where('user_type', '=', 'referee')
            ->where('is_active', '=', true)
            ->with(['zone'])
            ->withCount(['availabilities', 'assignments']);

        $this->applyUserVisibility($query, $user);

        return $query->orderBy($sortBy, $sortDirection)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Ottiene top arbitri per disponibilità.
     */
    public function getTopReferees(int $limit = 10, ?User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $user = $user ?? auth()->user();

        $query = User::query()
            ->where('user_type', '=', 'referee')
            ->where('is_active', '=', true)
            ->withCount('availabilities')
            ->orderBy('availabilities_count', 'desc')
            ->limit($limit);

        return $this->applyUserVisibility($query, $user)->get();
    }

    /**
     * Ottiene statistiche complete per la vista disponibilità.
     */
    public function getFullStats(
        ?string $month = null,
        string $sortBy = 'availabilities_count',
        string $sortDirection = 'desc',
        ?User $user = null
    ): array {
        $user = $user ?? auth()->user();
        $generalStats = $this->getGeneralStats($user);

        return [
            'total' => $generalStats['total'],
            'by_zone' => $this->getByZone($month, $user),
            'by_level' => $this->getByLevel($month, $user),
            'conversion_rate' => $this->getConversionRate($month, $user),
            'totale_disponibilita' => Availability::count(),
            'arbitri_con_disponibilita' => $generalStats['referees_with_availability'],
            'tornei_con_disponibilita' => $generalStats['tournaments_with_availability'],
            'disponibilita_per_mese' => $this->getByMonth($user),
            'top_arbitri' => $this->getTopReferees(10, $user),
            'referees_ranking' => $this->getRefereesRanking($user, $sortBy, $sortDirection),
        ];
    }

    /**
     * Query base con visibilità applicata.
     */
    protected function baseQuery(?User $user = null)
    {
        $user = $user ?? auth()->user();
        $query = Availability::query();

        return $this->applyTournamentRelationVisibility($query, $user);
    }
}
