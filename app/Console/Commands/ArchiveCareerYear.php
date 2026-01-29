<?php

namespace App\Console\Commands;

use App\Services\CareerHistoryService;
use Illuminate\Console\Command;

class ArchiveCareerYear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'career:archive-year
                            {year? : Anno da archiviare (default: anno corrente)}
                            {--user= : ID utente specifico (opzionale)}
                            {--clear : Elimina i dati sorgente dopo l\'archiviazione}
                            {--dry-run : Mostra cosa verrebbe fatto senza eseguire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trasferisce assegnazioni e disponibilità dell\'anno nello storico career history';

    protected CareerHistoryService $careerService;

    public function __construct(CareerHistoryService $careerService)
    {
        parent::__construct();
        $this->careerService = $careerService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $clearData = (bool) $this->option('clear');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("=== Archiviazione Career History - Anno {$year} ===");
        $this->newLine();

        if ($dryRun) {
            $this->warn('MODALITA\' DRY-RUN: Nessuna modifica verrà effettuata');
            $this->newLine();
        }

        if ($clearData) {
            $this->warn('ATTENZIONE: I dati sorgente verranno ELIMINATI dopo l\'archiviazione!');
            if (! $this->confirm('Sei sicuro di voler procedere?')) {
                $this->info('Operazione annullata.');

                return self::SUCCESS;
            }
        }

        try {
            if ($userId) {
                // Archivia solo per un utente specifico
                $userIdInt = (int) $userId;
                $this->info("Archiviazione per utente ID: {$userIdInt}");

                if ($dryRun) {
                    $this->showUserPreview($userIdInt, $year);
                } else {
                    $result = $this->careerService->archiveYearForUser($userIdInt, $year);
                    $this->displayUserResult($result);
                }
            } else {
                // Archivia per tutti gli arbitri
                $this->info('Archiviazione per tutti gli arbitri con attività nell\'anno...');
                $this->newLine();

                if ($dryRun) {
                    $this->showGlobalPreview($year);
                } else {
                    $stats = $this->careerService->archiveYear($year, $clearData);
                    $this->displayGlobalResults($stats);
                }
            }

            $this->newLine();
            $this->info('Operazione completata.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Errore durante l\'archiviazione: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function showUserPreview(int $userId, int $year): void
    {
        $userField = \App\Models\Assignment::getUserField();

        $assignmentsCount = \App\Models\Assignment::where($userField, $userId)
            ->whereYear('assigned_at', $year)
            ->count();

        $availabilitiesCount = \App\Models\Availability::where('user_id', $userId)
            ->whereHas('tournament', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            })
            ->count();

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Assegnazioni da archiviare', $assignmentsCount],
                ['Disponibilità da archiviare', $availabilitiesCount],
            ]
        );
    }

    private function showGlobalPreview(int $year): void
    {
        $userField = \App\Models\Assignment::getUserField();

        $refereesWithAssignments = \App\Models\Assignment::whereYear('assigned_at', $year)
            ->distinct($userField)
            ->count($userField);

        $totalAssignments = \App\Models\Assignment::whereYear('assigned_at', $year)->count();

        $refereesWithAvailabilities = \App\Models\Availability::whereHas('tournament', function ($q) use ($year) {
            $q->whereYear('start_date', $year);
        })->distinct('user_id')->count('user_id');

        $totalAvailabilities = \App\Models\Availability::whereHas('tournament', function ($q) use ($year) {
            $q->whereYear('start_date', $year);
        })->count();

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Arbitri con assegnazioni', $refereesWithAssignments],
                ['Totale assegnazioni', $totalAssignments],
                ['Arbitri con disponibilità', $refereesWithAvailabilities],
                ['Totale disponibilità', $totalAvailabilities],
            ]
        );
    }

    private function displayUserResult(array $result): void
    {
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Tornei archiviati', $result['tournaments_count']],
                ['Assegnazioni archiviate', $result['assignments_count']],
                ['Disponibilità archiviate', $result['availabilities_count']],
            ]
        );
    }

    private function displayGlobalResults(array $stats): void
    {
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Arbitri processati', $stats['referees_processed']],
                ['Tornei archiviati', $stats['tournaments_archived']],
                ['Assegnazioni archiviate', $stats['assignments_archived']],
                ['Disponibilità archiviate', $stats['availabilities_archived']],
                ['Errori', count($stats['errors'])],
            ]
        );

        if (! empty($stats['errors'])) {
            $this->newLine();
            $this->warn('Errori riscontrati:');
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }
    }
}
