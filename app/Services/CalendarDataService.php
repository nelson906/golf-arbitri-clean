<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Servizio centralizzato per la preparazione dati calendario.
 *
 * Unifica la logica di preparazione dati usata in:
 * - Admin/TournamentController::calendar()
 * - TournamentController::calendar()
 * - User/AvailabilityController::calendar()
 */
class CalendarDataService
{
    protected TournamentColorService $colorService;

    public function __construct(TournamentColorService $colorService)
    {
        $this->colorService = $colorService;
    }

    /**
     * Prepara dati calendario per vista ADMIN.
     * Mostra tutti i tornei della zona/nazionali con colori per tipo torneo.
     */
    public function prepareAdminCalendarData(Collection $tournaments): Collection
    {
        return $tournaments->map(function ($tournament) {
            return [
                'id' => $tournament->id,
                'title' => $tournament->name,
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $this->colorService->getAdminEventColor($tournament),
                'borderColor' => $this->colorService->getAdminBorderColor($tournament),
                'extendedProps' => $this->getAdminExtendedProps($tournament),
            ];
        });
    }

    /**
     * Prepara dati calendario per vista ARBITRO.
     * Mostra tornei con colori basati su stato personale (assegnato/disponibile/candidabile).
     *
     * @param  array  $availableTournamentIds  IDs tornei dove l'utente ha dichiarato disponibilità
     * @param  array  $assignedTournamentIds  IDs tornei dove l'utente è assegnato
     */
    public function prepareRefereeCalendarData(
        Collection $tournaments,
        User $user,
        array $availableTournamentIds = [],
        array $assignedTournamentIds = []
    ): Collection {
        return $tournaments->map(function ($tournament) use ($availableTournamentIds, $assignedTournamentIds) {
            $isAvailable = in_array($tournament->id, $availableTournamentIds);
            $isAssigned = in_array($tournament->id, $assignedTournamentIds);

            return [
                'id' => $tournament->id,
                'title' => $tournament->name ?? 'Torneo #'.$tournament->id,
                'start' => $tournament->start_date ? $tournament->start_date->format('Y-m-d') : now()->format('Y-m-d'),
                'end' => $tournament->end_date ? $tournament->end_date->addDay()->format('Y-m-d') : now()->addDay()->format('Y-m-d'),
                'color' => $this->colorService->getRefereeEventColor($tournament, $isAssigned, $isAvailable),
                'borderColor' => $this->colorService->getRefereeBorderColor($isAssigned, $isAvailable),
                'extendedProps' => $this->getRefereeExtendedProps($tournament, $isAvailable, $isAssigned),
            ];
        });
    }

    /**
     * Prepara dati calendario per vista MISTA (admin/referee).
     * Usato quando la stessa vista serve entrambi i ruoli.
     */
    public function prepareMixedCalendarData(
        Collection $tournaments,
        User $user,
        array $availableTournamentIds = [],
        array $assignedTournamentIds = []
    ): Collection {
        $isAdmin = in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);

