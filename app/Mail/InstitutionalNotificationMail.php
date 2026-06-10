<?php

namespace App\Mail;

use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InstitutionalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;


    public function __construct(
        public Tournament $tournament,
        public string $notificationType
    ) {
        // FIX A4: dispatch solo dopo il commit della transazione DB attiva
        // (evita invii orfani in caso di rollback). NB: $afterCommit è
        // proprietà del trait Queueable — non ridichiararla nella classe.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->notificationType}] Assegnazione {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament_assignment_generic',
            with: [
                'recipient_name' => 'Ufficio Campionati',
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range ?? $this->tournament->start_date->format('d/m/Y').' - '.$this->tournament->end_date->format('d/m/Y'),
                'club_name' => $this->tournament->club->name,
                'referees' => $this->tournament->assignments->map(function ($assignment) {
                    return [
                        'name' => $assignment->user->name ?? 'N/D',
                        'role' => $assignment->role ?? 'Arbitro',
                    ];
                })->toArray(),
            ]
        );
    }
}
