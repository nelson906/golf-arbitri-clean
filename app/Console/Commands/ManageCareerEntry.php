<?php

namespace App\Console\Commands;

use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use App\Services\CareerHistoryService;
use Illuminate\Console\Command;

class ManageCareerEntry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'career:manage
                            {action : Azione: add, remove, show}
                            {--user= : ID o email dell\'utente}
                            {--year= : Anno}
                            {--tournament= : ID del torneo}
                            {--role= : Ruolo (per add)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestisce singole voci nel career history di un arbitro';

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
        $action = $this->argument('action');

        return match ($action) {
            'show' => $this->showHistory(),
            'add' => $this->addEntry(),
            'remove' => $this->removeEntry(),
            default => $this->invalidAction($action),
        };
    }

    private function showHistory(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $history = RefereeCareerHistory::where('user_id', $user->id)->first();

        if (! $history) {
            $this->warn("Nessun career history trovato per {$user->name}");

            return self::SUCCESS;
        }

        $this->info("=== Career History: {$user->name} ===");
        $this->newLine();

        $year = $this->option('year');

        if ($year) {
            // Mostra solo un anno specifico
            $this->showYearDetails($history, (int) $year);
        } else {
            // Mostra riepilogo tutti gli anni
            $this->showSummary($history);
        }

        return self::SUCCESS;
    }

    private function showSummary(RefereeCareerHistory $history): void
    {
        $tournaments = $history->tournaments_by_year ?? [];

        $this->info('Anni disponibili:');

        $rows = [];
        foreach ($tournaments as $year => $yearTournaments) {
            $assignments = $history->assignments_by_year[$year] ?? [];
            $availabilities = $history->availabilities_by_year[$year] ?? [];

            $rows[] = [
                $year,
                count($yearTournaments),
                count($assignments),
                count($availabilities),
            ];
        }

        $this->table(
            ['Anno', 'Tornei', 'Assegnazioni', 'Disponibilità'],
            $rows
        );

        $this->newLine();
        $this->info('Career Stats:');
        $this->line(json_encode($history->career_stats, JSON_PRETTY_PRINT) ?: '{}');
    }

    private function showYearDetails(RefereeCareerHistory $history, int $year): void
    {
        $tournaments = $history->tournaments_by_year[$year] ?? [];

        if (empty($tournaments)) {
            $this->warn("Nessun dato per l'anno {$year}");

            return;
        }

        $this->info("Tornei {$year}:");

        $rows = [];
        foreach ($tournaments as $tournament) {
            $rows[] = [
                $tournament['id'],
                $tournament['name'],
                $tournament['club_name'] ?? 'N/A',
                $tournament['start_date'],
                $tournament['end_date'],
            ];
        }

        $this->table(
            ['ID', 'Nome', 'Club', 'Inizio', 'Fine'],
            $rows
        );

        // Mostra anche assegnazioni
        $assignments = $history->assignments_by_year[$year] ?? [];
        if (! empty($assignments)) {
            $this->newLine();
            $this->info("Assegnazioni {$year}:");

            $assignRows = [];
            foreach ($assignments as $assignment) {
                $assignRows[] = [
                    $assignment['tournament_id'],
                    $assignment['tournament_name'] ?? 'N/A',
                    $assignment['role'] ?? 'N/A',
                    $assignment['assigned_at'] ?? 'N/A',
                ];
            }

            $this->table(
                ['Torneo ID', 'Nome', 'Ruolo', 'Data Assegnazione'],
                $assignRows
            );
        }
    }

    private function addEntry(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $year = $this->option('year');
        $tournamentId = $this->option('tournament');

        if (! $year || ! $tournamentId) {
            $this->error('Devi specificare --year e --tournament');

            return self::FAILURE;
        }

        $tournament = Tournament::with('club')->find($tournamentId);
        if (! $tournament) {
            $this->error("Torneo ID {$tournamentId} non trovato");

            return self::FAILURE;
        }

        $tournamentData = [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'club_id' => $tournament->club_id,
            'club_name' => $tournament->club->name ?? null,
            'start_date' => $tournament->start_date?->format('Y-m-d') ?? '',
            'end_date' => $tournament->end_date?->format('Y-m-d') ?? '',
        ];

        $this->careerService->addTournamentEntry($user->id, (int) $year, $tournamentData);

        // Se c'è un ruolo, aggiungi anche l'assegnazione
        $role = $this->option('role');
        if ($role) {
            $history = RefereeCareerHistory::where('user_id', $user->id)->first();
            if ($history) {
                $assignments = $history->assignments_by_year ?? [];

                if (! isset($assignments[$year])) {
                    $assignments[$year] = [];
                }

                $assignments[$year][] = [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'role' => $role,
                    'assigned_at' => now()->format('Y-m-d'),
                    'status' => 'confirmed',
                ];

                $history->assignments_by_year = $assignments;
                $history->save();
            }
        }

        $this->info("Torneo '{$tournament->name}' aggiunto allo storico {$year} di {$user->name}");

        return self::SUCCESS;
    }

    private function removeEntry(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $year = $this->option('year');
        $tournamentId = $this->option('tournament');

        if (! $year || ! $tournamentId) {
            $this->error('Devi specificare --year e --tournament');

            return self::FAILURE;
        }

        $removed = $this->careerService->removeTournamentEntry($user->id, (int) $year, (int) $tournamentId);

        if ($removed) {
            $this->info("Torneo ID {$tournamentId} rimosso dallo storico {$year} di {$user->name}");
        } else {
            $this->warn('Torneo non trovato nello storico');
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userOption = $this->option('user');

        if (! $userOption) {
            $this->error('Devi specificare --user (ID o email)');

            return null;
        }

        if (is_numeric($userOption)) {
            $user = User::find($userOption);
        } else {
            $user = User::where('email', $userOption)->first();
        }

        if (! $user) {
            $this->error("Utente '{$userOption}' non trovato");

            return null;
        }

        return $user;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Azione '{$action}' non valida. Usa: show, add, remove");

        return self::FAILURE;
    }
}
