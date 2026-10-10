<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\CalendarDataService;
use App\Traits\HasZoneVisibility;
use App\Traits\TournamentControllerTrait;
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

    public function __construct(CalendarDataService $calendarService)
    {
        $this->initTournamentServices($calendarService);
    }

    /**
     * Lista tornei unificata
     * - Solo tornei futuri (se non ci sono filtri month/search)
     * - Ordinata in ASC (dal più vicino al più lontano)
     */
    public function index(Request $request): View
    {
        $user = $this->authUser();

        $query = Tournament::with(['tournamentType', 'zone', 'club'])->withCount(['assignments', 'availabilities']);
        $this->applyTournamentVisibility($query, $user);

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
        $user = $this->authUser();
        $forceUserMode = $request->string('view_as')->toString() === 'user';
        $isAdmin = $forceUserMode ? false : $this->isAdmin($user);

        $query = Tournament::with([
            // is_national serve a decidere chi puo' modificare (SZR no sui nazionali)
            'tournamentType:id,name,short_name,calendar_color,is_national',
            'club:id,name,zone_id',
            'club.zone:id,name',
            'zone:id,name',
        ]);

        if ($isAdmin) {
            $query->withCount(['assignments', 'availabilities']);
        }

        $this->applyTournamentVisibility($query, $user);

        $currentYear = $request->integer('year', now()->year);
        $query->whereBetween('start_date', [
            now()->setYear($currentYear)->startOfYear(),
            now()->setYear($currentYear)->endOfYear(),
        ]);

        $tournaments = $query->orderBy('start_date')->get();

        // Dati user-specific
        $userAvailabilities = [];
        $userAssignments = [];
        if ($user->isReferee()) {
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
        $user = $this->authUser();
        $isAdmin = $this->isAdmin($user);

        // 🔐 CHECK ACCESS
        $this->checkTournamentAccess($tournament, $user, $isAdmin);

        // 📚 LOAD RELATIONS
        $tournament->load(['tournamentType', 'zone', 'club']);

        // 👤 REFEREE-SPECIFIC DATA
        $userAvailability = null;
        $userAssignment = null;

        if ($user->isReferee()) {
            $userAvailability = $tournament->availabilities()->where('user_id', $user->id)->first();
            $userAssignment = $tournament->assignments()->where('user_id', $user->id)->first();
        }

        // Get required referees from tournament type
        $required_referees = $tournament->tournamentType->min_referees ?? 1;

        return view('tournaments.show', compact(
            'tournament',
            'userAvailability',
            'userAssignment',
            'required_referees'
        ));
    }

    // ===============================================
    // HELPER METHODS
    // ===============================================

    // Nota: isAdmin, isNationalAdmin, isNationalReferee sono nel trait HasZoneVisibility

    /**
     * @param  \App\Models\Tournament  $tournament
     * @param  \App\Models\User|null  $user
     * @param  bool  $isAdmin
     */
    private function checkTournamentAccess($tournament, $user, $isAdmin): void
    {
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
