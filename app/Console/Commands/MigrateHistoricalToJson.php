<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Carbon\Carbon;

class MigrateHistoricalToJson extends Command
{
    protected $signature = 'migrate:historical-json
                            {--year-start=2015}
                            {--year-end=2025}
                            {--batch-size=50}
                            {--dry-run}
                            {--debug}
                            {--debug-levels : Mostra debug dettagliato livelli storici}
                            {--cache-arbitri : Usa cache per record arbitri}
                            {--only-user= : Processa solo un utente specifico}';

    protected $description = 'Migra dati storici da 48+ tabelle a JSON (da assignments_YYYY, tournaments_YYYY a referee_career_history)';

    private array $stats = [];
    private array $availableYears = [];
    private array $arbitriCache = []; // <- AGGIUNGI QUESTA

    public function handle()
    {
        $yearStart = (int) $this->option('year-start');
        $yearEnd = (int) $this->option('year-end');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $debug = $this->option('debug');

        $this->info('MIGRAZIONE STORICO JSON');
        $this->info('Da 48+ tabelle storiche → referee_career_history');
        $this->info('=============================================');

        if ($dryRun) {
            $this->warn('MODALITA DRY-RUN attiva');
        }

        // Inizializza stats
        $this->stats = [
            'users_processed' => 0,
            'years_processed' => 0,
            'total_assignments' => 0,
            'total_tournaments' => 0,
            'total_availabilities' => 0,
            'errors' => 0
        ];

        // 1. Test connessione
        if (!$this->testConnections()) {
            return 1;
        }

        // 2. Analizza tabelle disponibili
        $this->analyzeAvailableTables($yearStart, $yearEnd);

        // 3. Verifica prerequisiti
        if (!$this->checkPrerequisites()) {
            return 1;
        }

        // 4. Migrazione per batch di users
        $this->migrateHistoricalData($yearStart, $yearEnd, $batchSize, $dryRun, $debug);

        // 5. Report finale
        $this->showFinalReport();

        $this->info('Migrazione storico JSON completata');
        return 0;
    }

    private function testConnections(): bool // <- cambia nome da testConnection
    {
        try {
            // Test su una tabella storica (codice esistente)
            $count = DB::connection('old_mysql')->table('assignments_2024')->count();
            $this->info("Connessione DB vecchio OK - assignments_2024: {$count} records");

            // AGGIUNGI questo controllo tabella arbitri
            $arbitriCount = DB::connection('old_mysql')->table('arbitri')->count();
            $this->info("Connessione tabella arbitri OK - arbitri: {$arbitriCount} records");

            return true;
        } catch (\Exception $e) {
            $this->error("Errore connessione DB vecchio: {$e->getMessage()}");
            return false;
        }
    }

    private function analyzeAvailableTables(int $yearStart, int $yearEnd): void
    {
        $this->info('Analisi tabelle storiche disponibili...');

        $tableTypes = ['assignments', 'tournaments', 'availabilities'];
        $this->availableYears = [];

        for ($year = $yearStart; $year <= $yearEnd; $year++) {
            $yearTables = [];

            foreach ($tableTypes as $type) {
                $tableName = "{$type}_{$year}";

                try {
                    if (Schema::connection('old_mysql')->hasTable($tableName)) {
                        $count = DB::connection('old_mysql')->table($tableName)->count();
                        $yearTables[$type] = $count;
                    } else {
                        $yearTables[$type] = null;
                    }
                } catch (\Exception $e) {
                    $yearTables[$type] = null;
                }
            }

            // Considera l'anno disponibile se ha almeno assignments o tournaments
            if ($yearTables['assignments'] !== null || $yearTables['tournaments'] !== null) {
                $this->availableYears[$year] = $yearTables;
            }
        }

        // Report tabelle disponibili
        $this->table(
            ['Anno', 'Assignments', 'Tournaments', 'Availabilities'],
            collect($this->availableYears)->map(function ($tables, $year) {
                return [
                    $year,
                    $tables['assignments'] ?? 'N/A',
                    $tables['tournaments'] ?? 'N/A',
                    $tables['availabilities'] ?? 'N/A'
                ];
            })->toArray()
        );

        $this->info(count($this->availableYears) . " anni disponibili per migrazione");
    }

    private function checkPrerequisites(): bool
    {
        // Verifica users esistenti
        $userCount = User::where('user_type', 'referee')->count();
        if ($userCount === 0) {
            $this->error('Nessun referee trovato. Esegui prima migrate:core-data');
            return false;
        }

        // Verifica tabella referee_career_history
        if (!Schema::hasTable('referee_career_history')) {
            $this->error('Tabella referee_career_history non trovata. Esegui migrate:fresh');
            return false;
        }

        $this->info("Prerequisites OK - {$userCount} referees trovati");
        return true;
    }

