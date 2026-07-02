<?php

namespace App\Services;

use App\Models\TournamentNotification;
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
     * Salva una notifica come bozza con tutti i dati
     */
    public function saveAsDraft(
        TournamentNotification $notification,
        array $metadata,
        array $clauses = []
    ): void {
        DB::beginTransaction();

        try {
            // Aggiorna metadati
            $notification->update(['metadata' => $metadata]);

            // Salva clausole se presenti
            if (! empty($clauses)) {
                $this->saveClausesInTransaction($notification, $clauses);
            }

            // Rigenera documenti con le nuove clausole
            try {
                $documents = $this->documentService->regenerateAllDocuments($notification);
                $notification->update(['documents' => $documents]);
            } catch (\Exception $e) {
                Log::warning('Could not regenerate documents for draft', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
                // Non bloccare il salvataggio della bozza
            }

            // Marca come preparata
            $notification->update(['is_prepared' => true]);

            DB::commit();

            Log::info('Notification saved as draft', [
                'notification_id' => $notification->id,
            ]);
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
     * Salva le clausole all'interno di una transazione esistente
     */
    private function saveClausesInTransaction(
        TournamentNotification $notification,
        array $clauses
    ): void {
        // Rimuovi le selezioni precedenti
        \App\Models\NotificationClauseSelection::where('tournament_notification_id', $notification->id)->delete();

        foreach ($clauses as $placeholder => $clauseId) {
            if (! empty($clauseId)) {
                try {
                    \App\Models\NotificationClauseSelection::create([
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
        }
    }
}
