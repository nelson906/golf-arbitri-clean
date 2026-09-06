<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchAvailabilityAdminNotification extends Mailable implements ShouldQueue
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
        $zone = $this->user->zone->name ?? 'N/A';

        return new Envelope(
            subject: "[DISPONIBILITÀ] {$this->user->name} - Zona {$zone}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-availability-notification',
            with: [
                'referee' => $this->user,
                'referee_name' => $this->user->name,
                'referee_code' => $this->user->referee_code ?? 'N/A',
                'referee_level' => $this->user->level ?? 'N/A',
                'zone' => $this->user->zone->name ?? 'N/A',
                'added_tournaments' => $this->addedTournaments,
                'removed_tournaments' => $this->removedTournaments,
                'updated_at' => now()->format('d/m/Y H:i'),
            ]
        );
    }
}
