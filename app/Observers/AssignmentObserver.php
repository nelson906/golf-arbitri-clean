<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\TournamentNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer per il modello Assignment.
 *
 * Mantiene sincronizzati:
 *  1. referee_list e details.total_recipients in TournamentNotification
 *  2. total_tournaments e tournaments_current_year in User
 *
 * Registrazione in AppServiceProvider::boot():
 *   Assignment::observe(AssignmentObserver::class);
 */
class AssignmentObserver
{
    /**
     * Aggiorna referee_list e contatori dopo la creazione di una nuova assegnazione.
     */
    public function created(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
        $this->syncUserCounters($assignment->user_id);
    }

    /**
     * Aggiorna referee_list dopo la modifica di un'assegnazione (es. cambio ruolo).
     * I contatori non cambiano per un semplice update (la quantità rimane uguale).
     */
    public function updated(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
    }

    /**
     * Aggiorna referee_list e contatori dopo l'eliminazione di un'assegnazione.
     */
    public function deleted(Assignment $assignment): void
    {
        $this->syncNotificationRecipientInfo($assignment->tournament_id);
        $this->syncUserCounters($assignment->user_id);
    }

    /**
     * Ricalcola total_tournaments e tournaments_current_year per un arbitro.
     *
     * Usa updateQuietly() per evitare eventi ricorsivi e loop infiniti.
     */
    private function syncUserCounters(int $userId): void
    {
        try {
            $user = User::find($userId);
            if (! $user || $user->user_type !== 'referee') {
                return;
            }

            $total = Assignment::where('user_id', $userId)->count();

            $currentYear = Assignment::where('assignments.user_id', $userId)
                ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
                ->whereYear('tournaments.start_date', now()->year)
                ->count();

            $user->updateQuietly([
                'total_tournaments'        => $total,
                'tournaments_current_year' => $currentYear,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AssignmentObserver: impossibile aggiornare contatori user', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
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
