<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchAvailabilityNotification extends Mailable
{
    use SerializesModels;

    public function __construct(
        public $user,
        public $addedTournaments,
        public $removedTournaments
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Conferma aggiornamento disponibilità',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.referee-availability-confirmation',
            with: [
                'referee_name' => $this->user->name,
                'added_count' => $this->addedTournaments->count(),
                'removed_count' => $this->removedTournaments->count(),
                'added_tournaments' => $this->addedTournaments,
                'removed_tournaments' => $this->removedTournaments,
                'total_availabilities' => $this->user->availabilities()->count()
            ]
        );
    }
}
