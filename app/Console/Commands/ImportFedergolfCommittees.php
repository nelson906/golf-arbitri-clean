<?php

namespace App\Console\Commands;

use App\Enums\AssignmentRole;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Services\FedergolfCommitteeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa in batch i Comitati di Gara da federgolf.it per un dato anno.
 *
 * Il comando:
 *  1. Carica tutte le gare FIG dell'anno indicato
 *  2. Per ognuna, cerca il torneo locale con il miglior match (nome + data + circolo)
 *  3. Recupera il Comitato di Gara da FIG
 *  4. Mette in corrispondenza i nomi con gli arbitri locali
 *  5. Mostra un report prima di scrivere (--dry-run per solo anteprima)
 *  6. Importa le assegnazioni mancanti (salta quelle già presenti)
 *
 * Utilizzo:
 *   php artisan federgolf:import-committees --anno=2025
 *   php artisan federgolf:import-committees --anno=2025 --dry-run
 *   php artisan federgolf:import-committees --anno=2025 --min-score=70
 */
class ImportFedergolfCommittees extends Command
{
    protected $signature = 'federgolf:import-committees
                            {--anno= : Anno delle gare FIG (default: anno corrente)}
                            {--dry-run : Solo anteprima, nessuna scrittura su DB}
                            {--min-score=60 : Score minimo (0-100) per considerare valido il match torneo locale}
                            {--min-name-score=82 : Score minimo per il match nome arbitro (sotto soglia → da creare manualmente)}
                            {--skip-existing : Salta gare FIG per cui tutti gli arbitri sono già assegnati}
                            {--force-before= : Sovrascrivi assegnazioni create PRIMA di questa data (es. 2025-09-01)}
                            {--force : Sovrascrivi TUTTE le assegnazioni esistenti}';

    protected $description = 'Importa i Comitati di Gara da federgolf.it per un anno intero';

    // Statistiche globali
    private int $gareTotali         = 0;
    private int $gareSenzaMatch     = 0;
    private int $gareSenzaComitato  = 0;
    private int $assegnazioniCreate  = 0;
    private int $assegnazioniSaltate = 0;
    private int $nomiSenzaMatch      = 0;

    /** Nomi FIG non trovati nel DB: da creare come utenti */
    private array $nomiDaCreare = [];

