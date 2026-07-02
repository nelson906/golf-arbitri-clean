<?php

namespace App\Services;

use App\Helpers\ZoneHelper;
use App\Mail\ClubNotificationMail;
use App\Models\TournamentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notification Service - Invio notifiche tornei ZONALI.
 *
 * MODELLO UNIFICATO A MAIL SINGOLA (refactor 2026-06, allineato al CRC nazionale):
 *
 *   UNA SOLA email per torneo:
 *     TO (competenza)  = CIRCOLO, con allegati convocazione + lettera (facsimile)
 *     CC (conoscenza)  = arbitri assegnati selezionati + istituzionali selezionati
 *                        + sezione di zona (se richiesto) + email aggiuntive
 *   Se il circolo non è selezionato o è senza email, il primo CC viene
 *   promosso a TO (stessa logica del flusso nazionale).
 *
 *   NB tecnico: in un'unica email gli allegati raggiungono anche i CC —
 *   è un limite del mezzo, non una scelta. La competenza resta il circolo.
 *
 * FIX D1: la fonte dei destinatari è SEMPRE metadata['recipients'] (l'intento
 *         del form salvato da saveAsDraft). La colonna `recipients` viene
 *         scritta solo come traccia di ciò che è stato effettivamente usato,
 *         e non è MAI più letta come input.
 * FIX D2: se metadata non contiene 'recipients' (es. record import FIG con
 *         metadata {source, command}), l'invio viene rifiutato con eccezione
 *         dedicata: il chiamante reindirizza al form di preparazione.
 *
 * FIX C1 (audit 2026-07): l'invio NON avviene più dentro una transazione DB
 * (vedi NotificationTransactionService::sendWithTransaction). Con QUEUE=sync
 * il Mailable (ShouldQueue+afterCommit) viene ora eseguito INLINE dentro
 * $mailer->send(): il try/catch qui sotto cattura davvero gli errori SMTP e
 * status/success_count/last_error riflettono l'esito reale dell'invio.
 *
 * RAZIONALIZZAZIONE 2026-06: rimossi la dipendenza DocumentGenerationService
 * (inutilizzata dopo il refactor — la generazione vive in
 * NotificationDocumentService), il parametro $force (nessun caller: il
 * reinvio passa sempre dal form) e i Mailable RefereeAssignmentMail /
 * InstitutionalNotificationMail (sostituiti dalla mail unica).
 */
class NotificationService
{
    public const ERR_MISSING_RECIPIENTS = 'Missing notification recipients';

