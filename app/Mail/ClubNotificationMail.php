<?php

namespace App\Mail;

use App\Models\Tournament;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubNotificationMail extends Mailable
{
    use SerializesModels;

    public $tournament;

    public $attachmentPaths;

    public $sortedAssignments;

    public $content;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, ?string $content = null, array $attachmentPaths = [])
    {
        $this->tournament = $tournament;
        $this->content = $content;
        $this->attachmentPaths = $attachmentPaths;

        // ORDINA GLI ARBITRI PER GERARCHIA
        $roleOrder = [
            'Direttore di Torneo' => 1,
            'Arbitro' => 2,
            'Osservatore' => 3,
        ];

        $this->sortedAssignments = $tournament->assignments->sort(function ($a, $b) use ($roleOrder) {
            $orderA = $roleOrder[$a->role] ?? 999;
            $orderB = $roleOrder[$b->role] ?? 999;

            if ($orderA === $orderB) {
                return strcmp($a->user->name, $b->user->name);
            }

            return $orderA - $orderB;
        });
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Arbitri Assegnati - {$this->tournament->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Prepara i dati per la view
        $referees = $this->tournament->assignments->map(function ($assignment) {
            return [
                'name' => $assignment->user->name,
                'role' => $assignment->role,
                'email' => $assignment->user->email,
            ];
        })->toArray();

        return new Content(
            view: 'emails.tournament_assignment_generic',
            with: [
                'recipient_name' => $this->tournament->club->name,
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range,
                'club_name' => $this->tournament->club->name,
                'referees' => $referees,
                'zone_email' => "szr{$this->tournament->zone_id}@federgolf.it",
                'club_email' => $this->tournament->club->email,
                'attachments_info' => count($this->attachmentPaths) > 0 ?
                    ['Facsimile convocazione in formato Word'] : null,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        if (empty($this->attachmentPaths)) {
            return $mailAttachments;
        }

        foreach ($this->attachmentPaths as $attachment) {
            if (! is_array($attachment) || ! isset($attachment['path'])) {
                continue;
            }

            $path = $attachment['path'];
            $name = $attachment['name'] ?? basename($path);

            if (file_exists($path)) {
                $mailAttachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path)
                    ->as($name);
            } else {
                \Illuminate\Support\Facades\Log::warning('Attachment file not found', [
                    'path' => $path,
                    'name' => $name,
                ]);
            }
        }

        return $mailAttachments;
    }

    // RIMUOVI COMPLETAMENTE IL METODO build()
}
