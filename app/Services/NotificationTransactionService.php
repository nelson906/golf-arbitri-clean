<?php

namespace App\Services;

use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\TournamentNotification;
use App\Support\Untrusted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per la gestione delle transazioni di invio notifiche
 */
class NotificationTransactionService
{
    public function __construct(
        private NotificationService $notificationService,
        private NotificationDocumentService $documentService
    ) {}

    /**
     * Invia una notifica.
     *
     * FIX C1 (audit 2026-07): NIENTE transazione attorno all'invio.
     *
     * Prima qui c'era DB::beginTransaction()/commit(). Con QUEUE_CONNECTION=sync
     * e Mailable ShouldQueue+afterCommit, SyncQueue::push() DIFFERISCE il job
     * SMTP a dentro DB::commit(): il try/catch attorno a Mail::send() in
     * NotificationService non poteva mai catturare gli errori SMTP, e lo stato
     * 'sent' + success_count veniva committato PRIMA dell'invio reale. Un
     * fallimento SMTP lasciava il DB a 'sent' mostrando però errore all'admin.
     *
     * Senza transazione attiva, afterCommit esegue il job SUBITO (inline, sync):
     * gli errori SMTP tornano catturabili e lo stato riflette l'esito reale.
     * L'update dello stato è una singola riga: non serve atomicità multi-statement.
     * Se un giorno si passa a un queue driver reale (redis/database + worker),
     * questo resta corretto: il job parte subito dopo il dispatch.
     */
    public function sendWithTransaction(TournamentNotification $notification): void
    {
        try {
            Log::info('Sending notification', [
                'notification_id' => $notification->id,
                'metadata' => $notification->metadata,
            ]);

            // Invia tramite il servizio notifiche (gestisce internamente
            // stato sent/partial/failed in base all'esito reale)
            $this->notificationService->send($notification);

            Log::info('Notification sent successfully', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Elimina una notifica con cleanup completo
     */
    public function deleteWithCleanup(TournamentNotification $notification): void
    {
        DB::beginTransaction();

        try {
            Log::info('Starting notification deletion', [
                'notification_id' => $notification->id,
                'tournament_id' => $notification->tournament_id,
                'documents' => $notification->documents,
            ]);

            // Elimina i documenti associati
            $this->documentService->deleteAllDocuments($notification);

            // Elimina la notifica
            $notification->delete();

            DB::commit();

            Log::info('Notification deleted successfully', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // NOTA (audit 2026-06): prepareAndSend() rimosso — mai chiamato
    // (il flusso reale è saveAsDraft() + sendWithTransaction()).

    /**
     * Salva una notifica come bozza con tutti i dati.
     *
     * Le clausole che non esistono piu' o sono disattivate vengono scartate
     * senza bloccare il salvataggio del resto (decisione 2026-10-15): il
     * chiamante riceve i segnaposti scartati per avvisare l'admin.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<array-key, mixed>  $clauses  segnaposto => id clausola
     * @return list<string> segnaposti delle clausole scartate
     */
    public function saveAsDraft(
        TournamentNotification $notification,
        array $metadata,
        array $clauses = []
    ): array {
        $discarded = [];

        DB::beginTransaction();

        try {
            // Aggiorna metadati: i campi del form sostituiscono i precedenti,
            // l'esito dell'ultimo invio (success_count, last_error,
            // last_attempt_at) resta (2026-10-07)
            $previous = is_array($notification->metadata) ? $notification->metadata : [];
            $notification->update(['metadata' => array_merge($previous, $metadata)]);

            // Salva clausole se presenti
            if (! empty($clauses)) {
                $discarded = $this->saveClausesInTransaction($notification, $clauses);
            }

            // Gli allegati NON si rigenerano: si usa sempre la versione salvata
            // (creata una volta con "Crea allegati" o ricaricata corretta).
            // Decisione 2026-10-07.

            // Marca come preparata
            $notification->update(['is_prepared' => true]);

            DB::commit();

            Log::info('Notification saved as draft', [
                'notification_id' => $notification->id,
                'discarded_clauses' => $discarded,
            ]);

            return $discarded;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error saving notification as draft', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Salva le clausole all'interno di una transazione esistente.
     *
     * Sostituisce sempre le selezioni precedenti: un segnaposto lasciato vuoto
     * toglie la clausola che c'era. Una clausola inesistente o disattivata
     * viene scartata (non salvata) e il suo segnaposto restituito.
     *
     * @param  array<array-key, mixed>  $clauses  segnaposto => id clausola
     * @return list<string> segnaposti delle clausole scartate
     */
    private function saveClausesInTransaction(
        TournamentNotification $notification,
        array $clauses
    ): array {
        // Rimuovi le selezioni precedenti
        NotificationClauseSelection::where('tournament_notification_id', $notification->id)->delete();

        $requested = [];
        foreach ($clauses as $placeholder => $clauseId) {
            if (empty($clauseId)) {
                continue;
            }
            $requested[(string) $placeholder] = Untrusted::int($clauseId);
        }

        if ($requested === []) {
            return [];
        }

        $validIds = Untrusted::intList(
            NotificationClause::active()->whereIn('id', array_values($requested))->pluck('id')->all()
        );

        $discarded = [];

        foreach ($requested as $placeholder => $clauseId) {
            // Le chiavi numeriche diventano int negli array PHP: si riportano a stringa
            $placeholder = (string) $placeholder;

            if (! in_array($clauseId, $validIds, true)) {
                $discarded[] = $placeholder;

                Log::warning('Clause discarded: missing or inactive', [
                    'notification_id' => $notification->id,
                    'placeholder' => $placeholder,
                    'clause_id' => $clauseId,
                ]);

                continue;
            }

            try {
                NotificationClauseSelection::create([
                    'tournament_notification_id' => $notification->id,
                    'clause_id' => $clauseId,
                    'placeholder_code' => $placeholder,
                ]);
            } catch (\Exception $e) {
                Log::error('Error saving clause selection', [
                    'notification_id' => $notification->id,
                    'placeholder' => $placeholder,
                    'clause_id' => $clauseId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $discarded;
    }
}
