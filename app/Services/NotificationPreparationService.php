<?php

namespace App\Services;

use App\Models\InstitutionalEmail;
use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per la preparazione e validazione delle notifiche
 */
class NotificationPreparationService
{
    /**
     * Prepara o recupera una notifica per un torneo
     */
    public function prepareNotification(Tournament $tournament): TournamentNotification
    {
        $total = $tournament->assignments->count() + 1;

        return TournamentNotification::firstOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'status' => 'pending',
                'referee_list' => $tournament->assignments->pluck('user.name')->implode(', '),
                'details' => ['total_recipients' => $total],
                'sent_by' => auth()->id(),
            ]
        );
    }

    /**
     * Aggiorna i metadati della notifica (destinatari, messaggio, ecc.)
     */
    public function updateNotificationMetadata(
        TournamentNotification $notification,
        array $data
    ): TournamentNotification {
        $metadata = [
            'recipients' => [],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'attach_convocation' => $data['attach_convocation'] ?? true,
        ];

        // Gestisci arbitri
        if (isset($data['recipients'])) {
            $metadata['recipients']['referees'] = $data['recipients'];
        }

        // Gestisci club
        $metadata['recipients']['club'] = $data['send_to_club'] ?? true;

        // Gestisci email istituzionali
        if (isset($data['fixed_addresses'])) {
            $metadata['recipients']['institutional'] = $data['fixed_addresses'];
        }

        $notification->update(['metadata' => $metadata]);

        return $notification->fresh();
    }

    /**
     * Salva le clausole selezionate per una notifica
     */
    public function saveClauseSelections(
        TournamentNotification $notification,
        array $clauses
    ): int {
        DB::beginTransaction();

        try {
            // Rimuovi le selezioni precedenti
            NotificationClauseSelection::where('tournament_notification_id', $notification->id)->delete();

            $savedCount = 0;
            foreach ($clauses as $placeholder => $clauseId) {
                if (! empty($clauseId)) {
                    NotificationClauseSelection::create([
                        'tournament_notification_id' => $notification->id,
                        'clause_id' => $clauseId,
                        'placeholder_code' => $placeholder,
                    ]);
                    $savedCount++;
                }
            }

            DB::commit();

            Log::info('Clauses saved', [
                'notification_id' => $notification->id,
                'saved_count' => $savedCount,
            ]);

            return $savedCount;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving clauses', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Aggiorna la lista arbitri e il totale destinatari per una notifica
     */
    public function updateRecipientInfo(TournamentNotification $notification): void
    {
        $tournament = $notification->tournament;

        $refereeNames = $tournament->assignments
            ->map(fn ($assignment) => $assignment->user->name)
            ->implode(', ');

        $total = $tournament->assignments->count() + 1; // arbitri + circolo

        // Aggiorna solo se necessario
        $currentDetails = $notification->details ?? [];
        if (empty($notification->referee_list) || ($currentDetails['total_recipients'] ?? 0) != $total) {
            $notification->update([
                'referee_list' => $refereeNames,
                'details' => array_merge($currentDetails, ['total_recipients' => $total]),
            ]);
        }
    }

    /**
     * Valida che un torneo sia pronto per l'invio notifiche
     */
    public function validateTournamentForNotification(Tournament $tournament): array
    {
        $errors = [];

        if ($tournament->assignments->isEmpty()) {
            $errors[] = 'Il torneo non ha arbitri assegnati';
        }

        if (! $tournament->club) {
            $errors[] = 'Il torneo non ha un circolo associato';
        }

        if (! $tournament->start_date) {
            $errors[] = 'Il torneo non ha una data di inizio';
        }

        return $errors;
    }

    /**
     * Carica i dati necessari per il form di preparazione notifica
     */
    public function loadFormData(Tournament $tournament): array
    {
        return [
            'institutionalEmails' => InstitutionalEmail::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'groupedEmails' => InstitutionalEmail::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category'),
            'availableClauses' => NotificationClause::active()
                ->ordered()
                ->get()
                ->groupBy('applies_to')
                ->toArray(),
            'assignedReferees' => $tournament->referees()->get(),
        ];
    }

    /**
     * Prepara i dati per l'anteprima email
     */
    public function prepareEmailPreview(
        TournamentNotification $notification,
        Tournament $tournament
    ): array {
        $metadata = $notification->metadata;

        return [
            'subject' => $metadata['subject'] ?? '',
            'message' => $metadata['message'] ?? '',
            'recipients' => [
                'club' => ($metadata['recipients']['club'] ?? false) ? $tournament->club->email : null,
                'referees' => $tournament->assignments()
                    ->whereIn('user_id', $metadata['recipients']['referees'] ?? [])
                    ->with('user')
                    ->get()
                    ->map(fn ($a) => [
                        'name' => $a->user->name,
                        'email' => $a->user->email,
                        'role' => $a->role,
                    ]),
                'institutional' => InstitutionalEmail::whereIn(
                    'id',
                    $metadata['recipients']['institutional'] ?? []
                )->pluck('email'),
            ],
            'documents' => $notification->documents,
        ];
    }

    /**
     * Marca una notifica come preparata
     */
    public function markAsPrepared(TournamentNotification $notification): void
    {
        $notification->update(['is_prepared' => true]);
    }
}
