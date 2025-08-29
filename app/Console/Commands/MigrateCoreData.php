<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;

class MigrateCoreData extends Command
{
    protected $signature = 'migrate:core-data {--dry-run} {--limit=0} {--debug}';
    protected $description = 'Migra users unificata dal vecchio sistema (users + referees → users)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $debug = $this->option('debug');

        $this->info('🚀 Migrazione Core Data - Users Unificata');
        $this->info('=====================================');

        if ($dryRun) {
            $this->warn('🧪 MODALITÀ DRY-RUN (nessuna modifica al database)');
        }

        if ($debug) {
            $this->warn('🔍 DEBUG MODE ATTIVO');
        }

        // 1. Test connessione database vecchio
        if (!$this->testOldConnection()) {
            return 1;
        }

        // 2. Verifica zones
        $zonesCount = Zone::count();
        $this->info("📍 Zone presenti: {$zonesCount}");

        if ($zonesCount < 8) {
            $this->warn('⚠️ Poche zone trovate. Esegui: php artisan db:seed --class=CoreDataSeeder');
        }

        // 3. Migrazione users unificata
        $this->migrateUsersUnified($dryRun, $limit, $debug);

        // 4. Report finale
        if (!$dryRun) {
            $this->showFinalReport();
        }

        $this->info('✅ Migrazione completata!');
        return 0;
    }

    private function testOldConnection(): bool
    {
        try {
            $count = DB::connection('old_mysql')->table('users')->count();
            $this->info("✅ Connessione DB vecchio: {$count} users trovati");
            return true;
        } catch (\Exception $e) {
            $this->error('❌ Errore connessione database vecchio:');
            $this->error($e->getMessage());
            $this->warn('Verifica configurazione database in .env e config/database.php');
            return false;
        }
    }

    private function migrateUsersUnified(bool $dryRun, int $limit, bool $debug): void
    {
        $this->info('👥 Migrazione Users Unificata (users + referees → users)...');

        // Trunca tabella users per ripartire da capo
        if (!$dryRun) {
            $this->warn('🗑️ Svuoto tabella users per migrazione pulita...');

            try {
                // Metodo 1: Prova truncate con FK disable
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                DB::table('users')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                $this->info('✅ Truncate completato');
            } catch (\Exception $e) {
                // Metodo 2: Fallback con DELETE
                $this->warn('⚠️ Truncate fallito, uso DELETE');
                DB::table('users')->delete();
                // Reset auto increment
                DB::statement('ALTER TABLE users AUTO_INCREMENT = 1');
                $this->info('✅ DELETE completato');
            }
        }
        // Query users dal DB vecchio
        $query = DB::connection('old_mysql')->table('users');

        if ($limit > 0) {
            $query->limit($limit);
            $this->warn("⚠️ LIMITE ATTIVO: Solo {$limit} users");
        }

        $oldUsers = $query->get();

        // Get referees dal DB vecchio
        $oldReferees = DB::connection('old_mysql')
            ->table('referees')
            ->get()
            ->keyBy('user_id');

        $this->info("📊 Trovati {$oldUsers->count()} users e {$oldReferees->count()} referees");

        if ($oldUsers->isEmpty()) {
            $this->warn('⚠️ Nessun user trovato da migrare');
            return;
        }

        $progress = $this->output->createProgressBar($oldUsers->count());
        $progress->start();

        $migrated = 0;
        $errors = 0;

        foreach ($oldUsers as $oldUser) {
            try {
                $referee = $oldReferees->get($oldUser->id);

                // ✅ MAPPATURA DIRETTA - USA VALORI REALI
                $unifiedData = [
                    // Identità - VALORI REALI DAL DEBUG
                    'name' => $oldUser->name, // NON trim, mantieni originale
                    'first_name' => $oldUser->first_name, // Campo esiste!
                    'last_name' => $oldUser->last_name,   // Campo esiste!
                    'email' => strtolower($oldUser->email),

                    // Sistema - VALORI REALI
                    'user_type' => $oldUser->user_type, // admin/referee - mantieni originale
                    'password' => $oldUser->password,
                    'email_verified_at' => $oldUser->email_verified_at,
                    'remember_token' => $oldUser->remember_token,

                    // ✅ REFEREE DATA - VALORI REALI DAL DEBUG
                    'referee_code' => !empty(trim($oldUser->referee_code)) ? $oldUser->referee_code : null,
                    'level' => $oldUser->level, // Valore enum corretto già presente
                    'gender' => $this->mapGender($oldUser->category), // category → gender
                    'certified_date' => $this->parseDate($oldUser->certified_date),
                    'zone_id' => $oldUser->zone_id, // Mantieni diretto

                    // Contatti - VALORI REALI
                    'phone' => $oldUser->phone,
                    'city' => $oldUser->city,
                    'address' => $referee?->address,
                    'postal_code' => $referee?->postal_code,

                    // Extended data (da referees se esiste)
                    'tax_code' => $referee?->tax_code,
                    'badge_number' => $referee?->badge_number,
                    'first_certification_date' => $referee?->first_certification_date,
                    'last_renewal_date' => $referee?->last_renewal_date,
                    'expiry_date' => $referee?->expiry_date,
                    'bio' => $referee?->bio,
                    'experience_years' => $referee?->experience_years ?? 0,

                    // JSON fields
                    'qualifications' => $referee?->qualifications,
                    'languages' => $referee?->languages,
                    'specializations' => $referee?->specializations,
                    'preferences' => $oldUser->preferences ?? $referee?->preferences,

                    // Flags - VALORI REALI
                    'is_active' => (bool)$oldUser->is_active,
                    'available_for_international' => (bool)($referee?->available_for_international ?? false),

                    // Stats
                    'total_tournaments' => $referee?->total_tournaments ?? 0,
                    'tournaments_current_year' => $referee?->tournaments_current_year ?? 0,

                    // Timestamps - VALORI REALI
                    'last_login_at' => $oldUser->last_login_at,
                    'profile_completed_at' => $referee?->profile_completed_at,
                    'created_at' => $oldUser->created_at,
                    'updated_at' => $oldUser->updated_at,
                    'deleted_at' => $oldUser->deleted_at,
                ];

                // ✅ DEBUG per vedere mapping
                if ($debug && $migrated < 3) {
                    $this->newLine();
                    $this->warn("🔍 DEBUG User ID {$oldUser->id}:");
                    $this->table(
                        ['Campo', 'OLD Value', 'NEW Value'],
                        [
                            ['name', $oldUser->name, $unifiedData['name']],
                            ['first_name', $oldUser->first_name, $unifiedData['first_name']],
                            ['last_name', $oldUser->last_name, $unifiedData['last_name']],
                            ['user_type', $oldUser->user_type, $unifiedData['user_type']],
                            ['level', $oldUser->level, $unifiedData['level']],
                            ['referee_code', $oldUser->referee_code ?? 'NULL', $unifiedData['referee_code'] ?? 'NULL'],
                            ['zone_id', $oldUser->zone_id ?? 'NULL', $unifiedData['zone_id'] ?? 'NULL'],
                            ['category→gender', $oldUser->category ?? 'NULL', $unifiedData['gender']],
                        ]
                    );
                }

                if (!$dryRun) {
                    // ✅ INSERT DIRETTO invece di User::create per evitare mutators
                    DB::table('users')->insert($unifiedData);
                }

                $migrated++;
            } catch (\Exception $e) {
                $errors++;
                if ($debug) {
                    $this->newLine();
                    $this->error("❌ Errore user ID {$oldUser->id}: {$e->getMessage()}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();
        $this->info("✅ Migrati: {$migrated} users");

        if ($errors > 0) {
            $this->warn("❌ Errori: {$errors}");
        }
    }

    // ✅ HELPER SEMPLIFICATO
    private function mapGender(?string $category): string
    {
        if (empty($category)) return 'mixed';

        $cat = strtolower(trim($category));
        return match ($cat) {
            'maschile', 'uomini', 'm', 'male' => 'male',
            'femminile', 'donne', 'f', 'female' => 'female',
            'misto', 'mixed', 'm/f' => 'mixed',
            default => 'mixed'
        };
    }

    private function parseDate($dateValue): ?string
    {
        if (empty($dateValue) || $dateValue === 'NULL') return null;

        try {
            return Carbon::parse($dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
    private function normalizeLevel(?string $level): string
    {
        if (empty($level)) return '1_livello';

        // Mantieni il valore se già corretto
        $validLevels = ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale', 'Archivio'];
        if (in_array($level, $validLevels)) {
            return $level;
        }

        // Mappatura per valori non standard
        return match (strtolower(trim($level))) {
            'aspirante' => 'Aspirante',
            '1_livello', 'primo_livello', 'primo livello' => '1_livello',
            'regionale' => 'Regionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
            'archivio' => 'Archivio',
            default => '1_livello'
        };
    }

    private function mapZoneId(?int $zoneId): ?int
    {
        if (empty($zoneId)) return null;

        // Se zone ID esiste nel nuovo DB, usalo
        $zone = Zone::find($zoneId);
        if ($zone) {
            return $zone->id;
        }

        // Fallback alla prima zona
        $firstZone = Zone::first();
        return $firstZone ? $firstZone->id : null;
    }

    private function parseJson($jsonValue): ?array
    {
        if (empty($jsonValue)) return null;
        if (is_array($jsonValue)) return $jsonValue;

        try {
            $decoded = json_decode($jsonValue, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function showFinalReport(): void
    {
        $this->info('📊 REPORT FINALE:');
        $this->info('==================');

        $totalUsers = User::count();
        $refereeUsers = User::where('user_type', 'referee')->count();
        $adminUsers = User::where('user_type', '!=', 'referee')->count();

        $this->table(
            ['Tipo', 'Count'],
            [
                ['Total Users', $totalUsers],
                ['Referees', $refereeUsers],
                ['Admins', $adminUsers],
                ['Zones', Zone::count()],
            ]
        );

        // Distribuzione per user_type
        $userTypes = User::select('user_type', DB::raw('count(*) as count'))
            ->groupBy('user_type')
            ->get();

        $this->info('📊 Distribuzione User Types:');
        $this->table(
            ['User Type', 'Count'],
            $userTypes->map(fn($ut) => [$ut->user_type, $ut->count])->toArray()
        );
    }
}
