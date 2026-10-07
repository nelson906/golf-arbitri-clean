<?php

// File: app/Http/Controllers/Admin/AssignmentController.php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentRole;
use App\Enums\RefereeLevel;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;
use App\Services\AssignmentValidationService;
use App\Support\TournamentVisibility;
use App\Traits\HasZoneVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    use HasZoneVisibility;

    public function __construct(
        private readonly AssignmentValidationService $validationService,
    ) {}

    /**
     * Display lista assegnazioni
     */
    public function index(Request $request): View
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
    public function create(Request $request): View
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
                // Verifica accesso zona al torneo (IDOR fix)
                $this->checkTournamentAccess($tournament);

                // IDs arbitri già assegnati a questo torneo
                $assignedRefereeIds = $tournament->assignments()->pluck('user_id')->toArray();

                // Arbitri che hanno dato disponibilità per questo torneo
                $availableRefereeIds = $tournament->availabilities()->pluck('user_id')->toArray();
                $availableReferees = User::where('user_type', 'referee')
                    ->where('is_active', true)
                    ->whereIn('id', $availableRefereeIds)
                    ->whereNotIn('id', $assignedRefereeIds)
                    ->when($isNationalAdmin, fn ($q) => $q->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]))
                    ->orderBy('name')
                    ->get();

                // Altri arbitri
                $zoneId = (! $isNationalAdmin && $user && $user->isZoneAdmin()) ? $user->zone_id : null;

                $otherReferees = User::where('user_type', 'referee')
                    ->where('is_active', true)
                    ->whereNotIn('id', $availableRefereeIds)
                    ->whereNotIn('id', $assignedRefereeIds)
                    ->when($isNationalAdmin, fn ($q) => $q->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]))
                    ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                    ->orderBy('name')
                    ->get();
            }
        }

        $tournamentsQuery = Tournament::where('end_date', '>=', now()->startOfDay());
        $this->applyTournamentVisibility($tournamentsQuery, $user);
        $tournaments = $tournamentsQuery->orderBy('start_date')->get();

        return view('admin.assignments.create', compact(
            'tournament',
            'availableReferees',
            'otherReferees',
            'tournaments'
        ));
    }

    /**
     * Store singola assegnazione
     *
     * NOTA (audit 2026-06): usa AssignmentRequest (prima era FormRequest orfana,
     * mai cablata): authorize() verifica ruolo+zona, rules() aggiunge controlli
     * business (arbitro attivo, non gia' assegnato, stessa zona per i zonali).
     * P6 (2026-10-03): max arbitri e livello richiesto sono solo indicazioni.
     */
    public function store(AssignmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Difesa in profondità: oltre ad authorize() della FormRequest,
        // applica le regole di visibilità complete (es. national admin
        // limitato ai tornei nazionali) — IDOR fix
        $tournament = Tournament::findOrFail($request->integer('tournament_id'));
        $this->checkTournamentAccess($tournament);

        $exists = Assignment::where('tournament_id', $validated['tournament_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Arbitro già assegnato a questo torneo');
        }

        $role = $validated['role'] ?? null;
        if (! is_string($role) || $role === '') {
            $role = $this->isObserverOnly($tournament)
                ? AssignmentRole::Observer->value
                : AssignmentRole::default()->value;
        }
        $validated['role'] = $role;

        if (! $this->observerRoleAllowed($tournament, $role)) {
            return back()->withInput()->with('error', 'Sui tornei nazionali l\'admin di zona designa solo osservatori.');
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
     *
     * @param  int|string  $assignmentId
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

        $roles = AssignmentRole::values();

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
            'role' => ['required', Rule::enum(AssignmentRole::class)],
            'notes' => 'nullable|string|max:1000',
        ]);

        // P9: sui nazionali l'admin di zona modifica solo osservatori, e solo come osservatori
        if (! $this->observerRoleAllowed($assignment->tournament, $assignment->role)
            || ! $this->observerRoleAllowed($assignment->tournament, $request->string('role')->toString())) {
            return back()->withInput()->with('error', 'Sui tornei nazionali l\'admin di zona gestisce solo gli osservatori.');
        }

        // Verifica che il nuovo arbitro non sia già assegnato allo stesso torneo
        // Usa !== (strict) per evitare problemi di type juggling stringa/int
        if ((int) $validated['user_id'] !== (int) $assignment->user_id) {
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
     *
     * @param  \App\Models\Assignment  $assignment
     */
    private function checkAssignmentAccess($assignment): void
    {
        $this->checkTournamentAccess($assignment->tournament);
    }

    /**
     * Mostra form per assegnare arbitri a un torneo
     */
    public function assignReferees(Tournament $tournament): View
    {
        // Verifica accesso zona al torneo (IDOR fix: il middleware controlla solo il ruolo)
        $this->checkTournamentAccess($tournament);

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
        /** @var list<int> $assignedRefereeIds */
        $assignedRefereeIds = $assignedReferees->pluck('user_id')->values()->all();

        // Ottieni arbitri disponibili (hanno dichiarato disponibilità)
        $availableReferees = $this->getAvailableReferees($tournament, $assignedRefereeIds);

        // Ottieni arbitri possibili (stessa zona, non hanno dichiarato disponibilità)
        $possibleReferees = $this->getPossibleReferees($tournament, $assignedRefereeIds);

        // Ottieni arbitri nazionali (per tornei nazionali)
        $nationalReferees = $this->getNationalReferees($tournament, $assignedRefereeIds, $availableReferees);

        // P7 (2026-10-03): altre designazioni della stagione e conflitti di date
        $refereeLoad = $this->buildRefereeLoad(
            $tournament,
            collect([$availableReferees, $possibleReferees, $nationalReferees])->flatten(1)->pluck('id')
        );

        // P9 (2026-10-03): sui tornei nazionali l'admin di zona designa solo osservatori
        $observerOnly = $this->isObserverOnly($tournament);

        return view('admin.assignments.assign-referees', compact(
            'tournament',
            'availableReferees',
            'possibleReferees',
            'nationalReferees',
            'assignedReferees',
            'refereeLoad',
            'observerOnly'
        ))->with('isNationalAdmin', $this->isNationalAdmin());
    }

    /**
     * P9 (2026-10-03): sui tornei nazionali l'admin di zona (SZR) designa solo
     * gli osservatori; arbitri e Direttore di Torneo sono del CRC.
     */
    private function isObserverOnly(Tournament $tournament): bool
    {
        return $this->isZoneAdmin() && ($tournament->tournamentType->is_national ?? false);
    }

    /**
     * P9: un admin di zona su un torneo nazionale puo' toccare solo il ruolo Osservatore.
     */
    private function observerRoleAllowed(Tournament $tournament, ?string $role): bool
    {
        return ! $this->isObserverOnly($tournament) || $role === AssignmentRole::Observer->value;
    }

    /**
     * P7 (2026-10-03): per ogni arbitro candidato, le altre designazioni della
     * stessa stagione e quelle che si sovrappongono alle date del torneo.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $refereeIds
     * @return array<int, array{count: int, conflicts: list<array{name: string, dates: string, role: string}>, others: list<array{name: string, dates: string, role: string}>}>
     */
    private function buildRefereeLoad(Tournament $tournament, Collection $refereeIds): array
    {
        if ($refereeIds->isEmpty()) {
            return [];
        }

        $start = $tournament->start_date->copy()->startOfDay();
        $end = ($tournament->end_date ?? $tournament->start_date)->copy()->endOfDay();

        $others = Assignment::with('tournament:id,name,start_date,end_date')
            ->whereIn('user_id', $refereeIds->all())
            ->where('tournament_id', '!=', $tournament->id)
            ->whereHas('tournament', fn ($q) => $q->whereYear('start_date', $start->year))
            ->get()
            ->groupBy('user_id');

        $load = [];

        foreach ($others as $userId => $assignments) {
            $entry = ['count' => 0, 'conflicts' => [], 'others' => []];

            foreach ($assignments->sortBy('tournament.start_date') as $a) {
                $t = $a->tournament;
                if (! $t) {
                    continue;
                }

                $tStart = $t->start_date;
                $tEnd = $t->end_date ?? $t->start_date;
                $row = [
                    'name' => $t->name,
                    'dates' => $tStart->format('d/m').($tEnd->isSameDay($tStart) ? '' : '–'.$tEnd->format('d/m')),
                    'role' => (string) $a->role,
                ];

                $entry['count']++;
                $entry['others'][] = $row;

                if ($tStart <= $end && $tEnd >= $start) {
                    $entry['conflicts'][] = $row;
                }
            }

            $load[(int) $userId] = $entry;
        }

        return $load;
    }

    /**
     * Ottieni arbitri già assegnati al torneo
     *
     * @param  \App\Models\Tournament  $tournament
     * @return Collection<int, \App\Models\Assignment>
     */
    private function getAssignedReferees($tournament): Collection
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
     *
     * @param  \App\Models\Tournament  $tournament
     * @param  list<int>  $excludeIds
     * @return Collection<int, \App\Models\User>
     */
    private function getAvailableReferees($tournament, $excludeIds = []): Collection
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
            $query->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]);
        }

        // Escludi già assegnati
        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Ottieni arbitri della stessa zona che non hanno dichiarato disponibilità
     *
     * @param  \App\Models\Tournament  $tournament
     * @param  list<int>  $excludeIds
     * @return Collection<int, \App\Models\User>
     */
    private function getPossibleReferees($tournament, $excludeIds = []): Collection
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
     *
     * @param  \App\Models\Tournament  $tournament
     * @param  list<int>  $excludeIds
     * @param  \Illuminate\Support\Collection<int, \App\Models\User>|null  $availableReferees
     * @return Collection<int, \App\Models\User>
     */
    private function getNationalReferees($tournament, $excludeIds = [], $availableReferees = null): Collection
    {
        // CRC admin: mostra sempre arbitri nazionali (che non hanno dato disponibilità)
        // Per admin zonali: mostra solo se il torneo è nazionale
        if (! $this->isNationalAdmin() && (! isset($tournament->tournamentType) || ! $tournament->tournamentType->is_national)) {
            return collect();
        }

        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per livello nazionale/internazionale
        $query->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]);

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
    public function storeMultiple(Request $request, Tournament $tournament): RedirectResponse
    {
        // Verifica accesso zona al torneo (IDOR fix: il middleware controlla solo il ruolo)
        $this->checkTournamentAccess($tournament);

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
            // Precaricare in una sola query tutti gli user_id già assegnati al torneo
            $existingUserIds = Assignment::where('tournament_id', $tournament->id)
                ->pluck('user_id')
                ->toArray();

            $roles = $request->array('roles');
            $observerOnly = $this->isObserverOnly($tournament);

            foreach ($request->array('referee_ids') as $refereeId) {
                if (in_array($refereeId, $existingUserIds)) {
                    $skipped++;
                    continue;
                }

                $defaultRole = $observerOnly
                    ? AssignmentRole::Observer->value
                    : AssignmentRole::default()->value;

                $role = is_string($refereeId) || is_int($refereeId)
                    ? ($roles[$refereeId] ?? null)
                    : null;
                // "Seleziona ruolo" lasciato vuoto = ruolo di default
                $role = is_string($role) && $role !== '' ? $role : $defaultRole;

                // P9: sui nazionali l'admin di zona designa solo osservatori
                if (! $this->observerRoleAllowed($tournament, $role)) {
                    DB::rollBack();

                    return back()->with('error', 'Sui tornei nazionali l\'admin di zona designa solo osservatori: arbitri e Direttore di Torneo sono del CRC.');
                }

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
            }

            DB::commit();

            try {
                // ✅ HOOK: Auto-crea TournamentNotification se non esiste
                $existingNotification = TournamentNotification::where('tournament_id', $tournament->id)->first();

                // P13 (2026-10-03): solo i tornei zonali hanno una bozza automatica.
                // Le notifiche nazionali (CRC arbitri, SZR osservatori) nascono all'invio.
                $isNational = $tournament->tournamentType->is_national ?? false;

                if (! $existingNotification && $created > 0 && ! $isNational) {
                    TournamentNotification::create([
                        'tournament_id'     => $tournament->id,
                        'notification_type' => null,
                        'status'            => 'pending',
                        'sent_by'           => auth()->id(),
                        'details'           => [
                            'referees_count' => $created,
                            'auto_created'   => true,
                        ],
                    ]);

                    Log::info('Auto-created TournamentNotification', [
                        'tournament_id' => $tournament->id,
                        'referees_count' => $created,
                        'is_national' => $isNational,
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
    public function removeFromTournament(Tournament $tournament, User $referee): RedirectResponse
    {
        // Verifica accesso zona al torneo (IDOR fix: il middleware controlla solo il ruolo)
        $this->checkTournamentAccess($tournament);

        $assignment = Assignment::where('tournament_id', $tournament->id)
            ->where('user_id', $referee->id)
            ->first();

        if ($assignment && ! $this->observerRoleAllowed($tournament, $assignment->role)) {
            return back()->with('error', 'Sui tornei nazionali l\'admin di zona può rimuovere solo gli osservatori.');
        }

        if ($assignment) {
            $assignment->delete();

            return back()->with('success', 'Arbitro rimosso dal torneo');
        }

        return back()->with('error', 'Assegnazione non trovata');
    }

    /**
     * Helper: verifica accesso al torneo (usa il trait HasZoneVisibility)
     *
     * @param  \App\Models\Tournament  $tournament
     */
    private function checkTournamentAccess($tournament): void
    {
        if (! $this->canAccessTournament($tournament)) {
            abort(403, 'Non autorizzato a gestire questo torneo');
        }
    }

    // Nota: il controllo conflitti di date è gestito da
    // AssignmentValidationService::detectDateConflicts() e
    // AssignmentValidationService::suggestConflictResolutions().

    /**
     * Remove assignment by ID
     */
    public function destroy(Assignment $assignment): RedirectResponse
    {
        try {
            // Verifica permessi usando il trait
            if ($assignment->tournament && ! $this->canAccessTournament($assignment->tournament)) {
                return back()->with('error', 'Non hai i permessi per rimuovere questa assegnazione');
            }
            if ($assignment->tournament && ! $this->observerRoleAllowed($assignment->tournament, $assignment->role)) {
                return back()->with('error', 'Sui tornei nazionali l\'admin di zona può rimuovere solo gli osservatori.');
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
    public function validation(): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        // Ottieni riepilogo di tutti i problemi
        $nationalOnly = $this->validationForCrc($user);
        $summary = $this->validationService->getValidationSummary($zoneId, $nationalOnly);

        // Statistiche aggiuntive, con la stessa visibilita' delle liste
        $stats = [
            'total_assignments' => TournamentVisibility::applyViaRelation(Assignment::query(), $user)->count(),

            'active_tournaments' => TournamentVisibility::apply(Tournament::query(), $user)
                ->where('end_date', '>=', now()->startOfDay())
                ->count(),

            'active_referees' => User::where('user_type', 'referee')
                ->where('is_active', true)
                ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                ->when($nationalOnly, fn ($q) => $q->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]))
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
    public function validationConflicts(): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        $conflicts = $this->validationService->detectDateConflicts($zoneId, $this->validationForCrc($user));

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
    public function missingRequirements(): View
    {
        $user = auth()->user();
        $zoneId = $this->getZoneIdForUser($user);

        $tournaments = $this->validationService->findMissingRequirements($zoneId, $this->validationForCrc($user));

        // Statistiche sui problemi
        $issueTypes = $tournaments->flatMap(function ($item) {
            return collect((array) $item['issues'])->pluck('type');
        })->countBy()->toArray();

        $stats = [
            'total_tournaments' => $tournaments->count(),
            'issue_types' => $issueTypes,
            'high_severity' => $tournaments->filter(function ($item) {
                return collect((array) $item['issues'])->contains('severity', 'high');
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

        // Threshold configurabile — minimo 1 per evitare che valori negativi o zero
        // restituiscano tutti gli arbitri come "sovrassegnati"
        $threshold = max(1, $request->integer('threshold', 5));

        $referees = $this->validationService->findOverassignedReferees($zoneId, $threshold, $this->validationForCrc($user));

        // Statistiche
        $stats = [
            'total_overassigned' => $referees->count(),
            'avg_assignments' => round((float) $referees->avg('assignments_count'), 1),
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

        // Threshold configurabile — minimo 1 per evitare che valori negativi o zero
        // restituiscano tutti gli arbitri come "sottoutilizzati"
        $threshold = max(1, $request->integer('threshold', 2));

        $referees = $this->validationService->findUnderassignedReferees($zoneId, $threshold, $this->validationForCrc($user));

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
     * Validazione Assegnazioni per il CRC: solo tornei nazionali e arbitri di
     * livello nazionale, come nel resto delle sue liste. Il super admin vede tutto.
     */
    private function validationForCrc(?User $user): bool
    {
        return $user?->user_type === UserType::NationalAdmin;
    }
}
