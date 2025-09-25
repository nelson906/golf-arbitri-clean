<?php

namespace App\Services;

use App\Models\User;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\RefereeCareerHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RefereeCareerService
{
    /**
     * Get career data for a referee, optionally filtered by year
     */
    public function getCareerData(User $referee, ?int $year = null): array
    {
        // Prendi i dati storici dalla tabella referee_career_history
        $careerHistory = RefereeCareerHistory::where('user_id', $referee->id)->first();

        if ($careerHistory) {
            // Usa i dati storici
            $historicalData = [
                'tournaments' => $careerHistory->tournaments_by_year ?? [],
                'assignments' => $careerHistory->assignments_by_year ?? [],
                'availability' => $careerHistory->availabilities_by_year ?? [],
                'career_levels' => $careerHistory->level_changes_by_year ?? [],
                'career_summary' => $careerHistory->career_stats ?? [],
            ];

            // Calcola first_year dai dati storici
            $allYears = [];
            if (is_array($historicalData['assignments'])) {
                $allYears = array_merge($allYears, array_keys($historicalData['assignments']));
            }
            if (is_array($historicalData['tournaments'])) {
                $allYears = array_merge($allYears, array_keys($historicalData['tournaments']));
            }

            $firstYear = !empty($allYears) ? min($allYears) : null;

            // Aggiorna career_summary con first_year corretto
            if (isset($historicalData['career_summary'])) {
                $historicalData['career_summary']['first_year'] = $firstYear;
            }

        } else {
            // Fallback se non ci sono dati storici
            $currentAssignments = $this->getCurrentAssignmentsData($referee);
            $totalAssignments = count($currentAssignments);

            $historicalData = [
                'tournaments' => [],
                'assignments' => [],
                'availability' => [],
                'career_levels' => [now()->year => ['level' => $referee->level]],
                'career_summary' => [
                    'total_assignments' => $totalAssignments,
                    'total_years' => $totalAssignments > 0 ? $this->calculateActiveYears($currentAssignments) : 0,
                    'roles_summary' => array_count_values(array_column($currentAssignments, 'role')),
                    'first_year' => $totalAssignments > 0 ? $this->getFirstYear($currentAssignments) : null,
                ],
            ];
        }

        if ($year) {
            $yearAssignments = isset($historicalData['assignments'][$year]) ? $historicalData['assignments'][$year] : [];
            $yearTournaments = isset($historicalData['tournaments'][$year]) ? $historicalData['tournaments'][$year] : [];

            return [
                'tournaments' => $yearTournaments,
                'assignments' => $yearAssignments,
                'availability' => [],
                'career_levels' => ['level' => $referee->level],
                'career_summary' => $historicalData['career_summary'],
                'year_summary' => $this->getYearSummary($yearAssignments, $referee->level, $yearTournaments),
            ];
        }

        return [
            'tournaments' => $historicalData['tournaments'],
            'assignments' => $historicalData['assignments'],
            'availability' => $historicalData['availability'],
            'career_levels' => $historicalData['career_levels'],
            'career_summary' => $historicalData['career_summary'] ?: [
                'total_assignments' => 0,
                'total_years' => 0,
                'roles_summary' => [],
                'first_year' => null,
            ],
        ];
    }

    /**
     * Get year-specific data for a referee
     */
    public function getYearData(User $referee, int $year): array
    {
        // Prendi i dati dal career history se disponibili
        $careerHistory = RefereeCareerHistory::where('user_id', $referee->id)->first();

        if ($careerHistory) {
            $yearAssignments = isset($careerHistory->assignments_by_year[$year]) ? $careerHistory->assignments_by_year[$year] : [];
            $yearTournaments = isset($careerHistory->tournaments_by_year[$year]) ? $careerHistory->tournaments_by_year[$year] : [];
        } else {
            // Fallback ai dati correnti
            $yearAssignments = $this->getAssignmentsForYear($referee, $year);
            $yearTournaments = $this->getTournamentsForYear($referee, $year);
        }

        return [
            'level' => $referee->level,
            'total_tournaments' => count($yearTournaments),
            'roles' => array_count_values(array_column($yearAssignments, 'role')),
        ];
    }

    protected function getCurrentAssignmentsData(User $referee): array
    {
        $assignments = Assignment::where('user_id', $referee->id)
            ->with('tournament')
            ->get()
            ->filter(function($assignment) {
                return $assignment->tournament && $assignment->tournament->start_date;
            })
            ->map(function($assignment) {
                $year = date('Y', strtotime($assignment->tournament->start_date));
                return [
                    'id' => $assignment->id,
                    'tournament_id' => $assignment->tournament_id,
                    'role' => $assignment->role,
                    'tournament_name' => $assignment->tournament->name ?? 'N/A',
                    'tournament_date' => $assignment->tournament->start_date,
                    'year' => (int)$year,
                    'is_confirmed' => $assignment->is_confirmed ?? false,
                ];
            })
            ->toArray();

        return $assignments;
    }

    protected function getAssignmentsForYear(User $referee, int $year): array
    {
        $assignments = Assignment::where('user_id', $referee->id)
            ->whereHas('tournament', function($q) use ($year) {
                $q->whereYear('start_date', $year);
            })
            ->with('tournament')
            ->get()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'tournament_id' => $assignment->tournament_id,
                    'role' => $assignment->role,
                    'tournament_name' => $assignment->tournament->name ?? 'N/A',
                    'tournament_date' => $assignment->tournament->start_date ?? null,
                    'is_confirmed' => $assignment->is_confirmed ?? false,
                ];
            })
            ->toArray();

        return $assignments;
    }

    protected function getTournamentsForYear(User $referee, int $year): array
    {
        $tournaments = Tournament::whereYear('start_date', $year)
            ->whereHas('assignments', function($q) use ($referee) {
                $q->where('user_id', $referee->id);
            })
            ->get()
            ->map(function($tournament) {
                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'start_date' => $tournament->start_date,
                    'status' => $tournament->status,
                ];
            })
            ->toArray();

        return $tournaments;
    }

    protected function calculateActiveYears(array $assignments): int
    {
        if (empty($assignments)) {
            return 0;
        }

        $years = [];
        foreach ($assignments as $assignment) {
            if (isset($assignment['year']) && is_numeric($assignment['year'])) {
                $years[] = (int)$assignment['year'];
            }
        }

        return count(array_unique($years));
    }

    protected function getFirstYear(array $assignments): ?int
    {
        if (empty($assignments)) {
            return null;
        }

        $years = [];
        foreach ($assignments as $assignment) {
            if (isset($assignment['year']) && is_numeric($assignment['year'])) {
                $years[] = (int)$assignment['year'];
            }
        }

        return !empty($years) ? min($years) : null;
    }

    protected function getYearSummary(array $assignments, ?string $level, array $tournaments = []): array
    {
        $roleCount = array_count_values(array_column($assignments, 'role'));

        return [
            'total_tournaments' => count($tournaments),
            'roles' => $roleCount,
            'level' => $level,
        ];
    }

    public function getHistoricalStats(?int $year = null): Collection
    {
        $query = RefereeCareerHistory::with('user');

        if ($year) {
            // Add any year-specific filtering if needed
        }

        return $query->get()->map(function ($history) use ($year) {
            $data = $this->getCareerData($history->user, $year);

            return [
                'user' => $history->user,
                'stats' => $data['career_summary'] ?? null,
                'year_data' => $year ? ($data['year_summary'] ?? null) : null,
            ];
        });
    }

    public function archiveYear(int $year): void
    {
        throw new \Exception('Not implemented yet');
    }
}
