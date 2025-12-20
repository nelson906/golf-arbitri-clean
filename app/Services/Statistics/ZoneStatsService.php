<?php

namespace App\Services\Statistics;

use App\Models\Assignment;
use App\Models\User;
use App\Models\Zone;
use App\Traits\HasZoneVisibility;

class ZoneStatsService
{
    use HasZoneVisibility;

    /**
     * Ottiene statistiche complete per tutte le zone.
     */
    public function getAllZonesStats(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?User $user = null
    ): array {
        $user = $user ?? auth()->user();

        // Solo admin nazionali possono vedere tutte le zone
        if (! $this->isNationalAdmin($user)) {
            return [];
        }

        $zones = Zone::with(['users', 'clubs', 'tournaments'])->get();

        return $zones->map(function ($zone) use ($dateFrom, $dateTo) {
            return $this->getZoneStats($zone, $dateFrom, $dateTo);
        })->toArray();
    }

    /**
     * Ottiene statistiche per una singola zona.
     */
    public function getZoneStats(Zone $zone, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $tournamentsQuery = $zone->tournaments();
        if ($dateFrom) {
            $tournamentsQuery->where('start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $tournamentsQuery->where('start_date', '<=', $dateTo);
        }

        $assignmentsQuery = Assignment::whereHas('tournament', function ($q) use ($zone) {
            $q->where('zone_id', $zone->id);
        });
        if ($dateFrom) {
            $assignmentsQuery->whereHas('tournament', function ($q) use ($dateFrom) {
                $q->where('start_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $assignmentsQuery->whereHas('tournament', function ($q) use ($dateTo) {
                $q->where('start_date', '<=', $dateTo);
            });
        }

        return [
            'zone' => $zone,
            'referees' => $zone->users()->where('user_type', '=', 'referee')->count(),
            'active_referees' => $zone->users()
                ->where('user_type', '=', 'referee')
                ->where('is_active', '=', true)
                ->count(),
            'clubs' => $zone->clubs()->count(),
            'tournaments' => $tournamentsQuery->count(),
            'assignments' => $assignmentsQuery->count(),
            'availability_rate' => $this->getZoneAvailabilityRate($zone->id),
            'activity_score' => $this->getZoneActivityScore($zone->id),
        ];
    }

    /**
     * Ottiene tasso di disponibilità per una zona.
     */
    public function getZoneAvailabilityRate(int $zoneId): float
    {
        $totalReferees = User::query()
            ->where('user_type', '=', 'referee')
            ->where('zone_id', '=', $zoneId)
            ->where('is_active', '=', true)
            ->count();

        if ($totalReferees === 0) {
            return 0;
        }

        $refereesWithAvailability = User::query()
            ->where('user_type', '=', 'referee')
            ->where('zone_id', '=', $zoneId)
            ->where('is_active', '=', true)
            ->has('availabilities')
            ->count();

        return round(($refereesWithAvailability / $totalReferees) * 100, 1);
    }

    /**
     * Ottiene punteggio attività per una zona.
     */
    public function getZoneActivityScore(int $zoneId): float
    {
        $referees = User::query()
            ->where('user_type', '=', 'referee')
            ->where('zone_id', '=', $zoneId)
            ->where('is_active', '=', true)
            ->withCount(['assignments', 'availabilities'])
            ->get();

        if ($referees->isEmpty()) {
            return 0;
        }

        // Score basato su assegnazioni e disponibilità medie
        $avgAssignments = $referees->avg('assignments_count');
        $avgAvailabilities = $referees->avg('availabilities_count');

        // Normalizza a un punteggio 0-100
        return min(100, round(($avgAssignments * 5) + ($avgAvailabilities * 2), 1));
    }

    /**
     * Ottiene riepilogo zone per dashboard.
     */
    public function getZonesSummary(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        if (! $this->isNationalAdmin($user)) {
            return [];
        }

        return Zone::with(['tournaments', 'users'])
            ->get()
            ->map(function ($zone) {
                return [
                    'name' => $zone->name,
                    'tournaments' => $zone->tournaments->count(),
                    'referees' => $zone->users()->where('user_type', '=', 'referee')->count(),
                    'active_referees' => $zone->users()
                        ->where('user_type', '=', 'referee')
                        ->where('is_active', '=', true)
                        ->count(),
                ];
            })
            ->toArray();
    }
}
