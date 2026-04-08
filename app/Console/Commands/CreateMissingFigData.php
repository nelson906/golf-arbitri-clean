<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea utenti e tornei rilevati come mancanti dopo l'import FIG 2025.
 *
 * Utilizzo:
 *   php artisan federgolf:create-missing --dry-run    (anteprima)
 *   php artisan federgolf:create-missing              (scrittura reale)
 */
class CreateMissingFigData extends Command
{
    protected $signature = 'federgolf:create-missing
                            {--dry-run : Solo anteprima, nessuna scrittura su DB}';

    protected $description = 'Crea gli utenti e i tornei mancanti rilevati dopo import FIG 2025';

    // ── Arbitri FIG non trovati in DB ─────────────────────────────────────────
    // Formato: "COGNOME, NOME"  (oppure "COGNOME, E. NOME" per seconde iniziali)
    // DASCOLI e D'ASCOLI sono lo stesso soggetto: la deduplicazione normalizzata
    // li identifica e salta il secondo.
    private array $utentiDaCreare = [
        'GUIDA, ARMANDO',
        'STOMACI, DARIO',
        'DALL\'OLMO, DAVIS',
        'CIMA, LORENZO'
    ];

    // ── Tornei 2025 non trovati in DB ─────────────────────────────────────────
    private array $torneiDaCreare = [
        ['nome' => 'TROFEO CITTA\' DI MODENA - U.N.V.S.- AMBASCIATORI DELLO SPORT ',                    'data' => '13/03/2026', 'club' => 'MODENA'],
        ['nome' => '72° LEONE DI SAN MARCO ',                                                           'data' => '27/03/2026', 'club' => 'VENEZIA'],
        ['nome' => 'CAMPIONATO REGIONALE TOSCANO A SQUADRE ',                                           'data' => '11/04/2026', 'club' => 'PUNTA ALA'],
        ['nome' => 'TGF U16 METAPONTINO D\'ORO ', 'data' => '18/04/2026', 'club' => 'METAPONTO'],
        ['nome' => 'MENAGGIO LEGACY U16 TROPHY ', 'data' => '25/04/2026', 'club' => 'MENAGGIO'],
        ['nome' => '41° LEONCINO D\'ORO ', 'data' => '09/05/2026', 'club' => 'VENEZIA'],
        ['nome' => 'TROFEO CITTA\' DI CASTELFRANCO VENETO ', 'data' => '29/05/2026', 'club' => 'CA\' AMATA'],
        ['nome' => '28° TROFEO BOYS CHALLENGE LUCIO VISONA\' ', 'data' => '03/06/2026', 'club' => 'FOLGARIA'],
        ['nome' => 'TROFEO GIOVANILE FEDERALE U16 ', 'data' => '07/06/2026', 'club' => 'CONERO'],
        ['nome' => 'GARA NAZIONALE 54/54 A REGOLAMENTO SPECIALE ', 'data' => '09/06/2026', 'club' => 'FONTI'],
        ['nome' => 'CAMPIONATO NAZIONALE FEMMINILE MEDAL - TROFEO ISA GOLDSCHMID ', 'data' => '10/06/2026', 'club' => 'ROMA ACQUASANTA'],
        ['nome' => 'TGF UNDER 16 PICCOLO CIMBRO ', 'data' => '16/06/2026', 'club' => 'ASIAGO'],
        ['nome' => 'TROFEO GIOVANILE FEDERALE UNDER 18 ', 'data' => '20/06/2026', 'club' => 'IS MOLAS SSD'],
        ['nome' => 'GRAN PREMIO PADOVA ', 'data' => '22/06/2026', 'club' => 'PADOVA'],
        ['nome' => 'TROFEO CITTA\' DI TREVISO ', 'data' => '10/07/2026', 'club' => 'VILLA CONDULMER'],
        ['nome' => 'TROFEO DELLA MONTECCHIA ', 'data' => '15/07/2026', 'club' => 'MONTECCHIA GOLF'],
        ['nome' => 'TORNEO NAZIONALE DI QUALIFICA MASCHILE A SQUADRE ', 'data' => '28/07/2026', 'club' => 'FONTI'],
        ['nome' => 'TROFEO GIOVANILE FEDERALE U18 ', 'data' => '28/07/2026', 'club' => 'CA\' AMATA'],
        ['nome' => 'TORNEO NAZIONALE DI QUALIFICA FEMMINILE A SQUADRE ', 'data' => '29/07/2026', 'club' => 'BOLOGNA'],
        ['nome' => 'TORNEO DI SELEZIONE MASCHILE A SQUADRE ', 'data' => '30/07/2026', 'club' => 'LECCO'],
        ['nome' => 'TROFEO GIOVANILE FEDERALE UNDER 16 ', 'data' => '30/07/2026', 'club' => 'CAVAGLIA'],
        ['nome' => '30° GRAN PREMIO CITTA\' DI CERVIA ', 'data' => '05/08/2026', 'club' => 'CERVIA'],
        ['nome' => 'ASOLO HILLS GARA A REGOLAMENTO SPECIALE ', 'data' => '18/09/2026', 'club' => 'ASOLO'],
        ['nome' => 'TROFEO ROCCA D\'ASOLO ', 'data' => '24/10/2026', 'club' => 'ASOLO'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('');
        $this->info('🏌️  Creazione dati mancanti — FIG 2025');
        $this->info($dryRun ? '   ⚠️  DRY-RUN: nessuna scrittura su DB' : '   ✅  Modalità scrittura attiva');
        $this->newLine();

        $this->processUtenti($dryRun);
        $this->newLine();
        $this->processTornei($dryRun);

        $this->newLine();
        if ($dryRun) {
            $this->info('DRY-RUN completato. Per eseguire la creazione reale:');
            $this->info('  php artisan federgolf:create-missing');
        } else {
            $this->info('Creazione completata. ✅');
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTENTI
    // ─────────────────────────────────────────────────────────────────────────

    private function processUtenti(bool $dryRun): void
    {
        $this->info('👤  ARBITRI');
        $this->info(str_repeat('─', 60));

        $esistenti = User::referees()->get(['id', 'name', 'first_name', 'last_name', 'email']);

        $creati  = 0;
        $saltati = 0;
        $seenNorm = []; // per deduplicare la lista input

        foreach ($this->utentiDaCreare as $figStr) {
            [$lastName, $firstName] = $this->splitNomeFig($figStr);

            // Deduplicazione nella lista input (es. DASCOLI / D'ASCOLI)
            $normKey = $this->normalizeStr($lastName . ' ' . $firstName);
            if (isset($seenNorm[$normKey])) {
                $this->line("  <fg=gray>  ↷ {$figStr} — duplicato di '{$seenNorm[$normKey]}' (saltato)</>");
                $saltati++;
                continue;
            }
            $seenNorm[$normKey] = $figStr;

            // Controlla se esiste già nel DB (fuzzy cognome + nome)
            $existing = $this->findExistingUser($lastName, $firstName, $esistenti);
            if ($existing) {
                $this->line("  <fg=gray>  ↷ {$figStr} — già presente: {$existing->name} ({$existing->email})</>");
                $saltati++;
                continue;
            }

            $displayName = $this->toDisplayName($firstName, $lastName);
            $email       = $this->generateEmail($firstName, $lastName);

            $this->line("  <fg=green>  + {$figStr}</> → {$displayName}  <{$email}>");
            $creati++;

            if (! $dryRun) {
                User::create([
                    'name'       => $displayName,
                    'first_name' => $this->titleCase($firstName),
                    'last_name'  => $this->titleCase($lastName),
                    'email'      => $email,
                    'password'   => Hash::make(Str::random(24)),
                    'user_type'  => 'referee',
                    'level'      => 'Aspirante',
                    'is_active'  => true,
                    'notes'      => 'Creato da import FIG 2025 — email placeholder, dati da verificare',
                ]);
            }
        }

        $this->newLine();
        $this->line("  Creati: <fg=green>{$creati}</>  |  Saltati (già presenti / duplicati): <fg=yellow>{$saltati}</>");
    }

    /**
     * Spezza la stringa FIG "COGNOME, NOME" nelle due parti.
     * Gestisce anche "COGNOME, E. NOME" (iniziale intermedia tipo "E. TIMOTHY").
     */
    private function splitNomeFig(string $figStr): array
    {
        if (str_contains($figStr, ',')) {
            [$cognome, $nome] = explode(',', $figStr, 2);

            return [trim($cognome), trim($nome)];
        }

        // Fallback: primo token = cognome, resto = nome
        $parts = preg_split('/\s+/', trim($figStr), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Cerca un utente esistente per cognome (≥80%) + nome (≥55%) normalizzati.
     */
    private function findExistingUser(string $lastName, string $firstName, $esistenti): ?User
    {
        $cognomeNorm = $this->normalizeStr($lastName);
        $nomeNorm    = $this->normalizeStr($firstName);

        foreach ($esistenti as $user) {
            $dbLast  = $user->last_name  ? $this->normalizeStr($user->last_name)  : '';
            $dbFirst = $user->first_name ? $this->normalizeStr($user->first_name) : '';

            if (! $dbLast) {
                $parts   = preg_split('/\s+/', $this->normalizeStr($user->name));
                $dbLast  = end($parts) ?: '';
                $dbFirst = implode(' ', array_slice($parts, 0, -1));
            }

            similar_text($cognomeNorm, $dbLast, $cognomePct);
            if ($cognomePct < 80.0) {
                continue;
            }

            if ($nomeNorm && $dbFirst) {
                similar_text($nomeNorm, $dbFirst, $nomePct);
                if ($nomePct < 55.0) {
                    continue;
                }
            }

            return $user;
        }

        return null;
    }

    private function toDisplayName(string $firstName, string $lastName): string
    {
        return $this->titleCase($firstName) . ' ' . $this->titleCase($lastName);
    }

    /**
     * Title-case con gestione apostrofo: D'AMICO → D'Amico, E. TIMOTHY → E. Timothy.
     */
    private function titleCase(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');

        return ucwords($s, " \t\r\n\f-'.");
    }

    /**
     * Genera un'email placeholder garantita unica nel DB.
     * Formato: nome.cognome@arbitri.placeholder.it
     */
    private function generateEmail(string $firstName, string $lastName): string
    {
        $fn   = $this->slugify($firstName);
        $ln   = $this->slugify($lastName);
        $base = "{$fn}.{$ln}@arbitri.placeholder.it";
        $email = $base;
        $i = 2;
        while (User::where('email', $email)->exists()) {
            $email = "{$fn}.{$ln}{$i}@arbitri.placeholder.it";
            $i++;
        }

        return $email;
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(
            ["'", "\u{2019}", 'à', 'è', 'é', 'ì', 'ò', 'ù', 'á', 'ê', 'î', 'ô', 'û'],
            ['',  '',         'a', 'e', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
            $s
        );

        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TORNEI
    // ─────────────────────────────────────────────────────────────────────────

    private function processTornei(bool $dryRun): void
    {
        $this->info('📅  TORNEI');
        $this->info(str_repeat('─', 60));

        $clubs = Club::all(['id', 'name', 'zone_id']);

        $creati  = 0;
        $saltati = 0;

        foreach ($this->torneiDaCreare as $item) {
            $nomeLocal = $item['nome'];
            $data      = Carbon::createFromFormat('d/m/Y', $item['data']);
            $clubFig   = trim($item['club']);

            // Controlla se esiste già un torneo con stesso nome e stessa data
            $nomeNorm = $this->normalizeStr($nomeLocal);
            $existing = Tournament::whereDate('start_date', $data->toDateString())->get();
            $duplicate = $existing->first(function ($t) use ($nomeNorm) {
                similar_text($nomeNorm, $this->normalizeStr($t->name), $pct);

                return $pct >= 80;
            });

            if ($duplicate) {
                $this->line("  <fg=gray>  ↷ {$nomeLocal} ({$item['data']}) — già presente: '{$duplicate->name}' (id:{$duplicate->id})</>");
                $saltati++;
                continue;
            }

            // Trova il circolo tramite fuzzy match
            $clubPct = 0;
            $club    = null;
            if ($clubFig !== '') {
                $club = $this->findClub($clubFig, $clubs, $clubPct);
            }

            if ($club) {
                $clubLabel = "<fg=green>{$club->name}</> ({$clubPct}%)";
            } elseif ($clubFig !== '') {
                $clubLabel = "<fg=yellow>'{$clubFig}' non trovato — club_id null</>";
            } else {
                $clubLabel = '<fg=yellow>nessun circolo indicato</>';
            }

            $this->line("  <fg=green>  + {$nomeLocal}</> ({$item['data']})");
            $this->line("      circolo: {$clubLabel}");
            $creati++;

            if (! $dryRun) {
                Tournament::create([
                    'name'                  => $nomeLocal,
                    'club_id'               => $club?->id,
                    'zone_id'               => $club?->zone_id,
                    'tournament_type_id'    => 9,
                    'start_date'            => $data,
                    'end_date'              => $data,
                    'availability_deadline' => $data,
                    'created_by'            => 1,
                    'status'                => 'open',
                    'notes'                 => 'Creato da import FIG 2025',
                ]);
            }
        }

        $this->newLine();
        $this->line("  Creati: <fg=green>{$creati}</>  |  Saltati (già presenti): <fg=yellow>{$saltati}</>");
    }

    /**
     * Fuzzy match sul nome circolo.
     * Usa similar_text + boost substring (es. "MODENA" in "GOLF CLUB MODENA").
     * Soglia minima: 50%.
     */
    private function findClub(string $figClub, $clubs, float &$bestPct): ?Club
    {
        $figNorm = $this->normalizeStr($figClub);
        $best    = null;
        $bestPct = 0.0;

        foreach ($clubs as $club) {
            $localNorm = $this->normalizeStr($club->name);
            similar_text($figNorm, $localNorm, $pct);

            // Boost se il nome FIG è substring del nome locale o viceversa
            $shorter = strlen($figNorm) <= strlen($localNorm) ? $figNorm : $localNorm;
            $longer  = strlen($figNorm) >  strlen($localNorm) ? $figNorm : $localNorm;
            if (strlen($shorter) >= 4 && str_contains($longer, $shorter)) {
                $pct = max($pct, 82.0);
            }

            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best    = $club;
            }
        }

        $bestPct = (float) round($bestPct);

        return $bestPct >= 50 ? $best : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS COMUNI
    // ─────────────────────────────────────────────────────────────────────────

    private function normalizeStr(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(
            ["'", "\u{2019}", 'à', 'è', 'é', 'ì', 'ò', 'ù', 'á', 'ê', 'î', 'ô', 'û'],
            ['',  '',         'a', 'e', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
            $s
        );
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
