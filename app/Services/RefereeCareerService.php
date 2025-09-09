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
        // Get historical data from referee_career_history (user_id)
        $careerHistory = RefereeCareerHistory::where('user_id', $referee->id)->first();
        
        // Start with current year data
        $currentYearData = $this->getCurrentYearData($referee);
        $currentYear = now()->year;
        
        if (!$careerHistory) {
            return [
                'tournaments' => [$currentYear => $currentYearData['tournaments']],
                'assignments' => [$currentYear => $currentYearData['assignments']],
                'availability' => [],
                'career_levels' => [$currentYear => ['level' => $referee->level, 'effective_date' => now()->startOfYear()->format('Y-m-d')]],
                'career_summary' => [
                    'total_assignments' => count($currentYearData['assignments']),
                    'total_years' => 1,
                    'roles_summary' => array_count_values(array_column($currentYearData['assignments'], 'role')),
                ],
            ];
        }

        // Align to real column names
        $historicalData = [
            'tournaments' => $careerHistory->tournaments_by_year ?? [],
            'assignments' => $careerHistory->assignments_by_year ?? [],
            'availability' => $careerHistory->availabilities_by_year ?? [],
            'career_levels' => $careerHistory->level_changes_by_year ?? [],
            'career_summary' => $careerHistory->career_stats ?? [],
        ];

        // If year is specified, return only that year's data
        if ($year) {
            return [
                'tournaments' => $historicalData['tournaments'][$year] ?? [],
                'assignments' => $historicalData['assignments'][$year] ?? [],
                'availability' => $historicalData['availability'][$year] ?? [],
                'career_levels' => $historicalData['career_levels'][$year] ?? null,
                'year_summary' => $this->calculateYearSummary($year, $historicalData),
            ];
        }

        // For current year, merge with live data
        $currentYear = now()->year;
        $currentYearData = $this->getCurrentYearData($referee);

        if (isset($historicalData['tournaments'][$currentYear])) {
            $historicalData['tournaments'][$currentYear] = array_merge(
                $historicalData['tournaments'][$currentYear],
                $currentYearData['tournaments']
            );
            $historicalData['assignments'][$currentYear] = array_merge(
                $historicalData['assignments'][$currentYear],
                $currentYearData['assignments']
            );
        } else {
            $historicalData['tournaments'][$currentYear] = $currentYearData['tournaments'];
            $historicalData['assignments'][$currentYear] = $currentYearData['assignments'];
        }

        return $historicalData;
    }

    /**
     * Get current year data from live tables
     */
    public function getCurrentYearData(User $referee): array
    {
        $currentYear = now()->year;

        // Get assignments for current year
        $assignments = Assignment::with('tournament')
            ->where('user_id', $referee->id)
            ->whereYear('created_at', $currentYear)
            ->get();

        // Format tournaments and assignments data
        $tournamentsData = [];
        $assignmentsData = [];
        $processedTournaments = [];

        foreach ($assignments as $assignment) {
            $tournament = $assignment->tournament;

            // Add tournament only if not already processed
            if (!isset($processedTournaments[$tournament->id])) {
                $tournamentsData[] = [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'club_id' => $tournament->club_id,
                    'start_date' => $tournament->start_date,
                    'end_date' => $tournament->end_date,
                ];
                $processedTournaments[$tournament->id] = true;
            }

            $assignmentsData[] = [
                'role' => $assignment->role,
                'assigned_at' => $assignment->created_at,
                'tournament_id' => $tournament->id,
            ];
        }

        return [
            'tournaments' => $tournamentsData,
            'assignments' => $assignmentsData,
        ];
    }

    /**
     * Calculate summary statistics for a specific year
     */
    private function calculateYearSummary(int $year, array $historicalData): array
    {
        $assignments = $historicalData['assignments'][$year] ?? [];

        $roleCount = array_count_values(array_column($assignments, 'role'));

        return [
            'total_tournaments' => count($assignments),
            'roles' => $roleCount,
            'level' => $historicalData['career_levels'][$year]['level'] ?? null,
        ];
    }

    /**
     * Get historical statistics for all referees
     */
    public function getHistoricalStats(?int $year = null): Collection
    {
        $query = RefereeCareerHistory::with('user');

        if ($year) {
            // Add any year-specific filtering if needed
        }

        return $query->get()->map(function ($history) use ($year) {
            $ref = $history->user;
            $data = $this->getCareerData($ref, $year);
            
            return [
                'referee' => $ref,
                'stats' => $data['career_summary'] ?? null,
                'year_data' => $year ? ($data['year_summary'] ?? null) : null,
            ];
        });
    }

    /**
     * Archive data for a specific year
     */
    public function archiveYear(int $year): void
    {
        // This will be implemented in the command
        // For now throwing an exception
        throw new \Exception('Not implemented yet');
    }
}
