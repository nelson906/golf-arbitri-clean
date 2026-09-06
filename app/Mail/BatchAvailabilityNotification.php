<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchAvailabilityNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;


    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Tournament>  $addedTournaments
     * @param  \Illuminate\Support\Collection<int, \App\Models\Tournament>  $removedTournaments
     */
    public function __construct(
        public \App\Models\User $user,
        public \Illuminate\Support\Collection $addedTournaments,
        public \Illuminate\Support\Collection $removedTournaments
    ) {
        // FIX A4: dispatch solo dopo il commit della transazione DB attiva
        // (evita invii orfani in caso di rollback). NB: $afterCommit è
        // proprietà del trait Queueable — non ridichiararla nella classe.
        $this->afterCommit();
    }

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
                'total_availabilities' => $this->user->availabilities()->count(),
            ]
        );
    }
}
