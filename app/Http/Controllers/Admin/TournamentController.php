<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use App\Traits\HasZoneVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Display a listing of tournaments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Base query with eager loading
        $query = Tournament::with(['club.zone', 'tournamentType', 'notification']);

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($query, $user);

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('zone_id') && $request->zone_id !== '') {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        // Tournament type filter
        if ($request->has('tournament_type_id') && $request->tournament_type_id !== '') {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->has('month') && $request->month !== '') {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            });
        }

        // Search in tournament name or club name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('club', function ($clubQuery) use ($search) {
                        $clubQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        // Club filter
        if ($request->filled('club_id')) {
            $query->where('club_id', $request->club_id);
        }

        // Filtra per tornei futuri - solo se non ci sono altri filtri
        if (!$request->filled('month') && !$request->filled('search')) {
            $query->where('start_date', '>=', Carbon::now()->startOfDay());
        }

        // Order by start date ascending (più vicini per primi)
        $tournaments = $query->orderBy('start_date', 'asc')->paginate(20);

        // Calcola days_until_deadline per ogni torneo
        $tournaments->getCollection()->transform(function ($tournament) {
            $now = Carbon::now();
            $deadline = Carbon::parse($tournament->availability_deadline);
            // Cast a int per evitare decimali (267.58907249284 -> 267)
            $tournament->days_until_deadline = (int) $now->diffInDays($deadline, false);
            return $tournament;
        });

        // Get data for filters
        $zones = $this->isNationalAdmin($user) ? Zone::orderBy('name', 'asc')->get() : collect();
        $tournamentTypes = TournamentType::active()->ordered()->get();
        $statuses = Tournament::STATUSES;

        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'tournamentTypes',
            'statuses'
        ))->with('isNationalAdmin', $this->isNationalAdmin($user));
    }

    /**
     * Show tournaments calendar view
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        // Query con scope di visibilità
        $tournaments = Tournament::visible($user)
            ->with(['tournamentType', 'zone', 'club', 'assignments.user'])
            ->get();

        // Get zones for filter
        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Prepara dati calendario tramite servizio
        $calendarData = $this->calendarService->prepareFullCalendarData(
            $tournaments,
            $user,
            'admin',
            [
                'zones' => $zones,
                'clubs' => Club::visible($user)->active()->orderBy('name')->get(),
                'tournamentTypes' => TournamentType::active()->ordered()->get(),
            ]
        );

        // Aggiungi campi specifici admin
        $calendarData['userRoles'] = $this->getAdminRoles($user);
        $calendarData['canModify'] = true;

        return view('admin.tournaments.calendar', compact('calendarData'));
    }

    /**
     * Get admin roles for permissions
     */
    private function getAdminRoles($user): array
    {
        $roles = ['Admin'];
        if ($user->user_type === 'super_admin') {
            $roles[] = 'SuperAdmin';
        } elseif ($user->user_type === 'national_admin') {
            $roles[] = 'NationalAdmin';
        }

        return $roles;
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create()
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Tutti gli admin vedono tutti i tipi di torneo attivi
        $tournamentTypes = TournamentType::active()->ordered()->get();

        // Get zones con visibilità
        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Get clubs con visibilità
        $clubsQuery = Club::active();
        $this->applyClubVisibility($clubsQuery, $user);
        $clubs = $clubsQuery->ordered()->get();

        return view('admin.tournaments.create', compact('tournamentTypes', 'zones', 'clubs'));
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Tournament $tournament)
    {
        // Check access usando il trait
        $this->checkTournamentAccess($tournament);

        // Check if editable
        if (! $tournament->isEditable()) {
            return redirect()
                ->route('admin.tournaments.show', $tournament)
                ->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
        }

        $user = auth()->user();
        // Tutti gli admin vedono tutti i tipi di torneo attivi
        $tournamentTypes = TournamentType::active()->ordered()->get();

        // Get zones con visibilità
        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Get clubs della zona del torneo
        $clubs = Club::active()
            ->where('zone_id', $tournament->zone_id)
            ->ordered()
            ->get();

        return view('admin.tournaments.edit', compact('tournament', 'tournamentTypes', 'zones', 'clubs'));
    }

    /**
     * Store a newly created tournament in storage.
     */
    public function store(TournamentRequest $request)
    {
        $data = $request->validated();

        // Set zone_id from club se admin zonale
        if ($this->isZoneAdmin()) {
            $club = Club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
        }
        $data['created_by'] = auth()->id();

        // Create tournament
        $tournament = Tournament::create($data);

        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo creato con successo!');
    }

    /**
     * Display the specified tournament for admin view
     */
    public function show(Tournament $tournament)
    {
        $user = auth()->user();

        // Check permissions
        if ($user->user_type === 'admin' && $user->zone_id !== $tournament->zone_id) {
            abort(403, 'Non hai i permessi per visualizzare questo torneo.');
        }

        // Ora le relazioni funzioneranno con le tabelle corrette
        $tournament->load([
            'tournamentType',
            'zone',
            'club',
            'assignments.user',
            'availabilities.user',
        ]);

        // Ottieni gli arbitri assegnati
        $assignedReferees = $tournament->assignments()
            ->with(relations: 'user')
            ->get();

        $availableReferees = $tournament->availabilities()
            ->with(relations: 'user')
            ->get();

        // Statistics
        $stats = [
            'total_assignments' => $assignedReferees ? $assignedReferees->count() : 0,
            'total_availabilities' => $availableReferees ? $availableReferees->count() : 0,
            'assigned_referees' => $assignedReferees ? $assignedReferees->count() : 0,
            'required_referees' => $tournament->tournamentType->min_referees ?? 2,
            'days_until_deadline' => $tournament->availability_deadline
                ? now()->diffInDays($tournament->availability_deadline, false)
                : null,
        ];

        return view('admin.tournaments.show', compact(
            'tournament',
            'assignedReferees',
            'availableReferees',
            'stats'
        ));
    }

    /**
     * Update the specified tournament in storage.
     */
    public function update(TournamentRequest $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if editable
        if (! $tournament->isEditable()) {
            return redirect()
                ->route('admin.tournaments.show', $tournament)->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
        }

        $data = $request->validated();

        // Update zone_id from club if changed
        if (isset($data['club_id']) && $data['club_id'] != $tournament->club_id) {
            $club = Club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
        }

        $tournament->update($data);

        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo aggiornato con successo!');
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Request $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if has assignments and needs confirmation
        if ($tournament->assignments()->exists() && ! $request->has('confirm')) {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('warning', 'Questo torneo ha delle assegnazioni. Per eliminarlo, conferma nuovamente l\'eliminazione.')
                ->with('tournament_id', $tournament->id)
                ->with('tournament_name', $tournament->name);
        }

        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Torneo eliminato con successo!');
    }

    /**
     * Update tournament status.
     */
    public function updateStatus(Request $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Tournament::STATUSES))],
        ]);

        $newStatus = $request->status;
        $currentStatus = $tournament->status;

        // Validate status transition
        $validTransitions = [
            'draft' => ['open'],
            'open' => ['closed'],
            'closed' => ['open', 'assigned'],
            'assigned' => ['completed'],
            'completed' => [],
        ];

        if (! in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Transizione di stato non valida.',
            ], 400);
        }

        // Additional checks
        if ($newStatus === 'assigned' && $tournament->assignments()->count() < $tournament->required_referees) {
            return response()->json([
                'success' => false,
                'message' => 'Non ci sono abbastanza arbitri assegnati.',
            ], 400);
        }

        $tournament->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Stato aggiornato con successo.',
            'new_status' => $newStatus,
            'new_status_label' => Tournament::STATUSES[$newStatus],
            'new_status_color' => $tournament->status_color,
        ]);
    }

    /**
     * Change tournament status with override (bypasses workflow validation).
     * Use this for manual corrections or administrative overrides.
     */
    public function changeStatus(Request $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Tournament::STATUSES))],
        ]);

        $oldStatus = $tournament->status;
        $newStatus = $request->status;

        // Update status directly (no workflow validation)
        $tournament->update(['status' => $newStatus]);

        // Log the override for audit trail
        Log::info('Tournament status override', [
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Stato cambiato da '{$oldStatus}' a '{$newStatus}'.",
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'new_status_label' => Tournament::STATUSES[$newStatus],
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Stato torneo cambiato da '".Tournament::STATUSES[$oldStatus]."' a '".Tournament::STATUSES[$newStatus]."'.");
    }

    /**
     * Show availabilities for a tournament.
     */
    public function availabilities(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Get available referees with their level and zone
        $availabilities = $tournament->availabilities()
            ->with([
                'user' => function ($query) {
                    $query->with('zone');
                },
            ])
            ->get()
            ->sortBy('user.name');

        // Get all eligible referees who haven't declared availability
        $eligibleReferees = \App\Models\User::where('user_type', '=', 'referee')
            ->where('is_active', '=', true)

            // ✅ FIXED: Use tournamentType relationship
            ->when($tournament->tournamentType->is_national, function ($q) {
                $q->whereIn('level', ['nazionale', 'internazionale']);
            }, function ($q) use ($tournament) {
                $q->where('zone_id', '=', $tournament->zone_id);
            })
            ->whereNotIn('id', $tournament->availabilities()->pluck('user_id'))
            ->whereNotIn('id', $tournament->assignments()->pluck('user_id'))
            ->orderBy('name', 'asc')->get();

        return view('admin.tournaments.availabilities', compact(
            'tournament',
            'availabilities',
            'eligibleReferees'
        ));
    }

    /**
     * Check if user can access tournament (usa il trait HasZoneVisibility).
     */
    private function checkTournamentAccess(Tournament $tournament)
    {
        if (! $this->canAccessTournament($tournament)) {
            abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
        }
    }

    /**
     * Get clubs for a specific zone (AJAX).
     */
    public function getclubsByZone(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
        ]);

        $clubs = club::active()
            ->where('zone_id', $request->zone_id)
            ->ordered()
            ->get(['id', 'name', 'short_name']);

        return response()->json($clubs);
    }

    /**
     * Configurazione per il trait
     */
    protected function getEntityName($model): string
    {
        return 'Torneo';
    }

    protected function getIndexRoute(): string
    {
        return 'admin.tournaments.index';
    }

    protected function getDeleteErrorMessage($model): string
    {
        return 'Impossibile eliminare un torneo con assegnazioni.';
    }

    protected function canBeDeleted($tournament): bool
    {
        return ! $tournament->assignments()->exists() && $tournament->status === 'draft';
    }

    protected function checkAccess($tournament): void
    {
        $this->checkTournamentAccess($tournament);
    }

    // ===============================================
    // 🎨 HELPER METHODS (colori centralizzati in TournamentColorService)
    // ===============================================

    /**
     * 🎨 Calculate management priority
     */
    private function getManagementPriority($tournament): string
    {
        try {
            $availabilities = $tournament->availabilities()->count();
            $assignments = $tournament->assignments()->count();
            $required = $tournament->required_referees ?? $tournament->tournamentType->min_referees ?? 1;

            // Calcola giorni fino alla deadline
            $daysUntilDeadline = 999;
            if ($tournament->availability_deadline) {
                $daysUntilDeadline = $tournament->availability_deadline->diffInDays(now(), false);
            }

            // Urgent: Missing referees or overdue deadline
            if ($daysUntilDeadline < 0 || $assignments < $required) {
                return 'urgent';
            }

            // Complete: Fully staffed
            if ($assignments >= $required) {
                return 'complete';
            }

            // In progress: Has some availability/assignments but not complete
            if ($availabilities > 0 || $assignments > 0) {
                return 'in_progress';
            }

            // Open: Ready for availability submissions
            return 'open';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
