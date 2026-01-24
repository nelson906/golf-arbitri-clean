<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use App\Traits\HasZoneVisibility;
use App\Traits\TournamentControllerTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TournamentController Unificato
 * Usa HasZoneVisibility per logica di filtraggio centralizzata
 */
class TournamentController extends Controller
{
    use HasZoneVisibility;
    use TournamentControllerTrait;

    public function __construct(TournamentColorService $colorService, CalendarDataService $calendarService)
    {
        $this->initTournamentServices($colorService, $calendarService);  // ← USA METODO TRAIT
    }

    /**
     * Lista tornei unificata
     * - Solo tornei futuri (se non ci sono filtri month/search)
     * - Ordinata in ASC (dal più vicino al più lontano)
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = Tournament::with(['tournamentType', 'zone', 'club']);
        $this->applyTournamentVisibility($query, $user);

        // Per i non-admin, mostra solo tornei con status visibili
        if (! $this->isAdmin($user)) {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);
        }

        // Usa metodo condiviso dal trait
        $this->applyCommonFilters($query, $request);

        $tournaments = $query->orderBy('start_date', 'asc')->paginate(20);

        // Usa metodo condiviso dal trait
        $this->addDeadlineInfo($tournaments);

        $stats = $this->isAdmin($user) ? $this->calculateTournamentStats($tournaments) : [];

        return view('tournaments.index', [
            'tournaments' => $tournaments,
            'isAdmin' => $this->isAdmin($user),
            'stats' => $stats,
            'isNationalAdmin' => $this->isNationalAdmin($user),
        ]);
    }

    /**
     * Calendario unificato
     * Ora centralizzato tramite CalendarDataService
     */
    public function calendar(Request $request): View
    {
        $user = auth()->user();
        $forceUserMode = $request->get('view_as') === 'user';
        $isAdmin = $forceUserMode ? false : $this->isAdmin($user);

        $query = Tournament::with([
            'tournamentType:id,name,short_name,calendar_color',
            'club:id,name,zone_id',
            'club.zone:id,name',
        ]);

        if ($isAdmin) {
            $query->withCount(['assignments', 'availabilities']);
        }

        $this->applyTournamentVisibility($query, $user);

        if (! $isAdmin) {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);
        }

        $currentYear = $request->get('year', now()->year);
        $query->whereBetween('start_date', [
            Carbon::create($currentYear, 1, 1)->startOfYear(),
            Carbon::create($currentYear, 12, 31)->endOfYear(),
        ]);

        $tournaments = $query->orderBy('start_date')->get();

        // Dati user-specific
        $userAvailabilities = [];
        $userAssignments = [];
        if ($user->user_type === 'referee') {
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();
        }

        // Usa metodo condiviso dal trait
        $calendarData = $this->prepareCalendarData(
            $tournaments,
            $user,
            $isAdmin ? 'admin' : 'referee',
            [
                'availableTournamentIds' => $userAvailabilities,
                'assignedTournamentIds' => $userAssignments,
            ]
        );

        return view('tournaments.calendar', compact('calendarData'));
    }

    /**
     * ✅ Dettagli torneo
     */
    public function show(Tournament $tournament): View
    {
        $user = auth()->user();
        $isAdmin = $this->isAdmin($user);

        // 🔐 CHECK ACCESS
        $this->checkTournamentAccess($tournament, $user, $isAdmin);

        // 📚 LOAD RELATIONS
        $tournament->load(['tournamentType', 'zone', 'club']);

        if ($isAdmin) {
            $tournament->load(['assignments.user', 'availabilities.user']);
        }

        // 👤 REFEREE-SPECIFIC DATA
        $userAvailability = null;
        $userAssignment = null;

        if ($user->user_type === 'referee') {
            $userAvailability = $tournament->availabilities()->where('user_id', $user->id)->first();
            $userAssignment = $tournament->assignments()->where('user_id', $user->id)->first();
        }

        // 📊 ADMIN-SPECIFIC STATS
        $stats = [];
        $assignedReferees = collect();
        $availableReferees = collect();

        if ($isAdmin) {
            $stats = [
                'total_assignments' => $tournament->assignments()->count(),
                'total_availabilities' => $tournament->availabilities()->count(),
                'required_referees' => $tournament->required_referees ?? 1,
                'max_referees' => $tournament->max_referees ?? 4,
                'days_until_deadline' => $tournament->availability_deadline
                    ? Carbon::parse($tournament->availability_deadline)->diffInDays(now(), false)
                    : null,
                'is_editable' => method_exists($tournament, 'isEditable') ? $tournament->isEditable() : true,
            ];

            $assignedReferees = $tournament->assignedReferees;
            $availableReferees = $tournament->availabilities()->with('user')->get();
        }

        // Get required referees from tournament type
        $required_referees = $tournament->tournamentType?->min_referees ?? 1;

        return view('tournaments.show', compact(
            'tournament',
            'userAvailability',
            'userAssignment',
            'stats',
            'assignedReferees',
            'availableReferees',
            'required_referees',
            'isAdmin'
        ));
    }

    // ===============================================
    // HELPER METHODS
    // ===============================================

    // Nota: isAdmin, isNationalAdmin, isNationalReferee sono nel trait HasZoneVisibility

    private function checkTournamentAccess($tournament, $user, $isAdmin): void
    {
        // Non-admin non possono vedere tornei in draft
        if (! $isAdmin && $tournament->status === 'draft') {
            abort(404);
        }

        // Usa il metodo centralizzato del trait per verificare l'accesso
        if (! $this->canAccessTournament($tournament, $user)) {
            abort(403, 'Non hai accesso a questo torneo.');
        }
    }
}

/*
=================================================================
🎨 CODIFICA COLORI RECUPERATA:
=================================================================

ADMIN VIEW:
- Colore principale: Categoria Torneo
  * Categoria A: #FF6B6B (Rosso)
  * Categoria B: #4ECDC4 (Teal)
  * Categoria C: #45B7D1 (Blu)
  * Categoria D: #96CEB4 (Verde)

- Bordo: Status Torneo
  * Draft: #F59E0B (Amber)
  * Open: #10B981 (Green)
  * Closed: #6B7280 (Gray)
  * Assigned: #059669 (Dark Green)
  * Completed: #374151 (Dark Gray)
  * Cancelled: #EF4444 (Red)

REFEREE VIEW:
- Colore: Personal Status
  * Assigned: #10B981 (Green)
  * Available: #F59E0B (Yellow)
  * Can Apply: #3B82F6 (Blue)

- Bordo: Personal Status
  * Assigned: #059669 (Dark Green)
  * Available: #D97706 (Dark Yellow)
  * Can Apply: #1E40AF (Dark Blue)

MANAGEMENT PRIORITY:
- urgent: deadline passata o arbitri mancanti
- complete: completamente staffato
- in_progress: parzialmente staffato
- open: pronto per disponibilità
*/
