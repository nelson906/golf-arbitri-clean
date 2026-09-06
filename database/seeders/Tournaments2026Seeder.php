<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Models\User;
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
     * - PRESERVA assignments e availabilities esistenti per tornei 2026
     * - Aggiorna tornei 2026 esistenti invece di cancellarli
     * - Crea solo nuovi tornei se non esistono
     * - Crea circoli mancanti se necessario
     * - Crea/verifica tournament_types
     * - Importa i tornei con circolo T.B.A. (To Be Assigned) usando la sola
     *   zona, quando il CSV la indica: prima venivano scartati e sparivano
     *   dal calendario finche' qualcuno non li reinseriva a mano
     */
    public function run(): void
    {
        $this->command->info('🏌️ Importazione Tornei 2026 dai Calendari PDF');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Step 0: Verifica esistenza super_admin per created_by
        $superAdmin = User::where('user_type', 'super_admin')->first();
        if (! $superAdmin) {
            $this->command->error('❌ Nessun super_admin trovato. Creare almeno un utente super_admin prima di eseguire questo seeder.');

            return;
        }

        // Step 1: Verifica tornei 2026 con dati collegati
        $this->checkExistingData();

        // Step 2: Crea/verifica tournament types
        $this->ensureTournamentTypes();

        // Step 3: Crea circoli mancanti
        $this->ensureClubs();

        // Step 4: Importa/aggiorna tornei dal CSV
        $this->importTournaments($superAdmin->id);

        $this->command->info('');
        $this->command->info('✅ Importazione completata!');
    }

    /**
     * Verifica tornei 2026 esistenti e mostra eventuali dati collegati
     */
    private function checkExistingData(): void
    {
        $this->command->warn('🔍 Verifica dati esistenti 2026...');

        $tournaments2026 = Tournament::where('start_date', '>=', '2026-01-01')
            ->where('start_date', '<', '2027-01-01')
            ->withCount(['assignments', 'availabilities'])
            ->get();

        if ($tournaments2026->isEmpty()) {
            $this->command->info('   Nessun torneo 2026 esistente');

            return;
        }

        $total = $tournaments2026->count();
        $withAssignments = $tournaments2026->where('assignments_count', '>', 0)->count();
        $withAvailabilities = $tournaments2026->where('availabilities_count', '>', 0)->count();

        $this->command->info("   Tornei 2026 esistenti: {$total}");
        if ($withAssignments > 0) {
            $this->command->warn("   ⚠️  Tornei con assignments: {$withAssignments}");
        }
        if ($withAvailabilities > 0) {
            $this->command->warn("   ⚠️  Tornei con availabilities: {$withAvailabilities}");
        }

        if ($withAssignments > 0 || $withAvailabilities > 0) {
            $this->command->info('   ✓ I dati esistenti saranno PRESERVATI');
            $this->command->info('   ℹ️  I tornei saranno aggiornati invece di cancellati');
        }

        // Verifica tornei 2025
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
     * Mapping CSV -> CODE del database
     *
     * @return array<string, mixed>
     */
    private function getCsvToCodeMapping(): array
    {
        return [
            'GOLF NAZIONALE' => 'NAZIONALE',
            'RIVIERA GOLF' => 'RIVIERA',
            'CONTINENTAL VERBANIA' => 'VERBANIA',
            'TOLCINASCO' => 'CASTELLO TOLCINASCO',
            'VILLA PARADISO' => 'VILLA PARADISO SSD',
            'RIVA TOSCANA' => 'RIVA TOSCANA GOLF RESORT',
            'MONTECCHIA GOLF' => 'MONTECCHIA',
            'MADONNA CAMPIGLIO' => 'MADONNA DI CAMPIGLIO',
            'SANTO STEFANO GOLF' => 'SANTO STEFANO',
            'OASI DI MAGLIANO-FIORDALISI' => 'FIORDALISI',
        ];
    }

    /**
     * Cerca un circolo nel DB per CODE
     */
    private function findClub(string $csvName): ?Club
    {
        // Controlla mapping
        $mapping = $this->getCsvToCodeMapping();
        $code = $mapping[$csvName] ?? $csvName;

        return Club::where('code', $code)->first();
    }

    /**
     * Risolve la zona indicata dal CSV per i tornei T.B.A.
     *
     * Il campo `zona_circolo` non ha un formato garantito (a volte il codice
     * "SZR3", a volte il nome esteso): si prova prima il codice, poi il nome.
     */
    private function findZone(string $csvZona): ?Zone
    {
        $zona = trim($csvZona);

        if ($zona === '' || $zona === 'N/D') {
            return null;
        }

        return Zone::where('code', $zona)->first()
            ?? Zone::where('name', $zona)->first();
    }

    /**
     * Verifica circoli (NON crea nulla)
     */
    private function ensureClubs(): void
    {
        $this->command->info('');
        $this->command->info('🏌️ Verifica Circoli...');

        $totalClubs = Club::count();
        $this->command->info("   ✓ Circoli nel database: {$totalClubs}");
        $this->command->info('   ℹ️  Nessun circolo verrà creato (solo ricerca)');
    }

    /**
     * Importa/aggiorna tornei dal CSV preservando assignments e availabilities
     */
    private function importTournaments(int $createdBy): void
    {
        $this->command->info('');
        $this->command->info('📅 Importazione Tornei 2026...');

        $csvPath = database_path('../calendari_2026_consolidato.csv');
        $file = fopen($csvPath, 'r');
        if ($file === false) {
            $this->command->error('CSV non apribile.');

            return;
        }

        $header = fgetcsv($file);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine(array_map('strval', $header ?: []), $row);

            try {
                // Torneo T.B.A.: data e zona note, circolo non ancora deciso.
                // Si importa lo stesso con club_id null (migration
                // 2026_09_06_000001), purche' il CSV dica almeno la zona:
                // senza zona non sarebbe visibile a nessun admin zonale e
                // resterebbe un record orfano.
                $isTba = $data['circolo'] === 'T.B.A.';

                if ($isTba) {
                    $zonaTba = $this->findZone((string) $data['zona_circolo']);

                    if (! $zonaTba) {
                        $this->command->warn("   ⚠ T.B.A. senza zona utilizzabile: {$data['nome_gara']}");
                        $skipped++;

                        continue;
                    }

                    $club = null;
                    $zoneId = $zonaTba->id;
                } else {
                    if ($data['zona_circolo'] === 'N/D') {
                        $skipped++;

                        continue;
                    }

                    // Trova circolo usando ricerca intelligente
                    $circoloName = $data['circolo'];
                    $club = $this->findClub((string) $circoloName);

                    if (! $club) {
                        $this->command->warn("   ⚠ Circolo non trovato: {$circoloName}");
                        $skipped++;

                        continue;
                    }

                    $zoneId = $club->zone_id;
                }

                // Trova tournament type
                $tournamentType = TournamentType::where('short_name', $data['short_name'])->first();
                if (! $tournamentType) {
                    $this->command->warn("   ⚠ Tournament type non trovato: {$data['short_name']}");
                    $skipped++;

                    continue;
                }

                // Calcola availability_deadline (3 settimane prima start_date)
                $startDate = Carbon::parse($data['data_inizio']);
                $endDate = Carbon::parse($data['data_fine']);
                $availabilityDeadline = $startDate->copy()->subWeeks(3)->setTime(23, 59, 59);

                // Cerca torneo esistente. Sui T.B.A. il circolo non c'e'
                // ancora, quindi l'identita' e' (nome, data) piu' la zona.
                $clubId = $club?->id;

                $existing = Tournament::where('name', $data['nome_gara'])
                    ->where('start_date', $startDate)
                    ->when(
                        $clubId !== null,
                        fn ($q) => $q->where('club_id', $clubId),
                        fn ($q) => $q->whereNull('club_id')->where('zone_id', $zoneId)
                    )
                    ->first();

                if ($existing) {
                    // AGGIORNA invece di cancellare (preserva assignments/availabilities)
                    $existing->update([
                        'end_date' => $endDate,
                        'tournament_type_id' => $tournamentType->id,
                        'zone_id' => $zoneId,
                        'availability_deadline' => $availabilityDeadline,
                        // NON aggiorniamo status se era già assigned/completed
                        // 'status' => Tournament::STATUS_OPEN,
                    ]);
                    $updated++;
                } else {
                    // Crea nuovo torneo
                    Tournament::create([
                        'name' => $data['nome_gara'],
                        'club_id' => $club?->id,
                        'tournament_type_id' => $tournamentType->id,
                        'zone_id' => $zoneId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'availability_deadline' => $availabilityDeadline,
                        'status' => Tournament::STATUS_OPEN,
                        'created_by' => $createdBy,
                    ]);
                    $imported++;
                }

                if (($imported + $updated) % 50 === 0) {
                    $this->command->info("   ... {$imported} nuovi, {$updated} aggiornati");
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
        $this->command->info("   ✓ Nuovi: {$imported}");
        $this->command->info("   ↻ Aggiornati: {$updated}");
        $this->command->info("   ⊘ Saltati: {$skipped}");
        if ($errors > 0) {
            $this->command->warn("   ❌ Errori: {$errors}");
        }
    }
}
