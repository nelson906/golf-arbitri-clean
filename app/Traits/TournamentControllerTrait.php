<?php

namespace App\Traits;

use App\Models\Club;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Trait condiviso per la logica comune dei TournamentController
 */
trait TournamentControllerTrait
{
    protected TournamentColorService $colorService;

    protected CalendarDataService $calendarService;

    /**
     * Inizializza i servizi (da chiamare nel costruttore)
     */
    protected function initTournamentServices(
        TournamentColorService $colorService,
        CalendarDataService $calendarService
    ): void {
        $this->colorService = $colorService;
        $this->calendarService = $calendarService;
    }

    /**
     * Applica filtri comuni alla query tornei
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Tournament>  $query
     */
    protected function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->integer('zone_id'));
            });
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->integer('tournament_type_id'));
        }

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });
        }

        // Filtra per tornei futuri - solo se non ci sono filtri temporali
        if (! $request->filled('month') && ! $request->filled('search')) {
            $query->where('start_date', '>=', Carbon::now()->startOfDay());
        }
    }

    /**
     * Calcola days_until_deadline per ogni torneo
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Tournament>  $tournaments
     */
    protected function addDeadlineInfo($tournaments): void
    {
        $tournaments->getCollection()->transform(function ($tournament) {
            if ($tournament->availability_deadline) {
                $now = Carbon::now();
                $deadline = Carbon::parse($tournament->availability_deadline);
                $tournament->days_until_deadline = (int) $now->diffInDays($deadline, false);
            } else {
                $tournament->days_until_deadline = null;
            }

            return $tournament;
        });
    }

    /**
     * Prepara dati comuni per il calendario
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Tournament>|\Illuminate\Support\Collection<int, \App\Models\Tournament>  $tournaments
     * @param  \App\Models\User  $user
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function prepareCalendarData($tournaments, $user, string $mode, array $options = []): array
    {
        $calendarData = $this->calendarService->prepareFullCalendarData(
            $tournaments instanceof \Illuminate\Support\Collection
                ? $tournaments
                : $tournaments->getCollection(),
            $user,
            $mode,
            array_merge([
                'zones' => Zone::orderBy('name')->get(),
                'clubs' => Club::active()->ordered()->get(),
                'tournamentTypes' => TournamentType::active()->ordered()->get(),
            ], $options)
        );

        $calendarData['userRoles'] = [$user->user_type->value];
        $calendarData['canModify'] = true;
        $calendarData['totalTournaments'] = $tournaments->count();
        $calendarData['lastUpdated'] = now()->toISOString();

        return $calendarData;
    }

    /**
     * Calcola statistiche tornei
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Tournament>|\Illuminate\Support\Collection<int, \App\Models\Tournament>  $tournaments
     * @return array<string, mixed>
     */
    protected function calculateTournamentStats($tournaments): array
    {
        if (is_object($tournaments) && method_exists($tournaments, 'getCollection')) {
            $collection = $tournaments->getCollection();
            $total = $tournaments->count();
        } else {
            $collection = $tournaments;
            $total = $tournaments->count();
        }

        $byStatus = $collection->groupBy(fn ($t) => $t->status->value);

        return [
            'total' => $total,
            'draft' => $byStatus->get('draft', collect())->count(),
            'open' => $byStatus->get('open', collect())->count(),
            'closed' => $byStatus->get('closed', collect())->count(),
            'assigned' => $byStatus->get('assigned', collect())->count(),
            'completed' => $byStatus->get('completed', collect())->count(),
        ];
    }
}
