<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\TournamentNotification;
use Illuminate\Support\Facades\Log;

/**
 * Observer per il modello Assignment.
 *
 * Mantiene sincronizzato il campo referee_list e details.total_recipients
 * in TournamentNotification ogni volta che le assegnazioni cambiano.
 *
 * Questo elimina la necessità di chiamare updateRecipientInfo() in loop
 * all'interno di NotificationController::index(), che causava N+1 UPDATE
 * queries ad ogni visualizzazione della pagina.
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

            $refereeNames = $assignments
                ->map(fn ($a) => $a->user?->name)
                ->filter()
                ->implode(', ');

            $total = $assignments->count() + 1; // arbitri + circolo

            foreach ($notifications as $notification) {
                $currentDetails = $notification->details ?? [];
                $needsUpdate = $notification->referee_list !== $refereeNames
                    || ($currentDetails['total_recipients'] ?? 0) !== $total;

                if ($needsUpdate) {
                    $notification->updateQuietly([
                        'referee_list' => $refereeNames,
                        'details' => array_merge($currentDetails, [
                            'total_recipients' => $total,
                        ]),
                    ]);
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
