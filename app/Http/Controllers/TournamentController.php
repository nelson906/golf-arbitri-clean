<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use App\Traits\HasZoneVisibility;
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

    protected TournamentColorService $colorService;

    protected CalendarDataService $calendarService;

    public function __construct(TournamentColorService $colorService, CalendarDataService $calendarService)
    {
        $this->colorService = $colorService;
        $this->calendarService = $calendarService;
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

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($query, $user);

        // Per i non-admin, mostra solo tornei con status visibili
        if (! $this->isAdmin($user)) {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);
        }

        // Filtri aggiuntivi da request
        $this->applyFilters($query, $request);

        // Filtra per tornei futuri - solo se non ci sono altri filtri
        if (! $request->filled('month') && ! $request->filled('search')) {
            $query->where('start_date', '>=', Carbon::now()->startOfDay());
        }

        // Ordina ASC (più vicini per primi) e pagina
        $tournaments = $query->orderBy('start_date', 'asc')->paginate(20);

        // Calcola days_until_deadline per ogni torneo
        $tournaments->getCollection()->transform(function ($tournament) {
            $now = Carbon::now();
            $deadline = Carbon::parse($tournament->availability_deadline);
            $tournament->days_until_deadline = (int) $now->diffInDays($deadline, false);
            return $tournament;
        });

        // Statistiche per admin
        $stats = $this->isAdmin($user) ? $this->calculateStats($tournaments) : [];

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
        // Allow forcing user mode with ?view_as=user parameter
        $forceUserMode = $request->get('view_as') === 'user';
        $isAdmin = $forceUserMode ? false : $this->isAdmin($user);

        // Query base con relazioni ottimizzate
        $query = Tournament::with([
            'tournamentType:id,name,short_name,calendar_color',
            'club:id,name,zone_id',
            'club.zone:id,name',
        ]);

        // Load additional relations based on user type
        if ($isAdmin) {
            $query->withCount(['assignments', 'availabilities']);
        }

        // Filtro visibilità per zona/ruolo
        $this->applyTournamentVisibility($query, $user);

        // Per i non-admin, mostra solo tornei con status visibili
        if (! $isAdmin) {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);
        }

        // Filtri opzionali
        $currentYear = $request->get('year', now()->year);
        $startDate = Carbon::create($currentYear, 1, 1)->startOfYear();
        $endDate = Carbon::create($currentYear, 12, 31)->endOfYear();
        $query->whereBetween('start_date', [$startDate, $endDate]);

        if ($request->filled('zone_id')) {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('type_id')) {
            $query->where('tournament_type_id', $request->type_id);
        }

        $query->orderBy('start_date');
        $tournaments = $query->get();

        // Get user-specific availability/assignment data
        $userAvailabilities = [];
        $userAssignments = [];

        if ($user->user_type === 'referee') {
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();
        }

        // Use CalendarDataService to prepare calendar data
        $calendarData = $this->calendarService->prepareFullCalendarData(
            $tournaments,
            $user,
            $isAdmin ? 'admin' : 'referee',
            [
                'zones' => Zone::orderBy('name')->get(),
                'clubs' => Club::active()->ordered()->get(),
                'tournamentTypes' => TournamentType::active()->ordered()->get(),
                'availableTournamentIds' => $userAvailabilities,
                'assignedTournamentIds' => $userAssignments,
            ]
        );

        // Add UI-specific context fields
        $calendarData['userRoles'] = [$user->user_type];
        $calendarData['canModify'] = true;
        $calendarData['totalTournaments'] = $tournaments->count();
        $calendarData['lastUpdated'] = now()->toISOString();

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

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {

        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->filled('month')) {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });
        }
    }

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

    private function calculateStats($tournaments): array
    {
        if (is_object($tournaments) && method_exists($tournaments, 'getCollection')) {
            /** @var \Illuminate\Pagination\LengthAwarePaginator $tournaments */
            $collection = $tournaments->getCollection();
            $total = $tournaments->count();
        } else {
            $collection = $tournaments;
            $total = $tournaments->count();
        }

        $byStatus = $collection->groupBy('status');

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