    private function migrateHistoricalData(int $yearStart, int $yearEnd, int $batchSize, bool $dryRun, bool $debug): void
    {
        $referees = User::where('user_type', 'referee')->get();
        $this->info("Processando {$referees->count()} referees...");

        // Progress bar per users
        $progress = $this->output->createProgressBar($referees->count());
        $progress->start();

        // Process in batches
        foreach ($referees->chunk($batchSize) as $batchIndex => $refereeBatch) {
            $this->processBatch($refereeBatch, $dryRun, $debug);
            $progress->advance($refereeBatch->count());
        }

        $progress->finish();
        $this->newLine();
    }

    private function processBatch($referees, bool $dryRun, bool $debug): void
    {
        foreach ($referees as $referee) {
            try {
                $historicalData = $this->buildHistoricalDataForUser($referee);

                if ($debug && $this->stats['users_processed'] < 2) {
                    $this->showDebugInfo($referee, $historicalData);
                }

                if (!$dryRun && !empty(array_filter($historicalData))) {
                    $this->saveHistoricalData($referee->id, $historicalData);
                }

                $this->stats['users_processed']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                if ($debug) {
                    $this->newLine();
                    $this->error("Errore user {$referee->id}: {$e->getMessage()}");
                }
            }
        }
    }

    private function buildHistoricalDataForUser(User $referee): array
    {
        $tournamentsData = [];
        $assignmentsData = [];
        $availabilitiesData = [];
        $levelChangesData = [];

        foreach ($this->availableYears as $year => $tables) {

            // 1. Get tournaments per anno (via assignments)
            if ($tables['assignments'] !== null) {
                $yearTournaments = $this->getTournamentsForUserYear($referee->id, $year);
                if (!empty($yearTournaments)) {
                    $tournamentsData[$year] = $yearTournaments;
                }
            }

            // 2. Get assignments per anno
            if ($tables['assignments'] !== null) {
                $yearAssignments = $this->getAssignmentsForUserYear($referee->id, $year);
                if (!empty($yearAssignments)) {
                    $assignmentsData[$year] = $yearAssignments;
                    $this->stats['total_assignments'] += count($yearAssignments);
                }
            }

            // 3. Get availabilities per anno
            if ($tables['availabilities'] !== null) {
                $yearAvailabilities = $this->getAvailabilitiesForUserYear($referee->id, $year);
                if (!empty($yearAvailabilities)) {
                    $availabilitiesData[$year] = $yearAvailabilities;
                    $this->stats['total_availabilities'] += count($yearAvailabilities);
                }
            }

            // 4. Level changes (inferenza)
            $levelForYear = $this->inferLevelForYear($referee, $year);
            if ($levelForYear) {
                $levelChangesData[$year] = [
                    'level' => $levelForYear,
                    'effective_date' => "{$year}-01-01"
                ];
            }
        }

        // 5. Calcola career stats
        $careerStats = $this->calculateCareerStats($tournamentsData, $assignmentsData, $availabilitiesData);

        return [
            'tournaments_by_year' => $tournamentsData,
            'assignments_by_year' => $assignmentsData,
            'availabilities_by_year' => $availabilitiesData,
            'level_changes_by_year' => $levelChangesData,
            'career_stats' => $careerStats
        ];
    }

