<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Console\Command;

/**
 * Corregge i record TournamentNotification con notification_type errato.
 *
 * Problema: import FIG 2025 (e potenziali errori manuali) hanno creato record
 * con notification_type='crc_referees' per tornei zonali (is_national=false)
 * oppure notification_type=null per tornei nazionali (is_national=true).
 *
 * Regola di classificazione (fonte di verità unica):
 *   tournament.tournamentType.is_national = true  → crc_referees (o zone_observers)
 *   tournament.tournamentType.is_national = false → null (zonale)
 *
 * Utilizzo:
 *   php artisan federgolf:fix-notification-types --dry-run
 *   php artisan federgolf:fix-notification-types
 */
class FixNotificationTypes extends Command
{
    protected $signature = 'federgolf:fix-notification-types
                            {--dry-run : Solo anteprima, nessuna scrittura su DB}';

    protected $description = 'Corregge notification_type errato in TournamentNotification (fonte di verità: tournamentType.is_national)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('');
        $this->info('🔧  Fix notification_type — fonte di verità: tournamentType.is_national');
        $this->info($dryRun ? '   ⚠️  DRY-RUN: nessuna scrittura su DB' : '   ✅  Modalità scrittura attiva');
        $this->newLine();

        // Carica tutte le notifiche con le relazioni necessarie
        $notifications = TournamentNotification::with([
            'tournament.tournamentType',
        ])->get();

        $fixes   = [];
        $correct = 0;

        foreach ($notifications as $notif) {
            $tournament = $notif->tournament;
            if (! $tournament) {
                $this->warn("  ⚠  Notifica ID {$notif->id}: torneo non trovato — saltata");
                continue;
            }

            $isNational      = $tournament->tournamentType?->is_national ?? false;
            $currentType     = $notif->notification_type;

            // Determina il tipo corretto
            if (! $isNational) {
                // Torneo zonale: il tipo DEVE essere null
                // zone_observers e crc_referees non hanno senso per tornei zonali
                if ($currentType !== null) {
                    $fixes[] = [
                        'id'          => $notif->id,
                        'tournament'  => $tournament->name,
                        'old_type'    => $currentType,
                        'new_type'    => null,
                        'reason'      => 'torneo zonale (is_national=false) aveva tipo non-null',
                    ];
                } else {
                    $correct++;
                }
            } else {
                // Torneo nazionale: il tipo DEVE essere crc_referees o zone_observers (mai null)
                if ($currentType === null) {
                    // Non sappiamo se doveva essere crc_referees o zone_observers
                    // Per sicurezza lo impostiamo a crc_referees (il tipo primario)
                    $fixes[] = [
                        'id'          => $notif->id,
                        'tournament'  => $tournament->name,
                        'old_type'    => null,
                        'new_type'    => 'crc_referees',
                        'reason'      => 'torneo nazionale (is_national=true) aveva tipo null',
                    ];
                } else {
                    $correct++;
                }
            }
        }

        if (empty($fixes)) {
            $this->info('  ✅  Nessuna correzione necessaria — tutti i record sono coerenti.');
            $this->newLine();
            $this->line("   Record corretti: {$correct} / {$notifications->count()}");
            return self::SUCCESS;
        }

        // Mostra le correzioni
        $this->info("   Record da correggere: " . count($fixes));
        $this->info("   Record già corretti:  {$correct}");
        $this->newLine();

        $this->table(
            ['ID', 'Torneo', 'Tipo attuale', 'Tipo corretto', 'Motivo'],
            array_map(fn ($f) => [
                $f['id'],
                mb_strimwidth($f['tournament'], 0, 40, '…'),
                $f['old_type'] ?? '(null/zonale)',
                $f['new_type'] ?? '(null/zonale)',
                $f['reason'],
            ], $fixes)
        );

        if (! $dryRun) {
            $this->newLine();
            $this->info('   Applicazione correzioni...');

            $updated = 0;
            foreach ($fixes as $fix) {
                TournamentNotification::where('id', $fix['id'])
                    ->update(['notification_type' => $fix['new_type']]);
                $updated++;
            }

            $this->info("   ✅  {$updated} record aggiornati.");
        } else {
            $this->newLine();
            $this->warn('   DRY-RUN completato — nessuna modifica applicata.');
            $this->info("   Per applicare le correzioni:");
            $this->info("     php artisan federgolf:fix-notification-types");
        }

        return self::SUCCESS;
    }
}
