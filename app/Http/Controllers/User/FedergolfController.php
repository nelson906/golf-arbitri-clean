<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FedergolfController extends Controller
{
    // Cerca gare per nome
    public function searchCompetitions(Request $request)
    {
        $keyword = $request->input('keyword', '');

        $response = Http::asForm()->post(
            config('golf.fig.ajax_url'),
            [
                'action' => 'competitions-search',
                'tipo' => '',
                'keyword' => $keyword,
                'anno' => date('Y'),
                'mese' => '',
            ]
        );

        $data = $response->json();

        // C2: corpo non-JSON (manutenzione/WAF) → errore esplicito,
        // non una lista vuota spacciata per "nessun risultato".
        if (! is_array($data)) {
            return response()->json(['success' => false, 'message' => 'Errore connessione']);
        }

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

    /**
     * Carica iscritti di una gara specifica.
     *
     * Restituisce un payload con state enum esplicito per eliminare
     * combinazioni di flag (success/iscrizioni_aperte/iscritti.length) che
     * il frontend doveva ricomporre. Stati possibili:
     *
     *   - 'ready': gara chiusa con iscritti ammessi → usa $iscritti
     *   - 'open':  ci sono iscritti ma nessuno ancora ammesso (lista non chiusa)
     *   - 'empty': gara senza iscritti
     *   - 'error': rete/timeout/HTTP error (federgolf.it non risponde)
     *
     * Cache 60s per gara_id: federgolf.it può essere lenta o rate-limit;
     * cachare il dato per un minuto riduce di ~10× le chiamate durante una
     * sessione di lavoro normale.
     */
    public function getIscritti(Request $request)
    {
        // C1: senza validazione un gara_id array/assente produce 500
        // (Array to string conversion) o chiave cache condivisa.
        $validated = $request->validate(['gara_id' => 'required|integer']);
        $garaId = $validated['gara_id'];

        $cacheKey = "federgolf.iscritti.{$garaId}";
        $payload = Cache::remember($cacheKey, 60, fn () => $this->fetchIscritti($garaId));

        // Errori di rete: NON cachiamo per 60s, sennò "blocchiamo" l'utente.
        // Cache::remember non sa il nostro state, quindi rileggiamo e invalidiamo.
        if (($payload['state'] ?? null) === 'error') {
            Cache::forget($cacheKey);
        }

        return response()->json($payload);
    }

    /**
     * Esegue la chiamata HTTP a federgolf.it e classifica il risultato in
     * uno dei 4 state. Restituisce un array semplice (non Response) così
     * Cache::remember può serializzarlo.
     */
    protected function fetchIscritti(string|int|null $garaId): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(10)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(config('golf.fig.ajax_url'), [
                    'action' => 'competition-player-list',
                    'competition_id' => $garaId,
                    'page_number' => 1,
                    'page_size' => 250,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Federgolf timeout/connect getIscritti', [
                'gara_id' => $garaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'state' => 'error',
                'iscritti' => [],
                'message' => 'Federgolf.it non risponde (timeout). Riprovare tra qualche secondo.',
            ];
        }

        if (! $response->successful()) {
            return [
                'state' => 'error',
                'iscritti' => [],
                'message' => 'Federgolf.it ha risposto con errore HTTP '.$response->status().'.',
            ];
        }

        $data = $response->json();

        // C2: 200 con corpo non-JSON (manutenzione/WAF federgolf) → json() = null.
        // Senza questo guard verrebbe classificato 'empty' ("Gara senza iscritti")
        // e cacheato 60s: dato falso all'utente.
        if (! is_array($data)) {
            return [
                'state' => 'error',
                'iscritti' => [],
                'message' => 'Federgolf.it ha risposto in un formato inatteso. Riprovare tra qualche secondo.',
            ];
        }

        $entries = $data['data']['processedData'] ?? [];

        $iscritti = [];
        $totale = count($entries);
        $ammessi = 0;

        // C4: il parsing dipende dalla shape esterna ($entry[8]/$entry[1] stringhe).
        // Se federgolf cambia formato → TypeError: meglio 'state: error' che un 500.
        try {
            foreach ($entries as $entry) {
                // Solo iscritti ammessi o con wildcard: l'ammissione è segnalata da `icona-ammesso`
                // o `icona-wildcard` nella colonna stato (l'ultima).
                // Finché le iscrizioni non sono chiuse, nessun iscritto ha questa icona → 0 ammessi.
                $isAmmesso = isset($entry[8]) && is_string($entry[8])
                    && (strpos($entry[8], 'icona-ammesso') !== false || strpos($entry[8], 'icona-wildcard') !== false);
                if (! $isAmmesso) {
                    continue;
                }
                $ammessi++;
                if (isset($entry[1]) && is_string($entry[1])
                    && preg_match('/<span class="nome-giocatore">([^<]+)<\/span>/', $entry[1], $matches)) {
                    // Decodifica entità HTML (es. &#039; → ' nei nomi tipo "D'Oro")
                    $iscritti[] = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Federgolf shape inattesa in getIscritti', [
                'gara_id' => $garaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'state' => 'error',
                'iscritti' => [],
                'message' => 'Risposta di Federgolf.it in formato inatteso.',
            ];
        }

        // Classifica lo stato in base ai contatori
        if ($totale === 0) {
            return [
                'state' => 'empty',
                'iscritti' => [],
                'message' => 'Gara senza iscritti.',
            ];
        }
        if ($ammessi === 0) {
            return [
                'state' => 'open',
                'iscritti' => [],
                'message' => 'Iscrizioni non ancora chiuse: nessun iscritto ammesso. Riprovare dopo la chiusura.',
            ];
        }

        return [
            'state' => 'ready',
            'iscritti' => $iscritti,
            'message' => null,
        ];
    }

    public function loadAllCompetitions(Request $request)
    {
        try {
            $annoCorrente = (int) date('Y');
            $entries = $this->fetchCompetitionsYear($annoCorrente);

            if ($entries === null) {
                return response()->json(['success' => false, 'message' => 'Errore connessione']);
            }

            // C5: la ricerca federgolf è per anno solare. Da novembre in poi si
            // preparano le gare di gennaio/febbraio: interroghiamo anche anno+1
            // e uniamo. Se la seconda chiamata fallisce, l'anno corrente basta.
            if ((int) date('n') >= 11) {
                $entriesNextYear = $this->fetchCompetitionsYear($annoCorrente + 1);
                if ($entriesNextYear !== null) {
                    $entries = array_merge($entries, $entriesNextYear);
                } else {
                    Log::warning('Federgolf: caricamento gare anno successivo fallito', [
                        'anno' => $annoCorrente + 1,
                    ]);
                }
            }

            $oggi = new \DateTime;
            $gare = [];

            foreach ($entries as $gara) {
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

    /**
     * Chiama competitions-search per un anno. Ritorna l'array 'data' della
     * risposta, oppure null su errore rete/HTTP/corpo non-JSON (C2/C5).
     */
    protected function fetchCompetitionsYear(int $anno): ?array
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(config('golf.fig.ajax_url'), [
                    'action' => 'competitions-search',
                    'tipo' => '',
                    'keyword' => '',
                    'anno' => (string) $anno,
                    'mese' => '',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Federgolf timeout/connect loadAllCompetitions', [
                'anno' => $anno,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['data']) || ! is_array($data['data'])) {
            return null;
        }

        return $data['data'];
    }
}