        return $tournaments->map(function ($tournament) use ($isAdmin, $availableTournamentIds, $assignedTournamentIds) {
            $isAvailable = in_array($tournament->id, $availableTournamentIds);
            $isAssigned = in_array($tournament->id, $assignedTournamentIds);

            return [
                'id' => $tournament->id,
                'title' => $tournament->name,
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $this->colorService->getEventColor($tournament, $isAssigned, $isAvailable, $isAdmin),
                'borderColor' => $this->colorService->getBorderColor($tournament, $isAssigned, $isAvailable, $isAdmin),
                'extendedProps' => $this->getMixedExtendedProps($tournament, $isAvailable, $isAssigned, $isAdmin),
            ];
        });
    }

    /**
     * Prepara dati completi per la pagina calendario (tornei + filtri + metadata).
     *
     * @param  string  $viewType  'admin' | 'referee' | 'mixed'
     * @param  array  $options  Opzioni aggiuntive (availableTournamentIds, assignedTournamentIds, etc.)
     */
    public function prepareFullCalendarData(
        Collection $tournaments,
        User $user,
        string $viewType = 'mixed',
        array $options = []
    ): array {
        $availableTournamentIds = $options['availableTournamentIds'] ?? [];
        $assignedTournamentIds = $options['assignedTournamentIds'] ?? [];

        // Prepara tornei in base al tipo di vista
        $calendarTournaments = match ($viewType) {
            'admin' => $this->prepareAdminCalendarData($tournaments),
            'referee' => $this->prepareRefereeCalendarData($tournaments, $user, $availableTournamentIds, $assignedTournamentIds),
            default => $this->prepareMixedCalendarData($tournaments, $user, $availableTournamentIds, $assignedTournamentIds),
        };

        // Prepara filtri
        $zones = $options['zones'] ?? Zone::orderBy('name')->get();
        $clubs = $options['clubs'] ?? Club::visible($user)->orderBy('name')->get();
        $tournamentTypes = $options['tournamentTypes'] ?? TournamentType::orderBy('name')->get();

        return [
            'tournaments' => $calendarTournaments,
            'zones' => $this->formatZones($zones),
            'clubs' => $this->formatClubs($clubs),
            'tournamentTypes' => $this->formatTournamentTypes($tournamentTypes),
            'userType' => $user->user_type,
            'legend' => $this->getLegendForViewType($viewType),
        ];
    }

    /**
     * Extended props per vista ADMIN
     */
    protected function getAdminExtendedProps(Tournament $tournament): array
    {
        return [
            'club' => $tournament->club->name ?? 'N/A',
            'zone' => $tournament->zone->name ?? 'N/A',
            'zone_id' => $tournament->zone_id,
            'tournament_type' => $tournament->tournamentType->name ?? 'N/A',
            'status' => $tournament->status,
            'tournament_url' => route('admin.tournaments.show', $tournament),
            'deadline' => $tournament->availability_deadline ? $tournament->availability_deadline->format('d/m/Y') : 'N/A',
            'type_id' => $tournament->tournament_type_id,
            'availabilities_count' => $tournament->availabilities_count ?? $tournament->availabilities->count(),
            'assignments_count' => $tournament->assignments_count ?? $tournament->assignments->count(),
            'required_referees' => $tournament->required_referees ?? 1,
            'max_referees' => $tournament->tournamentType?->max_referees ?? 4,
        ];
    }

    /**
     * Extended props per vista ARBITRO
     */
    protected function getRefereeExtendedProps(Tournament $tournament, bool $isAvailable, bool $isAssigned): array
    {
        return [
            'club' => $tournament->club->name ?? 'N/A',
            'zone' => $tournament->club->zone->name ?? 'N/A',
            'category' => $tournament->tournamentType->name ?? 'N/A',
            'status' => $tournament->status ?? 'active',
            'is_available' => $isAvailable,
            'is_assigned' => $isAssigned,
            'personal_status' => $this->colorService->getPersonalStatus($isAssigned, $isAvailable),
        ];
    }

    /**
     * Extended props per vista MISTA
     */
    protected function getMixedExtendedProps(Tournament $tournament, bool $isAvailable, bool $isAssigned, bool $isAdmin): array
    {
        $props = [
            'club' => $tournament->club->name ?? 'N/A',
            'zone' => $tournament->zone->name ?? 'N/A',
            'category' => $tournament->tournamentType->name ?? 'N/A',
            'type_id' => $tournament->tournament_type_id,
            'zone_id' => $tournament->zone_id,
            'club_id' => $tournament->club_id,
            'status' => $tournament->status,
        ];

        if ($isAdmin) {
            $props['tournament_url'] = route('admin.tournaments.show', $tournament);
            $props['availabilities_count'] = $tournament->availabilities_count ?? $tournament->availabilities->count();
            $props['assignments_count'] = $tournament->assignments_count ?? $tournament->assignments->count();
        } else {
            $props['is_available'] = $isAvailable;
            $props['is_assigned'] = $isAssigned;
            $props['personal_status'] = $this->colorService->getPersonalStatus($isAssigned, $isAvailable);
        }

        return $props;
    }

    /**
     * Formatta zone per dropdown filtri
     */
    protected function formatZones(Collection $zones): Collection
    {
        return $zones->map(fn ($zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
        ]);
    }

    /**
     * Formatta club per dropdown filtri
     */
    protected function formatClubs(Collection $clubs): Collection
    {
        return $clubs->map(fn ($club) => [
            'id' => $club->id,
            'name' => $club->name,
            'zone_id' => $club->zone_id,
        ]);
    }

    /**
     * Formatta tipi torneo per dropdown filtri
     */
    protected function formatTournamentTypes(Collection $types): Collection
    {
        return $types->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
            'short_name' => $type->short_name,
            'color' => $type->calendar_color ?? '#3B82F6',
        ]);
    }

    /**
     * Ottieni legenda colori per tipo vista
     */
    protected function getLegendForViewType(string $viewType): array
    {
        return match ($viewType) {
            'admin' => $this->colorService->getAdminLegendColors(),
            'referee' => $this->colorService->getRefereeLegendColors(),
            default => array_merge(
                $this->colorService->getAdminLegendColors(),
                $this->colorService->getRefereeLegendColors()
            ),
        };
    }

    /**
     * Ottieni statistiche tornei per dashboard
     */
    public function getTournamentStats(Collection $tournaments): array
    {
        $byStatus = $tournaments->groupBy('status');

        return [
            'total' => $tournaments->count(),
            'draft' => $byStatus->get('draft', collect())->count(),
            'open' => $byStatus->get('open', collect())->count(),
            'closed' => $byStatus->get('closed', collect())->count(),
            'assigned' => $byStatus->get('assigned', collect())->count(),
            'completed' => $byStatus->get('completed', collect())->count(),
        ];
    }
}
