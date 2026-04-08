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
    /**
     * Prepara o recupera una notifica per un torneo.
     *
     * NOTA: crea il record con notification_type basato su tournamentType.is_national:
     *   - zonale  → notification_type = null
     *   - nazionale → notification_type = 'crc_referees' (draft iniziale CRC)
     *
     * Per tornei nazionali, sendNationalNotification() gestirà poi la distinzione
     * CRC/SZR e la pulizia di eventuali bozze.
     */
    public function prepareNotification(Tournament $tournament): TournamentNotification
    {
        $isNational = $tournament->tournamentType?->is_national ?? false;
        $notificationType = $isNational ? 'crc_referees' : null;

        $total = $tournament->assignments->count() + 1;

        return TournamentNotification::firstOrCreate(
            [
                'tournament_id'     => $tournament->id,
                'notification_type' => $notificationType,
            ],
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
    public function loadFormData(Tournament $tournament, ?TournamentNotification $notification = null): array
    {
        // Una sola query: la collection viene riusata per costruire sia la lista flat che quella raggruppata
        $allEmails = InstitutionalEmail::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // IDs istituzionali già salvati nella notifica precedente (per pre-selezionare le checkbox)
        $savedInstitutionalIds = [];
        if ($notification) {
            $metadata = $notification->metadata ?? [];
            $savedInstitutionalIds = $metadata['recipients']['institutional'] ?? [];
        }

        return [
            'institutionalEmails' => $allEmails,
            'groupedEmails' => $allEmails->groupBy('category'),
            'availableClauses' => NotificationClause::active()
                ->ordered()
                ->get()
                ->groupBy('applies_to')
                ->toArray(),
            'assignedReferees' => $tournament->referees()->get(),
            // Array di ID InstitutionalEmail già selezionati nella notifica corrente.
            // Usato dalla view per pre-spuntare le checkbox. Vuoto = prima volta (usa is_default).
            'savedInstitutionalIds' => $savedInstitutionalIds,
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
