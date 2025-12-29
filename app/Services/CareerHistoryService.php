<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CareerHistoryService
{
    /**
     * Archivia un anno intero per tutti gli arbitri.
     * Trasferisce i dati dalle tabelle correnti allo storico.
     *
     * @param  int  $year  Anno da archiviare
     * @param  bool  $clearSourceData  Se true, elimina i dati dalla tabella sorgente dopo l'archiviazione
     * @return array Statistiche dell'operazione
     */
    public function archiveYear(int $year, bool $clearSourceData = false): array
    {
        $stats = [
            'referees_processed' => 0,
            'assignments_archived' => 0,
            'availabilities_archived' => 0,
            'tournaments_archived' => 0,
            'errors' => [],
        ];

        // Trova tutti gli arbitri con assegnazioni o disponibilità nell'anno
        $userField = Assignment::getUserField();

        $refereeIds = Assignment::whereYear('assigned_at', $year)
            ->pluck($userField)
            ->merge(
                Availability::whereHas('tournament', function ($q) use ($year) {
                    $q->whereYear('start_date', $year);
                })->pluck('user_id')
            )
            ->unique()
            ->filter();

        foreach ($refereeIds as $userId) {
            try {
                $result = $this->archiveYearForUser($userId, $year);

                $stats['referees_processed']++;
                $stats['assignments_archived'] += $result['assignments_count'];
                $stats['availabilities_archived'] += $result['availabilities_count'];
                $stats['tournaments_archived'] += $result['tournaments_count'];
            } catch (\Exception $e) {
                $stats['errors'][] = "User {$userId}: ".$e->getMessage();
                Log::error('Errore archiviazione career history', [
                    'user_id' => $userId,
                    'year' => $year,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Opzionalmente elimina i dati sorgente
        if ($clearSourceData && empty($stats['errors'])) {
            $this->clearSourceData($year);
        }

        return $stats;
    }

    /**
     * Archivia un anno per un singolo utente.
     */
    public function archiveYearForUser(int $userId, int $year): array
    {
        $userField = Assignment::getUserField();

        // Recupera assegnazioni dell'anno
        $assignments = Assignment::where($userField, $userId)
            ->whereYear('assigned_at', $year)
            ->with(['tournament:id,name,club_id,start_date,end_date', 'tournament.club:id,name'])
            ->get();

        // Recupera disponibilità dell'anno
        $availabilities = Availability::where('user_id', $userId)
            ->whereHas('tournament', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            })
            ->with(['tournament:id,name,club_id,start_date,end_date'])
            ->get();

        // Recupera tornei unici (da assegnazioni)
        $tournamentIds = $assignments->pluck('tournament_id')->unique();
        $tournaments = Tournament::whereIn('id', $tournamentIds)
            ->with('club:id,name')
            ->get();

        // Prepara i dati nel formato JSON
        $tournamentsData = $tournaments->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'club_id' => $t->club_id,
            'club_name' => $t->club->name ?? null,
            'start_date' => $t->start_date->format('Y-m-d'),
            'end_date' => $t->end_date->format('Y-m-d'),
        ])->values()->toArray();

        $assignmentsData = $assignments->map(fn ($a) => [
            'tournament_id' => $a->tournament_id,
            'tournament_name' => $a->tournament->name ?? null,
            'role' => $a->role,
            'assigned_at' => $a->assigned_at?->format('Y-m-d'),
            'status' => $a->status,
        ])->values()->toArray();

        $availabilitiesData = $availabilities->map(fn ($av) => [
            'tournament_id' => $av->tournament_id,
            'tournament_name' => $av->tournament->name ?? null,
            'submitted_at' => Carbon::parse($av->submitted_at)?->format('Y-m-d H:i'),
            'notes' => $av->notes,
        ])->values()->toArray();

        // Aggiorna o crea il record career history
        $this->updateCareerHistory($userId, $year, [
            'tournaments' => $tournamentsData,
            'assignments' => $assignmentsData,
            'availabilities' => $availabilitiesData,
        ]);

        return [
            'tournaments_count' => count($tournamentsData),
            'assignments_count' => count($assignmentsData),
            'availabilities_count' => count($availabilitiesData),
        ];
    }

    /**
     * Aggiorna il career history aggiungendo i dati di un anno.
     */
    public function updateCareerHistory(int $userId, int $year, array $yearData): RefereeCareerHistory
    {
        $history = RefereeCareerHistory::firstOrCreate(
            ['user_id' => $userId],
            [
                'tournaments_by_year' => [],
                'assignments_by_year' => [],
                'availabilities_by_year' => [],
                'level_changes_by_year' => [],
                'career_stats' => [],
            ]
        );

        // Aggiungi i dati dell'anno
        $tournaments = $history->tournaments_by_year ?? [];
        $tournaments[$year] = $yearData['tournaments'];

        $assignments = $history->assignments_by_year ?? [];
        $assignments[$year] = $yearData['assignments'];

        $availabilities = $history->availabilities_by_year ?? [];
        $availabilities[$year] = $yearData['availabilities'];

        $history->tournaments_by_year = $tournaments;
        $history->assignments_by_year = $assignments;
        $history->availabilities_by_year = $availabilities;
        $history->last_updated_year = (string) $year;

        // Ricalcola stats
        $history->career_stats = $history->generateStatsSummary();
        $history->data_completeness_score = $this->calculateCompletenessScore($history);

        $history->save();

        return $history;
    }

    /**
     * Modifica una singola voce torneo per un utente/anno.
     */
    public function updateTournamentEntry(int $userId, int $year, int $tournamentId, array $data): bool
    {
        $history = RefereeCareerHistory::where('user_id', $userId)->first();

        if (! $history) {
            return false;
        }

        $tournaments = $history->tournaments_by_year ?? [];

        if (! isset($tournaments[$year])) {
            return false;
        }

        // Trova e aggiorna il torneo
        $updated = false;
        foreach ($tournaments[$year] as $key => $tournament) {
            if ($tournament['id'] == $tournamentId) {
                $tournaments[$year][$key] = array_merge($tournament, $data);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $history->tournaments_by_year = $tournaments;
            $history->save();
        }

        return $updated;
    }

    /**
     * Aggiunge un torneo manualmente allo storico di un utente.
     */
    public function addTournamentEntry(int $userId, int $year, array $tournamentData): bool
    {
        $history = RefereeCareerHistory::firstOrCreate(
            ['user_id' => $userId],
            [
                'tournaments_by_year' => [],
                'assignments_by_year' => [],
                'availabilities_by_year' => [],
                'level_changes_by_year' => [],
                'career_stats' => [],
            ]
        );

        $tournaments = $history->tournaments_by_year ?? [];

        if (! isset($tournaments[$year])) {
            $tournaments[$year] = [];
        }

        $tournaments[$year][] = $tournamentData;
        $history->tournaments_by_year = $tournaments;
        $history->career_stats = $history->generateStatsSummary();
        $history->save();

        return true;
    }

    /**
     * Rimuove un torneo dallo storico.
     */
    public function removeTournamentEntry(int $userId, int $year, int $tournamentId): bool
    {
        $history = RefereeCareerHistory::where('user_id', $userId)->first();

        if (! $history) {
            return false;
        }

        $tournaments = $history->tournaments_by_year ?? [];

        if (! isset($tournaments[$year])) {
            return false;
        }

        $tournaments[$year] = array_values(array_filter(
            $tournaments[$year],
            fn ($t) => $t['id'] != $tournamentId
        ));

        $history->tournaments_by_year = $tournaments;
        $history->career_stats = $history->generateStatsSummary();
        $history->save();

        return true;
    }

    /**
     * Calcola lo score di completezza dei dati.
     */
    private function calculateCompletenessScore(RefereeCareerHistory $history): float
    {
        $score = 0;
        $maxScore = 5;

        // Ha tornei?
        if (! empty($history->tournaments_by_year)) {
            $score += 1;
        }

        // Ha assegnazioni?
        if (! empty($history->assignments_by_year)) {
            $score += 1;
        }

        // Ha disponibilità?
        if (! empty($history->availabilities_by_year)) {
            $score += 1;
        }

        // Ha più di un anno di dati?
        $yearsCount = count($history->tournaments_by_year ?? []);
        if ($yearsCount > 1) {
            $score += 1;
        }
        if ($yearsCount > 3) {
            $score += 1;
        }

        return round($score / $maxScore, 2);
    }

    /**
     * Elimina i dati sorgente dopo l'archiviazione.
     */
    public function clearSourceData(int $year): array
    {
        // ATTENZIONE: Questa operazione è irreversibile
        // Elimina assegnazioni, disponibilità e tornei dell'anno specificato

        $tournamentIds = Tournament::whereYear('start_date', $year)->pluck('id');

        $assignmentsDeleted = Assignment::whereIn('tournament_id', $tournamentIds)->count();
        Assignment::whereIn('tournament_id', $tournamentIds)->delete();

        $availabilitiesDeleted = Availability::whereIn('tournament_id', $tournamentIds)->count();
        Availability::whereIn('tournament_id', $tournamentIds)->delete();

        $tournamentsDeleted = Tournament::whereYear('start_date', $year)->count();
        Tournament::whereYear('start_date', $year)->delete();

        Log::info("Dati sorgente eliminati per anno {$year}", [
            'assignments_deleted' => $assignmentsDeleted,
            'availabilities_deleted' => $availabilitiesDeleted,
            'tournaments_deleted' => $tournamentsDeleted,
        ]);

        return [
            'assignments_deleted' => $assignmentsDeleted,
            'availabilities_deleted' => $availabilitiesDeleted,
            'tournaments_deleted' => $tournamentsDeleted,
        ];
    }
}
