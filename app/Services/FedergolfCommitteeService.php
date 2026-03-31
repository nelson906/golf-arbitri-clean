<?php

namespace App\Services;

use App\Enums\AssignmentRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recupera il Comitato di Gara da federgolf.it e lo mette in corrispondenza
 * con gli arbitri presenti nel database locale.
 *
 * Non scrive nulla sul DB: restituisce solo dati per la revisione dell'admin.
 */
class FedergolfCommitteeService
{
    private const FIG_BASE       = 'https://www.federgolf.it';
    private const FIG_AJAX       = 'https://www.federgolf.it/wp-admin/admin-ajax.php';
    private const MATCH_MIN_SCORE = 60; // % similarità minima per considerare un match

    // ─────────────────────────────────────────────────────────────────────────
    // 1. RECUPERO DATI DA FEDERGOLF
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Carica il comitato di gara per una competition FIG.
     * Tenta prima via AJAX WordPress, poi via HTML scraping della pagina di dettaglio.
     *
     * @param  string $competitionId  GUID della gara su federgolf.it
     * @return array<int, array{nome: string, cognome: string, ruolo: string, ruolo_normalizzato: string}>
     */
    public function fetchCommittee(string $competitionId): array
    {
        // Tentativo 1: AJAX endpoint (più veloce e stabile)
        $committee = $this->fetchViaAjax($competitionId);

        // Tentativo 2: parsing HTML della pagina di dettaglio
        if (empty($committee)) {
            $committee = $this->fetchViaHtml($competitionId);
        }

        return $committee;
    }

