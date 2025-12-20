<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use App\Services\CareerHistoryService;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CareerHistoryController extends Controller
{
    use HasZoneVisibility;

    protected CareerHistoryService $careerService;

    public function __construct(CareerHistoryService $careerService)
    {
        $this->careerService = $careerService;
    }

    /**
     * Lista arbitri con storico carriera.
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $zoneRestriction = $this->getUserZoneId($currentUser);

        $query = User::where('user_type', 'referee')
            ->with(['careerHistory', 'zone'])
            ->withCount(['assignments', 'availabilities']);

        // Zone filter for non-super_admin
        if ($zoneRestriction !== null) {
            $query->where('zone_id', $zoneRestriction);
        }

        // Additional zone filter from request (for super_admin)
        if ($request->filled('zone_id') && $this->isSuperAdmin()) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro per chi ha/non ha storico
        if ($request->filled('has_history')) {
            if ($request->has_history === '1') {
                $query->whereHas('careerHistory');
            } else {
                $query->whereDoesntHave('careerHistory');
            }
        }

        $referees = $query->orderBy('name')->paginate(30);

        // Get zones for filter (only for super_admin)
        $zones = $this->isSuperAdmin($currentUser) ? Zone::orderBy('name')->get() : collect();
        $canArchiveAll = $this->isSuperAdmin($currentUser);

        return view('admin.career-history.index', compact('referees', 'zones', 'canArchiveAll'));
    }

    /**
     * Mostra lo storico di un arbitro.
     */
    public function show(User $user)
    {
        // Check zone access
        $currentUser = auth()->user();
        if (! $this->isSuperAdmin($currentUser) && $user->zone_id !== $this->getUserZoneId($currentUser)) {
            abort(403, 'Non hai accesso a questo arbitro');
        }

        $history = RefereeCareerHistory::where('user_id', $user->id)->first();

        return view('admin.career-history.show', compact('user', 'history'));
    }

    /**
     * Form per archiviare un anno.
     */
    public function archiveForm()
    {
        $currentUser = auth()->user();
        $currentYear = now()->year;
        $zoneRestriction = $this->getUserZoneId($currentUser);

        // Statistiche per preview (filtrate per zona se necessario)
        $stats = $this->getYearStats($currentYear, $zoneRestriction);

        // Get referees for dropdown (filtered by zone)
        $refereesQuery = User::where('user_type', 'referee')->orderBy('name');
        if ($zoneRestriction !== null) {
            $refereesQuery->where('zone_id', $zoneRestriction);
        }
        $referees = $refereesQuery->get(['id', 'name', 'email']);

        $canArchiveAll = $this->isSuperAdmin($currentUser);

        return view('admin.career-history.archive-form', compact('currentYear', 'stats', 'referees', 'canArchiveAll'));
    }

    /**
     * Esegue l'archiviazione di un anno.
     */
    public function processArchive(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:'.now()->year,
            'user_id' => 'nullable|exists:users,id',
            'clear_data' => 'nullable|boolean',
        ]);

        $year = (int) $request->year;
        $userId = $request->user_id;
        $clearData = $request->boolean('clear_data', false);
        $currentUser = auth()->user();

        // Only super_admin can clear data
        if ($clearData && ! $this->isSuperAdmin($currentUser)) {
            return redirect()
                ->back()
                ->with('error', 'Solo il super admin puo svuotare le tabelle');
        }

        // If not super_admin, must specify a user (can't archive all)
        if (! $this->isSuperAdmin($currentUser) && ! $userId) {
            return redirect()
                ->back()
                ->with('error', 'Devi selezionare un arbitro specifico');
        }

        // If user specified, check zone access
        if ($userId) {
            $targetUser = User::find($userId);
            if ($targetUser && ! $this->isSuperAdmin($currentUser) && $targetUser->zone_id !== $this->getUserZoneId($currentUser)) {
                abort(403, 'Non hai accesso a questo arbitro');
            }
        }

        try {
            if ($userId) {
                // Archivia solo per un utente
                $result = $this->careerService->archiveYearForUser($userId, $year);
                $targetUser = User::find($userId);

                return redirect()
                    ->route('admin.career-history.show', $targetUser)
                    ->with('success', "Anno {$year} archiviato per {$targetUser?->name}: {$result['tournaments_count']} tornei, {$result['assignments_count']} assegnazioni");
            } else {
                // Archivia per tutti (solo super_admin)
                $stats = $this->careerService->archiveYear($year, false);

                $message = "Anno {$year} archiviato: {$stats['referees_processed']} arbitri, {$stats['assignments_archived']} assegnazioni";

                if (! empty($stats['errors'])) {
                    $message .= " ({$stats['errors']} errori)";
                }
                // Svuota le tabelle se richiesto
                if ($clearData && empty($stats['errors'])) {
                    $clearStats = $this->careerService->clearSourceData($year);
                    $message .= ". Tabelle svuotate: {$clearStats['tournaments_deleted']} tornei, {$clearStats['assignments_deleted']} assegnazioni, {$clearStats['availabilities_deleted']} disponibilita eliminate.";
                }

                return redirect()
                    ->route('admin.career-history.index')
                    ->with('success', $message);
            }
        } catch (\Exception $e) {
            Log::error('Errore archiviazione career history', [
                'year' => $year,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Errore durante l\'archiviazione: '.$e->getMessage());
        }
    }

    /**
     * Form per modificare un anno specifico.
     */
    public function editYear(User $user, int $year)
    {
        // Check zone access
        $currentUser = auth()->user();
        if (! $this->isSuperAdmin($currentUser) && $user->zone_id !== $this->getUserZoneId($currentUser)) {
            abort(403, 'Non hai accesso a questo arbitro');
        }

        $history = RefereeCareerHistory::where('user_id', $user->id)->first();
        if (! $history) {
            return redirect()
                ->route('admin.career-history.show', $user)
                ->with('error', 'Nessuno storico trovato per questo arbitro');
        }

        $tournaments = $history->tournaments_by_year[$year] ?? [];
        $assignments = $history->assignments_by_year[$year] ?? [];
        $availabilities = $history->availabilities_by_year[$year] ?? [];

        // Tornei disponibili per aggiunta
        $availableTournaments = Tournament::whereYear('start_date', $year)
            ->whereNotIn('id', collect($tournaments)->pluck('id'))
            ->with('club')
            ->orderBy('start_date')
            ->get();

        return view('admin.career-history.edit-year', compact(
            'user',
            'history',
            'year',
            'tournaments',
            'assignments',
            'availabilities',
            'availableTournaments'
        ));
    }

    /**
     * Aggiunge un torneo allo storico.
     */
    public function addTournament(Request $request, User $user)
    {
        // Check zone access
        $currentUser = auth()->user();
        if (! $this->isSuperAdmin($currentUser) && $user->zone_id !== $this->getUserZoneId($currentUser)) {
            abort(403, 'Non hai accesso a questo arbitro');
        }

        $request->validate([
            'year' => 'required|integer',
            'tournament_id' => 'required|exists:tournaments,id',
            'role' => 'nullable|string|max:100',
        ]);

        $tournament = Tournament::with('club')->find($request->tournament_id);

        if (! $tournament) {
            return redirect()
                ->back()
                ->with('error', 'Torneo non trovato');
        }

        $tournamentData = [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'club_id' => $tournament->club_id,
            'club_name' => $tournament->club->name ?? null,
            'start_date' => $tournament->start_date?->format('Y-m-d') ?? '',
            'end_date' => $tournament->end_date?->format('Y-m-d') ?? '',
        ];

        $this->careerService->addTournamentEntry($user->id, $request->year, $tournamentData);

        // Se c'è un ruolo, aggiungi anche l'assegnazione
        if ($request->filled('role')) {
            $history = RefereeCareerHistory::where('user_id', $user->id)->first();
            if ($history) {
                $assignments = $history->assignments_by_year ?? [];

                if (! isset($assignments[$request->year])) {
                    $assignments[$request->year] = [];
                }

                $assignments[$request->year][] = [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'role' => $request->role,
                    'assigned_at' => now()->format('Y-m-d'),
                    'status' => 'manual_entry',
                ];

                $history->assignments_by_year = $assignments;
                $history->career_stats = $history->generateStatsSummary();
                $history->save();
            }
        }

        return redirect()
            ->route('admin.career-history.edit-year', [$user, $request->year])
            ->with('success', "Torneo '{$tournament->name}' aggiunto");
    }

    /**
     * Aggiunge piu tornei allo storico in una volta.
     */
    public function addMultipleTournaments(Request $request, User $user)
    {
        // Check zone access
        $currentUser = auth()->user();
        if (! $this->isSuperAdmin($currentUser) && $user->zone_id !== $this->getUserZoneId($currentUser)) {
            abort(403, 'Non hai accesso a questo arbitro');
        }

        $request->validate([
            'year' => 'required|integer',
            'tournament_ids' => 'required|array|min:1',
            'tournament_ids.*' => 'exists:tournaments,id',
            'role' => 'nullable|string|max:100',
        ]);

        $tournaments = Tournament::with('club')
            ->whereIn('id', $request->tournament_ids)
            ->get();

        $addedCount = 0;
        $history = null;

        foreach ($tournaments as $tournament) {
            $tournamentData = [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'club_id' => $tournament->club_id,
                'club_name' => $tournament->club->name ?? null,
                'start_date' => $tournament->start_date?->format('Y-m-d') ?? '',
                'end_date' => $tournament->end_date?->format('Y-m-d') ?? '',
            ];

            $this->careerService->addTournamentEntry($user->id, $request->year, $tournamentData);
            $addedCount++;

            // Se c'e un ruolo, aggiungi anche l'assegnazione
            if ($request->filled('role')) {
                if (! $history) {
                    $history = RefereeCareerHistory::where('user_id', $user->id)->first();
                }

                if ($history) {
                    $assignments = $history->assignments_by_year ?? [];

                    if (! isset($assignments[$request->year])) {
                        $assignments[$request->year] = [];
                    }

                    $assignments[$request->year][] = [
                        'tournament_id' => $tournament->id,
                        'tournament_name' => $tournament->name,
                        'role' => $request->role,
                        'assigned_at' => now()->format('Y-m-d'),
                        'status' => 'manual_entry',
                    ];

                    $history->assignments_by_year = $assignments;
                }
            }
        }

        // Save history with all assignments
        if ($history && $request->filled('role')) {
            $history->career_stats = $history->generateStatsSummary();
            $history->save();
        }

        return redirect()
            ->route('admin.career-history.edit-year', [$user, $request->year])
            ->with('success', "{$addedCount} tornei aggiunti allo storico");
    }

    /**
     * Rimuove un torneo dallo storico.
     */
    public function removeTournament(Request $request, User $user)
    {
        // Check zone access
        $currentUser = auth()->user();
        if (! $this->isSuperAdmin($currentUser) && $user->zone_id !== $this->getUserZoneId($currentUser)) {
            abort(403, 'Non hai accesso a questo arbitro');
        }

        $request->validate([
            'year' => 'required|integer',
            'tournament_id' => 'required|integer',
        ]);

        $removed = $this->careerService->removeTournamentEntry(
            $user->id,
            $request->year,
            $request->tournament_id
        );

        // Rimuovi anche dalle assegnazioni
        $history = RefereeCareerHistory::where('user_id', $user->id)->first();
        if ($history) {
            $assignments = $history->assignments_by_year ?? [];
            if (isset($assignments[$request->year])) {
                $assignments[$request->year] = array_values(array_filter(
                    $assignments[$request->year],
                    fn ($a) => $a['tournament_id'] != $request->tournament_id
                ));
                $history->assignments_by_year = $assignments;
                $history->career_stats = $history->generateStatsSummary();
                $history->save();
            }
        }

        return redirect()
            ->route('admin.career-history.edit-year', [$user, $request->year])
            ->with($removed ? 'success' : 'warning', $removed ? 'Torneo rimosso' : 'Torneo non trovato');
    }

    /**
     * Preview dati anno prima di archiviare.
     */
    public function previewYear(Request $request)
    {
        $currentUser = auth()->user();
        $year = $request->get('year', now()->year);
        $zoneRestriction = $this->getUserZoneId($currentUser);

        $stats = $this->getYearStats($year, $zoneRestriction);

        return response()->json($stats);
    }

    /**
     * Calcola statistiche per un anno.
     */
    private function getYearStats(int $year, ?int $zoneId = null): array
    {
        $assignmentsQuery = \App\Models\Assignment::whereYear('assigned_at', $year);
        $availabilitiesQuery = \App\Models\Availability::whereHas('tournament', function ($q) use ($year) {
            $q->whereYear('start_date', $year);
        });
        $tournamentsQuery = Tournament::whereYear('start_date', $year);

        // Apply zone filter if needed
        if ($zoneId !== null) {
            $assignmentsQuery->whereHas('user', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
            $availabilitiesQuery->whereHas('user', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
            $tournamentsQuery->whereHas('club', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return [
            'year' => $year,
            'zone_id' => $zoneId,
            'referees_with_assignments' => (clone $assignmentsQuery)
                ->distinct('user_id')
                ->count('user_id'),
            'total_assignments' => (clone $assignmentsQuery)->count(),
            'referees_with_availabilities' => (clone $availabilitiesQuery)
                ->distinct('user_id')
                ->count('user_id'),
            'total_availabilities' => (clone $availabilitiesQuery)->count(),
            'tournaments_count' => (clone $tournamentsQuery)->count(),
        ];
    }
}
