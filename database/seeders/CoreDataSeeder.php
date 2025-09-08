<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // $this->seedZones();
        // $this->seedTournamentTypes();
        $this->seedInstitutionalMails();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function seedZones(): void
    {
        DB::table('zones')->truncate();
        DB::table('zones')->insert([
            ['code' => 'SZR1', 'name' => 'Sezione Zonale Regole 1', 'email' => 'szr1@federgolf.it', 'description' => 'Piemonte-Valle d\'Aosta-Liguria', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR2', 'name' => 'Sezione Zonale Regole 2', 'email' => 'szr2@federgolf.it', 'description' => 'Lombardia', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR3', 'name' => 'Sezione Zonale Regole 3', 'email' => 'szr3@federgolf.it', 'description' => 'Veneto-Trentino-Friuli', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR4', 'name' => 'Sezione Zonale Regole 4', 'email' => 'szr4@federgolf.it', 'description' => 'Emilia-Romagna', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR5', 'name' => 'Sezione Zonale Regole 5', 'email' => 'szr5@federgolf.it', 'description' => 'Toscana-Umbria', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR6', 'name' => 'Sezione Zonale Regole 6', 'email' => 'szr6@federgolf.it', 'description' => 'Lazio-Abruzzo-Molise-Sardegna', 'is_national' => false, 'is_active' => true],
            ['code' => 'SZR7', 'name' => 'Sezione Zonale Regole 7', 'email' => 'szr7@federgolf.it', 'description' => 'Sud Italia-Sicilia', 'is_national' => false, 'is_active' => true],
            ['code' => 'CRC', 'name' => 'Comitato Regole Campionati', 'email' => 'crc@federgolf.it', 'description' => 'Comitato Regole e Campionati', 'is_national' => true, 'is_active' => true],
        ]);
    }

    private function seedTournamentTypes(): void
    {
        DB::table('tournament_types')->truncate();
        DB::table('tournament_types')->insert([
            // Zonali
            ['name' => 'Torneo 18 buche', 'short_name' => 'T18', 'is_national' => false, 'level' => 'zonale', 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 2, 'sort_order' => 10, 'is_active' => true],
            ['name' => 'Torneo 14 buche', 'short_name' => 'T14', 'is_national' => false, 'level' => 'zonale', 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 2, 'sort_order' => 8, 'is_active' => true],
            ['name' => 'Gara Giovanile', 'short_name' => 'GIOV', 'is_national' => false, 'level' => 'zonale', 'required_level' => 'aspirante', 'min_referees' => 1, 'max_referees' => 2, 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Gara a Squadre', 'short_name' => 'SQUAD', 'is_national' => false, 'level' => 'zonale', 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 3, 'sort_order' => 15, 'is_active' => true],
            ['name' => 'Gara Nazionale 36 buche', 'short_name' => 'GN-36', 'is_national' => false, 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 4, 'sort_order' => 25, 'is_active' => true],
            ['name' => 'Gara Nazionale 54 buche', 'short_name' => 'GN-54', 'is_national' => false, 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 4, 'sort_order' => 30, 'is_active' => true],

            // Nazionali
            ['name' => 'Gara Nazionale 72 buche', 'short_name' => 'GN-72', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 5, 'sort_order' => 35, 'is_active' => true],
            ['name' => 'Gara Nazionale 72/54 buche', 'short_name' => 'GN-72/54', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 5, 'sort_order' => 35, 'is_active' => true],
            ['name' => 'Campionato Nazionale', 'short_name' => 'CNZ', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 6, 'sort_order' => 40, 'is_active' => true],
            ['name' => 'Campionato Italiano', 'short_name' => 'CI', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 6, 'sort_order' => 45, 'is_active' => true],
            ['name' => 'Torneo Nazionale', 'short_name' => 'TNZ', 'is_national' => true, 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 4, 'sort_order' => 20, 'is_active' => true],
        ]);
    }

    private function seedInstitutionalMails(): void
    {
        DB::table('institutional_emails')->insert([
            // FIG
            ['name' => 'Federazione Italiana Golf - Segreteria', 'email' => 'segreteria@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
            ['name' => 'FIG - Direzione Tecnica', 'email' => 'tecnica@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
            ['name' => 'FIG - Comitato Regole', 'email' => 'regole@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],

            // Regionali (esempi)
            ['name' => 'Comitato Regionale Lombardia', 'email' => 'lombardia@federgolf.it', 'category' => 'Regionale', 'is_global' => false, 'zone_id' => 1, 'is_active' => true],
            ['name' => 'Comitato Regionale Lazio', 'email' => 'lazio@federgolf.it', 'category' => 'Regionale', 'is_global' => false, 'zone_id' => 2, 'is_active' => true],

            // Altri enti
            ['name' => 'European Golf Association', 'email' => 'info@ega-golf.ch', 'category' => 'Internazionale', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
        ]);
    }
}
