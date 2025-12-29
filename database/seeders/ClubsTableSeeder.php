<?php

namespace Database\Seeders;

use App\Models\Club;
use Illuminate\Database\Seeder;

class ClubsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clubs = [
            // SZR1 - Piemonte-Valle d'Aosta-Liguria
            [
                'name' => 'Golf Club Torino',
                'code' => 'GCT001',
                'email' => 'info@golftorino.it',
                'phone' => '+39 011 1234567',
                'address' => 'Strada del Golf 10',
                'city' => 'Torino',
                'province' => 'TO',
                'zone_id' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Royal Park I Roveri',
                'code' => 'RPR001',
                'email' => 'info@royalparkroveri.it',
                'phone' => '+39 011 9234567',
                'address' => 'Rotta Cerbiatta 24',
                'city' => 'Fiano',
                'province' => 'TO',
                'zone_id' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Circolo Golf e Tennis Rapallo',
                'code' => 'GTR001',
                'email' => 'info@golfrapallo.it',
                'phone' => '+39 0185 261777',
                'address' => 'Via Mameli 377',
                'city' => 'Rapallo',
                'province' => 'GE',
                'zone_id' => 1,
                'is_active' => true,
            ],

            // SZR2 - Lombardia
            [
                'name' => 'Golf Milano',
                'code' => 'GMI001',
                'email' => 'info@golfmilano.it',
                'phone' => '+39 02 4567890',
                'address' => 'Parco di Monza',
                'city' => 'Monza',
                'province' => 'MB',
                'zone_id' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'L\'Albenza Golf Club',
                'code' => 'ALB001',
                'email' => 'info@lalbenza.com',
                'phone' => '+39 035 640028',
                'address' => 'Via Longuelo 12',
                'city' => 'Bergamo',
                'province' => 'BG',
                'zone_id' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Franciacorta Golf Club',
                'code' => 'FRA001',
                'email' => 'info@franciacortagolf.it',
                'phone' => '+39 030 984167',
                'address' => 'Via Provinciale 34 bis',
                'city' => 'Nigoline di Corte Franca',
                'province' => 'BS',
                'zone_id' => 2,
                'is_active' => true,
            ],

            // SZR3 - Veneto-Trentino-Friuli
            [
                'name' => 'Golf Club Venezia',
                'code' => 'GCV001',
                'email' => 'info@golfvenezia.it',
                'phone' => '+39 041 731015',
                'address' => 'Via del Forte 6',
                'city' => 'Venezia',
                'province' => 'VE',
                'zone_id' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Golf Club Asolo',
                'code' => 'ASO001',
                'email' => 'info@golfasolo.it',
                'phone' => '+39 0423 529648',
                'address' => 'Via Forestuzzo 3',
                'city' => 'Asolo',
                'province' => 'TV',
                'zone_id' => 3,
                'is_active' => true,
            ],

            // SZR4 - Emilia-Romagna
            [
                'name' => 'Bologna Golf Club',
                'code' => 'BGL001',
                'email' => 'info@bolognagolf.it',
                'phone' => '+39 051 969100',
                'address' => 'Via Sabattini 69',
                'city' => 'Monte San Pietro',
                'province' => 'BO',
                'zone_id' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Modena Golf & Country Club',
                'code' => 'MGC001',
                'email' => 'info@modenagolf.it',
                'phone' => '+39 059 553582',
                'address' => 'Via Papotti 21',
                'city' => 'Colombaro',
                'province' => 'MO',
                'zone_id' => 4,
                'is_active' => true,
            ],

            // SZR5 - Toscana-Umbria
            [
                'name' => 'Circolo Golf Ugolino',
                'code' => 'UGO001',
                'email' => 'info@golfugolino.it',
                'phone' => '+39 055 2301009',
                'address' => 'Via Chiantigiana 3',
                'city' => 'Grassina',
                'province' => 'FI',
                'zone_id' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Golf Club Punta Ala',
                'code' => 'PTA001',
                'email' => 'info@puntaalagolf.it',
                'phone' => '+39 0564 922121',
                'address' => 'Via del Golf',
                'city' => 'Punta Ala',
                'province' => 'GR',
                'zone_id' => 5,
                'is_active' => true,
            ],

            // SZR6 - Lazio-Abruzzo-Molise
            [
                'name' => 'Olgiata Golf Club',
                'code' => 'OLG001',
                'email' => 'info@olgiata.it',
                'phone' => '+39 06 30889141',
                'address' => 'Largo Olgiata 15',
                'city' => 'Roma',
                'province' => 'RM',
                'zone_id' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Marco Simone Golf & Country Club',
                'code' => 'MSI001',
                'email' => 'info@marcosimone.com',
                'phone' => '+39 06 90197070',
                'address' => 'Via di Marco Simone 84',
                'city' => 'Guidonia Montecelio',
                'province' => 'RM',
                'zone_id' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Circolo Golf Mirasole',
                'code' => 'MIR001',
                'email' => 'info@golfmirasole.it',
                'phone' => '+39 06 71354477',
                'address' => 'Via di Fioranello 41',
                'city' => 'Roma',
                'province' => 'RM',
                'zone_id' => 6,
                'is_active' => true,
            ],

            // SZR7 - Sud Italia-Sicilia-Sardegna
            [
                'name' => 'Villa Airoldi Golf Club',
                'code' => 'VAI001',
                'email' => 'info@villaairoldi.it',
                'phone' => '+39 091 8769041',
                'address' => 'Via Plauto 47',
                'city' => 'Palermo',
                'province' => 'PA',
                'zone_id' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Is Molas Golf Club',
                'code' => 'ISM001',
                'email' => 'info@ismolas.it',
                'phone' => '+39 070 9241013',
                'address' => 'SS 195 km 30.3',
                'city' => 'Pula',
                'province' => 'CA',
                'zone_id' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Pevero Golf Club',
                'code' => 'PEV001',
                'email' => 'info@peverogolf.com',
                'phone' => '+39 0789 96072',
                'address' => 'Cala di Volpe',
                'city' => 'Porto Cervo',
                'province' => 'SS',
                'zone_id' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Acaya Golf Club',
                'code' => 'ACA001',
                'email' => 'info@acayagolf.com',
                'phone' => '+39 0832 861385',
                'address' => 'SP 364 km 3.5',
                'city' => 'Vernole',
                'province' => 'LE',
                'zone_id' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($clubs as $clubData) {
            Club::create($clubData);
        }

        $this->command->info('✓ Created '.count($clubs).' clubs');
    }
}
