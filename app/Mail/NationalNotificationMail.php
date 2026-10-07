<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Config;

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


    /**
     * @param  string|null  $senderName  nome del mittente; null = CRC
     * @param  string|null  $replyToAddress  indirizzo per le risposte; null = CRC
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?string $senderName = null,
        public ?string $replyToAddress = null
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
        // La comunicazione degli osservatori parte dalla zona: nome e risposte
        // della SZR (2026-10-07); quella degli arbitri dal CRC
        $crcEmail = $this->replyToAddress ?? Config::string('golf.emails.crc');
        $senderName = $this->senderName ?? 'CRC - Comitato Regole e Campionati';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                Config::string('mail.from.address'),
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