    public function __construct(private readonly FedergolfCommitteeService $committeeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $anno    = (int) ($this->option('anno') ?? date('Y'));
        $dryRun  = (bool) $this->option('dry-run');
        $minScore = (int) $this->option('min-score');
        $minNameScore = (int) $this->option('min-name-score');

        $this->info("");
        $this->info("🏌️  Import Comitati di Gara — FIG {$anno}");
        $this->info($dryRun ? "   ⚠️  DRY-RUN: nessuna scrittura su DB" : "   ✅  Modalità scrittura attiva");
        $this->info("   Score minimo torneo locale: {$minScore}%  |  Score minimo arbitro: {$minNameScore}%");
        $this->newLine();

        // ── 1. Carica gare FIG ──────────────────────────────────────────────
        $this->info("📡 Caricamento gare FIG {$anno}…");
        $gareFig = $this->loadFigCompetitions($anno);

        if (empty($gareFig)) {
            $this->error("Nessuna gara trovata su federgolf.it per l'anno {$anno}.");
            return self::FAILURE;
        }

        $this->info("   → " . count($gareFig) . " gare caricate.");

        // ── De-duplicazione MASCHILE/FEMMINILE ──────────────────────────────
        // MASCHILE e FEMMINILE dello stesso circolo nella stessa data hanno
        // identico Comitato di Gara: si processa una volta sola.
        $gareFig = $this->deduplicateGare($gareFig);
        $this->info("   → " . count($gareFig) . " gare uniche dopo de-duplicazione (MASCHILE/FEMMINILE).");
        $this->newLine();

        // ── 2. Carica tornei locali ─────────────────────────────────────────
        $torneiLocali = Tournament::with(['club', 'assignments'])
            ->whereIn('status', ['draft', 'open', 'assigned', 'completed'])
            ->whereYear('start_date', $anno)
            ->get();

        $this->info("🗂️  Tornei locali {$anno}: " . $torneiLocali->count());
        $this->newLine();

        // ── 3. Processa ogni gara FIG (de-duplicata) ────────────────────────
        $this->gareTotali = count($gareFig);
        $report = [];

        foreach ($gareFig as $gara) {
            $result = $this->processGara($gara, $torneiLocali, $dryRun, $minScore, $minNameScore);
            $report[] = $result;

            // Piccola pausa per non martellare FIG
            if (! $dryRun) {
                usleep(500_000); // 0.5 secondi
            }
        }

        // ── 4. Report finale ────────────────────────────────────────────────
        $this->newLine();
        $this->printSummary($report, $dryRun, $torneiLocali);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROCESSAMENTO SINGOLA GARA
    // ─────────────────────────────────────────────────────────────────────────

    private function processGara(
        array $gara,
        \Illuminate\Database\Eloquent\Collection $torneiLocali,
        bool $dryRun,
        int $minScore,
        int $minNameScore
    ): array {
        $result = [
            'fig_id'          => $gara['id'],
            'fig_nome'        => $gara['nome'],
            'fig_data'        => $gara['data'],
            'fig_club'        => $gara['club'] ?? null,
            'torneo_locale'   => null,
            'torneo_locale_id' => null,
            'match_score'     => 0,
            'comitato_size'   => 0,
            'creati'          => 0,
            'saltati'         => 0,
            'senza_match'     => 0,
            'stato'           => '',
        ];

        $this->line("  ▸ <fg=cyan>{$gara['nome']}</> ({$gara['data']})");

        // ── Match torneo locale ──────────────────────────────────────────────
        $bestMatch = $this->findBestTournamentMatch($gara, $torneiLocali, $minScore);

        if (! $bestMatch) {
            $result['stato'] = 'no_torneo_locale';
            $this->line("    <fg=yellow>⚠ Nessun torneo locale con score ≥{$minScore}% — saltato</>");
            $this->gareSenzaMatch++;

            return $result;
        }

        $result['torneo_locale']    = $bestMatch['torneo']->name;
        $result['torneo_locale_id'] = $bestMatch['torneo']->id;
        $result['match_score']      = $bestMatch['score'];
        $this->line("    → Torneo locale: <fg=green>{$bestMatch['torneo']->name}</> ({$bestMatch['score']}%)");

        // ── Recupera comitato da FIG ─────────────────────────────────────────
        // Tenta tutti gli ID del gruppo (es. MASCHILE + FEMMINILE) finché trova dati
        $figIds    = $gara['fig_ids'] ?? [$gara['id']];
        $committee = [];

        foreach ($figIds as $figId) {
            try {
                $committee = $this->committeeService->fetchCommittee($figId);
                if (! empty($committee)) {
                    break; // trovato — non serve provare gli altri
                }
            } catch (\Throwable $e) {
                $this->line("    <fg=yellow>⚠ Errore fetch ID {$figId}: {$e->getMessage()} — provo il prossimo</>");
            }
        }

        if (empty($committee)) {
            $result['stato'] = 'comitato_vuoto';
            $this->line("    <fg=yellow>⚠ Comitato non trovato su FIG</>");
            $this->gareSenzaComitato++;

            return $result;
        }

        $result['comitato_size'] = count($committee);

        // ── Match arbitri locali ─────────────────────────────────────────────
        $matched = $this->committeeService->matchWithUsers($committee);

        $torneoId    = $bestMatch['torneo']->id;
        $assegnedBy  = $this->getSystemUserId();
        $force       = (bool) $this->option('force');
        $forceBefore = $this->option('force-before')
            ? Carbon::parse($this->option('force-before'))
            : null;

        foreach ($matched as $row) {
            $nomeFig = $row['fig']['nome_completo'];

            // Match sotto soglia: nome sconosciuto, da creare come utente
            if (! $row['match'] || $row['match']['score'] < $minNameScore) {
                $bestGuess = $row['match']
                    ? " (miglior candidato: {$row['match']['name']} {$row['match']['score']}% — scartato)"
                    : ' (nessun candidato trovato)';
                $this->line("    <fg=red>  ✗ {$nomeFig} → NON TROVATO{$bestGuess}</>");
                $result['senza_match']++;
                $this->nomiSenzaMatch++;
                // Accumula per il report "da creare"
                $this->nomiDaCreare[] = [
                    'nome_fig'  => $nomeFig,
                    'ruolo'     => $row['fig']['ruolo_normalizzato'],
                    'torneo'    => $bestMatch['torneo']->name,
                    'candidato' => $row['match']
                        ? "{$row['match']['name']} ({$row['match']['score']}%)"
                        : '—',
                ];
                continue;
            }

            $userId = $row['match']['user_id'];
            $ruolo  = AssignmentRole::normalize($row['fig']['ruolo_normalizzato'])->value;
            $nome   = $row['match']['name'];
            $score  = $row['match']['score'];

            // Cerca assegnazione esistente con data e note
            $existing = Assignment::where('tournament_id', $torneoId)
                ->where('user_id', $userId)
                ->first(['id', 'tournament_id', 'user_id', 'created_at', 'notes', 'role']);

            if ($existing) {
                $dataCreazione = $existing->created_at?->format('d/m/Y');
                $noteExisting  = $existing->notes ? " [{$existing->notes}]" : '';

                // Determina se sovrascrivere
                $doOverwrite = $force
                    || ($forceBefore && $existing->created_at && $existing->created_at->lt($forceBefore));

                if ($doOverwrite) {
                    $this->line("    <fg=yellow>  ↺ {$nomeFig} → {$nome} — sovrascrive ({$dataCreazione}{$noteExisting})</>");
                    $result['creati']++;
                    $this->assegnazioniCreate++;

                    if (! $dryRun) {
                        $existing->update([
                            'role'        => $ruolo,
                            'assigned_by' => $assegnedBy,
                            'assigned_at' => now(),
                            'notes'       => 'Import batch FIG ' . ($this->option('anno') ?? date('Y')),
                        ]);
                    }
                } else {
                    // Mostra data per distinguere seed da reale
                    $this->line("    <fg=gray>  ↷ {$nomeFig} → {$nome} — già presente ({$dataCreazione}{$noteExisting})</>");
                    $result['saltati']++;
                    $this->assegnazioniSaltate++;
                }
                continue;
            }

            $this->line("    <fg=green>  ✓ {$nomeFig} → {$nome} ({$score}%) — {$ruolo}</>");
            $result['creati']++;
            $this->assegnazioniCreate++;

            if (! $dryRun) {
                Assignment::create([
                    'tournament_id' => $torneoId,
                    'user_id'       => $userId,
                    'role'          => $ruolo,
                    'assigned_by'   => $assegnedBy,
                    'assigned_at'   => now(),
                    'status'        => 'assigned',
                    'is_confirmed'  => false,
                    'notes'         => 'Import batch FIG ' . ($this->option('anno') ?? date('Y')),
                ]);
            }
        }

        $result['stato'] = 'ok';

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MATCH TORNEO LOCALE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trova il torneo locale che meglio corrisponde a una gara FIG.
     * Combina similarità del nome (35%), club (35%) e prossimità della data (30%).
     *
     * @return array{torneo: Tournament, score: int}|null
     */
    private function findBestTournamentMatch(
        array $gara,
        \Illuminate\Database\Eloquent\Collection $tornei,
        int $minScore
    ): ?array {
        $figNome  = $this->normalizeStr($gara['nome']);
        $figClub  = $this->normalizeStr($gara['club'] ?? '');
        $figData  = \DateTime::createFromFormat('d/m/Y', $gara['data'] ?? '');

        $best      = null;
        $bestScore = 0;

        foreach ($tornei as $torneo) {
            $localNome = $this->normalizeStr($torneo->name);
            $localClub = $this->normalizeStr($torneo->club->name ?? '');
            $localData = $torneo->start_date;

            // Score nome (0-100) — usa strategia multi-approccio per gestire
            // casi in cui il nome locale è substring del nome FIG più lungo
            // (es. "MEMORIAL PIETRINO MANCA" in "14^ TAPPA ... - 'MEMORIAL PIETRINO MANCA'")
            $namePct = $this->computeTournamentNameScore($figNome, $localNome);

            // Score club (0-100) — 0 se mancante
            $clubPct = 0.0;
            if ($figClub && $localClub) {
                similar_text($figClub, $localClub, $clubPct);
                // Boost substring circolo: abbreviazioni FIG tipo "C.TEODORO SOLDATI"
                // vs nome completo locale "CIRCOLO TEODORO SOLDATI"
                $clubPct = max($clubPct, $this->computeSubstringBoost($figClub, $localClub, 70.0));
            }

            // Score data (0-100) — 100 se stessa data, scala a 0 oltre 14 giorni
            $dataPct = 0.0;
            if ($figData && $localData) {
                $diffDays = abs($figData->diff($localData->toDateTime())->days);
                $dataPct  = $diffDays === 0 ? 100.0 : max(0.0, 100.0 - ($diffDays * 7.0));
            }

            // Score combinato pesato
            // Nome: 35% — FIG usa varianti (MASCHILE/FEMMINILE, abbreviazioni) poco affidabili
            // Circolo: 35% — identificatore preciso e stabile
            // Data: 30% — molto discriminante, stessa data = stesso evento
            $score = (int) round(($namePct * 0.35) + ($clubPct * 0.35) + ($dataPct * 0.30));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $torneo;
            }
        }

        if ($bestScore < $minScore || ! $best) {
            return null;
        }

        /** @var Tournament $best */
        return ['torneo' => $best, 'score' => $bestScore];
    }

    /**
     * Calcola la similarità tra due nomi torneo usando più strategie:
     *
     * 1. similar_text diretto
     * 2. Segmento dopo l'ultimo '-' nel nome FIG (spesso è il "vero" nome del torneo,
     *    es. "14^ TAPPA C.TEODORO SOLDATI - 'MEMORIAL PIETRINO MANCA'" → "memorial pietrino manca")
     * 3. Boost substring: se il nome locale è interamente contenuto nel nome FIG
     *    (o viceversa) → score fisso 88
     *
     * Restituisce il massimo tra le tre strategie.
     */
    private function computeTournamentNameScore(string $figNome, string $localNome): float
    {
        // 1. Score diretto
        similar_text($figNome, $localNome, $direct);

        // 2. Segmento dopo l'ultimo trattino nel nome FIG
        //    Molti tornei FIG hanno la forma "N^ TAPPA CIRCOLO - NOME REALE"
        $afterDash = 0.0;
        $parts = preg_split('/\s+-+\s*/', $figNome);
        if (count($parts) > 1) {
            $lastPart = trim(end($parts));
            // Vale la pena solo se il segmento ha almeno 6 caratteri
            if (mb_strlen($lastPart) >= 6) {
                similar_text($lastPart, $localNome, $afterDash);
            }
        }

        // 3. Boost substring
        $substrBoost = $this->computeSubstringBoost($figNome, $localNome, 88.0);

        return max($direct, $afterDash, $substrBoost);
    }

    /**
     * Restituisce $boostScore se la stringa più corta è interamente contenuta
     * in quella più lunga (minimo 8 caratteri per evitare falsi positivi),
     * altrimenti 0.
     */
    private function computeSubstringBoost(string $a, string $b, float $boostScore): float
    {
        if (mb_strlen($a) < 8 || mb_strlen($b) < 8) {
            return 0.0;
        }
        $shorter = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longer  = mb_strlen($a) >  mb_strlen($b) ? $a : $b;

        return str_contains($longer, $shorter) ? $boostScore : 0.0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CARICAMENTO GARE FIG
    // ─────────────────────────────────────────────────────────────────────────

    private function loadFigCompetitions(int $anno): array
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent'       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept'           => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action'  => 'competitions-search',
                    'tipo'    => '',
                    'keyword' => '',
                    'anno'    => $anno,
                    'mese'    => '',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            $gare = [];

            foreach ($data['data'] ?? [] as $gara) {
                if ($gara['annullata'] ?? false) {
                    continue;
                }
                $nome = $gara['nome'] ?? $gara['title'] ?? '';
                if (preg_match('/ANNULLAT|RINVIAT/i', $nome)) {
                    continue;
                }
                $gare[] = [
                    'id'   => $gara['competition_id'] ?? $gara['id'],
                    'nome' => $nome,
                    'data' => $gara['data'] ?? null,
                    'club' => $gara['club'] ?? null,
                ];
            }

            // Ordina per data crescente
            usort($gare, function ($a, $b) {
                $da = \DateTime::createFromFormat('d/m/Y', $a['data'] ?? '');
                $db = \DateTime::createFromFormat('d/m/Y', $b['data'] ?? '');
                if (! $da || ! $db) {
                    return 0;
                }

                return $da <=> $db;
            });

            return $gare;

        } catch (\Throwable $e) {
            $this->error("Errore connessione FIG: " . $e->getMessage());

            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // DE-DUPLICAZIONE MASCHILE/FEMMINILE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Raggruppa le gare FIG per (data, circolo normalizzato).
     * Quando MASCHILE e FEMMINILE cadono nello stesso (data, circolo),
     * vengono fuse in una sola voce: si usa la gara MASCHILE come
     * rappresentante (o la prima trovata) e si raccolgono entrambi gli ID
     * per il fetch del comitato (che è identico).
     *
     * @param  array[] $gare
     * @return array[]
     */
    private function deduplicateGare(array $gare): array
    {
        $groups = [];

        foreach ($gare as $gara) {
            // Chiave di raggruppamento: data + circolo (senza MASCHILE/FEMMINILE nel nome)
            $clubKey = $this->normalizeStr($gara['club'] ?? '');
            $dataKey = $gara['data'] ?? '';
            $key     = $dataKey . '|' . $clubKey;

            if (! isset($groups[$key])) {
                $groups[$key] = $gara;
                // Normalizza il nome togliendo il suffisso genere
                $groups[$key]['nome'] = $this->stripGenere($gara['nome']);
                $groups[$key]['fig_ids'] = [$gara['id']]; // tutti gli ID FIG del gruppo
            } else {
                // Aggiungi l'ID alternativo (servirà come fallback se il primo non ha comitato)
                $groups[$key]['fig_ids'][] = $gara['id'];
            }
        }

        return array_values($groups);
    }

    /**
     * Rimuove i suffissi di genere da un nome gara FIG.
     * "COPPA MARIO ROSSI - MASCHILE" → "COPPA MARIO ROSSI"
     */
    private function stripGenere(string $nome): string
    {
        return trim(preg_replace('/\s*[-–]\s*(MASCHILE|FEMMINILE|MISTO)\s*$/i', '', $nome));
    }

    private function normalizeStr(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(
            ['à','è','é','ì','ò','ù','á','ê','î','ô','û'],
            ['a','e','e','i','o','u','a','e','i','o','u'],
            $s
        );
        // Rimuovi suffissi MASCHILE/FEMMINILE che differenziano gare FIG ma non i tornei locali
        $s = preg_replace('/\s*[-–]\s*(maschile|femminile|misto)\s*$/i', '', $s);
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    /**
     * Restituisce l'ID del primo admin attivo — usato come `assigned_by` nelle assegnazioni batch.
     */
    private function getSystemUserId(): int
    {
        return \App\Models\User::where('user_type', 'admin')
            ->where('is_active', true)
            ->value('id') ?? 1;
    }

    private function printSummary(
        array $report,
        bool $dryRun,
        \Illuminate\Database\Eloquent\Collection $torneiLocali
    ): void {
        // ID tornei locali abbinati ad almeno una gara FIG
        $abbinatiIds = array_filter(array_column($report, 'torneo_locale_id'));
        $abbinatiIds = array_unique($abbinatiIds);

        // Tornei locali che non hanno ricevuto nessuna gara FIG abbinata
        /** @var \Illuminate\Database\Eloquent\Collection<int, Tournament> $torneiSenzaFig */
        $torneiSenzaFig = $torneiLocali->filter(
            fn ($t) => ! in_array($t->id, $abbinatiIds, true)
        );

        $this->info("═══════════════════════════════════════════════════");
        $this->info("  RIEPILOGO IMPORT" . ($dryRun ? " (DRY-RUN)" : ""));
        $this->info("═══════════════════════════════════════════════════");
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Gare FIG elaborate',                                       $this->gareTotali],
                ['Gare FIG senza torneo locale',                             $this->gareSenzaMatch],
                ['Gare FIG senza comitato',                                  $this->gareSenzaComitato],
                ['Tornei locali senza gara FIG corrispondente',              $torneiSenzaFig->count()],
                ['Assegnazioni create' . ($dryRun ? ' (simulato)' : ''),     $this->assegnazioniCreate],
                ['Assegnazioni già presenti',                                 $this->assegnazioniSaltate],
                ['Arbitri senza match locale',                                $this->nomiSenzaMatch],
            ]
        );

        // ── Gare FIG senza match torneo — utile per intervento manuale
        $senzaMatch = array_filter($report, fn ($r) => $r['stato'] === 'no_torneo_locale');
        if (! empty($senzaMatch)) {
            $this->newLine();
            $this->warn("Gare FIG senza torneo locale corrispondente:");
            foreach ($senzaMatch as $r) {
                $this->line("  - {$r['fig_nome']} ({$r['fig_data']}) — {$r['fig_club']}");
            }
        }

        // ── Tornei locali senza gara FIG — probabile assenza su federgolf.it o mismatch
        if ($torneiSenzaFig->isNotEmpty()) {
            $this->newLine();
            $this->warn("Tornei locali senza gara FIG corrispondente:");
            $this->table(
                ['Torneo locale', 'Data inizio', 'Circolo', 'Assegnazioni'],
                $torneiSenzaFig->map(fn ($t) => [
                    $t->name,
                    $t->start_date?->format('d/m/Y') ?? '—',
                    $t->club->name ?? '—',
                    $t->assignments->count(),
                ])->toArray()
            );
        }

        // Lista completa dei nomi da creare come nuovi utenti
        if (! empty($this->nomiDaCreare)) {
            $this->newLine();
            $this->warn("Arbitri FIG NON trovati nel DB — da creare come utenti:");
            $this->table(
                ['Nome FIG', 'Ruolo', 'Torneo', 'Miglior candidato scartato'],
                array_map(fn ($n) => [
                    $n['nome_fig'],
                    $n['ruolo'],
                    $n['torneo'],
                    $n['candidato'],
                ], $this->nomiDaCreare)
            );
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY-RUN completato. Per eseguire l'import reale:");
            $this->info("  php artisan federgolf:import-committees --anno=" . ($this->option('anno') ?? date('Y')));
        } else {
            $this->info("Import completato. ✅");
        }
    }
}
