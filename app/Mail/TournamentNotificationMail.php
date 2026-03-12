<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email di notifica per un torneo.
 *
 * Usa l'API moderna Laravel (Envelope/Content) invece del vecchio build().
 * Permette template HTML, testabilità con Mail::fake() e queueing futuro.
 *
 * Nota Aruba (deploy senza SSH):
 *   Usare ->send() sincrono (QUEUE_CONNECTION=sync nel .env).
 *   La classe è comunque vantaggiosa per la testabilità.
 */
class TournamentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<array{path: string, name: string}>  $fileAttachments
     */
    public function __construct(
        public readonly Tournament $tournament,
        public readonly TournamentNotification $notification,
        public readonly string $recipientType,
        private readonly array $fileAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        $content = $this->notification->content ?? [];

        return new Envelope(
            subject: $content['subject'] ?? 'Notifica Torneo — ' . $this->tournament->name,
        );
    }

    public function content(): Content
    {
        $content = $this->notification->content ?? [];

        return new Content(
            view: 'emails.tournament-notifications.layout',
            with: [
                'tournament'    => $this->tournament,
                'message'       => $content['message'] ?? '',
                'recipientType' => $this->recipientType,
            ],
        );
    }

    /**
     * @return Attachment[]
     */
    public function attachments(): array
    {
        return array_filter(
            array_map(
                fn (array $file) => file_exists($file['path'])
                    ? Attachment::fromPath($file['path'])->as($file['name'])
                    : null,
                $this->fileAttachments
            )
        );
    }

    /**
     * Costruisce la mail per un reinvio usando i metadata salvati.
     *
     * @param  array{subject?: string, message?: string}  $metadata
     */
    public static function fromMetadata(
        TournamentNotification $notification,
        array $metadata,
        string $recipientType = 'generic'
    ): self {
        $tournament = $notification->tournament;

        // Inietta i dati dal metadata nel campo content per riusare il template
        $notification->content = [
            'subject' => $metadata['subject'] ?? 'Designazione Arbitri — ' . $tournament->name,
            'message' => $metadata['message'] ?? '',
        ];

        return new self(
            tournament:       $tournament,
            notification:     $notification,
            recipientType:    $recipientType,
        );
    }
}
