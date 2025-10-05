<?php

namespace App\Services;

use App\Services\DocumentGenerationService;

/**
 * Notification Service - Gestione notifiche tornei
 */
class NotificationService
{
    protected $documentService;
    protected $fileStorage;
    
    public function __construct(
        DocumentGenerationService $documentService,
        FileStorageService $fileStorage
    ) {
        $this->documentService = $documentService;
        $this->fileStorage = $fileStorage;
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
                    'institutional' => []
                ]
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
        $clauses = $notification->selectedClauses ?? [];

        try {
            // Genera convocazione
            $convocationData = $this->documentService->generateConvocationForTournament(
                $tournament, $clauses
            );

            // Genera lettera circolo
            $clubLetterData = $this->documentService->generateClubDocument(
                $tournament, $clauses
            );

            // Salva riferimenti documenti
            $notification->update([
                'documents' => [
                    'convocation' => basename($convocationData['path']),
                    'club_letter' => basename($clubLetterData['path'])
                ]
            ]);

            Log::info('Documents generated for notification', [
                'notification_id' => $notification->id,
                'tournament_id' => $tournament->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating documents', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function send(TournamentNotification $notification)
    {
        if ($notification->status === 'sent') {
            throw new \Exception('Notification already sent');
        }

        $tournament = $notification->tournament;
        $successCount = 0;
        $errorCount = 0;

        try {
            // 1. Invia al circolo
            if ($notification->recipients['club']) {
                $this->sendToClub($notification);
                $successCount++;
            }

            // 2. Invia agli arbitri
            foreach ($notification->recipients['referees'] as $refereeId) {
                try {
                    $this->sendToReferee($notification, $refereeId);
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Error sending to referee', [
                        'referee_id' => $refereeId,
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }

            // 3. Invia istituzionali
            foreach ($notification->recipients['institutional'] as $emailId) {
                try {
                    $this->sendToInstitutional($notification, $emailId);
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Error sending to institutional', [
                        'email_id' => $emailId,
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }

            // Aggiorna stato
            $status = $errorCount > 0 ? 'partial' : 'sent';
            
            $notification->update([
                'status' => $status,
                'sent_at' => now(),
                'metadata' => array_merge($notification->metadata ?? [], [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'last_error' => null
                ])
            ]);

            // Log success
            Log::info('Notification sent', [
                'notification_id' => $notification->id,
                'success' => $successCount,
                'errors' => $errorCount
            ]);

        } catch (\Exception $e) {
            // Log error
            Log::error('Error sending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            // Update status
            $notification->update([
                'status' => 'failed',
                'metadata' => array_merge($notification->metadata ?? [], [
                    'last_error' => $e->getMessage(),
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ])
            ]);

            throw $e;
        }
    }

    private function sendToClub(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;
        $club = $tournament->club;

        if (!$club->email) {
            throw new \Exception("Club email not found");
        }

        Mail::to($club->email)
            ->send(new ClubNotificationMail(
                $tournament,
                $notification->content,
                $this->getClubAttachments($notification)
            ));
    }

    private function sendToReferee(TournamentNotification $notification, $refereeId)
    {
        $tournament = $notification->tournament;
        $assignment = $tournament->assignments()
            ->where('user_id', $refereeId)
            ->first();

        if (!$assignment) {
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
        if (!$institutionalEmail) {
            throw new \Exception("Institutional email {$emailId} not found");
        }

        Mail::to($institutionalEmail->email)
            ->send(new InstitutionalNotificationMail(
                $notification->tournament,
                $notification->content
            ));
    }

    private function getClubAttachments(TournamentNotification $notification): array
    {
        if (empty($notification->documents)) {
            return [];
        }

        $attachments = [];
        $zone = $this->fileStorage->getZoneFolder($notification->tournament);
        $basePath = storage_path("app/public/convocazioni/{$zone}/generated/");

        // Aggiungi lettera circolo
        if (!empty($notification->documents['club_letter'])) {
            $attachments[] = [
                'path' => $basePath . $notification->documents['club_letter'],
                'name' => 'Lettera_Circolo.docx'
            ];
        }

        return $attachments;
    }

    private function getRefereeAttachments(TournamentNotification $notification): array
    {
        if (empty($notification->documents)) {
            return [];
        }

        $attachments = [];
        $zone = $this->fileStorage->getZoneFolder($notification->tournament);
        $basePath = storage_path("app/public/convocazioni/{$zone}/generated/");

        // Aggiungi convocazione
        if (!empty($notification->documents['convocation'])) {
            $attachments[] = [
                'path' => $basePath . $notification->documents['convocation'],
                'name' => 'Convocazione.docx'
            ];
        }

        return $attachments;
    }
