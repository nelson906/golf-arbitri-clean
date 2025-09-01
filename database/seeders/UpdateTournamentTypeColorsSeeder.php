<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TournamentType;

class UpdateTournamentTypeColorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colors = [
            'G12'    => '#c4aeda',
            'G14'    => '#66FFFF',
            'G16'    => '#ffff99',
            'G18'    => '#808080',
            'T18'    => '#ff7171',
            'S14'    => '#00FFFF',
            'TG'     => '#98cb00',
            'TGF'    => '#FFFF00',
            'GN36'   => '#76933C',
            'GN54'   => '#586D2D',
            'GN72'   => '#39471D',
            'CR'     => '#ff860d',
            'TR'     => '#fabf8e',
            'CNZ'    => '#0070c0',
            'TNZ'    => '#0070c0',
            'CI'     => '#0070c0',
            'PRO'    => '#000000',
            'PATR'   => '#91ccdc',
            'MP'     => '#953634',
            'USK'    => '#f43208',
            'EVENTO' => '#f43208',
            'GRS'    => '#f43208',
        ];

        foreach ($colors as $code => $color) {
            TournamentType::where('short_name', $code)
                ->orWhere('name', $code)
                ->update(['calendar_color' => $color]);
        }

        echo "Tournament type colors updated successfully!\n";
    }
}
