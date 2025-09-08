<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Assignment;
use App\Models\Tournament;

class RefereeAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * L'assegnazione dell'arbitro
     */
    public Assignment $assignment;

    /**
     * Il torneo di riferimento
     */
    public Tournament $tournament;

    /**
     * I percorsi degli allegati
     */
    public array $attachmentPaths;

    /**
     * Create a new message instance.
     */
    public function __construct($assignment, Tournament $tournament, array $attachmentPaths = [])
    {
        $this->assignment = $assignment;
        $this->tournament = $tournament;
        $this->attachmentPaths = $attachmentPaths;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Convocazione {$this->assignment->role} - {$this->tournament->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament_assignment_generic',
            with: [
                'assignment' => $this->assignment,
                'tournament' => $this->tournament,
                'recipient_name' => $this->assignment->user->name,
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range,
                'club_name' => $this->tournament->club->name,
                'referees' => [[
                    'name' => $this->assignment->user->name,
                    'role' => $this->assignment->role,
                    'email' => $this->assignment->user->email
                ]],
                'zone_email' => "szr{$this->tournament->zone_id}@federgolf.it",
                'club_email' => $this->tournament->club->email,
                'attachments_info' => count($this->attachmentPaths) > 0 ?
                    ['Convocazione ufficiale in formato PDF'] : null
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        foreach ($this->attachmentPaths as $path) {
            if (file_exists($path)) {
                $mailAttachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path);
            }
        }

        return $mailAttachments;
    }
}
