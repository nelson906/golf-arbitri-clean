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

        // REGRESSIONE: senza User-Agent + timeout esplicito + try/catch, federgolf.it
        // andava in cURL timeout 28 (30s) per gare con tanti partecipanti
        // (es. "Quercia d'Oro"), e il 500 risultante mostrava solo un alert
        // generico "Errore nel caricamento degli iscritti" senza diagnostica.
        // loadAllCompetitions usa già questi header: allineiamo getIscritti.
        try {
            $response = Http::timeout(60)
                ->connectTimeout(10)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action' => 'competition-player-list',
                    'competition_id' => $garaId,
                    'page_number' => 1,
                    'page_size' => 250,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::warning('Federgolf timeout/connect getIscritti', [
                'gara_id' => $garaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'iscritti' => [],
                'totale_iscritti' => 0,
                'ammessi' => 0,
                'iscrizioni_aperte' => false,
                'message' => 'Federgolf.it non risponde (timeout). Riprovare tra qualche secondo o controllare la connessione.',
            ], 200);  // 200 + success=false, così il JS può mostrare il message senza finire nel ramo catch
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'iscritti' => [],
                'totale_iscritti' => 0,
                'ammessi' => 0,
                'iscrizioni_aperte' => false,
                'message' => 'Federgolf.it ha risposto con errore HTTP '.$response->status().'. Riprovare più tardi.',
            ], 200);
        }

        $data = $response->json();
        $entries = $data['data']['processedData'] ?? [];

        $iscritti = [];
        $totale = count($entries);
        $ammessi = 0; // righe che hanno l'icona-ammesso (lista chiusa)

        foreach ($entries as $entry) {
            // Solo iscritti ammessi: l'ammissione è segnalata da `icona-ammesso`
            // nella colonna stato (l'ultima). Finché le iscrizioni non sono
            // chiuse, nessun iscritto ha questa icona → 0 ammessi.
            if (! isset($entry[8]) || strpos($entry[8], 'icona-ammesso') === false) {
                continue;
            }

            $ammessi++;

            preg_match('/<span class="nome-giocatore">([^<]+)<\/span>/', $entry[1], $matches);
            if (! empty($matches[1])) {
                // Decodifica entità HTML (es. &#039; → ' nei nomi tipo "D'Oro")
                $iscritti[] = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        // Se ci sono righe ma nessun ammesso → iscrizioni non ancora chiuse.
        // Avvisiamo il client perché NON deve sovrascrivere i campi nominativi
        // né azzerare i contatori a video.
        $iscrizioniAperte = ($totale > 0 && $ammessi === 0);

        return response()->json([
            'success'            => true,
            'iscritti'           => $iscritti,
            'totale_iscritti'    => $totale,
            'ammessi'            => $ammessi,
            'iscrizioni_aperte'  => $iscrizioniAperte,
            'message'            => $iscrizioniAperte
                ? 'Iscrizioni non ancora chiuse: nessun iscritto ammesso. Riprovare dopo la chiusura.'
                : null,
        ]);
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
