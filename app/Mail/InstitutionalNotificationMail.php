<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\Tournament;

class InstitutionalNotificationMail extends Mailable
{
    public function __construct(
        public Tournament $tournament,
        public string $notificationType
    ) {}

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
                'tournament_dates' => $this->tournament->date_range ?? $this->tournament->start_date->format('d/m/Y') . ' - ' . $this->tournament->end_date->format('d/m/Y'),
                'club_name' => $this->tournament->club->name,
                'referees' => $this->tournament->assignments->map(function($assignment) {
                    return [
                        'name' => $assignment->user->name ?? $assignment->referee->name ?? 'N/D',
                        'role' => $assignment->role ?? 'Arbitro'
                    ];
                })->toArray()
            ]
        );
    }
}
