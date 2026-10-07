<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\TournamentNotification;
use Illuminate\Support\Facades\Log;

/**
 * Observer per il modello Assignment.
 *
 * Mantiene sincronizzati referee_list e details.total_recipients in
 * TournamentNotification. (I contatori users.total_tournaments e
 * tournaments_current_year non sono piu' aggiornati: nessuno li leggeva.)
 *
 * Registrazione in AppServiceProvider::boot():
 *   Assignment::observe(AssignmentObserver::class);
 */
class AssignmentObserver
{
    /**
     * Aggiorna referee_list dopo la creazione di una nuova assegnazione.
     */
    public function created(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
    }

    /**
     * Aggiorna referee_list dopo la modifica di un'assegnazione (es. cambio ruolo).
     */
    public function updated(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
    }

    /**
     * Aggiorna referee_list dopo l'eliminazione di un'assegnazione.
     */
    public function deleted(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
    }

    /**
     * Ricalcola e salva referee_list e total_recipients per tutte le notifiche
     * associate al torneo specificato.
     *
     * Usa updateQuietly() per evitare eventi ricorsivi.
     */
    private function syncNotificationRecipientInfo(int $tournamentId): void
    {
        try {
            $notifications = TournamentNotification::where('tournament_id', $tournamentId)->get();

            if ($notifications->isEmpty()) {
                return;
            }

            // Carica le assegnazioni del torneo una sola volta
            $assignments = \App\Models\Assignment::with('user')
                ->where('tournament_id', $tournamentId)
                ->get();

            foreach ($notifications as $notification) {
                // Zonale: tutti i designati; CRC: arbitri e Direttore; SZR: osservatori
                $refereeNames = TournamentNotification::refereeListFor($assignments, $notification->notification_type);
                $currentDetails = is_array($notification->details) ? $notification->details : [];
                $changes = [];

                if ($notification->referee_list !== $refereeNames) {
                    $changes['referee_list'] = $refereeNames;
                }

                // Destinatari previsti solo sulla zonale (designati + circolo):
                // sui nazionali li calcola l'invio
                if ($notification->notification_type === null) {
                    $total = $assignments->count() + 1;
                    if (($currentDetails['total_recipients'] ?? 0) !== $total) {
                        $changes['details'] = array_merge($currentDetails, ['total_recipients' => $total]);
                    }
                }

                if ($changes !== []) {
                    $notification->updateQuietly($changes);
                }
            }
        } catch (\Throwable $e) {
            // Non bloccare il salvataggio dell'assegnazione per un errore di sync
            Log::warning('AssignmentObserver: impossibile sincronizzare referee_list', [
                'tournament_id' => $tournamentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
