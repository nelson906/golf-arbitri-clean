<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefereeCareerHistory;
use App\Models\User;
use App\Models\Tournament;
use App\Services\CareerHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CareerHistoryController extends Controller
{
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
        $query = User::where('user_type', 'referee')
            ->with('careerHistory')
            ->withCount(['assignments', 'availabilities']);

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

        return view('admin.career-history.index', compact('referees'));
    }

    /**
     * Mostra lo storico di un arbitro.
     */
    public function show(User $user)
    {
        $history = RefereeCareerHistory::where('user_id', $user->id)->first();

        return view('admin.career-history.show', compact('user', 'history'));
    }

    /**
     * Form per archiviare un anno.
     */
    public function archiveForm()
    {
        $currentYear = now()->year;

        // Statistiche per preview
        $stats = $this->getYearStats($currentYear);

        return view('admin.career-history.archive-form', compact('currentYear', 'stats'));
    }

    /**
     * Esegue l'archiviazione di un anno.
     */
    public function processArchive(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:' . now()->year,
            'user_id' => 'nullable|exists:users,id',
        ]);

        $year = (int) $request->year;
        $userId = $request->user_id;

        try {
            if ($userId) {
                // Archivia solo per un utente
                $result = $this->careerService->archiveYearForUser($userId, $year);
                $user = User::find($userId);

                return redirect()
                    ->route('admin.career-history.show', $user)
                    ->with('success', "Anno {$year} archiviato per {$user->name}: {$result['tournaments_count']} tornei, {$result['assignments_count']} assegnazioni");
            } else {
                // Archivia per tutti
                $stats = $this->careerService->archiveYear($year, false);

                $message = "Anno {$year} archiviato: {$stats['referees_processed']} arbitri, {$stats['assignments_archived']} assegnazioni";

                if (!empty($stats['errors'])) {
                    $message .= " ({$stats['errors']} errori)";
                }

                return redirect()
                    ->route('admin.career-history.index')
                    ->with('success', $message);
            }
        } catch (\Exception $e) {
            Log::error('Errore archiviazione career history', [
                'year' => $year,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Errore durante l\'archiviazione: ' . $e->getMessage());
        }
    }

    /**
     * Form per modificare un anno specifico.
     */
    public function editYear(User $user, int $year)
    {
        $history = RefereeCareerHistory::where('user_id', $user->id)->first();

        if (!$history) {
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
            'user', 'history', 'year', 'tournaments', 'assignments', 'availabilities', 'availableTournaments'
        ));
    }

    /**
     * Aggiunge un torneo allo storico.
     */
    public function addTournament(Request $request, User $user)
    {
        $request->validate([
            'year' => 'required|integer',
            'tournament_id' => 'required|exists:tournaments,id',
            'role' => 'nullable|string|max:100',
        ]);

        $tournament = Tournament::with('club')->find($request->tournament_id);

        $tournamentData = [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'club_id' => $tournament->club_id,
            'club_name' => $tournament->club->name ?? null,
            'start_date' => $tournament->start_date->format('Y-m-d'),
            'end_date' => $tournament->end_date->format('Y-m-d'),
        ];

        $this->careerService->addTournamentEntry($user->id, $request->year, $tournamentData);

        // Se c'è un ruolo, aggiungi anche l'assegnazione
        if ($request->filled('role')) {
            $history = RefereeCareerHistory::where('user_id', $user->id)->first();
            $assignments = $history->assignments_by_year ?? [];

            if (!isset($assignments[$request->year])) {
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

        return redirect()
            ->route('admin.career-history.edit-year', [$user, $request->year])
            ->with('success', "Torneo '{$tournament->name}' aggiunto");
    }

    /**
     * Rimuove un torneo dallo storico.
     */
    public function removeTournament(Request $request, User $user)
    {
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
                    fn($a) => $a['tournament_id'] != $request->tournament_id
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
        $year = $request->get('year', now()->year);

        $stats = $this->getYearStats($year);

        return response()->json($stats);
    }

    /**
     * Calcola statistiche per un anno.
     */
    private function getYearStats(int $year): array
    {
        $userField = \App\Models\Assignment::getUserField();

        return [
            'year' => $year,
            'referees_with_assignments' => \App\Models\Assignment::whereYear('assigned_at', $year)
                ->distinct($userField)
                ->count($userField),
            'total_assignments' => \App\Models\Assignment::whereYear('assigned_at', $year)->count(),
            'referees_with_availabilities' => \App\Models\Availability::whereHas('tournament', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            })->distinct('user_id')->count('user_id'),
            'total_availabilities' => \App\Models\Availability::whereHas('tournament', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            })->count(),
            'tournaments_count' => Tournament::whereYear('start_date', $year)->count(),
        ];
    }
}