    private function getAssignmentsForUserYear(int $userId, int $year): array
    {
        $table = "assignments_{$year}";

        try {
            $assignments = DB::connection('old_mysql')
                ->table($table)
                ->where('user_id', $userId)  // <- USA ID DIRETTO!
                ->select('tournament_id', 'role', 'assigned_at')
                ->get();

            return $assignments->map(function ($a) {
                return [
                    'tournament_id' => $a->tournament_id,
                    'role' => $a->role,
                    'assigned_at' => $a->assigned_at,
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTournamentsForUserYear(int $userId, int $year): array
    {
        $tournamentsTable = "tournaments_{$year}";
        $assignmentsTable = "assignments_{$year}";

        try {
            $tournaments = DB::connection('old_mysql')
                ->table("{$tournamentsTable} as t")
                ->join("{$assignmentsTable} as a", 't.id', '=', 'a.tournament_id')
                ->where('a.user_id', $userId)  // <- USA ID DIRETTO!
                ->select('t.id', 't.name', 't.start_date', 't.end_date', 't.club_id')
                ->get();

            return $tournaments->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'start_date' => $t->start_date,
                    'end_date' => $t->end_date,
                    'club_id' => $t->club_id
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getAvailabilitiesForUserYear(int $userId, int $year): array
    {
        $table = "availabilities_{$year}";

        try {
            $availabilities = DB::connection('old_mysql')
                ->table($table)
                ->where('user_id', $userId)  // <- USA ID DIRETTO!
                ->select('tournament_id', 'notes')
                ->get();

            return $availabilities->map(function ($a) {
                return [
                    'tournament_id' => $a->tournament_id,
                    'notes' => $a->notes
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
    private function inferLevelForYear(User $referee, int $year): ?string
    {
        try {
            // Cerca nella tabella arbitri
            $arbitroRecord = $this->findArbitroRecord($referee);

            if (!$arbitroRecord) {
                if ($this->option('debug')) {
                    $this->warn("Arbitro non trovato per user {$referee->id} ({$referee->name})");
                }
                return $referee->level;
            }

            // Campo per l'anno specifico
            $levelFieldName = "Livello_{$year}";

            // Verifica che il campo esista
            if (!isset($arbitroRecord->$levelFieldName) || empty($arbitroRecord->$levelFieldName)) {
                // Cerca il livello più vicino
                $nearestLevel = $this->findNearestAvailableLevel($arbitroRecord, $year);

                if ($nearestLevel) {
                    return $this->mapLegacyLevelToModern($nearestLevel);
                }

                return $referee->level;
            }

            // Mappa il livello legacy
            $legacyLevel = $arbitroRecord->$levelFieldName;
            return $this->mapLegacyLevelToModern($legacyLevel);
        } catch (\Exception $e) {
            if ($this->option('debug')) {
                $this->error("Errore inferLevelForYear per user {$referee->id}, anno {$year}: {$e->getMessage()}");
            }
            return $referee->level;
        }
    }

    private function findArbitroRecord(User $referee)
    {
        try {
            // METODO 1: Match per nome completo ESATTO
            $arbitro = DB::connection('old_mysql')
                ->table('arbitri')
                ->whereRaw("CONCAT(Nome, ' ', Cognome) = ?", [$referee->name])
                ->first();

            if ($arbitro) {
                return $arbitro;
            }

            // METODO 2: Match per nome completo LIKE
            $arbitro = DB::connection('old_mysql')
                ->table('arbitri')
                ->whereRaw("CONCAT(Nome, ' ', Cognome) LIKE ?", ["%{$referee->name}%"])
                ->first();

            if ($arbitro) {
                return $arbitro;
            }

            // METODO 3: Split migliorato per cognomi composti
            $nameParts = explode(' ', trim($referee->name));
            if (count($nameParts) >= 2) {
                $nome = $nameParts[0];
                $cognome = implode(' ', array_slice($nameParts, 1)); // Tutto tranne il primo come cognome

                $arbitro = DB::connection('old_mysql')
                    ->table('arbitri')
                    ->where('Nome', $nome)
                    ->where('Cognome', $cognome)
                    ->first();

                if ($arbitro) {
                    return $arbitro;
                }
            }

            // METODO 4: Match LIKE migliorato
            if (count($nameParts) >= 2) {
                $nome = $nameParts[0];
                $cognome = implode(' ', array_slice($nameParts, 1));

                $arbitro = DB::connection('old_mysql')
                    ->table('arbitri')
                    ->where('Nome', 'LIKE', "%{$nome}%")
                    ->where('Cognome', 'LIKE', "%{$cognome}%")
                    ->first();

                if ($arbitro) {
                    return $arbitro;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    private function calculateCareerStats(array $tournaments, array $assignments, array $availabilities): array
    {
        $totalYears = count($tournaments);
        $totalTournaments = collect($tournaments)->flatten(1)->count();
        $totalAssignments = collect($assignments)->flatten(1)->count();
        $totalAvailabilities = collect($availabilities)->flatten(1)->count();

        // Analisi ruoli
        $rolesSummary = collect($assignments)
            ->flatten(1)
            ->groupBy('role')
            ->map->count()
            ->toArray();

        // Anno più attivo
        $mostActiveYear = collect($tournaments)
            ->map(fn($yearData) => count($yearData))
            ->flip()
            ->keys()
            ->first();

        return [
            'total_years' => $totalYears,
            'total_tournaments' => $totalTournaments,
            'total_assignments' => $totalAssignments,
            'total_availabilities' => $totalAvailabilities,
            'roles_summary' => $rolesSummary,
            'most_active_year' => $mostActiveYear,
            'avg_tournaments_per_year' => $totalYears > 0 ? round($totalTournaments / $totalYears, 1) : 0
        ];
    }

    private function saveHistoricalData(int $userId, array $historicalData): void
    {
        $completenessScore = $this->calculateCompleteness($historicalData);

        DB::table('referee_career_history')->updateOrInsert(
            ['user_id' => $userId],
            [
                'tournaments_by_year' => json_encode($historicalData['tournaments_by_year']),
                'assignments_by_year' => json_encode($historicalData['assignments_by_year']),
                'availabilities_by_year' => json_encode($historicalData['availabilities_by_year']),
                'level_changes_by_year' => json_encode($historicalData['level_changes_by_year']),
                'career_stats' => json_encode($historicalData['career_stats']),
                'last_updated_year' => max(array_keys($this->availableYears)),
                'data_completeness_score' => $completenessScore,
                'updated_at' => now()
            ]
        );
    }

    private function calculateCompleteness(array $historicalData): float
    {
        $totalYears = count($this->availableYears);
        $dataPoints = 0;
        $maxPoints = $totalYears * 3; // tournaments, assignments, availabilities per year

        foreach ($this->availableYears as $year => $tables) {
            if (!empty($historicalData['tournaments_by_year'][$year])) $dataPoints++;
            if (!empty($historicalData['assignments_by_year'][$year])) $dataPoints++;
            if (!empty($historicalData['availabilities_by_year'][$year])) $dataPoints++;
        }

        return $maxPoints > 0 ? round($dataPoints / $maxPoints, 2) : 0.0;
    }

    private function showDebugInfo(User $referee, array $historicalData): void
    {
        $this->newLine();
        $this->warn("DEBUG - User {$referee->id} ({$referee->name}):");

        foreach ($historicalData['tournaments_by_year'] as $year => $tournaments) {
            $this->line("  {$year}: " . count($tournaments) . " tournaments");
        }

        foreach ($historicalData['assignments_by_year'] as $year => $assignments) {
            $roles = collect($assignments)->pluck('role')->countBy();
            $this->line("  {$year}: " . count($assignments) . " assignments - " . $roles->toJson());
        }

        $stats = $historicalData['career_stats'];
        $this->line("  Career: {$stats['total_tournaments']} tournaments, {$stats['total_assignments']} assignments");
    }

    private function showFinalReport(): void
    {
        $this->info('REPORT FINALE MIGRAZIONE STORICO:');
        $this->info('=================================');

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Users Processati', $this->stats['users_processed']],
                ['Anni Disponibili', count($this->availableYears)],
                ['Assignments Migrati', $this->stats['total_assignments']],
                ['Tournaments Migrati', $this->stats['total_tournaments']],
                ['Availabilities Migrate', $this->stats['total_availabilities']],
                ['Errori', $this->stats['errors']],
            ]
        );

        // Verifica records creati
        $historyRecords = DB::table('referee_career_history')->count();
        $avgCompleteness = DB::table('referee_career_history')
            ->avg('data_completeness_score');

        $this->info("Records referee_career_history: {$historyRecords}");
        $this->info("Completeness media: " . round($avgCompleteness * 100, 1) . "%");

        // Sample record
        $sample = DB::table('referee_career_history')
            ->join('users', 'referee_career_history.user_id', '=', 'users.id')
            ->select('users.name', 'referee_career_history.career_stats')
            ->first();

        if ($sample) {
            $this->info("Sample career stats ({$sample->name}):");
            $stats = json_decode($sample->career_stats, true);
            $this->line("  Total tournaments: {$stats['total_tournaments']}");
            $this->line("  Total assignments: {$stats['total_assignments']}");
            $this->line("  Years active: {$stats['total_years']}");
        }
    }
    /**
     * Trova il livello più vicino all'anno richiesto
     */
    private function findNearestAvailableLevel($arbitroRecord, int $targetYear): ?string
    {
        $availableYears = range(2015, 2025);
        $availableLevels = [];

        foreach ($availableYears as $year) {
            $fieldName = "Livello_{$year}";
            if (isset($arbitroRecord->$fieldName) && !empty($arbitroRecord->$fieldName)) {
                $availableLevels[$year] = $arbitroRecord->$fieldName;
            }
        }

        if (empty($availableLevels)) {
            return null;
        }

        // Trova l'anno più vicino
        $nearestYear = null;
        $minDistance = PHP_INT_MAX;

        foreach (array_keys($availableLevels) as $availableYear) {
            $distance = abs($availableYear - $targetYear);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestYear = $availableYear;
            }
        }

        return $nearestYear ? $availableLevels[$nearestYear] : null;
    }

    /**
     * Mappa i livelli legacy ai formati moderni
     */
    private function mapLegacyLevelToModern(?string $legacyLevel): string
    {
        if (empty($legacyLevel)) {
            return 'Aspirante';
        }

        $level = strtoupper(trim($legacyLevel));

        return match ($level) {
            'ARCH', 'ARCHIVIO' => 'Archivio',
            'ASP', 'ASPIRANTE' => 'Aspirante',
            'PRIMO', 'PRIMO_LIVELLO', '1_LIVELLO', '1° LIVELLO' => '1_livello',
            'REG', 'REGIONALE' => 'Regionale',
            'NAZ', 'NAZIONALE' => 'Nazionale',
            'INT', 'INTERNAZIONALE' => 'Internazionale',
            'GIOV' => 'Archivio',
            default => 'Aspirante'
        };
    }
}