    /**
     * Prova a ottenere il comitato via WordPress AJAX.
     */
    private function fetchViaAjax(string $competitionId): array
    {
        try {
            $response = Http::timeout(15)
                ->asForm()
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept'          => 'application/json',
                    'X-Requested-With'=> 'XMLHttpRequest',
                ])
                ->post(self::FIG_AJAX, [
                    'action'         => 'competition-details',
                    'competition_id' => $competitionId,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            // Se la risposta ha un campo comitato strutturato
            if (! empty($data['data']['comitato'])) {
                return $this->normalizeCommitteeItems($data['data']['comitato']);
            }

            // Alcuni endpoint restituiscono il comitato dentro 'committee'
            if (! empty($data['data']['committee'])) {
                return $this->normalizeCommitteeItems($data['data']['committee']);
            }

        } catch (\Throwable $e) {
            Log::debug('FedergolfCommitteeService::fetchViaAjax failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Scarica e fa parsing dell'HTML della pagina di dettaglio gara.
     */
    private function fetchViaHtml(string $competitionId): array
    {
        try {
            $url = self::FIG_BASE . '/attivita-agonistica/dettaglio-gara/' . $competitionId . '/';

            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept'     => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            // Decodifica entità HTML prima del parsing (es. &#039; → ', &amp; → &)
            $html = html_entity_decode($response->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $this->parseHtmlCommittee($html);

        } catch (\Throwable $e) {
            Log::debug('FedergolfCommitteeService::fetchViaHtml failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Estrae il Comitato di Gara dall'HTML della pagina di dettaglio.
     *
     * La struttura HTML di federgolf.it cambia occasionalmente, quindi
     * proviamo più pattern in cascata.
     */
    private function parseHtmlCommittee(string $html): array
    {
        $committee = [];

        // ── Tentativo A: sezione con classe o id "comitato" ─────────────────
        if (preg_match(
            '/<[^>]+(?:class|id)=["\'][^"\']*comitato[^"\']*["\'][^>]*>(.*?)<\/(?:div|section|article)>/is',
            $html,
            $block
        )) {
            $committee = $this->extractNamesFromBlock($block[1]);
        }

        // ── Tentativo B: heading "Comitato di Gara" seguito da contenuto ────
        if (empty($committee) && preg_match(
            '/Comitato\s+di\s+Gara.*?(<(?:ul|ol|table|div)[^>]*>.*?<\/(?:ul|ol|table|div)>)/is',
            $html,
            $block
        )) {
            $committee = $this->extractNamesFromBlock($block[1]);
        }

        // ── Tentativo C: cerca righe con ruoli noti vicino ai nomi ──────────
        if (empty($committee)) {
            $committee = $this->extractByRoleKeywords($html);
        }

        return $committee;
    }

    /**
     * Estrae coppie nome/ruolo da un blocco HTML (lista, tabella o paragrafi).
     *
     * @return array<int, array{nome: string, cognome: string, ruolo: string, ruolo_normalizzato: string}>
     */
    private function extractNamesFromBlock(string $html): array
    {
        // Rimuovi tag HTML, preservando separatori di riga significativi
        $text = preg_replace('/<\/?(tr|li|p|br|div)[^>]*>/i', "\n", $html);
        $text = strip_tags($text);

        $lines  = preg_split('/\n+/', $text);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 3) {
                continue;
            }

            $ruolo = $this->detectRole($line);
            if ($ruolo === null) {
                continue;
            }

            // Rimuovi la parte del ruolo dal testo per isolare il nome
            $nomePulito = $this->extractName($line, $ruolo['label_trovata']);
            // Decodifica entità HTML residue (es. &#039; → ')
            $nomePulito = html_entity_decode($nomePulito, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (! empty($nomePulito)) {
                [$nome, $cognome] = $this->splitNomeCognome($nomePulito);
                $result[] = [
                    'nome'               => $nome,
                    'cognome'            => $cognome,
                    'nome_completo'      => $nomePulito,
                    'ruolo'              => $ruolo['label_trovata'],
                    'ruolo_normalizzato' => $ruolo['valore'],
                ];
            }
        }

        return $result;
    }

    /**
     * Cerca nel testo globale della pagina righe contenenti ruoli noti.
     * Ultimo tentativo quando la struttura HTML non è riconoscibile.
     */
    private function extractByRoleKeywords(string $html): array
    {
        $text   = strip_tags($html);
        $lines  = preg_split('/\n+/', $text);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $ruolo = $this->detectRole($line);

            if ($ruolo === null) {
                continue;
            }

            $nomePulito = html_entity_decode(
                $this->extractName($line, $ruolo['label_trovata']),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            if (strlen($nomePulito) < 4 || strlen($nomePulito) > 60) {
                continue;
            }

            [$nome, $cognome] = $this->splitNomeCognome($nomePulito);
            $entry = [
                'nome'               => $nome,
                'cognome'            => $cognome,
                'nome_completo'      => $nomePulito,
                'ruolo'              => $ruolo['label_trovata'],
                'ruolo_normalizzato' => $ruolo['valore'],
            ];

            // Evita duplicati
            if (! in_array($entry, $result, true)) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. RILEVAMENTO RUOLI E NOMI
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Individua il ruolo in una stringa di testo.
     * Restituisce null se non trova ruoli riconoscibili.
     *
     * @return array{label_trovata: string, valore: string}|null
     */
    private function detectRole(string $text): ?array
    {
        $patterns = [
            // Direttore di Torneo (varianti)
            '/direttore\s+di\s+torneo/i'     => AssignmentRole::TournamentDirector->value,
            '/direttore\s+di\s+gara/i'       => AssignmentRole::TournamentDirector->value,
            '/tournament\s+director/i'        => AssignmentRole::TournamentDirector->value,
            '/dir\.\s+torneo/i'               => AssignmentRole::TournamentDirector->value,
            // Rules Official / Arbitro
            '/rules\s+official/i'             => AssignmentRole::Referee->value,
            '/\barbitro\b/i'                  => AssignmentRole::Referee->value,
            '/\barbitri\b/i'                  => AssignmentRole::Referee->value,
            '/rules\s+off\./i'                => AssignmentRole::Referee->value,
            // Osservatore
            '/\bosservatore\b/i'              => AssignmentRole::Observer->value,
            '/\bosservatori\b/i'              => AssignmentRole::Observer->value,
            '/\bobserver\b/i'                 => AssignmentRole::Observer->value,
        ];

        foreach ($patterns as $pattern => $valore) {
            if (preg_match($pattern, $text, $match)) {
                return [
                    'label_trovata' => $match[0],
                    'valore'        => $valore,
                ];
            }
        }

        return null;
    }

    /**
     * Estrae la parte nome da una riga che contiene anche il ruolo.
     */
    private function extractName(string $line, string $labelRuolo): string
    {
        // Rimuovi la label del ruolo dalla riga
        $nome = preg_replace('/' . preg_quote($labelRuolo, '/') . '/i', '', $line);
        // Rimuovi pattern ruolo estesi
        $nome = preg_replace(
            '/(direttore\s+di\s+(torneo|gara)|tournament\s+director|rules\s+official|arbitro|osservatore|observer)/i',
            '',
            $nome
        );
        // Rimuovi parentesi e contenuto: es. "()" o "(Club Golf Roma)"
        $nome = preg_replace('/\([^)]*\)/', '', $nome);
        // Rimuovi punteggiatura di separazione (trattini, due punti, pipe, virgola iniziale/finale)
        $nome = preg_replace('/^[\s\-–—:|,]+|[\s\-–—:|,]+$/', '', $nome);
        $nome = preg_replace('/\s{2,}/', ' ', $nome);

        return trim($nome);
    }

    /**
     * Divide un nome completo in (nome, cognome).
     *
     * Gestisce i formati usati da federgolf.it:
     *  - "COGNOME, NOME"  (con virgola — formato più comune nell'API FIG)
     *  - "COGNOME NOME"   (tutto maiuscolo, senza virgola)
     *  - "Nome COGNOME"   (formato misto)
     *
     * @return array{0: string, 1: string}
     */
    private function splitNomeCognome(string $nomeCompleto): array
    {
        // Pulisci parentesi residue prima di splittare
        $nomeCompleto = trim(preg_replace('/\([^)]*\)/', '', $nomeCompleto));
        $nomeCompleto = preg_replace('/\s{2,}/', ' ', $nomeCompleto);

        // Formato "COGNOME, NOME" (virgola come separatore) — tipico API FIG
        if (str_contains($nomeCompleto, ',')) {
            [$cognome, $nome] = array_map('trim', explode(',', $nomeCompleto, 2));
            // Converti in Title Case se tutto maiuscolo
            if ($cognome === strtoupper($cognome)) {
                $cognome = mb_convert_case($cognome, MB_CASE_TITLE, 'UTF-8');
            }
            if ($nome === strtoupper($nome)) {
                $nome = mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');
            }

            return [trim($nome), trim($cognome)];
        }

        $parts = explode(' ', trim($nomeCompleto));

        if (count($parts) < 2) {
            return [$nomeCompleto, ''];
        }

        // Se il primo token è tutto maiuscolo → probabile COGNOME Nome
        if ($parts[0] === strtoupper($parts[0]) && strlen($parts[0]) > 1) {
            $cognome = array_shift($parts);
            $nome    = implode(' ', $parts);
        } else {
            // Assume il formato Nome COGNOME (ultimo token = cognome)
            $cognome = array_pop($parts);
            $nome    = implode(' ', $parts);
        }

        return [trim($nome), trim($cognome)];
    }

    /**
     * Normalizza un array di items comitato provenienti da risposta AJAX strutturata.
     */
    private function normalizeCommitteeItems(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $nomeCompleto = html_entity_decode(
                trim(($item['nome'] ?? '') . ' ' . ($item['cognome'] ?? $item['lastName'] ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $ruoloRaw = $item['ruolo'] ?? $item['role'] ?? $item['incarico'] ?? 'Arbitro';
            $ruolo    = AssignmentRole::normalize($ruoloRaw);
            [$nome, $cognome] = $this->splitNomeCognome($nomeCompleto);

            $result[] = [
                'nome'               => $nome,
                'cognome'            => $cognome,
                'nome_completo'      => $nomeCompleto,
                'ruolo'              => $ruoloRaw,
                'ruolo_normalizzato' => $ruolo->value,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. MATCHING CON UTENTI LOCALI
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Per ogni membro del comitato FIG, cerca il miglior match tra gli arbitri locali.
     *
     * @param  array  $committee  Output di fetchCommittee()
     * @return array<int, array{
     *   fig: array,
     *   match: array{user_id: int, name: string, score: int}|null,
     *   candidati: array
     * }>
     */
    public function matchWithUsers(array $committee): array
    {
        /** @var Collection<int, User> $referees */
        $referees = User::whereIn('user_type', ['referee', 'admin', 'crc', 'zona'])
            ->where('is_active', true)
            ->select(['id', 'name', 'first_name', 'last_name', 'email'])
            ->get();

        $result = [];

        foreach ($committee as $membro) {
            $candidati = $this->findCandidates(
                $membro['cognome'],   // cognome FIG (da splitNomeCognome)
                $membro['nome'],      // nome/i FIG — può essere "Simone Gaetano"
                $referees
            );

            $bestMatch = ! empty($candidati) && $candidati[0]['score'] >= self::MATCH_MIN_SCORE
                ? $candidati[0]
                : null;

            $result[] = [
                'fig'       => $membro,
                'match'     => $bestMatch,
                'candidati' => $candidati,
            ];
        }

        return $result;
    }

    /**
     * Matching strutturato per componenti: confronta COGNOME vs cognome locale
     * e MIGLIOR TOKEN NOME vs nome locale separatamente.
     *
     * Questo gestisce correttamente:
     *  - Nomi composti FIG: "SIMONE GAETANO" → confronta solo "SIMONE" col DB
     *  - Nomi composti DB:  "Daniela Emma"   → il token FIG "DANIELA" matcha
     *  - Evita falsi positivi: CASTALDO+IGNAZIO vs CASTALDO+Ezio → basso score
     *
     * Formula: cognome (55%) + miglior_token_nome (45%)
     * Hard filter: se cognome < 65% → candidato scartato (evita omonimi di caso)
     *
     * @return array<int, array{user_id: int, name: string, email: string, score: int}>
     */
    private function findCandidates(string $cognomeFig, string $nomeFig, Collection $referees): array
    {
        $cognomeFigNorm  = $this->normalize($cognomeFig);

        // Tokenizza i nomi FIG: "Simone Gaetano" → ['simone', 'gaetano']
        // Ogni token viene confrontato col nome locale; si prende il migliore
        $nomeTokensFig = array_values(array_filter(
            array_map([$this, 'normalize'], preg_split('/\s+/', trim($nomeFig))),
            fn ($t) => strlen($t) >= 2
        ));

        $candidati = [];

        foreach ($referees as $user) {
            // ── Estrai cognome locale ──────────────────────────────────────
            // Priorità: last_name > ultimo token di name
            $cognomeLocal = $user->last_name
                ? $this->normalize($user->last_name)
                : $this->lastToken($this->normalize($user->name));

            // Hard filter sul cognome: evita confronti inutili
            similar_text($cognomeFigNorm, $cognomeLocal, $cognomePct);
            if ($cognomePct < 65.0) {
                continue;
            }

            // ── Estrai nome/i locale ───────────────────────────────────────
            // Priorità: first_name > primo/i token di name (tutto tranne l'ultimo)
            $nomeLocalNorm = $user->first_name
                ? $this->normalize($user->first_name)
                : $this->allButLastToken($this->normalize($user->name));

            // Tokenizza anche il nome locale (gestisce "Daniela Emma")
            $nomeTokensLocal = array_values(array_filter(
                array_map('trim', preg_split('/\s+/', $nomeLocalNorm)),
                fn ($t) => strlen($t) >= 2
            ));

            // Miglior match tra qualsiasi token FIG e qualsiasi token locale
            $bestNomePct = 0.0;
            foreach ($nomeTokensFig as $tokenFig) {
                foreach ($nomeTokensLocal as $tokenLocal) {
                    similar_text($tokenFig, $tokenLocal, $pct);
                    if ($pct > $bestNomePct) {
                        $bestNomePct = $pct;
                    }
                }
            }

            // Score finale pesato
            $score = (int) round(($cognomePct * 0.55) + ($bestNomePct * 0.45));

            if ($score > 0) {
                $candidati[] = [
                    'user_id' => $user->id,
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'score'   => $score,
                ];
            }
        }

        usort($candidati, fn ($a, $b) => $b['score'] - $a['score']);

        return array_slice($candidati, 0, 5);
    }

    /** Restituisce l'ultimo token di una stringa normalizzata. */
    private function lastToken(string $s): string
    {
        $parts = preg_split('/\s+/', trim($s));

        return end($parts) ?: $s;
    }

    /** Restituisce tutti i token tranne l'ultimo (= parte "nome" da "Nome Cognome"). */
    private function allButLastToken(string $s): string
    {
        $parts = preg_split('/\s+/', trim($s));
        if (count($parts) <= 1) {
            return $s;
        }
        array_pop($parts);

        return implode(' ', $parts);
    }

    /**
     * Calcola la similarità semplice tra due stringhe (0-100).
     * Usato ancora per il matching torneo/club nel Command, non per i nomi.
     */
    private function computeScore(string $a, string $b): int
    {
        if ($a === $b) {
            return 100;
        }
        similar_text($a, $b, $pct);

        return (int) round($pct);
    }

    /**
     * Normalizza una stringa per il confronto:
     * minuscolo, senza accenti, senza punteggiatura superflua.
     */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        // Sostituisci caratteri accentati con equivalenti ASCII
        $s = str_replace(
            ['à','è','é','ì','ò','ù','á','ê','î','ô','û','ë','ï','ü','ç'],
            ['a','e','e','i','o','u','a','e','i','o','u','e','i','u','c'],
            $s
        );
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
