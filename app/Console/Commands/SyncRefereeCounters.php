<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Riallinea i contatori denormalizzati su users:
 *   - total_tournaments         (totale assignments nella vita)
 *   - tournaments_current_year  (assignments nell'anno specificato)
 *
 * Il problema: l'import batch FIG 2025 (e i successivi) inserisce
 * direttamente le righe in assignments senza passare per il normale
 * flusso Controller → Observer, quindi i contatori non vengono mai
 * incrementati.
 *
 * Utilizzo:
 *   php artisan referees:sync-counters              (anno corrente)
 *   php artisan referees:sync-counters --year=2025  (anno specifico)
 *   php artisan referees:sync-counters --dry-run    (anteprima senza scrittura)
 */
class SyncRefereeCounters extends Command
{
    protected $signature = 'referees:sync-counters
                            {--year= : Anno di riferimento per tournaments_current_year (default: anno corrente)}
                            {--dry-run : Mostra cosa cambierebbe senza scrivere}
                            {--user= : Ricalcola solo questo user_id}';

    protected $description = 'Riallinea total_tournaments e tournaments_current_year per tutti gli arbitri';

    public function handle(): int
    {
        $year    = (int) ($this->option('year') ?: now()->year);
        $dryRun  = $this->option('dry-run');
        $userId  = $this->option('user') ? (int) $this->option('user') : null;

        $this->info("Sync contatori arbitri — anno di riferimento: {$year}");
        if ($dryRun) {
            $this->warn('  [DRY-RUN] Nessuna modifica verrà salvata.');
        }

        // Conteggio totale per user con una sola query
        $totalByUser = Assignment::select('user_id', DB::raw('COUNT(*) as cnt'))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        // Conteggio anno corrente: join con tournaments per filtrare per anno
        $currByUser = Assignment::select('assignments.user_id', DB::raw('COUNT(*) as cnt'))
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->whereYear('tournaments.start_date', $year)
            ->when($userId, fn ($q) => $q->where('assignments.user_id', $userId))
            ->groupBy('assignments.user_id')
            ->pluck('cnt', 'user_id');

        $query = User::where('user_type', 'referee')
            ->when($userId, fn ($q) => $q->where('id', $userId));

        $updated  = 0;
        $skipped  = 0;
        $bar      = $this->output->createProgressBar($query->count());

        $query->each(function (User $user) use ($totalByUser, $currByUser, $dryRun, &$updated, &$skipped, $bar) {
            $realTotal = $totalByUser[$user->id] ?? 0;
            $realCurr  = $currByUser[$user->id]  ?? 0;

            if ($user->total_tournaments === $realTotal && $user->tournaments_current_year === $realCurr) {
                $skipped++;
                $bar->advance();
                return;
            }

            if (! $dryRun) {
                $user->updateQuietly([
                    'total_tournaments'        => $realTotal,
                    'tournaments_current_year' => $realCurr,
                ]);
            }

            $updated++;
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Stato', 'Conteggio'],
            [
                ['Utenti aggiornati', $updated],
                ['Utenti già allineati (skip)', $skipped],
            ]
        );

        if ($dryRun && $updated > 0) {
            $this->warn("Riesegui senza --dry-run per applicare le {$updated} modifiche.");
        } elseif ($updated > 0) {
            $this->info("✓ {$updated} contatori aggiornati.");
        } else {
            $this->info('✓ Tutti i contatori erano già allineati.');
        }

        return self::SUCCESS;
    }
}
