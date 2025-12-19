<?php

namespace App\Services;

use App\Helpers\ZoneHelper;
use App\Mail\ClubNotificationMail;
use App\Mail\InstitutionalNotificationMail;
use App\Mail\RefereeAssignmentMail;
use App\Models\InstitutionalEmail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notification Service - Gestione notifiche tornei
 */
class NotificationService
{
    protected $documentService;

    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function prepareNotification(Tournament $tournament): TournamentNotification
    {
        // Crea nuova notifica o recupera esistente
        $notification = TournamentNotification::firstOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'status' => 'pending',
                'recipients' => [
                    'club' => true,
                    'referees' => $tournament->assignments->pluck('user_id')->toArray(),
                    'institutional' => [],
                ],
            ]
        );

        // Genera documenti se non esistono
        if (empty($notification->documents)) {
            $this->generateDocuments($notification);
        }

        return $notification;
    }

    public function generateDocuments(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;

        try {
            // Genera convocazione
            $convocationData = $this->documentService->generateConvocationForTournament(
                $tournament,
                $notification
            );

            // Genera lettera circolo
            $clubLetterData = $this->documentService->generateClubDocument(
                $tournament,
                $notification
            );

            // Salva riferimenti documenti
            $notification->update([
                'documents' => [
                    'convocation' => basename($convocationData['path']),
                    'club_letter' => basename($clubLetterData['path']),
                ],
            ]);

            Log::info('Documents generated for notification', [
                'notification_id' => $notification->id,
                'tournament_id' => $tournament->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating documents', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function send(TournamentNotification $notification, bool $force = false)
    {
        Log::info('Starting notification send', [
            'notification_id' => $notification->id,
            'force' => $force,
            'status' => $notification->status,
            'metadata' => $notification->metadata,
            'recipients' => $notification->recipients,
        ]);

        // Controllo metadata
        if (empty($notification->metadata)) {
            throw new \Exception('Missing notification metadata');
        }

        Log::info('Proceeding with send', ['force' => $force, 'status' => $notification->status]);

        // Normalize recipients from metadata and enforce alignment with current assignments
        $metadata = is_string($notification->metadata)
            ? (json_decode($notification->metadata, true) ?? [])
            : ($notification->metadata ?? []);

        $recipients = $notification->recipients ?: ($metadata['recipients'] ?? [
            'club' => false,
            'referees' => [],
            'institutional' => [],
        ]);

        // Get current assignments
        $tournament = $notification->tournament;
        $currentRefereeIds = $tournament->assignments()->pluck('user_id')->toArray();

        // RESEND: use current assignments, ignoring stored recipients
        if ($force === true) {
            Log::info('Resend: using current assignments', [
                'current_assignments' => $currentRefereeIds,
            ]);
            $selectedRefereeIds = $currentRefereeIds;
        }
        // SEND: use specified recipients, but validate they exist in assignments
        else {
            Log::info('Send: using specified recipients', [
                'recipients' => $recipients,
            ]);
            $selectedRefereeIds = isset($recipients['referees']) && is_array($recipients['referees'])
                ? $recipients['referees']
                : [];
        }

        // Default to sending to club unless explicitly disabled
        if (! array_key_exists('club', $recipients)) {
            $recipients['club'] = true;
        }

        // Enforce that only currently assigned referees are included
        $finalRefereeIds = array_values(array_intersect($selectedRefereeIds, $currentRefereeIds));

        // Persist back normalized recipients for traceability
        $notification->recipients = [
            'club' => (bool) ($recipients['club'] ?? false),
            'referees' => $finalRefereeIds,
            'institutional' => is_array($recipients['institutional'] ?? null) ? $recipients['institutional'] : [],
        ];

        Log::info('Normalized recipients for sending', [
            'recipients' => $notification->recipients,
            'current_assignments' => $currentRefereeIds,
        ]);

        $successCount = 0;
        $errorCount = 0;

        try {
            // 1. Invia al circolo
            if (isset($notification->recipients['club']) && $notification->recipients['club']) {
                $this->sendToClub($notification);
                $successCount++;
            }

            // 2. Invia agli arbitri
            Log::info('Processing referee notifications', [
                'has_referees' => isset($notification->recipients['referees']),
                'is_array' => isset($notification->recipients['referees']) ? is_array($notification->recipients['referees']) : null,
                'referees' => $notification->recipients['referees'] ?? null,
                'raw_recipients' => $notification->recipients,
            ]);

            if (isset($notification->recipients['referees']) && is_array($notification->recipients['referees'])) {
                Log::info('Sending to referees', ['referee_ids' => $notification->recipients['referees']]);
                foreach ($notification->recipients['referees'] as $refereeId) {
                    try {
                        $this->sendToReferee($notification, $refereeId);
                        $successCount++;
                        Log::info('Successfully sent to referee', ['referee_id' => $refereeId]);
                    } catch (\Exception $e) {
                        Log::error('Error sending to referee', [
                            'referee_id' => $refereeId,
                            'error' => $e->getMessage(),
                        ]);
                        $errorCount++;
                    }
                }
            } else {
                Log::warning('No referees to notify in recipients array');
            }

            // 3. Invia istituzionali
            if (isset($notification->recipients['institutional']) && is_array($notification->recipients['institutional'])) {
                foreach ($notification->recipients['institutional'] as $emailId) {
                    try {
                        $this->sendToInstitutional($notification, $emailId);
                        $successCount++;
                    } catch (\Exception $e) {
                        Log::error('Error sending to institutional', [
                            'email_id' => $emailId,
                            'error' => $e->getMessage(),
                        ]);
                        $errorCount++;
                    }
                }
            }

            // Aggiorna stato
            $status = $errorCount > 0 ? 'partial' : 'sent';

            $metadata = $notification->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }

            $notification->update([
                'status' => $status,
                'sent_at' => now(),
                'metadata' => array_merge($metadata, [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'last_error' => null,
                ]),
            ]);

            // Log success
            Log::info('Notification sent', [
                'notification_id' => $notification->id,
                'success' => $successCount,
                'errors' => $errorCount,
            ]);

        } catch (\Exception $e) {
            // Log error
            Log::error('Error sending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            // Update status
            $notification->update([
                'status' => 'failed',
                'metadata' => array_merge($notification->metadata ?? [], [
                    'last_error' => $e->getMessage(),
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                ]),
            ]);

            throw $e;
        }
    }

    private function sendToClub(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;
        $club = $tournament->club;

        if (! $club->email) {
            throw new \Exception('Club email not found');
        }

        // Get both club letter and convocation
        $attachments = array_merge(
            $this->getClubAttachments($notification),
            $this->getRefereeAttachments($notification)
        );

        Log::info('Sending club notification', [
            'notification_id' => $notification->id,
            'documents' => $notification->documents,
            'attachments' => $attachments,
        ]);

        // Parse metadata for message and flags
        $metadata = $notification->metadata ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }
        $content = $metadata['message'] ?? null;

        Log::info('Sending club notification', [
            'club_email' => $club->email,
            'attachments' => $attachments,
            'has_documents' => ! empty($notification->documents),
            'attach_enabled' => ! empty($metadata['attach_convocation']),
        ]);

        Mail::to($club->email)
            ->send(new ClubNotificationMail(
                $tournament,
                $content,
                $attachments
            ));
    }

    private function sendToReferee(TournamentNotification $notification, $refereeId)
    {
        $tournament = $notification->tournament;
        $assignment = $tournament->assignments()
            ->where('user_id', $refereeId)
            ->first();

        if (! $assignment) {
            throw new \Exception("Assignment not found for referee {$refereeId}");
        }

        Mail::to($assignment->user->email)
            ->send(new RefereeAssignmentMail(
                $assignment,
                $tournament,
                $this->getRefereeAttachments($notification)
            ));
    }

    private function sendToInstitutional(TournamentNotification $notification, $emailId)
    {
        $institutionalEmail = InstitutionalEmail::find($emailId);
        if (! $institutionalEmail) {
            throw new \Exception("Institutional email {$emailId} not found");
        }

        // Parse metadata for institutional email
        $metadata = $notification->metadata ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }
        $notificationType = $metadata['notification_type'] ?? 'Assegnazioni';

        Mail::to($institutionalEmail->email)
            ->send(new InstitutionalNotificationMail(
                $notification->tournament,
                $notificationType
            ));
    }

    private function getClubAttachments(TournamentNotification $notification): array
    {
        if (empty($notification->documents) || empty($notification->documents['club_letter'])) {
            return [];
        }

        $attachments = [];
        $zone = ZoneHelper::getFolderCodeForTournament($notification->tournament);
        $basePath = storage_path("app/public/convocazioni/{$zone}/generated/");

        // Aggiungi lettera circolo
        if (! empty($notification->documents['club_letter'])) {
            $fullPath = $basePath.$notification->documents['club_letter'];
            if (file_exists($fullPath)) {
                $attachments[] = [
                    'path' => $fullPath,
                    'name' => 'Lettera_Circolo.docx',
                ];
            }
        }

        return $attachments;
    }

    private function getRefereeAttachments(TournamentNotification $notification): array
    {
        if (empty($notification->documents) || empty($notification->documents['convocation'])) {
            return [];
        }

        $attachments = [];
        $zone = ZoneHelper::getFolderCodeForTournament($notification->tournament);
        $basePath = storage_path("app/public/convocazioni/{$zone}/generated/");

        // Aggiungi convocazione
        if (! empty($notification->documents['convocation'])) {
            $fullPath = $basePath.$notification->documents['convocation'];
            if (file_exists($fullPath)) {
                $attachments[] = [
                    'path' => $fullPath,
                    'name' => 'Convocazione.docx',
                ];
            }
        }

        return $attachments;
    }
}
