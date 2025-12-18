<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FedergolfController extends Controller
{
    // Cerca gare per nome
    public function searchCompetitions(Request $request)
    {
        $keyword = $request->input('keyword', '');

        $response = Http::asForm()->post(
            'https://www.federgolf.it/wp-admin/admin-ajax.php',
            [
                'action' => 'competitions-search',
                'tipo' => '',
                'keyword' => $keyword,
                'anno' => date('Y'),
                'mese' => '',
            ]
        );

        $data = $response->json();
        $gare = [];

        foreach ($data['data'] ?? [] as $gara) {
            $tipo = 'MISTA';
            if (stripos($gara['title'], 'MASCHILE') !== false) {
                $tipo = 'MASCHILE';
            } elseif (stripos($gara['title'], 'FEMMINILE') !== false) {
                $tipo = 'FEMMINILE';
            }

            $gare[] = [
                'id' => $gara['id'],
                'title' => $gara['title'],
                'tipo' => $tipo,
                'date' => $gara['date'] ?? null,
            ];
        }

        return response()->json(['success' => true, 'gare' => $gare]);
    }

    // Carica iscritti di una gara specifica
    public function getIscritti(Request $request)
    {
        $garaId = $request->input('gara_id');

        $response = Http::asForm()->post(
            'https://www.federgolf.it/wp-admin/admin-ajax.php',
            [
                'action' => 'competition-player-list',
                'competition_id' => $garaId,
                'page_number' => 1,
                'page_size' => 250,
            ]
        );

        $data = $response->json();
        $iscritti = [];

        foreach ($data['data']['processedData'] ?? [] as $entry) {
            // Solo iscritti ammessi
            if (strpos($entry[8], 'icona-ammesso') === false) {
                continue;
            }

            preg_match('/<span class="nome-giocatore">([^<]+)<\/span>/', $entry[1], $matches);
            if (! empty($matches[1])) {
                $iscritti[] = trim($matches[1]);
            }
        }

        return response()->json(['success' => true, 'iscritti' => $iscritti]);
    }

    public function loadAllCompetitions(Request $request)
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action' => 'competitions-search',
                    'tipo' => '',
                    'keyword' => '',
                    'anno' => date('Y'),
                    'mese' => '',
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => false, 'message' => 'Errore connessione']);
            }

            $data = $response->json();
            $oggi = new \DateTime;
            $gare = [];

            foreach ($data['data'] ?? [] as $gara) {
                // Salta gare annullate
                if ($gara['annullata'] == 1) {
                    continue;
                }

                // Salta gare con titolo che contiene "ANNULLATA" o "RINVIATA"
                if (
                    stripos($gara['nome'], 'ANNULLATA') !== false ||
                    stripos($gara['nome'], 'RINVIATA') !== false ||
                    stripos($gara['nome'], 'RINVIATO') !== false
                ) {
                    continue;
                }

                // Converti data da formato dd/mm/yyyy
                $dataGara = \DateTime::createFromFormat('d/m/Y', $gara['data']);

                // Salta gare passate
                if ($dataGara && $dataGara < $oggi) {
                    continue;
                }

                $tipo = 'MISTA';
                if (stripos($gara['nome'], 'MASCHILE') !== false) {
                    $tipo = 'MASCHILE';
                } elseif (stripos($gara['nome'], 'FEMMINILE') !== false) {
                    $tipo = 'FEMMINILE';
                }

                $gare[] = [
                    'id' => $gara['competition_id'],
                    'title' => $gara['nome'],
                    'tipo' => $tipo,
                    'date' => $gara['data'],
                    'club' => $gara['club'] ?? null,
                ];
            }

            // Ordina per data crescente
            usort($gare, function ($a, $b) {
                $dateA = \DateTime::createFromFormat('d/m/Y', $a['date']);
                $dateB = \DateTime::createFromFormat('d/m/Y', $b['date']);

                return $dateA <=> $dateB;
            });

            return response()->json(['success' => true, 'gare' => $gare]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
