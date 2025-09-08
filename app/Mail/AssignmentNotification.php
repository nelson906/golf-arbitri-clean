<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class AssignmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;
    public $variables;

    /**
     * Create a new message instance.
     */
    public function __construct(Notification $notification, array $variables = [])
    {
        $this->notification = $notification;
        $this->variables = $variables;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: $this->notification->subject,
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
            'recipient_name' => $this->variables['referee_name'] ?? 'N/A',
            'club_name' => $this->variables['club_name'] ?? 'N/A',
            'tournament_name' => $this->variables['tournament_name'] ?? 'N/A',
            'tournament_dates' => $this->variables['tournament_dates'] ?? 'N/A',
            'referees' => $this->getReferees(), // ← AGGIUNGI QUESTO
            'notification' => $this->notification,
            'variables' => $this->variables,
        ],
    );
}

/**
 * Get referees for the tournament
 */
private function getReferees(): array
{
    // Se la notifica è legata a un assignment, prendi il torneo da lì
    if ($this->notification->assignment_id && $this->notification->assignment) {
        $tournament = $this->notification->assignment->tournament;

        return $tournament->assignments->map(function($assignment) {
            return [
                'name' => $assignment->user->name,
                'role' => $assignment->role,
                'email' => $assignment->user->email,
                'phone' => $assignment->user->phone ?? 'N/A',
            ];
        })->toArray();
    }

    // Se hai il tournament_id nelle variabili, usalo
    if (isset($this->variables['tournament_id'])) {
        $tournament = \App\Models\Tournament::with('assignments.user')->find($this->variables['tournament_id']);

        if ($tournament) {
            return $tournament->assignments->map(function($assignment) {
                return [
                    'name' => $assignment->user->name,
                    'role' => $assignment->role,
                    'email' => $assignment->user->email,
                    'phone' => $assignment->user->phone ?? 'N/A',
                ];
            })->toArray();
        }
    }

    return [];
}
    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->notification->hasAttachments()) {
            foreach ($this->notification->attachments as $name => $path) {
                if (file_exists(storage_path('app/' . $path))) {
                    $attachments[] = Attachment::fromStorageDisk('local', $path)
                        ->as($name . '.' . pathinfo($path, PATHINFO_EXTENSION));
                }
            }
        }

        return $attachments;
    }
}
