<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\FigNotificationMarker;
use Illuminate\Console\Command;

/**
 * Marca come "notificate" le assegnazioni create via import batch FIG.
 *
 * Dal 2026-10-08 ogni caricamento da FIG lo fa da solo (FigNotificationMarker):
 * il comando serve per i tornei caricati prima. Include anche le designazioni
 * dell'import guidato ('Importato da federgolf.it').
 *
 * Crea un record TournamentNotification (status=sent) per ogni torneo
 * che ha assegnazioni con note 'Import batch FIG <anno>' e non ha già
 * una notifica dello stesso tipo.
 *
 * Il tipo viene rilevato automaticamente dal campo is_national del tipo torneo:
 *   - torneo nazionale  → notification_type = 'crc_referees'
 *   - torneo zonale     → notification_type = null
 *
 * Utilizzo:
 *   php artisan federgolf:mark-notified --anno=2025 --dry-run
 *   php artisan federgolf:mark-notified --anno=2025
 *   php artisan federgolf:mark-notified --anno=2025 --type=zonal   (forza zonale)
 *   php artisan federgolf:mark-notified --anno=2025 --type=crc_referees (forza CRC)
 */
class MarkFigAssignmentsNotified extends Command
{
    protected $signature = 'federgolf:mark-notified
                            {--anno=2025 : Anno delle assegnazioni FIG da marcare}
                            {--type=auto : Tipo notifica: auto | crc_referees | zone_observers | zonal. "auto" rileva dal tipo torneo (raccomandato)}
                            {--dry-run   : Solo anteprima, nessuna scrittura su DB}';

    protected $description = 'Marca come notificate le assegnazioni create via import batch FIG';

    public function handle(): int
    {
        $anno   = (int) ($this->option('anno') ?? 2025);
        $type   = $this->option('type') ?? 'auto';
        $dryRun = (bool) $this->option('dry-run');

        $this->info('');
        $this->info("🔔  Marca notificate — Import FIG {$anno}");
        $this->info('   Tipo notifica: ' . ($type === 'auto' ? 'auto (da tournamentType.is_national)' : $type));
        $this->info($dryRun ? '   ⚠️  DRY-RUN: nessuna scrittura su DB' : '   ✅  Modalità scrittura attiva');
        $this->newLine();

        // Trova tutte le assegnazioni dell'anno con note "Import batch FIG <anno>"
        $assignments = Assignment::with(['tournament.club', 'tournament.tournamentType', 'user'])
            ->whereHas('tournament', fn ($q) => $q->whereYear('start_date', $anno))
            // Anche le designazioni dell'import guidato (2026-10-08)
            ->where(fn ($q) => $q->where('notes', 'like', "Import batch FIG {$anno}%")
                ->orWhere('notes', 'Importato da federgolf.it'))
            ->get();

        if ($assignments->isEmpty()) {
            $this->warn("Nessuna assegnazione con note 'Import batch FIG {$anno}' trovata.");
            $this->line("  Verifica che l'import sia stato eseguito senza --dry-run.");
            return self::FAILURE;
        }

        // Raggruppa per torneo
        $byTournament = $assignments->groupBy('tournament_id');

        $this->info("   Tornei con assegnazioni FIG {$anno}: {$byTournament->count()}");
        $this->info("   Assegnazioni totali:              {$assignments->count()}");
        $this->newLine();

        $createdNotif  = 0;
        $skippedNotif  = 0;
        $marker = app(FigNotificationMarker::class);

        foreach ($byTournament as $tournamentId => $tournamentAssignments) {
            $torneo = $tournamentAssignments->first()?->tournament;
            if (! $torneo instanceof \App\Models\Tournament) {
                continue;
            }
            $dataStr = $torneo->start_date->format('d/m/Y');

            if ($dryRun) {
                $this->line("  <fg=green>  + {$torneo->name}</> ({$dataStr})");
                $createdNotif++;

                continue;
            }

            $esito = $marker->mark($torneo, "Import batch FIG {$anno}", is_string($type) ? $type : 'auto');
            if ($esito === FigNotificationMarker::SKIPPED) {
                $this->line("  <fg=gray>  ↷ {$torneo->name} ({$dataStr}) — gia' notificato</>");
                $skippedNotif++;
            } else {
                $this->line("  <fg=green>  + {$torneo->name}</> ({$dataStr})");
                $createdNotif++;
            }
        }

        // Riepilogo
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  RIEPILOGO' . ($dryRun ? ' (DRY-RUN)' : ''));
        $this->info('═══════════════════════════════════════════════════');
        $this->table(
            ['', ''],
            [
                ['Notifiche create',            $createdNotif],
                ['Tornei già notificati (skip)', $skippedNotif],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->info("Per eseguire la scrittura reale:");
            $this->info("  php artisan federgolf:mark-notified --anno={$anno} --type={$type}");
        }

        return self::SUCCESS;
    }
}
