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

    // NOTA (audit 2026-06): rimossi i metodi morti updateNotificationMetadata(),
    // updateRecipientInfo() (sostituito da AssignmentObserver),
    // validateTournamentForNotification() e markAsPrepared() — nessun caller.

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
            'preflight' => $this->buildPreflight($tournament),
        ];
    }

    /**
     * Pre-flight dei destinatari: stessa validazione dell'invio reale
     * (NotificationRecipientBuilder usa filter_var FILTER_VALIDATE_EMAIL e
     * SCARTA silenziosamente i malformati con un Log::warning).
     * Qui la rendiamo visibile all'admin PRIMA del send, così un destinatario
     * scartato è una decisione consapevole e non un fallimento silenzioso.
     *
     * @return array{entries: array<int, array{type: string, name: string, email: ?string, valid: bool}>, invalid: int}
     */
    public function buildPreflight(Tournament $tournament): array
    {
        $isValid = fn (?string $email): bool => $email !== null
            && $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $entries = [];

        $club = $tournament->club;
        $entries[] = [
            'type' => 'Circolo (TO)',
            'name' => $club->name ?? 'N/A',
            'email' => $club->email ?? null,
            'valid' => $isValid($club->email ?? null),
        ];

        $zone = $club->zone ?? null;
        $entries[] = [
            'type' => 'Sezione di zona (CC)',
            'name' => $zone->name ?? 'N/A',
            'email' => $zone->email ?? null,
            'valid' => $isValid($zone->email ?? null),
        ];

        foreach ($tournament->referees()->get() as $referee) {
            $entries[] = [
                'type' => 'Arbitro (CC)',
                'name' => $referee->name,
                'email' => $referee->email,
                'valid' => $isValid($referee->email),
            ];
        }

        return [
            'entries' => $entries,
            'invalid' => count(array_filter($entries, fn ($e) => ! $e['valid'])),
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
                'club' => ($metadata['recipients']['club'] ?? false) ? $tournament->club?->email : null,
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
}
