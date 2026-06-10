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
     * Invia una notifica con gestione transazionale.
     *
     * NOTA (FIX A3/A4): i Mailable implementano ShouldQueue con $afterCommit = true.
     * Dentro questa transazione le email vengono solo ACCODATE: i job partono
     * esclusivamente dopo il commit; in caso di rollback vengono scartati.
     * Nessuna email viene quindi inviata per notifiche il cui stato DB è stato
     * annullato. Richiede un queue worker attivo (vedi routes/console.php).
     */
    public function sendWithTransaction(TournamentNotification $notification): void
    {
        DB::beginTransaction();

        try {
            Log::info('Sending notification', [
                'notification_id' => $notification->id,
                'metadata' => $notification->metadata,
            ]);

            // Invia tramite il servizio notifiche
            $this->notificationService->send($notification);

            DB::commit();

            Log::info('Notification sent successfully', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

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
