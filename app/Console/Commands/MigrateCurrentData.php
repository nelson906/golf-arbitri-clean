<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\TournamentType;
use Carbon\Carbon;

class MigrateCurrentData extends Command
{
    protected $signature = 'migrate:current-data {--dry-run} {--debug}';
    protected $description = 'Migra dati correnti: tournaments, clubs, assignments, availabilities';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $debug = $this->option('debug');

        $this->info('Migrazione Dati Correnti');
        $this->info('========================');

        if ($dryRun) {
            $this->warn('MODALITA DRY-RUN attiva');
        }

        // Test connessione
        if (!$this->testConnection()) {
            return 1;
        }

        // Verifica prerequisiti
        if (!$this->checkPrerequisites()) {
            return 1;
        }

        // Migrazione in ordine di dipendenze
        $this->migrateTournamentTypes($dryRun, $debug);
        $this->migrateClubs($dryRun, $debug);
        $this->migrateTournaments($dryRun, $debug);
        $this->migrateAssignments($dryRun, $debug);
        $this->migrateAvailabilities($dryRun, $debug);

        // Report finale
        $this->showFinalReport();

        $this->info('Migrazione dati correnti completata');
        return 0;
    }

    private function testConnection(): bool
    {
        try {
            $count = DB::connection('old_mysql')->table('tournaments')->count();
            $this->info("Connessione DB vecchio OK: {$count} tournaments");
            return true;
        } catch (\Exception $e) {
            $this->error("Errore connessione: {$e->getMessage()}");
            return false;
        }
    }

    private function checkPrerequisites(): bool
    {
        $userCount = User::count();
        $zoneCount = Zone::count();
        $typeCount = TournamentType::count();

        $this->table(
            ['Prerequisito', 'Count', 'Status'],
            [
                ['Users', $userCount, $userCount > 0 ? 'OK' : 'ERRORE'],
                ['Zones', $zoneCount, $zoneCount >= 8 ? 'OK' : 'WARNING'],
                ['Tournament Types', $typeCount, $typeCount > 0 ? 'OK' : 'WARNING'],
            ]
        );

        if ($userCount === 0) {
            $this->error('ERRORE: Nessun user trovato. Esegui prima: php artisan migrate:core-data');
            return false;
        }

        return true;
    }

    private function migrateTournamentTypes(bool $dryRun, bool $debug): void
    {
        $this->info('1. Migrazione Tournament Types...');

        // Verifica se tournament types già esistono
        $existingCount = TournamentType::count();
        if ($existingCount > 0) {
            $this->warn("Tournament Types già presenti: {$existingCount}. Skip migrazione tournament types.");
            return;
        }

        try {
            $oldTypes = DB::connection('old_mysql')->table('tournament_types')->get();
        } catch (\Exception $e) {
            $this->warn("Tabella tournament_types non trovata nel DB vecchio: {$e->getMessage()}");
            return;
        }

        if ($oldTypes->isEmpty()) {
            $this->warn('Nessun tournament type trovato nel DB vecchio');
            return;
        }

        $progress = $this->output->createProgressBar($oldTypes->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;

        foreach ($oldTypes as $oldType) {
            try {
                $typeData = [
                    'id' => $oldType->id, // Mantieni lo stesso ID per mantenere le relazioni
                    'name' => $oldType->name,
                    'short_name' => $oldType->short_name ?? substr($oldType->name, 0, 10),
                    'description' => $oldType->description,
                    'is_national' => (bool)($oldType->is_national ?? false),
                    'level' => $oldType->level ?? 1,
                    'required_level' => $oldType->required_level ?? 1,
                    'min_referees' => $oldType->min_referees ?? 1,
                    'max_referees' => $oldType->max_referees ?? 2,
                    'sort_order' => $oldType->sort_order ?? 100,
                    'is_active' => (bool)($oldType->is_active ?? true),
                    'settings' => $oldType->settings ?? null,
                    'created_at' => $oldType->created_at ?? now(),
                    'updated_at' => $oldType->updated_at ?? now(),
                ];

                if ($debug && $migrated < 3) {
                    $this->newLine();
                    $this->warn("DEBUG Tournament Type ID {$oldType->id}:");
                    $this->line("Name: {$typeData['name']}");
                    $this->line("Short Name: {$typeData['short_name']}");
                    $this->line("Is National: " . ($typeData['is_national'] ? 'Yes' : 'No'));
                    $this->line("Level: {$typeData['level']}");
                    $this->line("Required Level: {$typeData['required_level']}");
                    $this->line("Min/Max Referees: {$typeData['min_referees']}/{$typeData['max_referees']}");
                }

                if (!$dryRun) {
                    // Usa insert diretto per mantenere l'ID originale
                    DB::table('tournament_types')->insert($typeData);
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
                if ($debug) {
                    $this->newLine();
                    $this->error("Errore tournament type ID {$oldType->id}: {$e->getMessage()}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("Tournament Types - Migrati: {$migrated}, Errori: {$errors}");
    }

    private function migrateClubs(bool $dryRun, bool $debug): void
    {
        $this->info('2. Migrazione Clubs...');

        // Verifica se clubs già esistono
        $existingCount = DB::table('clubs')->count();
        if ($existingCount > 0) {
            $this->warn("Clubs già presenti: {$existingCount}. Skip migrazione clubs.");
            return;
        }

        try {
            $oldClubs = DB::connection('old_mysql')->table('clubs')->get();
        } catch (\Exception $e) {
            $this->warn("Tabella clubs non trovata nel DB vecchio: {$e->getMessage()}");
            return;
        }

        if ($oldClubs->isEmpty()) {
            $this->warn('Nessun club trovato nel DB vecchio');
            return;
        }

        $progress = $this->output->createProgressBar($oldClubs->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;

        foreach ($oldClubs as $oldClub) {
            try {
                $clubData = [
                    'name' => $oldClub->name,
                    'code' => $oldClub->code,
                    'email' => $oldClub->email ?? 'info@golf.it',
                    'phone' => $oldClub->phone,
                    'address' => $oldClub->address,
                    'city' => $oldClub->city ?? 'Unknown',
                    'province' => $oldClub->province ?? 'IT',
                    'zone_id' => $this->mapZoneId($oldClub->zone_id),
                    'is_active' => (bool)($oldClub->is_active ?? true),
                    'created_at' => $oldClub->created_at ?? now(),
                    'updated_at' => $oldClub->updated_at ?? now(),
                ];

                if ($debug && $migrated < 2) {
                    $this->newLine();
                    $this->warn("DEBUG Club ID {$oldClub->id}:");
                    $this->line("Name: {$clubData['name']}");
                    $this->line("Zone: {$clubData['zone_id']}");
                }

                if (!$dryRun) {
                    DB::table('clubs')->insert($clubData);
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
                if ($debug) {
                    $this->newLine();
                    $this->error("Errore club ID {$oldClub->id}: {$e->getMessage()}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("Clubs - Migrati: {$migrated}, Errori: {$errors}");
    }

    private function migrateTournaments(bool $dryRun, bool $debug): void
    {
        $this->info('3. Migrazione Tournaments...');

        $oldTournaments = DB::connection('old_mysql')->table('tournaments')->get();

        if ($oldTournaments->isEmpty()) {
            $this->warn('Nessun tournament trovato');
            return;
        }

        $progress = $this->output->createProgressBar($oldTournaments->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;

        foreach ($oldTournaments as $oldTournament) {
            try {
                $tournamentData = [
                    'name' => $oldTournament->name,
                    'start_date' => $this->parseDate($oldTournament->start_date),
                    'end_date' => $this->parseDate($oldTournament->end_date),
                    'availability_deadline' => $this->parseDateTime($oldTournament->availability_deadline),
                    'club_id' => $this->mapClubId($oldTournament->club_id),
                    'tournament_type_id' => $this->mapTournamentTypeId($oldTournament->tournament_type_id),
                    'zone_id' => $this->mapZoneId($oldTournament->zone_id),
                    'status' => $this->mapStatus($oldTournament->status ?? 'draft'),
                    'description' => $oldTournament->description,
                    'notes' => $oldTournament->notes,
                    'created_by' => $this->mapUserId($oldTournament->created_by ?? 1),
                    'created_at' => $oldTournament->created_at ?? now(),
                    'updated_at' => $oldTournament->updated_at ?? now(),
                ];

                if ($debug && $migrated < 2) {
                    $this->newLine();
                    $this->warn("DEBUG Tournament ID {$oldTournament->id}:");
                    $this->line("Name: {$tournamentData['name']}");
                    $this->line("Club: {$tournamentData['club_id']}");
                    $this->line("Type ID: {$tournamentData['tournament_type_id']}");
                    $this->line("Zone ID: {$tournamentData['zone_id']}");
                }

                if (!$dryRun) {
                    DB::table('tournaments')->insert($tournamentData);
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
                if ($debug) {
                    $this->newLine();
                    $this->error("Errore tournament ID {$oldTournament->id}: {$e->getMessage()}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("Tournaments - Migrati: {$migrated}, Errori: {$errors}");
    }

    private function migrateAssignments(bool $dryRun, bool $debug): void
    {
        $this->info('4. Migrazione Assignments...');

        $oldAssignments = DB::connection('old_mysql')->table('assignments')->get();

        if ($oldAssignments->isEmpty()) {
            $this->warn('Nessun assignment trovato');
            return;
        }

        $progress = $this->output->createProgressBar($oldAssignments->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($oldAssignments as $oldAssignment) {
            try {
                // Verifica che user e tournament esistano nel nuovo DB
                $newUserId = $this->findMappedUserId($oldAssignment->user_id);
                $newTournamentId = $this->findMappedTournamentId($oldAssignment->tournament_id);

                if (!$newUserId || !$newTournamentId) {
                    $skipped++;
                    $progress->advance();
                    continue;
                }

                $assignmentData = [
                    'user_id' => $newUserId,
                    'tournament_id' => $newTournamentId,
                    'role' => $this->mapRole($oldAssignment->role),
                    'assigned_by' => $this->mapUserId($oldAssignment->assigned_by ?? 1),
                    'assigned_at' => $oldAssignment->assigned_at ?? now(),
                    'created_at' => $oldAssignment->created_at ?? now(),
                    'updated_at' => $oldAssignment->updated_at ?? now(),
                ];

                if (!$dryRun) {
                    DB::table('assignments')->insert($assignmentData);
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
                if ($debug) {
                    $this->newLine();
                    $this->error("Errore assignment ID {$oldAssignment->id}: {$e->getMessage()}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("Assignments - Migrati: {$migrated}, Errori: {$errors}, Skipped: {$skipped}");
    }

    private function migrateAvailabilities(bool $dryRun, bool $debug): void
    {
        $this->info('5. Migrazione Availabilities...');

        try {
            $oldAvailabilities = DB::connection('old_mysql')->table('availabilities')->get();
        } catch (\Exception $e) {
            $this->warn("Tabella availabilities non accessibile: {$e->getMessage()}");
            return;
        }

        if ($oldAvailabilities->isEmpty()) {
            $this->warn('Nessuna availability trovata (normale se tabella vuota)');
            return;
        }

        $progress = $this->output->createProgressBar($oldAvailabilities->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;

        foreach ($oldAvailabilities as $oldAvailability) {
            try {
                $availabilityData = [
                    'user_id' => $this->findMappedUserId($oldAvailability->user_id),
                    'tournament_id' => $this->findMappedTournamentId($oldAvailability->tournament_id),
                    'notes' => $oldAvailability->notes,
                    'submitted_at' => $oldAvailability->submitted_at ?? now(),
                    'created_at' => $oldAvailability->created_at ?? now(),
                    'updated_at' => $oldAvailability->updated_at ?? now(),
                ];

                if (!$dryRun) {
                    DB::table('availabilities')->insert($availabilityData);
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("Availabilities - Migrati: {$migrated}, Errori: {$errors}");
    }

    private function mapZoneId(?int $oldZoneId): int
    {
        if (!$oldZoneId) return 1;

        $zone = Zone::find($oldZoneId);
        return $zone ? $zone->id : 1;
    }

    private function mapClubId(?int $oldClubId): int
    {
       if (!$oldClubId) return 1;

       // Trova club nel nuovo DB per ID corrispondente
        $club = DB::table('clubs')->orderBy('id')->find($oldClubId);

        return $club ? $club->id : 1;
    }

    private function mapTournamentTypeId($oldTournament): int
    {
    // Cerca per tournament_type_id se esiste
        if (isset($oldTournament)) {
            $type = TournamentType::find($oldTournament);
            if ($type) return $type->id;
        }

        // Fallback: cerca per nome o prendi il primo
        $firstType = TournamentType::first();
        return $firstType ? $firstType->id : 1;
    }

    private function mapUserId(?int $oldUserId): int
    {
        if (!$oldUserId) return 1;

        $user = User::find($oldUserId);
        return $user ? $user->id : 1;
    }

    private function findMappedUserId(?int $oldUserId): ?int
    {
        if (!$oldUserId) return null;

        $user = User::find($oldUserId);
        return $user ? $user->id : null;
    }

    private function findMappedTournamentId(?int $oldTournamentId): ?int
    {
        if (!$oldTournamentId) return null;

        // Assumiamo che i tournament ID siano sequenziali nella migrazione
        $tournament = DB::table('tournaments')->where('id', $oldTournamentId)->first();
        return $tournament ? $tournament->id : null;
    }

    private function mapStatus(?string $status): string
    {
        $validStatuses = ['draft', 'open', 'closed', 'assigned', 'completed', 'cancelled'];
        return in_array($status, $validStatuses) ? $status : 'draft';
    }

    private function mapRole(?string $role): string
    {
        $validRoles = ['Direttore di Torneo', 'Arbitro', 'Osservatore'];
        return in_array($role, $validRoles) ? $role : 'Arbitro';
    }

    private function parseDate($dateValue): ?string
    {
        if (empty($dateValue)) return null;

        try {
            return Carbon::parse($dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateTime($dateTimeValue): ?string
    {
        if (empty($dateTimeValue)) return null;

        try {
            return Carbon::parse($dateTimeValue)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function showFinalReport(): void
    {
        $this->info('REPORT FINALE MIGRAZIONE DATI CORRENTI:');
        $this->info('=========================================');

        $counts = [
            ['Clubs', DB::table('clubs')->count()],
            ['Tournaments', DB::table('tournaments')->count()],
            ['Assignments', DB::table('assignments')->count()],
            ['Availabilities', DB::table('availabilities')->count()],
            ['Users', User::count()],
            ['Zones', Zone::count()],
            ['Tournament Types', TournamentType::count()],
        ];

        $this->table(['Tabella', 'Count'], $counts);
    }
}
