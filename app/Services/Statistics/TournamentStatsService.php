<?php

namespace App\Services\Statistics;

use App\Models\Notification;
use App\Models\Tournament;
use App\Models\User;
use App\Traits\HasZoneVisibility;
use Illuminate\Support\Collection;

class TournamentStatsService
{
    use HasZoneVisibility;

    /**
     * Ottiene statistiche generali sui tornei.
     */
    public function getGeneralStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return [
            'total' => $query->count(),
            'active' => $query->clone()->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'completed' => $query->clone()->where('status', 'completed')->count(),
        ];
    }

    /**
     * Ottiene tornei per tipo.
     */
    public function getByType(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        $query = Tournament::query()
            ->join('tournament_types', 'tournaments.tournament_type_id', '=', 'tournament_types.id');

        $this->applyTournamentVisibility($query, $user);

        return $query->selectRaw('tournament_types.name, COUNT(*) as totale')
            ->orderBy('tournament_types.name')
            ->groupBy('tournament_types.name')
            ->pluck('totale', 'name');
    }

    /**
     * Ottiene tornei per stato.
     */
    public function getByStatus(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->selectRaw('status, COUNT(*) as totale')
            ->groupBy('status')
            ->pluck('totale', 'status');
    }

    /**
     * Ottiene tornei per zona.
     */
    public function getByZone(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        // Solo per admin nazionali/super admin
        if (! $this->isNationalAdmin($user)) {
            return Collection::make([]);
        }

        return Tournament::query()
            ->join('zones', 'tournaments.zone_id', '=', 'zones.id')
            ->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name', 'asc')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }

    /**
     * Ottiene tornei per mese (anno corrente).
     */
    public function getByMonth(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        $monthlyData = $query->selectRaw('MONTH(start_date) as month, COUNT(*) as totale')
            ->whereYear('start_date', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('totale', 'month');

        $monthNames = [
            1 => 'Gennaio',
            2 => 'Febbraio',
            3 => 'Marzo',
            4 => 'Aprile',
            5 => 'Maggio',
            6 => 'Giugno',
            7 => 'Luglio',
            8 => 'Agosto',
            9 => 'Settembre',
            10 => 'Ottobre',
            11 => 'Novembre',
            12 => 'Dicembre',
        ];

        return $monthlyData->mapWithKeys(fn ($count, $monthNum) => [$monthNames[$monthNum] => $count]);
    }

    /**
     * Ottiene conteggio totale tornei.
     */
    public function getTotal(?User $user = null): int
    {
        return $this->baseQuery($user)->count();
    }

    /**
     * Ottiene tornei prossimi 30 giorni.
     */
    public function getUpcoming(int $days = 30, ?User $user = null): int
    {
        $user = $user ?? auth()->user();
        $query = $this->baseQuery($user);

        return $query->where('start_date', '>=', now())
            ->where('start_date', '<=', now()->addDays($days))
            ->count();
    }

    /**
     * Ottiene tornei con notifiche.
     */
    public function getWithNotifications(?User $user = null): int
    {
        return Notification::query()->distinct()->count('tournament_id');
    }

    /**
     * Ottiene statistiche complete per la vista tornei.
     */
    public function getFullStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        return [
            'totale_tornei' => $this->getTotal($user),
            'per_stato' => $this->getByStatus($user),
            'per_zona' => $this->getByZone($user),
            'by_type' => $this->getByType($user),
            'by_month' => $this->getByMonth($user),
            'prossimi_30_giorni' => $this->getUpcoming(30, $user),
            'con_notifiche' => $this->getWithNotifications($user),
        ];
    }

    /**
     * Query base con visibilità applicata.
     */
    protected function baseQuery(?User $user = null)
    {
        $user = $user ?? auth()->user();
        $query = Tournament::query();

        return $this->applyTournamentVisibility($query, $user);
    }
}
