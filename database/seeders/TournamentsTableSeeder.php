<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TournamentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Trova il primo admin o super_admin per created_by
        $admin = User::whereIn('user_type', ['super_admin', 'national_admin', 'admin'])
            ->first();

        if (! $admin) {
            $this->command->error('✗ No admin user found. Run UsersTableSeeder first.');

            return;
        }

        $clubs = Club::all();
        $tournamentTypes = TournamentType::all();

        if ($clubs->isEmpty() || $tournamentTypes->isEmpty()) {
            $this->command->error('✗ No clubs or tournament types found. Run ClubsTableSeeder and CoreDataSeeder first.');

            return;
        }

        $tournaments = [];

        // Tornei passati (completati)
        $tournaments[] = [
            'name' => 'Campionato Nazionale Assoluto',
            'start_date' => Carbon::now()->subMonths(3)->format('Y-m-d'),
            'end_date' => Carbon::now()->subMonths(3)->addDays(3)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->subMonths(3)->subDays(15)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'OLG001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'CI')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => null, // Nazionale
            'status' => 'completed',
            'description' => 'Campionato Italiano Assoluto - 72 buche stroke play',
            'notes' => 'Torneo completato con successo',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Torneo delle Regioni',
            'start_date' => Carbon::now()->subMonths(2)->format('Y-m-d'),
            'end_date' => Carbon::now()->subMonths(2)->addDays(2)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->subMonths(2)->subDays(20)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'GMI001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GN-72/54')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => null,
            'status' => 'completed',
            'description' => 'Gara a squadre tra le regioni italiane',
            'notes' => null,
            'created_by' => $admin->id,
        ];

        // Tornei imminenti (assegnati)
        $tournaments[] = [
            'name' => 'Trofeo Primavera 2025',
            'start_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(16)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'UGO001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GN-36')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 5, // SZR5
            'status' => 'assigned',
            'description' => 'Gara nazionale 36 buche - categoria under 18',
            'notes' => 'Arbitri assegnati, in attesa di conferma',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Open di Bergamo',
            'start_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addDays(8)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'ALB001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'T18')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 2, // SZR2
            'status' => 'assigned',
            'description' => 'Torneo 18 buche stableford',
            'notes' => null,
            'created_by' => $admin->id,
        ];

        // Tornei aperti per disponibilità
        $tournaments[] = [
            'name' => 'Campionato Regionale Lombardia',
            'start_date' => Carbon::now()->addDays(35)->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(36)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addDays(20)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'FRA001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GN-36')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 2,
            'status' => 'open',
            'description' => 'Campionato regionale 36 buche stroke play',
            'notes' => 'Si richiede la disponibilità degli arbitri regionali SZR2',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Coppa Italia Giovanile',
            'start_date' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(42)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addDays(25)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'MSI001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GIOV')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => null, // Nazionale
            'status' => 'open',
            'description' => 'Gara giovanile nazionale - categorie U12, U14, U16',
            'notes' => 'Richiesti arbitri con esperienza nelle gare giovanili',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Trofeo del Mare',
            'start_date' => Carbon::now()->addDays(50)->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(50)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addDays(30)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'PEV001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'T18')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 7, // SZR7
            'status' => 'open',
            'description' => 'Torneo sociale 18 buche',
            'notes' => null,
            'created_by' => $admin->id,
        ];

        // Tornei in bozza
        $tournaments[] = [
            'name' => 'Settimana Golfistica Toscana',
            'start_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(2)->addDays(6)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addMonths(1)->addDays(15)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'PTA001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GN-72')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 5,
            'status' => 'draft',
            'description' => 'Evento golfistico di 7 giorni con multiple gare',
            'notes' => 'In fase di pianificazione',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Memorial Angelo Binda',
            'start_date' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(3)->addDays(1)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addMonths(2)->addDays(15)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'BGL001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'GN-36')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 4,
            'status' => 'draft',
            'description' => 'Memorial dedicato al maestro Angelo Binda',
            'notes' => 'Date da confermare con il circolo',
            'created_by' => $admin->id,
        ];

        $tournaments[] = [
            'name' => 'Gara a Squadre Veneto',
            'start_date' => Carbon::now()->addMonths(2)->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(2)->addDays(11)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->addMonths(1)->addDays(20)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->where('code', 'ASO001')->first()->id ?? $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'SQUAD')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 3,
            'status' => 'draft',
            'description' => 'Gara a squadre regionale - formato fourball',
            'notes' => null,
            'created_by' => $admin->id,
        ];

        // Torneo cancellato
        $tournaments[] = [
            'name' => 'Trofeo di Natale 2024',
            'start_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'end_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'availability_deadline' => Carbon::now()->subMonths(2)->format('Y-m-d H:i:s'),
            'club_id' => $clubs->random()->id,
            'tournament_type_id' => $tournamentTypes->where('short_name', 'T18')->first()->id ?? $tournamentTypes->random()->id,
            'zone_id' => 1,
            'status' => 'cancelled',
            'description' => 'Torneo natalizio annullato',
            'notes' => 'Annullato per maltempo',
            'created_by' => $admin->id,
        ];

        foreach ($tournaments as $tournamentData) {
            Tournament::create($tournamentData);
        }

        $this->command->info('✓ Created '.count($tournaments).' tournaments');
    }
}
