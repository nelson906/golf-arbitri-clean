<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\RefereeCareerHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveRefereeYearCommand extends Command
{
    protected $signature = 'referee:archive-year {year}';
    protected $description = 'Archive referee data for a specific year';

    public function handle()
    {
        $year = $this->argument('year');
        
        if ($year >= now()->year) {
            $this->error('Cannot archive current or future year');
            return 1;
        }

        $this->info("Starting archival process for year {$year}...");

        // Get all referees
        $referees = User::where('user_type', 'referee')->get();
        $bar = $this->output->createProgressBar(count($referees));

        foreach ($referees as $referee) {
            $this->archiveRefereeData($referee, $year);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Archive process completed!');
    }

    private function archiveRefereeData(User $referee, int $year)
    {
        // Get assignments for the year
        $assignments = Assignment::with('tournament')
            ->where('user_id', $referee->id)
            ->whereYear('created_at', $year)
            ->get();

        // Format data
        $tournamentsData = [];
        $assignmentsData = [];

        foreach ($assignments as $assignment) {
            $tournament = $assignment->tournament;
            
            $tournamentsData[] = [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'club_id' => $tournament->club_id,
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
            ];

            $assignmentsData[] = [
                'role' => $assignment->role,
                'assigned_at' => $assignment->created_at,
                'tournament_id' => $tournament->id,
            ];
        }

        // Get or create career history
        $careerHistory = RefereeCareerHistory::firstOrNew(['user_id' => $referee->id]);
        
        // Get existing data
        $tournaments = $careerHistory->tournaments_by_year ?? [];
        $assignments = $careerHistory->assignments_by_year ?? [];
        $careerLevels = $careerHistory->level_changes_by_year ?? [];
        
        // Update data
        $tournaments[$year] = $tournamentsData;
        $assignments[$year] = $assignmentsData;
        $careerLevels[$year] = [
            'level' => $referee->level,
            'effective_date' => Carbon::create($year, 1, 1)->format('Y-m-d'),
        ];

        // Calculate career summary
        $totalAssignments = array_reduce($assignments, function($carry, $yearAssignments) {
            return $carry + count($yearAssignments);
        }, 0);

        $rolesSummary = [];
        foreach ($assignments as $yearAssignments) {
            foreach ($yearAssignments as $assignment) {
                $role = $assignment['role'];
                $rolesSummary[$role] = ($rolesSummary[$role] ?? 0) + 1;
            }
        }

        $careerSummary = [
            'total_years' => count($assignments),
            'roles_summary' => $rolesSummary,
            'most_active_year' => max(array_map('count', $assignments)),
            'total_assignments' => $totalAssignments,
            'total_tournaments' => $totalAssignments,
            'avg_tournaments_per_year' => $totalAssignments / count($assignments),
        ];

        // Save data
        $careerHistory->tournaments_by_year = $tournaments;
        $careerHistory->assignments_by_year = $assignments;
        $careerHistory->level_changes_by_year = $careerLevels;
        $careerHistory->career_stats = $careerSummary;
        $careerHistory->last_updated_year = $year;
        $careerHistory->save();
    }
}
