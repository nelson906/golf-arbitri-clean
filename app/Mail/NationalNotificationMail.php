<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica nazionale (CRC/SZR) — sostituisce le 2 chiamate Mail::raw()
 * presenti in NotificationController::sendNationalNotification().
 *
 * Il body è testo libero scritto dall'admin nazionale: viene escapato
 * (e()) e formattato con nl2br nella view per neutralizzare HTML injection.
 */
class NationalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;


    public function __construct(
        public string $subjectLine,
        public string $body
    ) {
        // FIX A4: dispatch solo dopo il commit della transazione DB attiva
        // (evita invii orfani in caso di rollback). NB: $afterCommit è
        // proprietà del trait Queueable — non ridichiararla nella classe.
        $this->afterCommit();
    }

    /**
     * FIX (2026-07): mittente identificato come CRC (display name + Reply-To)
     * invece del from generico di config. L'ADDRESS resta mail.from.address
     * per non rompere SPF/DKIM/DMARC con lo smarthost.
     */
    public function envelope(): Envelope
    {
        $crcEmail = config('golf.emails.crc');
        $senderName = 'CRC - Comitato Regole e Campionati';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $senderName
            ),
            replyTo: filter_var($crcEmail, FILTER_VALIDATE_EMAIL)
                ? [new \Illuminate\Mail\Mailables\Address($crcEmail, $senderName)]
                : [],
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
