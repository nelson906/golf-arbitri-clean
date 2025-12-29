<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TournamentNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $tournament;

    public $notification;

    public $recipientType;

    public $attachments;

    public function __construct(
        Tournament $tournament,
        TournamentNotification $notification,
        string $recipientType,
        array $attachments = []
    ) {
        $this->tournament = $tournament;
        $this->notification = $notification;
        $this->recipientType = $recipientType;
        $this->attachments = $attachments;
    }

    public function build()
    {
        $content = $this->notification->content;

        $mail = $this->subject($content['subject'] ?? 'Notifica Torneo')
            ->view('emails.tournament-notifications.layout')
            ->with([
                'tournament' => $this->tournament,
                'message' => $content['message'] ?? '',
                'recipientType' => $this->recipientType,
            ]);

        // Aggiungi allegati
        foreach ($this->attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                $mail->attach($attachment['path'], [
                    'as' => $attachment['name'],
                ]);
            }
        }

        return $mail;
    }
}
