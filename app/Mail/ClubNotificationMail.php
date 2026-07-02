<?php

namespace App\Mail;

use App\Enums\AssignmentRole;
use App\Helpers\ZoneHelper;
use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;


    public $tournament;

    public $attachmentPaths;

    public $sortedAssignments;

    public $content;

    /** Oggetto personalizzato dal form (null = default "Arbitri Assegnati - ...") */
    public $subjectLine;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, ?string $content = null, array $attachmentPaths = [], ?string $subjectLine = null)
    {
        $this->tournament = $tournament;
        $this->content = $content;
        $this->attachmentPaths = $attachmentPaths;
        $this->subjectLine = $subjectLine;

        // ORDINA GLI ARBITRI PER GERARCHIA (Direttore → Arbitro → Osservatore)
        $this->sortedAssignments = AssignmentRole::sortCollection($tournament->assignments);

        // FIX A4: dispatch solo dopo il commit della transazione DB attiva
        // (evita invii orfani in caso di rollback). NB: $afterCommit è
        // proprietà del trait Queueable — non ridichiararla nella classe.
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     *
     * FIX (2026-07): il mittente non è più quello generico di config
     * mail.from — il display name e il Reply-To identificano la sezione
     * competente: SZR di zona per i tornei zonali, CRC per i nazionali.
     * L'ADDRESS del from resta quello autenticato (mail.from.address)
     * per non rompere SPF/DKIM/DMARC con lo smarthost.
     */
    public function envelope(): Envelope
    {
        [$senderName, $replyToEmail] = $this->resolveSender();

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $senderName
            ),
            replyTo: $replyToEmail
                ? [new \Illuminate\Mail\Mailables\Address($replyToEmail, $senderName)]
                : [],
            subject: $this->subjectLine ?: "Arbitri Assegnati - {$this->tournament->name}",
        );
    }

    /**
     * Determina nome mittente e reply-to in base alla competenza del torneo.
     *
     * @return array{0: string, 1: string|null} [nome, email reply-to]
     */
    private function resolveSender(): array
    {
        // Torneo nazionale → mittente CRC
        if (ZoneHelper::isTournamentNational($this->tournament)) {
            $crcEmail = config('golf.emails.crc');

            return [
                'CRC - Comitato Regole e Campionati',
                filter_var($crcEmail, FILTER_VALIDATE_EMAIL) ? $crcEmail : null,
            ];
        }

        // Torneo zonale → mittente sezione zonale (SZR)
        $zoneId = $this->tournament->club->zone_id ?? $this->tournament->zone_id;
        $zone = $this->tournament->zone ?? $this->tournament->club?->zone;

        $code = ZoneHelper::getFolderCode($zoneId); // es. SZR6
        $senderName = $zone && $zone->name
            ? "{$code} - {$zone->name}"
            : "{$code} - Sezione Zonale Regole";

        // Email di zona dal DB se valida, altrimenti pattern szrN@federgolf.it.
        // (Il campo zones.email in alcuni record contiene il NOME della
        // sezione, non un indirizzo — v. warning "indirizzo email malformato".)
        $zoneEmail = $zone?->email;
        if (! $zoneEmail || ! filter_var($zoneEmail, FILTER_VALIDATE_EMAIL)) {
            $zoneEmail = $zoneId ? ZoneHelper::getEmailPattern($zoneId) : null;
        }

        return [$senderName, $zoneEmail];
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Prepara i dati per la view
        $referees = $this->tournament->assignments->map(function ($assignment) {
            return [
                'name' => $assignment->user->name,
                'role' => $assignment->role,
                'email' => $assignment->user->email,
            ];
        })->toArray();

        return new Content(
            view: 'emails.tournament_assignment_generic',
            with: [
                // FIX C2 (audit 2026-07): la view legge $message_content, ma
                // nessuno lo passava — il messaggio scritto dall'admin nel form
                // (metadata['message']) veniva silenziosamente scartato e
                // partiva sempre il testo di default.
                'message_content' => $this->content,
                'recipient_name' => $this->tournament->club->name,
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range,
                'club_name' => $this->tournament->club->name,
                'referees' => $referees,
                'zone_email' => ZoneHelper::getEmailPattern($this->tournament->zone_id),
                'club_email' => $this->tournament->club->email,
                'attachments_info' => count($this->attachmentPaths) > 0 ?
                    ['Facsimile convocazione in formato Word'] : null,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        if (empty($this->attachmentPaths)) {
            return $mailAttachments;
        }

        foreach ($this->attachmentPaths as $attachment) {
            if (! is_array($attachment) || ! isset($attachment['path'])) {
                continue;
            }

            $path = $attachment['path'];
            $name = $attachment['name'] ?? basename($path);

            if (file_exists($path)) {
                $mailAttachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path)
                    ->as($name);
            } else {
                \Illuminate\Support\Facades\Log::warning('Attachment file not found', [
                    'path' => $path,
                    'name' => $name,
                ]);
            }
        }

        return $mailAttachments;
    }

}
