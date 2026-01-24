<?php

// File: app/Http/Controllers/Admin/AssignmentController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;
use App\Services\AssignmentValidationService;
use App\Traits\HasZoneVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    use HasZoneVisibility;

    protected AssignmentValidationService $validationService;

    public function __construct(AssignmentValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Display lista assegnazioni
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Assignment::with(['tournament.club.zone', 'user', 'tournament.tournamentType']);

        // Filtri
        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Ordinamento
        if (request('sort')) {
            switch (request('sort')) {
                case 'surname_asc':
                    $query->join('users', 'assignments.user_id', '=', 'users.id')
                        ->orderBy('users.last_name')
                        ->orderBy('users.first_name')
                        ->select('assignments.*');
                    break;
                case 'surname_desc':
                    $query->join('users', 'assignments.user_id', '=', 'users.id')
                        ->orderByDesc('users.last_name')
                        ->orderByDesc('users.first_name')
                        ->select('assignments.*');
                    break;
                default:
                    $query->orderBy('assignments.id', 'desc');
            }
        } else {
            $query->orderBy('assignments.id', 'desc');
        }

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentRelationVisibility($query, $user, 'tournament');

        $assignments = $query->paginate(20);

        // Tornei con filtro visibilità
        $tournamentsQuery = Tournament::with('club');
        $this->applyTournamentVisibility($tournamentsQuery, $user);
        $tournaments = $tournamentsQuery->orderBy('name')->get();

        // Referee con filtro visibilità

        $refereesQuery = User::where('user_type', 'referee')->orderBy('last_name');

        $this->applyUserVisibility($refereesQuery, $user);

        $referees = $refereesQuery->get();

        return view('admin.assignments.index', compact(
            'assignments',
            'tournaments',
            'referees'
        ))->with('isNationalAdmin', $this->isNationalAdmin($user));
    }

    /**
     * Show form creazione
     */
    public function create(Request $request)
    {
        $tournament = null;
        $availableReferees = collect();
        $otherReferees = collect();
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin();

        if ($request->has('tournament_id')) {
            /** @var Tournament|null $tournament */
            $tournament = Tournament::with(['assignments.user', 'availabilities.user'])->find($request->tournament_id);

            if ($tournament instanceof Tournament) {
                // IDs arbitri già assegnati a questo torneo
                $assignedRefereeIds = $tournament->assignments()->pluck('user_id')->toArray();

                // Arbitri che hanno dato disponibilità per questo torneo
                $availableRefereeIds = $tournament->availabilities()->pluck('user_id')->toArray();
                $availableReferees = User::where('user_type', 'referee')
                    ->where('is_active', true)
                    ->whereIn('id', $availableRefereeIds)
                    ->whereNotIn('id', $assignedRefereeIds)
                    ->when($isNationalAdmin, fn ($q) => $q->whereIn('level', ['Nazionale', 'Internazionale']))
                    ->orderBy('name')
                    ->get();

                // Altri arbitri
                $zoneId = (! $isNationalAdmin && $user && $user->user_type === 'admin') ? $user->zone_id : null;

                $otherReferees = User::where('user_type', 'referee')
                    ->where('is_active', true)
                    ->whereNotIn('id', $availableRefereeIds)
                    ->whereNotIn('id', $assignedRefereeIds)
                    ->when($isNationalAdmin, fn ($q) => $q->whereIn('level', ['Nazionale', 'Internazionale']))
                    ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                    ->orderBy('name')
                    ->get();
            }
        }

        $tournaments = Tournament::where('status', 'open')->get();

        return view('admin.assignments.create', compact(
            'tournament',
            'availableReferees',
            'otherReferees',
            'tournaments'
        ));
    }

    /**
     * Store singola assegnazione
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $exists = Assignment::where('tournament_id', $validated['tournament_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Arbitro già assegnato a questo torneo');
        }

        // ✅ Crea assignment con campi aggiuntivi
        // Default role è 'Arbitro' se non specificato

        if (empty($validated['role'])) {

            $validated['role'] = 'Arbitro';
        }

        Assignment::create(array_merge($validated, [
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]));

        return redirect()
            ->route('admin.assignments.index')
            ->with('success', 'Assegnazione creata con successo');
    }

    /**
     * Show assignment details
     */
    public function show($assignmentId): View
    {
        // Trova l'assegnazione in tutti gli anni disponibili
        $assignment = null;

        $found = Assignment::with([
            'user',
            'tournament.club',
            'tournament.tournamentType',
            'assignedBy',
        ])
            ->find($assignmentId);

        if ($found) {
            $assignment = $found;
        }

        if (! $assignment) {
            abort(404, 'Assegnazione non trovata');
        }

        $this->checkAssignmentAccess($assignment);

        return view('admin.assignments.show', compact('assignment'));
    }

    /**
     * Show form per modificare assegnazione
     */
    public function edit(Request $request, Assignment $assignment): View
    {
        $this->checkAssignmentAccess($assignment);

        $user = auth()->user();
        $tournament = $assignment->tournament;

        // Carica relazioni
        $assignment->load(['user', 'tournament.club.zone', 'tournament.tournamentType']);

        // Arbitri disponibili per sostituzione
        $refereesQuery = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->orderBy('last_name');
        $this->applyUserVisibility($refereesQuery, $user);
        $referees = $refereesQuery->get();

        // Ruoli disponibili
        $roles = ['Arbitro', 'Direttore di Torneo', 'Osservatore', 'Starter', 'Segretario'];

        // Suggested referee from conflict resolution
        $suggestedRefereeId = $request->query('suggested_referee');
        $suggestedReferee = $suggestedRefereeId ? User::find($suggestedRefereeId) : null;

        return view('admin.assignments.edit', compact(
            'assignment',
            'tournament',
            'referees',
            'roles',
            'suggestedReferee'
        ));
    }

    /**
     * Aggiorna assegnazione
     */
    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verifica che il nuovo arbitro non sia già assegnato allo stesso torneo
        if ($validated['user_id'] != $assignment->user_id) {
            $exists = Assignment::where('tournament_id', $assignment->tournament_id)
                ->where('user_id', $validated['user_id'])
                ->where('id', '!=', $assignment->id)
                ->exists();

            if ($exists) {
                return back()
                    ->withInput()
                    ->with('error', 'Questo arbitro è già assegnato a questo torneo');
            }
        }

        try {
            $assignment->update($validated);

            return redirect()
                ->route('admin.assignments.show', $assignment)
                ->with('success', 'Assegnazione aggiornata con successo');
        } catch (\Exception $e) {
            Log::error('Error updating assignment', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento: '.$e->getMessage());
        }
    }

    /**
     * Check if user can access the assignment.
     */
    private function checkAssignmentAccess($assignment): void
    {
        $this->checkTournamentAccess($assignment->tournament);
    }

    /**
     * Confirm assignment.
     */
    public function confirm(Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        try {
            $assignment->is_confirmed = true;
            $assignment->save();

            return redirect()->back()
                ->with('success', 'Assegnazione confermata con successo.');
        } catch (\Exception $e) {
            Log::error('Error confirming assignment', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Errore durante la conferma dell\'assegnazione: '.$e->getMessage());
        }
    }

    /**
     * Mostra form per assegnare arbitri a un torneo
     */
    public function assignReferees(Tournament $tournament)
    {
        $user = auth()->user();

        // Carica relazioni base del torneo
        $tournament->load(['club']);

        // La zona viene già ottenuta attraverso il club (Tournament non ha relazione diretta con zone)
        if ($tournament->club) {
            $tournament->club->load('zone');
            // zone_id è già accessibile tramite accessor in Tournament
        }

        // Carica tipo torneo
        $tournament->load('tournamentType');

        // Ottieni arbitri già assegnati
        $assignedReferees = $this->getAssignedReferees($tournament);
        $assignedRefereeIds = $assignedReferees->pluck('user_id')->toArray();

        // Ottieni arbitri disponibili (hanno dichiarato disponibilità)
        $availableReferees = $this->getAvailableReferees($tournament, $assignedRefereeIds);

        // Ottieni arbitri possibili (stessa zona, non hanno dichiarato disponibilità)
        $possibleReferees = $this->getPossibleReferees($tournament, $assignedRefereeIds);

        // Ottieni arbitri nazionali (per tornei nazionali)
        $nationalReferees = $this->getNationalReferees($tournament, $assignedRefereeIds, $availableReferees, $possibleReferees);

        return view('admin.assignments.assign-referees', compact(
            'tournament',
            'availableReferees',
            'possibleReferees',
            'nationalReferees',
            'assignedReferees'
        ))->with('isNationalAdmin', $this->isNationalAdmin());
    }

    /**
     * Ottieni arbitri già assegnati al torneo
     */
    private function getAssignedReferees($tournament)
    {
        $assignedReferees = $tournament->assignments()
            ->with('user')
            ->get()
            ->map(function ($assignment) {
                // Aggiungi dati user all'assignment per la vista
                if ($assignment->user) {
                    $assignment->name = $assignment->user->name;
                    $assignment->email = $assignment->user->email;

                    // Campi user standard
                    $assignment->referee_code = $assignment->user->referee_code;
                    $assignment->level = $assignment->user->level;

                    // Usa user_id o referee_id a seconda della struttura
                    $assignment->user_id = $assignment->user->id;
                }

                return $assignment;
            });

        return $assignedReferees;
    }

    /**
     * Ottieni arbitri che hanno dichiarato disponibilità
     */
    private function getAvailableReferees($tournament, $excludeIds = [])
    {
        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per disponibilità dichiarata
        $query->whereHas('availabilities', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->id);
        });

        // Filtra solo arbitri attivi
        $query->where('is_active', true);

        // CRC admin: mostra solo arbitri nazionali/internazionali
        if ($this->isNationalAdmin()) {
            $query->whereIn('level', ['Nazionale', 'Internazionale']);
        }

        // Escludi già assegnati
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Ottieni arbitri della stessa zona che non hanno dichiarato disponibilità
     */
    private function getPossibleReferees($tournament, $excludeIds = [])
    {
        // CRC admin: non mostra arbitri "possibili" zonali, solo nazionali nella sezione dedicata
        if ($this->isNationalAdmin()) {
            return collect();
        }

        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per zona se disponibile
        if (isset($tournament->zone_id)) {
            $query->where('zone_id', $tournament->zone_id);
        }

        // Escludi quelli che hanno già dichiarato disponibilità
        $query->whereDoesntHave('availabilities', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->id);
        });

        // Filtra solo arbitri attivi
        $query->where('is_active', true);

        // Escludi già assegnati
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Ottieni arbitri nazionali/internazionali per tornei nazionali
     */
    private function getNationalReferees($tournament, $excludeIds = [], $availableReferees = null, $possibleReferees = null)
    {
        // CRC admin: mostra sempre arbitri nazionali (che non hanno dato disponibilità)
        // Per admin zonali: mostra solo se il torneo è nazionale
        if (! $this->isNationalAdmin()) {
            if (! isset($tournament->tournamentType) || ! $tournament->tournamentType->is_national) {
                return collect();
            }
        }

        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per livello nazionale/internazionale
        $query->whereIn('level', ['Nazionale', 'Internazionale']);

        // Filtra solo arbitri attivi
        $query->where('is_active', true);

        // Escludi già assegnati
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Escludi quelli già nelle altre liste (disponibili)
        if ($availableReferees && $availableReferees->count() > 0) {
            $query->whereNotIn('id', $availableReferees->pluck('id'));
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Salva assegnazioni multiple
     */
    public function storeMultiple(Request $request, Tournament $tournament)
    {
        $request->validate([
            'referee_ids' => 'required|array',
            'referee_ids.*' => 'exists:users,id',
            'roles' => 'array',
            'roles.*' => 'nullable|string|max:100',
        ]);

        $created = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($request->referee_ids as $refereeId) {
                // Verifica se esiste già
                $exists = Assignment::where('tournament_id', $tournament->id)
                    ->where('user_id', $refereeId)
                    ->exists();

                if (! $exists) {
                    // Default role è 'Arbitro' se non specificato
                    $role = $request->roles[$refereeId] ?? 'Arbitro';

                    $data = [
                        'tournament_id' => $tournament->id,
                        'user_id' => $refereeId,
                        'role' => $role,
                        'assigned_at' => now(),
                        'assigned_by' => auth()->id(),
                        'status' => 'assigned',
                    ];

                    Assignment::create($data);
                    $created++;
                } else {
                    $skipped++;
                }
            }

            DB::commit();

            try {
                // ✅ HOOK: Auto-crea TournamentNotification se non esiste
                $existingNotification = TournamentNotification::where('tournament_id', $tournament->id)->first();

                if (! $existingNotification && $created > 0) {
                    TournamentNotification::create([
                        'tournament_id' => $tournament->id,
                        'status' => 'draft',
                        'created_by' => auth()->id(),
                        'notifications_data' => json_encode([
                            'referees_count' => $created,
                            'auto_created' => true,
                            'created_at' => now()->toISOString(),
                        ]),
                        'attachments' => json_encode([]),
                    ]);

                    Log::info('Auto-created TournamentNotification', [
                        'tournament_id' => $tournament->id,
                        'referees_count' => $created,
                    ]);
                }
            } catch (\Exception $e) {
                // Non bloccare l'assegnazione se la notifica fallisce
                Log::warning('Failed to auto-create TournamentNotification', [
                    'tournament_id' => $tournament->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $message = "Assegnati {$created} arbitri al torneo.";
            if ($skipped > 0) {
                $message .= " {$skipped} erano già assegnati.";
            }

            // Torna alla stessa pagina con parametro per mostrare modal di scelta
            return redirect()
                ->route('admin.assignments.assign-referees', $tournament)
                ->with('success', $message)
                ->with('show_next_step_modal', true);
        } catch (\Exception $e) {
            DB::rollback();

            return back()
                ->with('error', 'Errore durante l\'assegnazione: '.$e->getMessage());
        }
    }

    /**
     * Rimuovi assegnazione
     */
    public function removeFromTournament(Tournament $tournament, User $referee)
    {
        $assignment = Assignment::where('tournament_id', $tournament->id)
            ->where('user_id', $referee->id)
            ->first();

        if ($assignment) {
            $assignment->delete();

            return back()->with('success', 'Arbitro rimosso dal torneo');
        }

        return back()->with('error', 'Assegnazione non trovata');
    }

    /**
     * Helper: verifica accesso al torneo (usa il trait HasZoneVisibility)
     */
    private function checkTournamentAccess($tournament)
    {
        if (! $this->canAccessTournament($tournament)) {
            abort(403, 'Non autorizzato a gestire questo torneo');
        }
    }

    /**
     * Helper: controlla conflitti di date (placeholder)
     */
    private function checkDateConflicts($referees, $tournament)
    {
        // Implementa logica per controllare conflitti se necessario
        // Per ora è solo un placeholder

        foreach ($referees as $referee) {
            $referee->has_conflicts = false; // Default: nessun conflitto

            // Verifica conflitti di date (usando start_date/end_date)
            // La logica di conflitto può essere implementata se necessario
        }
    }

    /**
     * Remove assignment by ID
     */
    public function destroy(Assignment $assignment)
    {
        try {
            // Verifica permessi usando il trait
            if ($assignment->tournament && ! $this->canAccessTournament($assignment->tournament)) {
                return back()->with('error', 'Non hai i permessi per rimuovere questa assegnazione');
            }
            // Salva info per il messaggio e il redirect
            $refereeName = $assignment->user->name ?? 'Arbitro';
            $tournamentName = $assignment->tournament->name ?? 'Torneo';
            $tournamentId = $assignment->tournament_id;

            // Elimina l'assegnazione
            $assignment->delete();

            // Redirect alla pagina del torneo (non back() perché la show dell'assignment non esiste più)
            return redirect()
                ->route('admin.tournaments.show', $tournamentId)
                ->with('success', "Assegnazione di {$refereeName} rimossa dal torneo {$tournamentName}");
        } catch (\Exception $e) {
            return back()->with('error', 'Errore durante la rimozione: '.$e->getMessage());
        }
    }

    /**
     * Dashboard principale della validazione
     * GET /admin/assignment-validation
     */
    public function validation(Request $request): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        // Ottieni riepilogo di tutti i problemi
        $summary = $this->validationService->getValidationSummary($zoneId);

        // Ottieni statistiche aggiuntive
        $stats = [
            'total_assignments' => Assignment::when($zoneId, function ($q) use ($zoneId) {
                $q->whereHas('tournament', fn ($tq) => $tq->where('zone_id', $zoneId));
            })->count(),

            'active_tournaments' => Tournament::whereIn('status', ['open', 'closed'])
                ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                ->count(),

            'active_referees' => User::where('user_type', 'referee')
                ->where('is_active', true)
                ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                ->count(),
        ];

        // Calcola percentuale di problemi
        $issuePercentage = $stats['total_assignments'] > 0
            ? round(($summary['total_issues'] / $stats['total_assignments']) * 100, 1)
            : 0;

        return view('admin.assignments.validation.index', compact(
            'summary',
            'stats',
            'issuePercentage'
        ));
    }

    /**
     * Mostra tutti i conflitti di date
     * GET /admin/assignment-validation/conflicts
     */
    public function validationConflicts(Request $request): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        $conflicts = $this->validationService->detectDateConflicts($zoneId);

        // Ordina per severità
        $conflicts = $conflicts->sortByDesc('severity');

        // Aggiungi suggerimenti per risolvere i conflitti
        $conflictsWithSuggestions = $this->validationService->suggestConflictResolutions($conflicts);

        // Statistiche sui conflitti
        $conflictStats = [
            'total' => $conflicts->count(),
            'high_severity' => $conflicts->where('severity', 'high')->count(),
            'medium_severity' => $conflicts->where('severity', 'medium')->count(),
            'low_severity' => $conflicts->where('severity', 'low')->count(),
        ];

        return view('admin.assignments.validation.conflicts', compact(
            'conflictsWithSuggestions',
            'conflictStats'
        ));
    }

    /**
     * Mostra tornei con requisiti mancanti
     * GET /admin/assignment-validation/missing-requirements
     */
    public function missingRequirements(Request $request): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        $tournaments = $this->validationService->findMissingRequirements($zoneId);

        // Statistiche sui problemi
        $issueTypes = $tournaments->flatMap(function ($item) {
            return collect($item['issues'])->pluck('type');
        })->countBy()->toArray();

        $stats = [
            'total_tournaments' => $tournaments->count(),
            'issue_types' => $issueTypes,
            'high_severity' => $tournaments->filter(function ($item) {
                return collect($item['issues'])->contains('severity', 'high');
            })->count(),
        ];

        return view('admin.assignments.validation.missing-requirements', compact(
            'tournaments',
            'stats'
        ));
    }

    /**
     * Mostra arbitri sovrassegnati
     * GET /admin/assignment-validation/overassigned-referees
     */
    public function overassignedReferees(Request $request): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        // Threshold configurabile
        $threshold = $request->input('threshold', 5);

        $referees = $this->validationService->findOverassignedReferees($zoneId, $threshold);

        // Statistiche
        $stats = [
            'total_overassigned' => $referees->count(),
            'avg_assignments' => round($referees->avg('assignments_count'), 1),
            'max_assignments' => $referees->max('assignments_count'),
            'total_over_threshold' => $referees->sum('over_threshold'),
        ];

        return view('admin.assignments.validation.overassigned-referees', compact(
            'referees',
            'stats',
            'threshold'
        ));
    }

    /**
     * Mostra arbitri sottoutilizzati
     * GET /admin/assignment-validation/underassigned-referees
     */
    public function underassignedReferees(Request $request): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        // Threshold configurabile
        $threshold = $request->input('threshold', 2);

        $referees = $this->validationService->findUnderassignedReferees($zoneId, $threshold);

        // Filtra per stato disponibilità se richiesto
        if ($request->has('only_available')) {
            $referees = $referees->filter(function ($item) {
                return $item['availability_status'] === 'available';
            });
        }

        // Statistiche
        $stats = [
            'total_underassigned' => $referees->count(),
            'available' => $referees->where('availability_status', 'available')->count(),
            'unavailable' => $referees->where('availability_status', 'unavailable')->count(),
            'unknown' => $referees->where('availability_status', 'unknown')->count(),
        ];

        return view('admin.assignments.validation.underassigned-referees', compact(
            'referees',
            'stats',
            'threshold'
        ));
    }

    /**
     * Applica correzioni automatiche ai conflitti
     * POST /admin/assignment-validation/fix-conflicts
     */
    public function fixConflicts(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        try {
            $result = $this->validationService->applyAutomaticFixes($zoneId);

            if ($result['summary']['total_fixed'] > 0) {
                $message = "Risolti automaticamente {$result['summary']['total_fixed']} conflitti.";

                if ($result['summary']['total_failed'] > 0) {
                    $message .= " {$result['summary']['total_failed']} correzioni non sono riuscite.";
                }

                return redirect()
                    ->route('admin.assignment-validation.conflicts')
                    ->with('success', $message)
                    ->with('fix_details', $result);
            } else {
                return redirect()
                    ->route('admin.assignment-validation.conflicts')
                    ->with('info', 'Nessun conflitto può essere risolto automaticamente.');
            }
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.assignment-validation.conflicts')
                ->with('error', 'Errore durante la risoluzione automatica: '.$e->getMessage());
        }
    }

    /**
     * Helper per ottenere zone_id in base al tipo di utente (usa il trait HasZoneVisibility)
     */
    private function getZoneIdForUser($user): ?int
    {
        // Super admin e national admin vedono tutto
        if ($this->isNationalAdmin($user)) {
            return null;
        }

        // Zone admin vede solo la sua zona
        return $user->zone_id;
    }
}
