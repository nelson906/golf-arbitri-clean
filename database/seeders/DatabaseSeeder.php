<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Dati fondamentali (zone, tournament_types, institutional_emails)
            CoreDataSeeder::class,

            // 2. Aggiorna colori dei tipi di torneo
            UpdateTournamentTypeColorsSeeder::class,

            // 3. Clausole di notifica
            NotificationClauseSeeder::class,

            // 4. Utenti (admin e arbitri)
            UsersTableSeeder::class,

            // 5. Circoli
            ClubsTableSeeder::class,

            // 6. Storia carriera arbitri (dipende da UsersTableSeeder)
            RefereeCareerHistorySeeder::class,

            // 7. Tornei (dipende da ClubsTableSeeder e UsersTableSeeder)
            TournamentsTableSeeder::class,
        ]);
    }
}
