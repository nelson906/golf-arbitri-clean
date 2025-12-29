<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ScrapeFedergolfIscritti extends Command
{
    protected $signature = 'scrape:federgolf-iscritti {gara_id?} {--nome= : Il nome della gara da cercare} {--url= : URL della pagina della gara}';

    protected $description = 'Effettua lo scraping degli iscritti da una gara Federgolf';

    public function handle()
    {
        $garaId = $this->argument('gara_id');
        $nomeGara = $this->option('nome');
        $url = $this->option('url');

        if (! $garaId && ! $nomeGara && ! $url) {
            $this->error("Devi specificare o l'ID della gara o il nome della gara da cercare o l'URL");

            return Command::FAILURE;
        }

        if ($url) {
            return $this->processaUrlGara($url);
        }

        if ($nomeGara) {
            return $this->cercaGaraPerNome($nomeGara);
        }

        return $this->processaGaraPerId($garaId);
    }

    private function cercaGaraPerNome($nomeGara)
    {
        $this->info("\nRicerca gara: $nomeGara");

        try {
            // Passo 1: Prova con l'API di ricerca
            $this->info('Provo la ricerca tramite API...');

            // Anno e mese correnti come default
            $anno = date('Y');
            $mese = date('n');

            // Prima prova senza filtri temporali
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action' => 'competitions-search',
                    'tipo' => '',
                    'keyword' => $nomeGara,
                    'anno' => '',
                    'mese' => '',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (empty($data['data'])) {
                    // Se la ricerca senza filtri fallisce, prova con anno/mese correnti
                    $response = Http::timeout(30)
                        ->asForm()
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                            'Accept' => 'application/json, text/javascript, */*; q=0.01',
                            'X-Requested-With' => 'XMLHttpRequest',
                        ])
                        ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                            'action' => 'competitions-search',
                            'tipo' => '',
                            'keyword' => $nomeGara,
                            'anno' => $anno,
                            'mese' => $mese,
                        ]);

                    if (! $response->successful()) {
                        $this->warn('Ricerca API fallita - Status: '.$response->status());
                        goto html_search;
                    }

                    $data = $response->json();
                }

                if (! empty($data['data'])) {
                    // Pulisci la chiave di ricerca rimuovendo MASCHILE/FEMMINILE
                    $chiaveRicerca = trim(preg_replace('/\b(MASCHILE|FEMMINILE)\b/i', '', $nomeGara));
                    $gare = [];
                    $annoCorrente = date('Y');

                    foreach ($data['data'] as $gara) {
                        // Pulisci il titolo della gara allo stesso modo
                        $titoloGara = trim(preg_replace('/\b(MASCHILE|FEMMINILE)\b/i', '', $gara['title'] ?? $gara['nome'] ?? ''));

                        // Estrai l'anno dalla data se disponibile
                        $dataGara = $gara['date'] ?? $gara['data'] ?? null;
                        $annoGara = $dataGara ? date('Y', strtotime($dataGara)) : null;

                        // Prendi solo le gare dell'anno corrente
                        if (stripos($titoloGara, $chiaveRicerca) !== false && (! $annoGara || $annoGara == $annoCorrente)) {
                            $tipo = 'MISTA';
                            if (stripos($gara['title'] ?? $gara['nome'] ?? '', 'MASCHILE') !== false) {
                                $tipo = 'MASCHILE';
                            } elseif (stripos($gara['title'] ?? $gara['nome'] ?? '', 'FEMMINILE') !== false) {
                                $tipo = 'FEMMINILE';
                            }

                            $id = $gara['id'] ?? $gara['competition_id'] ?? null;
                            if (! $id) {
                                continue;
                            }
                            $gare[] = [
                                'id' => $id,
                                'titolo' => $gara['title'] ?? $gara['nome'] ?? 'N/A',
                                'tipo' => $tipo,
                                'url' => "https://www.federgolf.it/attivita-agonistica/dettaglio-gara/{$id}/",
                            ];
                        }
                    }

                    if (! empty($gare)) {
                        $this->info("\nGare trovate tramite API:");
                        $headers = ['#', 'ID', 'Nome', 'Tipo'];
                        $rows = [];
                        foreach ($gare as $i => $gara) {
                            $rows[] = [
                                $i + 1,
                                $gara['id'] ?? 'N/A',
                                $gara['titolo'],
                                $gara['tipo'],
                            ];
                        }
                        $this->table($headers, $rows);

                        foreach ($gare as $gara) {
                            $this->info("\nAnalisi gara {$gara['tipo']}: {$gara['titolo']}");
                            if ($this->checkForApi($gara['id'])) {
                                $this->info('✓ Lista iscritti scaricata con successo');
                            } else {
                                $this->warn('× Non sono riuscito a ottenere la lista iscritti');
                            }
                            $this->info('--------------------');
                        }

                        return true;
                    }
                }
            }

            html_search:
            $this->info('Ricerca API non riuscita, provo con lo scraping della pagina principale...');

            // Passo 2: Se l'API fallisce, prova con lo scraping della pagina principale
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get('https://www.federgolf.it/attivita-agonistica/gare/');

            if (! $response->successful()) {
                $this->error('Errore nel caricamento della pagina principale - Status: '.$response->status());

                return false;
            }

            $html = $response->body();

            // Passo 2: Salva l'HTML per debug
            $debugFile = storage_path('app/federgolf_search_'.date('Y-m-d_His').'.html');
            file_put_contents($debugFile, $html);
            $this->info("Debug HTML salvato in: $debugFile");

            // Cerca tutti i link alle gare
            preg_match_all('/<a[^>]*href="([^"]*\/(?:dettaglio-gara|gara)\/[^"]+)"[^>]*>([^<]*)<\/a>/i', $html, $matches, PREG_SET_ORDER);

            // Pulisci la chiave di ricerca
            $chiaveRicerca = trim(preg_replace('/\b(MASCHILE|FEMMINILE)\b/i', '', $nomeGara));

            // Filtra i risultati per il nome della gara
            $matches = array_filter($matches, function ($match) use ($chiaveRicerca) {
                $titolo = trim(strip_tags($match[2]));
                // Pulisci il titolo allo stesso modo
                $titoloGara = trim(preg_replace('/\b(MASCHILE|FEMMINILE)\b/i', '', $titolo));

                return stripos($titoloGara, $chiaveRicerca) !== false;
            });
            $matches = array_values($matches);

            if (empty($matches)) {
                $this->error("Nessuna gara trovata con il nome: $nomeGara");

                return false;
            }

            $gare = [];
            foreach ($matches as $match) {
                $url = $match[1];
                if (! Str::startsWith($url, 'http')) {
                    $url = 'https://www.federgolf.it'.$url;
                }
                $titolo = strip_tags($match[2]);

                // Per ogni URL, visita la pagina e estrai l'ID
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                            'Accept' => 'text/html',
                        ])
                        ->get($url);

                    if ($response->successful()) {
                        $pageHtml = $response->body();
                        if (preg_match('/id="competition-id"\s+value="([a-f0-9-]{36})"/i', $pageHtml, $idMatch)) {
                            $tipo = 'MISTA';
                            if (stripos($titolo, 'MASCHILE') !== false) {
                                $tipo = 'MASCHILE';
                            } elseif (stripos($titolo, 'FEMMINILE') !== false) {
                                $tipo = 'FEMMINILE';
                            }

                            $gare[] = [
                                'id' => $idMatch[1],
                                'titolo' => $titolo,
                                'tipo' => $tipo,
                                'url' => $url,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("Errore nel recupero della pagina $url: ".$e->getMessage());
                }
            }

            if (empty($gare)) {
                $this->error('Non sono riuscito a estrarre gli ID delle gare.');

                return false;
            }

            $this->info("\nGare trovate:");
            $headers = ['#', 'ID', 'Nome', 'Tipo'];
            $rows = [];
            foreach ($gare as $i => $gara) {
                $rows[] = [
                    $i + 1,
                    $gara['id'],
                    $gara['titolo'],
                    $gara['tipo'],
                ];
            }
            $this->table($headers, $rows);

            foreach ($gare as $gara) {
                $this->info("\nAnalisi gara {$gara['tipo']}: {$gara['titolo']}");
                if ($this->checkForApi($gara['id'])) {
                    $this->info('✓ Lista iscritti scaricata con successo');
                } else {
                    $this->warn('× Non sono riuscito a ottenere la lista iscritti');
                }
                $this->info('--------------------');
            }

            return true;

        } catch (\Exception $e) {
            $this->error('Errore durante la ricerca: '.$e->getMessage());

            return false;
        }
    }

    private function processaUrlGara($url)
    {
        $ids = $this->estraiIdDaPagina($url);
        if (empty($ids)) {
            $this->error("Nessun ID gara trovato nell'URL fornito.");

            return Command::FAILURE;
        }

        $this->info("\nTrovate ".count($ids).' gare:');

        foreach ($ids as $id => $tipo) {
            $this->info("- Gara $tipo (ID: $id)");

            if ($this->checkForApi($id)) {
                $this->info("✓ Lista iscritti scaricata con successo\n--------------------\n");

                continue;
            }

            $this->warn("× Non sono riuscito a ottenere i dati per la gara $tipo\n--------------------\n");
        }

        return Command::SUCCESS;
    }

    private function processaGaraPerId($garaId)
    {
        $this->info("Tentativo di scraping per la gara: $garaId");

        if ($this->checkForApi($garaId)) {
            return Command::SUCCESS;
        }

        $this->warn('API non disponibile, provo approccio alternativo...');
        $url = "https://www.federgolf.it/attivita-agonistica/dettaglio-gara/{$garaId}/";

        return $this->processaUrlGara($url);
    }

    private function estraiIdDaPagina($url)
    {
        try {
            $response = Http::get($url);
            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            $ids = [];

            // Pattern per trovare l'ID nella pagina corrente
            if (preg_match('/id="competition-id"\s+value="([a-f0-9-]{36})"/i', $html, $match)) {
                $ids[$match[1]] = 'MISTA';
            }

            // Pattern per ID nell'URL
            if (preg_match('/dettaglio-gara\/([a-f0-9-]{36})/i', $url, $match)) {
                $ids[$match[1]] = 'MISTA';
            }

            // Pattern per trovare link a versioni maschili/femminili
            preg_match_all('/<a[^>]*href="[^"]*\/dettaglio-gara\/([a-f0-9-]{36})[^"]*"[^>]*>([^<]*(?:MASCHILE|FEMMINILE)[^<]*)<\/a>/i', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $tipo = stripos($match[2], 'MASCHILE') !== false ? 'MASCHILE' : 'FEMMINILE';
                $ids[$match[1]] = $tipo;
            }

            return $ids;

        } catch (\Exception $e) {
            return [];
        }
    }

    private function checkForApi($garaId)
    {
        $this->info('Controllo endpoint WordPress AJAX di Federgolf...');

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => "https://www.federgolf.it/attivita-agonistica/dettaglio-gara/{$garaId}/",
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action' => 'competition-player-list',
                    'competition_id' => $garaId,
                    'page_number' => 1,
                    'page_size' => 250,
                ]);

            if (! $response->successful()) {
                $this->error('Errore nella chiamata API - Status: '.$response->status());

                return false;
            }

            $data = $response->json();
            if (! isset($data['success']) || ! $data['success'] || ! isset($data['data'])) {
                $this->warn('Risposta API non valida: '.json_encode($data));

                return false;
            }

            $this->info("✓ Dati ricevuti con successo dall'API Federgolf");
            $this->processApiData($data);

            return true;

        } catch (\Exception $e) {
            $this->error('Errore: '.$e->getMessage());

            return false;
        }
    }

    private function processApiData($data)
    {
        $this->info("\nProcesso i dati degli iscritti...");

        // Salva il JSON completo per debug
        $rawFilename = storage_path('app/federgolf_raw_'.date('Y-m-d_His').'.json');
        file_put_contents($rawFilename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Dati raw salvati in: $rawFilename");

        if (! isset($data['data']['processedData'])) {
            $this->error('Formato dati non valido - mancano i processedData');

            return;
        }

        $entries = $data['data']['processedData'];
        $iscritti = [];

        foreach ($entries as $entry) {
            // Verifica se l'iscritto è ammesso
            if (strpos($entry[8], 'icona-ammesso') === false) {
                continue; // Salta se non è ammesso
            }

            // Estrae nome e circolo dall'HTML
            preg_match('/<span class="nome-giocatore">([^<]+)<\/span><span class="circolo-giocatore">([^<]+)<\/span>/', $entry[1], $matches);

            if (count($matches) < 3) {
                continue; // Salta se non riesce a estrarre nome e circolo
            }

            $iscritto = [
                'posizione' => intval($entry[0]) ?: 999999, // Se non è un numero valido, metti in fondo
                'nome' => html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'circolo' => html_entity_decode(trim($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'categoria' => trim($entry[2]),
                'qualifica' => trim($entry[3]),
                'wagr' => trim($entry[4]),
                'hcp' => trim($entry[5]),
                'odm' => trim($entry[6]),
            ];

            $iscritti[] = $iscritto;
        }

        // Ordina per posizione
        usort($iscritti, function ($a, $b) {
            return $a['posizione'] - $b['posizione'];
        });

        // Salva il JSON elaborato
        $filename = storage_path('app/federgolf_iscritti_'.date('Y-m-d_His').'.json');
        file_put_contents($filename, json_encode($iscritti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("\nTrovati ".count($iscritti).' iscritti');
        $this->info("Dati elaborati salvati in: $filename\n");

        // Mostra gli iscritti ammessi in ordine di posizione
        $headers = ['Pos', 'Nome', 'Circolo', 'HCP', 'Cat'];
        $rows = array_map(function ($i) {
            return [
                $i['posizione'] >= 999999 ? '-' : $i['posizione'],
                $i['nome'],
                $i['circolo'],
                $i['hcp'],
                $i['categoria'],
            ];
        }, $iscritti);

        // Salva anche in formato CSV
        $csvFilename = storage_path('app/federgolf_iscritti_'.date('Y-m-d_His').'.csv');
        $csvFile = fopen($csvFilename, 'w');
        // Aggiungi BOM per UTF-8
        fprintf($csvFile, "\xEF\xBB\xBF");

        // Intestazioni
        fputcsv($csvFile, ['Posizione', 'Nome', 'Circolo', 'HCP', 'Categoria']);

        // Dati
        foreach ($iscritti as $iscritto) {
            fputcsv($csvFile, [
                $iscritto['posizione'],
                $iscritto['nome'],
                $iscritto['circolo'],
                $iscritto['hcp'],
                $iscritto['categoria'],
            ]);
        }

        fclose($csvFile);
        $this->info("Lista iscritti salvata in CSV: $csvFilename");

        // Mostra a schermo
        $this->table($headers, $rows);
    }
}