    public function send(TournamentNotification $notification): void
    {
        $metadata = is_string($notification->metadata)
            ? (json_decode($notification->metadata, true) ?? [])
            : ($notification->metadata ?? []);

        Log::info('Starting notification send', [
            'notification_id' => $notification->id,
            'status' => $notification->status,
            'metadata_recipients' => $metadata['recipients'] ?? null,
        ]);

        // FIX D2: serve l'intento esplicito del form. Metadata "estranei"
        // (es. {source: "Import batch FIG"}) non bastano per inviare.
        if (empty($metadata['recipients']) || ! is_array($metadata['recipients'])) {
            throw new \Exception(self::ERR_MISSING_RECIPIENTS);
        }

        // FIX D1: i destinatari arrivano SOLO dal form (metadata), mai dalla
        // colonna `recipients` persistita da invii precedenti.
        $recipients = $metadata['recipients'];

        $tournament = $notification->tournament;
        $currentRefereeIds = $tournament->assignments()->pluck('user_id')->toArray();

        // Solo arbitri selezionati E ancora effettivamente assegnati al torneo
        $selectedRefereeIds = is_array($recipients['referees'] ?? null) ? $recipients['referees'] : [];
        $finalRefereeIds = array_values(array_intersect($selectedRefereeIds, $currentRefereeIds));

        $sendToClub = (bool) ($recipients['club'] ?? true);
        $sendToZone = (bool) ($recipients['zone'] ?? false);
        $institutionalIds = is_array($recipients['institutional'] ?? null) ? $recipients['institutional'] : [];
        $additional = is_array($recipients['additional'] ?? null) ? $recipients['additional'] : [];

        // Traccia di ciò che verrà usato (solo audit — mai più riletta come input)
        $notification->recipients = [
            'club' => $sendToClub,
            'referees' => $finalRefereeIds,
            'institutional' => $institutionalIds,
            'zone' => $sendToZone,
            'additional' => $additional,
        ];

        Log::info('Normalized recipients for sending', [
            'recipients' => $notification->recipients,
            'current_assignments' => $currentRefereeIds,
        ]);

        $subject = $metadata['subject'] ?? null;
        $content = $metadata['message'] ?? null;
        $attachConvocation = (bool) ($metadata['attach_convocation'] ?? true);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            // ═══════════════════════════════════════════════════════════
            // MAIL UNICA — TO circolo (competenza, con allegati),
            // CC arbitri + istituzionali + sezione di zona + email aggiuntive
            // ═══════════════════════════════════════════════════════════
            $builder = new NotificationRecipientBuilder;

            if ($sendToClub) {
                $builder->addClub($tournament); // TO (skippato se senza email)
            }

            $builder->addRefereesByIds($finalRefereeIds)
                ->addInstitutionalsByIds($institutionalIds);

            if ($sendToZone) {
                $builder->addZone($tournament); // CC sezione di zona
            }

            foreach ($additional as $extra) {
                if (! empty($extra['email'])) {
                    $builder->addCustomCc($extra['email'], $extra['name'] ?? null);
                }
            }

            $built = $builder->build();

            // Circolo richiesto ma irraggiungibile (senza email) → errore tracciato
            if ($sendToClub && empty($built['to'])) {
                Log::error('Error sending to club', [
                    'notification_id' => $notification->id,
                    'error' => 'Club email not found',
                ]);
                $errors[] = 'circolo: Club email not found';
                $errorCount++;
            }

            if (! $built['isEmpty']) {
                // FIX M3: allegati registrati ma assenti dal disco = errore
                // tracciato (prima: skip silenzioso, mail senza convocazione)
                $builtAttachments = $this->buildAttachments($notification, $attachConvocation);
                foreach ($builtAttachments['missing'] as $missingDoc) {
                    Log::error('Attachment registered but missing from disk', [
                        'notification_id' => $notification->id,
                        'document' => $missingDoc,
                    ]);
                    $errors[] = "allegato mancante: {$missingDoc}";
                    $errorCount++;
                }

                try {
                    // TO = circolo; in sua assenza il primo CC è promosso a TO
                    $all = array_merge($built['to'], $built['cc']);
                    $first = $all[0];
                    $rest = array_slice($all, 1);

                    $mailer = Mail::to($first['email']);
                    if (! empty($rest)) {
                        $mailer->cc($rest);
                    }
                    $mailer->send(new ClubNotificationMail(
                        $tournament,
                        $content,
                        $builtAttachments['attachments'],
                        $subject
                    ));

                    $successCount += count($all);
                } catch (\Exception $e) {
                    Log::error('Error sending zonal notification', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = 'invio: '.$e->getMessage();
                    $errorCount++;
                }
            } else {
                // FIX M5 (audit 2026-07): builder vuoto = NESSUNA mail partita.
                // Prima (con club non richiesto) terminava status 'sent' con
                // success_count 0 — lo storico mentiva.
                Log::error('No valid recipients for zonal notification', [
                    'notification_id' => $notification->id,
                ]);
                $errors[] = 'nessun destinatario valido';
                $errorCount++;
            }

            // Aggiorna stato in base all'esito REALE:
            //   tutto ok → sent; errori con almeno un invio → partial;
            //   errori senza alcun invio → failed (FIX M5)
            $status = $errorCount > 0
                ? ($successCount > 0 ? 'partial' : 'failed')
                : 'sent';

            $notification->update([
                'status' => $status,
                'sent_at' => now(),
                'metadata' => array_merge($metadata, [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'last_error' => empty($errors) ? null : implode(' | ', $errors),
                ]),
            ]);

            Log::info('Notification sent', [
                'notification_id' => $notification->id,
                'success' => $successCount,
                'errors' => $errorCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            $notification->update([
                'status' => 'failed',
                'metadata' => array_merge($metadata, [
                    'last_error' => $e->getMessage(),
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Allegati della mail unica: lettera circolo (facsimile) + convocazione.
     *
     * Il flag attach_convocation del form (prima salvato e mai letto) ora
     * è onorato: se false, la convocazione non viene allegata (la lettera
     * circolo viaggia comunque).
     *
     * FIX M2 (audit 2026-07): path dal disk privato config('golf.documents.disk')
     * — prima hardcoded su storage/app/public (file esposti via /storage).
     * FIX M3 (audit 2026-07): i documenti REGISTRATI ma assenti dal disco non
     * vengono più skippati in silenzio — tornano in 'missing' e il chiamante
     * li traccia come errore (status partial). Prima la mail partiva senza
     * convocazione con status 'sent' e admin ignaro.
     *
     * @return array{attachments: array<array{path: string, name: string}>, missing: string[]}
     */
    private function buildAttachments(TournamentNotification $notification, bool $attachConvocation = true): array
    {
        $documents = $notification->documents ?? [];
        if (empty($documents)) {
            return ['attachments' => [], 'missing' => []];
        }

        $zone = ZoneHelper::getFolderCodeForTournament($notification->tournament);
        $docsRoot = config('golf.documents.storage_path', 'convocazioni');
        $disk = \Illuminate\Support\Facades\Storage::disk(config('golf.documents.disk', 'docs'));
        $basePath = $disk->path("{$docsRoot}/{$zone}/generated/");

        $attachments = [];
        $missing = [];

        $candidates = [
            'club_letter' => 'Lettera_Circolo.docx',
            'convocation' => 'Convocazione.docx',
        ];

        foreach ($candidates as $key => $displayName) {
            if ($key === 'convocation' && ! $attachConvocation) {
                continue;
            }

            if (empty($documents[$key])) {
                continue;
            }

            $fullPath = $basePath.$documents[$key];
            if (file_exists($fullPath)) {
                $attachments[] = [
                    'path' => $fullPath,
                    'name' => $displayName,
                ];
            } else {
                $missing[] = $displayName;
            }
        }

        return ['attachments' => $attachments, 'missing' => $missing];
    }
}
