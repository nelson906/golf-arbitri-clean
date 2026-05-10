<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica nazionale (CRC/SZR) — sostituisce le 2 chiamate Mail::raw()
 * presenti in NotificationController::sendNationalNotification().
 *
 * Il body è testo libero scritto dall'admin nazionale: viene escapato
 * (e()) e formattato con nl2br nella view per neutralizzare HTML injection.
 */
class NationalNotificationMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.national-notification',
            with: [
                'body' => $this->body,
            ]
        );
    }
}
