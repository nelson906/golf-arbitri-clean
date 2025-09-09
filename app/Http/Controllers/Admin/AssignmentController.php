<?php
// File: app/Http/Controllers/Admin/AssignmentController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Availability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignmentController extends Controller
{
    /**
     * Mostra form per assegnare arbitri a un torneo
     */
    public function assignReferees(Tournament $tournament)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Carica relazioni base del torneo
        $tournament->load(['club']);

        // Aggiungi la zona attraverso il club (Tournament non ha relazione diretta con zone)
        if ($tournament->club) {
            $tournament->club->load('zone');
            // Crea proprietà virtuale per retrocompatibilità
            $tournament->zone = $tournament->club->zone;
            $tournament->zone_id = $tournament->club->zone_id;
        }

        // Carica tipo torneo se esiste
        if (Schema::hasColumn('tournaments', 'tournament_type_id')) {
            $tournament->load('tournamentType');
        }

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
            'assignedReferees',
            'isNationalAdmin'
        ));
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

                    // Campi opzionali
                    if (Schema::hasColumn('users', 'referee_code')) {
                        $assignment->referee_code = $assignment->user->referee_code;
                    }
                    if (Schema::hasColumn('users', 'level')) {
                        $assignment->level = $assignment->user->level;
                    }

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

        // Controlla se esiste la tabella availabilities
        if (Schema::hasTable('availabilities')) {
            $query->whereHas('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);

                // Se esiste il campo is_available
                if (Schema::hasColumn('availabilities', 'is_available')) {
                    $q->where('is_available', true);
                }
            });
        }

        // Filtra attivi (gestisci diversi nomi del campo)
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'active')) {
            $query->where('active', true);
        }

        // Escludi già assegnati
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Ottieni arbitri della stessa zona che non hanno dichiarato disponibilità
     */
    private function getPossibleReferees($tournament, $excludeIds = [])
    {
        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per zona se disponibile
        if (isset($tournament->zone_id)) {
            $query->where('zone_id', $tournament->zone_id);
        }

        // Escludi quelli che hanno già dichiarato (se la tabella esiste)
        if (Schema::hasTable('availabilities')) {
            $query->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            });
        }

        // Filtra attivi
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'active')) {
            $query->where('active', true);
        }

        // Escludi già assegnati
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Ottieni arbitri nazionali/internazionali per tornei nazionali
     */
    private function getNationalReferees($tournament, $excludeIds = [], $availableReferees = null, $possibleReferees = null)
    {
        // Se il torneo non è nazionale, ritorna collezione vuota
        if (!isset($tournament->tournamentType) || !$tournament->tournamentType->is_national) {
            return collect();
        }

        $query = User::with('zone')
            ->where('user_type', 'referee');

        // Filtra per livello nazionale/internazionale
        if (Schema::hasColumn('users', 'level')) {
            $query->whereIn('level', ['N', 'I', 'nazionale', 'internazionale']);
        }

        // Filtra attivi
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'active')) {
            $query->where('active', true);
        }

        // Escludi già assegnati
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Escludi quelli già nelle altre liste
        if ($availableReferees) {
            $query->whereNotIn('id', $availableReferees->pluck('id'));
        }
        if ($possibleReferees) {
            $query->whereNotIn('id', $possibleReferees->pluck('id'));
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

        $userField = Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';
        $created = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($request->referee_ids as $refereeId) {
                // Verifica se esiste già
                $exists = Assignment::where('tournament_id', $tournament->id)
                    ->where($userField, $refereeId)
                    ->exists();

                if (!$exists) {
                    $data = [
                        'tournament_id' => $tournament->id,
                        $userField => $refereeId,
                        'role' => $request->roles[$refereeId] ?? null,
                    ];
                    if (Schema::hasColumn('assignments', 'assigned_at')) {
                        $data['assigned_at'] = now();
                    }
                    if (Schema::hasColumn('assignments', 'assignedBy')) {
                        $data['assignedBy'] = auth()->id();
                    }                    // Aggiungi status se il campo esiste
                    if (Schema::hasColumn('assignments', 'status')) {
                        $data['status'] = 'pending';
                    }

                    Assignment::create($data);
                    $created++;
                } else {
                    $skipped++;
                }
            }

            DB::commit();

            $message = "Assegnati {$created} arbitri al torneo.";
            if ($skipped > 0) {
                $message .= " {$skipped} erano già assegnati.";
            }

            return redirect()
                ->route('admin.assignments.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->with('error', 'Errore durante l\'assegnazione: ' . $e->getMessage());
        }
    }

    /**
     * Rimuovi assegnazione
     */
    public function removeFromTournament(Tournament $tournament, User $referee)
    {
        $userField = Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';

        $assignment = Assignment::where('tournament_id', $tournament->id)
            ->where($userField, $referee->id)
            ->first();

        if ($assignment) {
            $assignment->delete();
            return back()->with('success', 'Arbitro rimosso dal torneo');
        }

        return back()->with('error', 'Assegnazione non trovata');
    }

    /**
     * Helper: verifica accesso al torneo
     */
    private function checkTournamentAccess($tournament)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Se non è admin nazionale, verifica che il torneo sia della sua zona
        if (!$isNationalAdmin && $tournament->club && $tournament->club->zone_id != $user->zone_id) {
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

            // Se il campo date esiste, controlla conflitti
            if (Schema::hasColumn('tournaments', 'date') && isset($tournament->date)) {
                // Logica per verificare conflitti...
            }
        }
    }

    // ===== METODI STANDARD CRUD =====

    /**
     * Display lista assegnazioni
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        $query = Assignment::with(['tournament.club.zone', 'user', 'tournament.tournamentType']);
                // Filtri
        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Restrizioni per zona
        if (!$isNationalAdmin && $user->zone_id) {
            $query->whereHas('tournament.club', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $assignments = $query->orderBy('id', 'desc')->paginate(20);

        $tournaments = Tournament::with('club')->orderBy('name')->get();
        $referees = User::where('user_type', 'referee')->orderBy('name')->get();

        return view('admin.assignments.index', compact(
            'assignments',
            'tournaments',
            'referees',
            'isNationalAdmin'
        ));
    }

    /**
     * Show form creazione
     */
    public function create()
    {
        $tournaments = Tournament::with('club')->orderBy('name')->get();
        $referees = User::where('user_type', 'referee')->orderBy('name')->get();

        return view('admin.assignments.create', compact('tournaments', 'referees'));
    }

    /**
     * Store singola assegnazione
     */
    public function store(Request $request)
    {
        $userField = Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';

        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            $userField => 'required|exists:users,id',
            'role' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Verifica duplicati
        $exists = Assignment::where('tournament_id', $validated['tournament_id'])
            ->where($userField, $validated[$userField])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Arbitro già assegnato a questo torneo');
        }

        Assignment::create($validated);

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
            'assignedBy'
        ])
            ->find($assignmentId);

        if ($found) {
            $assignment = $found;
        }

        if (!$assignment) {
            abort(404, 'Assegnazione non trovata');
        }

        $this->checkAssignmentAccess($assignment);

        return view('admin.assignments.show', compact('assignment'));
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
            \Log::error('Error confirming assignment', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Errore durante la conferma dell\'assegnazione: ' . $e->getMessage());
        }
    }

}
