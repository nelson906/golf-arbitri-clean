<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Carica arbitri
        $referees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Carica tornei per l'anno selezionato
        $tournaments = collect();

        $tournaments = Tournament::with(['club'])
            ->orderBy('start_date', 'desc')
            ->get();


        $query = Assignment::with(['user', 'tournament.club', 'tournament.zone', 'assignedBy'])
            ->when($request->filled('tournament_id'), function ($q) use ($request) {
                $q->where('tournament_id', $request->tournament_id);
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', $request->role);
            });

        // if ($user->user_type === 'admin' && $user->zone_id) {
        //     // Filtra assignments per zona tramite tournament
        //     $query->whereExists(function ($query) use ($user) {
        //         $query->select(DB::raw(1))
        //             ->from("tournaments")
        //             ->whereColumn("tournaments.id", "assignments.tournament_id")
        //             ->where("tournaments.zone_id", $user->zone_id);
        //     });
        // }

        // Zone filtering
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $assignments = $query->orderByDesc('assigned_at')->paginate(20);

        // Carica i tornei separatamente per evitare problemi di relazione
        $tournamentIds = $assignments->pluck('tournament_id')->unique();
        $tournamentsData = [];

        if ($tournamentIds->isNotEmpty()) {
            $tournamentsData = Tournament::with('club')
                ->whereIn('id', $tournamentIds)
                ->get()
                ->keyBy('id');
        }

        // Trasforma i dati per la view
        $assignments->getCollection()->transform(function ($assignment) use ($tournamentsData) {
            $assignment->referee_name = $assignment->user->name ?? 'N/A';

            $tournament = $tournamentsData[$assignment->tournament_id] ?? null;
            $assignment->tournament_name = $tournament->name ?? 'N/A';
            $assignment->club_name = $tournament->club->name ?? 'N/A';
            $assignment->tournament_start_date = $tournament->start_date ?? null;

            return $assignment;
        });

        return view('admin.assignments.index', compact(
            'assignments',
            'tournaments',
            'referees'
        ));

        return view('admin.assignments.index', compact('assignments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:' . implode(',', array_keys(Assignment::ROLES)),
            'notes' => 'nullable|string',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $referee = User::findOrFail($request->user_id);

        // Check if already assigned
        if (Assignment::where('tournament_id', $tournament->id)
            ->where('user_id', $referee->id)
            ->exists()
        ) {
            return back()->with('error', 'Arbitro già assegnato a questo torneo');
        }

        Assignment::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => $request->role,
            'notes' => $request->notes,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]);

        return back()->with('success', "Arbitro {$referee->name} assegnato come {$request->role}");
    }

    public function destroy(Assignment $assignment)
    {
        $assignment->delete();

        return back()->with('success', 'Assegnazione rimossa con successo');
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
                        'tournament.zone',
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
     * Check if user can access the tournament.
     */
    private function checkTournamentAccess($tournament): void
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            // Per ora permetti l'accesso, in futuro potresti fare abort(403)
            return;
        }
    }
    /**
     * Show assignment interface for a specific tournament.
     */
    public function assignReferees(Tournament $tournament): View
    {
        $this->checkTournamentAccess($tournament);

        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Load tournament with relations
        // CORRETTO ✅
        $tournament->load(['club', 'zone', 'tournamentType']);

        // Get currently assigned referees - CORRETTO ✅
        $assignedReferees = $tournament->assignments()
            ->with('user')
            ->get()
            ->map(function ($assignment) {
                // Aggiungi i dati user all'assignment per retrocompatibilità
                $assignment->name = $assignment->user->name;
                $assignment->level = $assignment->user->level;
                return $assignment;
            });
        $assignedRefereeIds = $assignedReferees->pluck('user_id')->toArray();

        // Get available referees - CORRETTO ✅
        $availableReferees = User::with('zone')
            ->whereHas('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->whereNotIn('id', $assignedRefereeIds)
            ->orderBy('name')
            ->get();

        // Get possible referees (zone referees who haven't declared availability) - EXCLUDE already assigned
        $possibleReferees = User::with(['referee', 'zone'])
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->where('zone_id', $tournament->zone_id)
            ->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->whereNotIn('id', $assignedRefereeIds)
            ->orderBy('name')
            ->get();


        // Get national referees (for national tournaments) - EXCLUDE already assigned
        $nationalReferees = collect();
        if ($tournament->tournamentType->is_national) {
            $nationalReferees = User::with(['referee', 'zone'])
                ->where('user_type', 'referee')
                ->where('is_active', true)
                ->whereHas('referee', function ($q) {
                    $q->whereIn('level', ['nazionale', 'internazionale']);
                })
                ->whereNotIn('id', $assignedRefereeIds)
                ->whereNotIn('id', $availableReferees->pluck('id')->merge($possibleReferees->pluck('id')))
                ->orderBy('name')
                ->get();
        }

        // Check conflicts for all referees
        $this->checkDateConflicts($availableReferees, $tournament);
        $this->checkDateConflicts($possibleReferees, $tournament);
        $this->checkDateConflicts($nationalReferees, $tournament);

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
     * Check date conflicts for referees.
     */
    private function checkDateConflicts($referees, Tournament $tournament)
    {
        foreach ($referees as $referee) {
            $conflicts = Assignment::where('user_id', $referee->id)
                ->whereHas('tournament', function ($q) use ($tournament) {
                    $q->where('id', '!=', $tournament->id)
                        ->where(function ($q2) use ($tournament) {
                            // Tournament dates overlap
                            $q2->whereBetween('start_date', [$tournament->start_date, $tournament->end_date])
                                ->orWhereBetween('end_date', [$tournament->start_date, $tournament->end_date])
                                ->orWhere(function ($q3) use ($tournament) {
                                    $q3->where('start_date', '<=', $tournament->start_date)
                                        ->where('end_date', '>=', $tournament->end_date);
                                });
                        });
                })
                ->with('tournament:id,name,start_date,end_date')
                ->get();

            $referee->conflicts = $conflicts;
            $referee->has_conflicts = $conflicts->count() > 0;
        }
    }

}
