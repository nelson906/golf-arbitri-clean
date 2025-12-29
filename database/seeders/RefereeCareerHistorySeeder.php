<?php

namespace Database\Seeders;

use App\Models\RefereeCareerHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class RefereeCareerHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Trova tutti gli arbitri (referee)
        $referees = User::where('user_type', 'referee')->get();

        foreach ($referees as $referee) {
            $careerData = $this->generateCareerData($referee);

            RefereeCareerHistory::create([
                'user_id' => $referee->id,
                'tournaments_by_year' => json_encode($careerData['tournaments_by_year']),
                'assignments_by_year' => json_encode($careerData['assignments_by_year']),
                'availabilities_by_year' => json_encode($careerData['availabilities_by_year']),
                'level_changes_by_year' => json_encode($careerData['level_changes_by_year']),
                'career_stats' => json_encode($careerData['career_stats']),
                'last_updated_year' => 2025,
                'data_completeness_score' => $careerData['completeness_score'],
            ]);
        }

        $this->command->info('✓ Created career history for '.$referees->count().' referees');
    }

    /**
     * Genera dati di carriera basati sul profilo dell'arbitro
     */
    private function generateCareerData(User $referee): array
    {
        $experienceYears = $referee->experience_years;
        $currentYear = 2025;
        $startYear = $currentYear - $experienceYears;

        $tournamentsByYear = [];
        $assignmentsByYear = [];
        $availabilitiesByYear = [];
        $levelChangesByYear = [];

        // Genera dati per ogni anno di esperienza
        for ($year = $startYear; $year <= $currentYear; $year++) {
            $yearExperience = $year - $startYear;

            // Numero tornei cresce con esperienza
            $numTournaments = $this->calculateTournamentsForYear($yearExperience, $referee->level);

            $tournamentsByYear[$year] = $numTournaments;
            $assignmentsByYear[$year] = $numTournaments; // Tutti i tornei sono stati assegnati
            $availabilitiesByYear[$year] = (int) ($numTournaments * 1.5); // Più disponibilità che assegnazioni
        }

        // Cambiamenti di livello
        $levelChangesByYear = $this->generateLevelChanges($referee, $startYear, $currentYear);

        // Statistiche carriera
        $careerStats = [
            'total_tournaments' => array_sum($tournamentsByYear),
            'total_assignments' => array_sum($assignmentsByYear),
            'total_availabilities' => array_sum($availabilitiesByYear),
            'acceptance_rate' => 0.67, // 67% di accettazione
            'avg_tournaments_per_year' => $experienceYears > 0 ? round(array_sum($tournamentsByYear) / $experienceYears, 1) : 0,
            'best_year' => [
                'year' => $this->getBestYear($tournamentsByYear),
                'tournaments' => max($tournamentsByYear),
            ],
            'role_distribution' => $this->generateRoleDistribution($referee->level),
            'tournament_types_count' => $this->generateTournamentTypesCount($referee->level),
            'zones_served' => $this->generateZonesServed($referee),
        ];

        // Completeness score basato sulla quantità di dati
        $completenessScore = $this->calculateCompletenessScore($experienceYears);

        return [
            'tournaments_by_year' => $tournamentsByYear,
            'assignments_by_year' => $assignmentsByYear,
            'availabilities_by_year' => $availabilitiesByYear,
            'level_changes_by_year' => $levelChangesByYear,
            'career_stats' => $careerStats,
            'completeness_score' => $completenessScore,
        ];
    }

    /**
     * Calcola il numero di tornei per anno basato su esperienza e livello
     */
    private function calculateTournamentsForYear(int $yearExperience, string $level): int
    {
        $baseTournaments = match ($level) {
            'Nazionale' => 20,
            'Regionale' => 12,
            '1_livello' => 8,
            'Aspirante' => 4,
            default => 5,
        };

        // Crescita progressiva nei primi anni
        $growthFactor = min(1, $yearExperience / 3);

        return (int) ($baseTournaments * $growthFactor) + rand(0, 3);
    }

    /**
     * Genera i cambiamenti di livello nel tempo
     */
    private function generateLevelChanges(User $referee, int $startYear, int $currentYear): array
    {
        $changes = [];

        $levelProgression = [
            'Aspirante' => ['years' => 2, 'next' => '1_livello'],
            '1_livello' => ['years' => 3, 'next' => 'Regionale'],
            'Regionale' => ['years' => 4, 'next' => 'Nazionale'],
            'Nazionale' => ['years' => null, 'next' => null],
        ];

        $currentLevel = 'Aspirante';
        $currentLevelYear = $startYear;

        // Traccia la progressione fino al livello attuale
        while ($currentLevel !== $referee->level && isset($levelProgression[$currentLevel])) {
            $yearsNeeded = $levelProgression[$currentLevel]['years'];
            $promotionYear = $currentLevelYear + $yearsNeeded;

            if ($promotionYear <= $currentYear) {
                $nextLevel = $levelProgression[$currentLevel]['next'];
                $changes[$promotionYear] = [
                    'from' => $currentLevel,
                    'to' => $nextLevel,
                    'date' => "$promotionYear-06-15",
                    'reason' => 'Promozione per merito',
                ];

                $currentLevel = $nextLevel;
                $currentLevelYear = $promotionYear;
            } else {
                break;
            }
        }

        return $changes;
    }

    /**
     * Trova l'anno con più tornei
     */
    private function getBestYear(array $tournamentsByYear): int
    {
        return array_key_exists(max($tournamentsByYear), array_flip($tournamentsByYear))
            ? array_search(max($tournamentsByYear), $tournamentsByYear)
            : array_key_first($tournamentsByYear);
    }

    /**
     * Genera distribuzione ruoli
     */
    private function generateRoleDistribution(string $level): array
    {
        return match ($level) {
            'Nazionale' => [
                'Direttore di Torneo' => 45,
                'Arbitro' => 50,
                'Osservatore' => 5,
            ],
            'Regionale' => [
                'Direttore di Torneo' => 20,
                'Arbitro' => 75,
                'Osservatore' => 5,
            ],
            '1_livello' => [
                'Arbitro' => 95,
                'Osservatore' => 5,
            ],
            'Aspirante' => [
                'Arbitro' => 100,
            ],
            default => ['Arbitro' => 100],
        };
    }

    /**
     * Genera conteggio tipi di torneo
     */
    private function generateTournamentTypesCount(string $level): array
    {
        return match ($level) {
            'Nazionale' => [
                'GN72' => 15,
                'GN54' => 20,
                'GN36' => 18,
                'CI' => 8,
                'CNZ' => 12,
                'TNZ' => 25,
            ],
            'Regionale' => [
                'GN36' => 25,
                'GN54' => 15,
                'TNZ' => 20,
                'T18' => 12,
            ],
            '1_livello' => [
                'T18' => 45,
                'T14' => 20,
                'GIOV' => 15,
                'SQUAD' => 10,
            ],
            'Aspirante' => [
                'GIOV' => 60,
                'T14' => 30,
                'T18' => 10,
            ],
            default => ['T18' => 50, 'GIOV' => 50],
        };
    }

    /**
     * Genera zone servite
     */
    private function generateZonesServed(User $referee): array
    {
        $zones = [$referee->zone_id];

        // Arbitri di livello più alto servono più zone
        if ($referee->level === 'Nazionale') {
            $zones = range(1, 8); // Tutte le zone
        } elseif ($referee->level === 'Regionale') {
            // Zona propria + 1-2 zone vicine
            $zones[] = ($referee->zone_id % 8) + 1;
            if (rand(0, 1)) {
                $zones[] = (($referee->zone_id + 1) % 8) + 1;
            }
        }

        return array_values(array_unique($zones));
    }

    /**
     * Calcola il completeness score
     */
    private function calculateCompletenessScore(int $experienceYears): float
    {
        // Più anni di esperienza = più dati completi
        $baseScore = min(0.95, 0.5 + ($experienceYears * 0.08));

        return round($baseScore, 2);
    }
}
