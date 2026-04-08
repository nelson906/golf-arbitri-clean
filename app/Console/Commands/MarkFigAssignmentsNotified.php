<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\TournamentNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Marca come "notificate" le assegnazioni create via import batch FIG.
 *
 * Crea un record TournamentNotification (status=sent) per ogni torneo
 * che ha assegnazioni con note 'Import batch FIG <anno>' e non ha già
 * una notifica dello stesso tipo.
 *
 * Il tipo viene rilevato automaticamente dal campo is_national del tipo torneo:
 *   - torneo nazionale  → notification_type = 'crc_referees'
 *   - torneo zonale     → notification_type = null
 *
 * Imposta anche is_confirmed=true sulle assegnazioni interessate.
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

        // sent_by è nullable (ON DELETE SET NULL) — null è il fallback corretto
        // se non esiste alcun admin nel DB (es. durante i test)
        $adminId = User::where('user_type', 'super_admin')->value('id')
            ?? User::where('user_type', 'national_admin')->value('id')
            ?? null;

        // Trova tutte le assegnazioni dell'anno con note "Import batch FIG <anno>"
        $assignments = Assignment::with(['tournament.club', 'user'])
            ->whereHas('tournament', fn ($q) => $q->whereYear('start_date', $anno))
            ->where('notes', 'like', "Import batch FIG {$anno}%")
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
        $confirmedAsgn = 0;

        foreach ($byTournament as $tournamentId => $tournamentAssignments) {
            $torneo     = $tournamentAssignments->first()->tournament;
            $nomeTorneo = $torneo?->name ?? "Torneo ID {$tournamentId}";
            $dataStr    = $torneo?->start_date?->format('d/m/Y') ?? '—';

            // Determina il tipo di notifica per questo specifico torneo
            if ($type === 'auto') {
                // Fonte di verità: is_national dal tipo torneo
                $isNational       = $torneo?->tournamentType?->is_national ?? false;
                $notificationType = $isNational ? 'crc_referees' : null;
            } elseif ($type === 'zonal') {
                $notificationType = null;
            } else {
                $notificationType = $type; // crc_referees | zone_observers
            }

            $typeLabel = match ($notificationType) {
                'crc_referees'   => 'nazionale (CRC)',
                'zone_observers' => 'nazionale (Zona)',
                null             => 'zonale',
                default          => $notificationType,
            };

            // Controlla se esiste già una notifica del tipo corretto per questo torneo
            $refereeNames = '';
            $existingNotif = TournamentNotification::where('tournament_id', $tournamentId)
                ->where(function ($q) use ($notificationType) {
                    $notificationType === null
                        ? $q->whereNull('notification_type')
                        : $q->where('notification_type', $notificationType);
                })
                ->where('status', 'sent')
                ->first();

            if ($existingNotif) {
                $sentAt = $existingNotif->sent_at?->format('d/m/Y H:i') ?? '—';
                $this->line("  <fg=gray>  ↷ {$nomeTorneo} ({$dataStr}) [{$typeLabel}] — già notificato il {$sentAt}</>");
                $skippedNotif++;
                // Anche se la notifica esiste, aggiorna is_confirmed sulle assegnazioni
            } else {
                // Costruisci referee_list e details
                $refereeNames = $tournamentAssignments
                    ->map(fn ($a) => $a->user?->name ?? '?')
                    ->filter()
                    ->implode(', ');

                $sentAt = $tournamentAssignments->max('assigned_at') ?? now();

                $this->line("  <fg=green>  + {$nomeTorneo}</> ({$dataStr}) [{$typeLabel}]");
                $this->line("      arbitri: {$refereeNames}");
                $createdNotif++;
            }

            // Imposta is_confirmed=true sulle assegnazioni non ancora confermate
            $daConfermare = $tournamentAssignments->where('is_confirmed', false);
            if ($daConfermare->isNotEmpty()) {
                $this->line("      <fg=cyan>✓ confermo {$daConfermare->count()} assegnazioni</>");
                $confirmedAsgn += $daConfermare->count();
            }

            // Scritture DB atomiche: notifica + conferma assegnazioni in un'unica transazione
            if (! $dryRun && (! $existingNotif || $daConfermare->isNotEmpty())) {
                DB::transaction(function () use (
                    $existingNotif, $tournamentId, $notificationType, $sentAt,
                    $adminId, $refereeNames, $tournamentAssignments, $anno, $daConfermare
                ) {
                    if (! $existingNotif) {
                        TournamentNotification::create([
                            'tournament_id'     => $tournamentId,
                            'notification_type' => $notificationType,
                            'status'            => 'sent',
                            'sent_at'           => $sentAt,
                            'sent_by'           => $adminId,
                            'referee_list'      => $refereeNames,
                            'details'           => [
                                'sent'             => $tournamentAssignments->count(),
                                'arbitri'          => $tournamentAssignments->count(),
                                'total_recipients' => $tournamentAssignments->count(),
                                'note'             => "Import automatico FIG {$anno}",
                            ],
                            'metadata'          => [
                                'source'  => "Import batch FIG {$anno}",
                                'command' => 'federgolf:mark-notified',
                            ],
                        ]);
                    }

                    if ($daConfermare->isNotEmpty()) {
                        Assignment::whereIn('id', $daConfermare->pluck('id'))
                            ->update(['is_confirmed' => true]);
                    }
                });
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
                ['Assegnazioni confermate',      $confirmedAsgn],
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
