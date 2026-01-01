<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Tournaments2026Seeder extends Seeder
{
    /**
     * Seeder per importare tornei 2026 dai calendari PDF.
     *
     * IMPORTANTE: Questo seeder:
     * - NON cancella i tornei 2025 (preserva storico per referee_history)
     * - Cancella SOLO tornei con start_date >= '2026-01-01'
     * - Crea circoli mancanti se necessario
     * - Crea/verifica tournament_types
     * - Salta tornei con circolo T.B.A. (To Be Assigned)
     */
    public function run(): void
    {
        $this->command->info('🏌️ Importazione Tornei 2026 dai Calendari PDF');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Step 0: Verifica esistenza super_admin per created_by
        $superAdmin = User::where('user_type', 'super_admin')->first();
        if (!$superAdmin) {
            $this->command->error('❌ Nessun super_admin trovato. Creare almeno un utente super_admin prima di eseguire questo seeder.');
            return;
        }

        // Step 1: Cancella SOLO tornei 2026 esistenti
        $this->deleteTournaments2026();

        // Step 2: Crea/verifica tournament types
        $this->ensureTournamentTypes();

        // Step 3: Crea circoli mancanti
        $this->ensureClubs();

        // Step 4: Importa tornei dal CSV
        $this->importTournaments($superAdmin->id);

        $this->command->info('');
        $this->command->info('✅ Importazione completata!');
    }

    /**
     * Cancella SOLO i tornei 2026 (preserva 2025 e precedenti)
     */
    private function deleteTournaments2026(): void
    {
        $this->command->warn('🗑️  Cancellazione tornei 2026 esistenti...');

        $count = Tournament::where('start_date', '>=', '2026-01-01')
            ->where('start_date', '<', '2027-01-01')
            ->count();

        if ($count > 0) {
            $this->command->info("   Trovati {$count} tornei 2026 da cancellare");

            // Cancella anche availabilities e assignments associati (cascade)
            Tournament::where('start_date', '>=', '2026-01-01')
                ->where('start_date', '<', '2027-01-01')
                ->delete();

            $this->command->info("   ✓ {$count} tornei 2026 cancellati");
        } else {
            $this->command->info('   Nessun torneo 2026 esistente');
        }

        // Verifica tornei 2025 preservati
        $count2025 = Tournament::where('start_date', '>=', '2025-01-01')
            ->where('start_date', '<', '2026-01-01')
            ->count();
        $this->command->info("   ✓ Tornei 2025 preservati: {$count2025}");
    }

    /**
     * Crea/verifica tournament types necessari
     */
    private function ensureTournamentTypes(): void
    {
        $this->command->info('');
        $this->command->info('📋 Verifica Tournament Types...');

        $types = [
            ['name' => 'Campionato Internazionale', 'short_name' => 'CI', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'internazionale', 'min_referees' => 3, 'max_referees' => 6],
            ['name' => 'Campionato Nazionale', 'short_name' => 'CNZ', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 6],
            ['name' => 'Torneo Nazionale', 'short_name' => 'TNZ', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 4],
            ['name' => 'Gara Nazionale 54/54', 'short_name' => 'GN54', 'is_national' => false, 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 4],
            ['name' => 'Trofeo Giovanile Federale', 'short_name' => 'TGF', 'is_national' => false, 'level' => 'zonale', 'required_level' => 'aspirante', 'min_referees' => 1, 'max_referees' => 2],
            ['name' => 'Gara Patrocinata', 'short_name' => 'PATR', 'is_national' => false, 'level' => 'zonale', 'required_level' => 'aspirante', 'min_referees' => 1, 'max_referees' => 2],
            ['name' => 'Gara Internazionale U.S. Kids', 'short_name' => 'USK', 'is_national' => false, 'level' => 'zonale', 'required_level' => 'aspirante', 'min_referees' => 1, 'max_referees' => 2],
            ['name' => 'Torneo 18 buche', 'short_name' => 'T18', 'is_national' => false, 'level' => 'zonale', 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['name' => 'Campionato Regionale', 'short_name' => 'CR', 'is_national' => false, 'level' => 'zonale', 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 3],
        ];

        $created = 0;
        $existing = 0;

        foreach ($types as $typeData) {
            $type = TournamentType::firstOrCreate(
                ['short_name' => $typeData['short_name']],
                array_merge($typeData, [
                    'is_active' => true,
                    'sort_order' => 0,
                ])
            );

            if ($type->wasRecentlyCreated) {
                $created++;
                $this->command->info("   + Creato: {$typeData['name']} ({$typeData['short_name']})");
            } else {
                $existing++;
            }
        }

        $this->command->info("   ✓ {$existing} esistenti, {$created} creati");
    }

    /**
     * Crea circoli mancanti dal CSV
     */
    private function ensureClubs(): void
    {
        $this->command->info('');
        $this->command->info('🏌️ Verifica Circoli...');

        $csvPath = database_path('../calendari_2026_consolidato.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("   ❌ File CSV non trovato: {$csvPath}");
            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $clubsToCreate = [];
        $circoli = [];

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            $circoloName = $data['circolo'];
            $zonaCircolo = $data['zona_circolo'];

            // Salta T.B.A. e duplicati
            if ($circoloName === 'T.B.A.' || $zonaCircolo === 'N/D' || isset($circoli[$circoloName])) {
                continue;
            }

            $circoli[$circoloName] = $zonaCircolo;
        }
        fclose($file);

        $created = 0;
        $existing = 0;

        foreach ($circoli as $circoloName => $zonaId) {
            // Cerca circolo esistente per nome o code normalizzato
            $code = strtoupper(str_replace(' ', '_', $circoloName));

            $club = Club::where('name', 'LIKE', "%{$circoloName}%")
                ->orWhere('code', $code)
                ->first();

            if (!$club) {
                // Crea nuovo circolo
                $club = Club::create([
                    'name' => ucwords(strtolower($circoloName)),
                    'code' => $code,
                    'zone_id' => $zonaId,
                    'email' => strtolower($code) . '@golf.it',
                    'is_active' => true,
                ]);
                $created++;
                $this->command->info("   + Creato: {$circoloName} (Zona {$zonaId})");
            } else {
                $existing++;
            }
        }

        $this->command->info("   ✓ {$existing} esistenti, {$created} creati");
    }

    /**
     * Importa tornei dal CSV
     */
    private function importTournaments(int $createdBy): void
    {
        $this->command->info('');
        $this->command->info('📅 Importazione Tornei 2026...');

        $csvPath = database_path('../calendari_2026_consolidato.csv');
        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            try {
                // Salta T.B.A.
                if ($data['circolo'] === 'T.B.A.' || $data['zona_circolo'] === 'N/D') {
                    $skipped++;
                    continue;
                }

                // Trova circolo
                $circoloName = $data['circolo'];
                $code = strtoupper(str_replace(' ', '_', $circoloName));

                $club = Club::where('name', 'LIKE', "%{$circoloName}%")
                    ->orWhere('code', $code)
                    ->first();

                if (!$club) {
                    $this->command->warn("   ⚠ Circolo non trovato: {$circoloName}");
                    $skipped++;
                    continue;
                }

                // Trova tournament type
                $tournamentType = TournamentType::where('short_name', $data['short_name'])->first();
                if (!$tournamentType) {
                    $this->command->warn("   ⚠ Tournament type non trovato: {$data['short_name']}");
                    $skipped++;
                    continue;
                }

                // Calcola availability_deadline (3 settimane prima start_date)
                $startDate = Carbon::parse($data['data_inizio']);
                $endDate = Carbon::parse($data['data_fine']);
                $availabilityDeadline = $startDate->copy()->subWeeks(3)->setTime(23, 59, 59);

                // Verifica duplicato
                $existing = Tournament::where('name', $data['nome_gara'])
                    ->where('club_id', $club->id)
                    ->where('start_date', $startDate)
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Crea torneo
                Tournament::create([
                    'name' => $data['nome_gara'],
                    'club_id' => $club->id,
                    'tournament_type_id' => $tournamentType->id,
                    'zone_id' => $club->zone_id, // Sempre dalla relazione club
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'availability_deadline' => $availabilityDeadline,
                    'status' => Tournament::STATUS_OPEN,
                    'created_by' => $createdBy,
                ]);

                $imported++;

                if ($imported % 50 === 0) {
                    $this->command->info("   ... {$imported} tornei importati");
                }

            } catch (\Exception $e) {
                $errors++;
                $this->command->error("   ❌ Errore: {$data['nome_gara']} - {$e->getMessage()}");
                Log::error("Errore import torneo 2026: {$data['nome_gara']}", [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        }

        fclose($file);

        $this->command->info('');
        $this->command->info("   ✓ Importati: {$imported}");
        $this->command->info("   ⊘ Saltati: {$skipped}");
        if ($errors > 0) {
            $this->command->warn("   ❌ Errori: {$errors}");
        }
    }
}
