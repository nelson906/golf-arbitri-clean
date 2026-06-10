<?php

namespace Tests\Feature\Notifications;

use App\Helpers\ZoneHelper;
use App\Mail\ClubNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MODELLO A MAIL SINGOLA 2026-06: una sola email con TO = circolo e
 * CC = interessati; gli allegati (lettera circolo + convocazione) sono
 * sulla mail unica e raggiungono quindi anche i CC (limite del mezzo).
 *
 * NOTA: i Mailable usano file_exists(storage_path(...)) negli attachments(),
 * quindi servono file REALI sul disco public (Storage::fake non intercetta
 * storage_path). I file creati vengono rimossi in tearDown.
 */
class NotificationAttachmentsTest extends TestCase
{
    /** @var string[] percorsi relativi (disk public) da ripulire */
    private array $createdFiles = [];

    private string $dir = '';

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $relPath) {
            if (Storage::disk('public')->exists($relPath)) {
                Storage::disk('public')->delete($relPath);
            }
        }
        parent::tearDown();
    }

    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    /**
     * Crea un torneo zonale con circolo + arbitro e i due documenti reali su disco.
     *
     * @return array{0: Tournament, 1: \App\Models\User, 2: string, 3: string}
     *               [tournament, referee, convocationPath, clubLetterPath] (assoluti)
     */
    private function setupWithRealDocuments(): array
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $zone = ZoneHelper::getFolderCodeForTournament($tournament);
        $this->dir = config('golf.documents.storage_path')."/{$zone}/generated";

        $convFile = 'Convocazione_real.docx';
        $clubFile = 'Lettera_real.docx';
        Storage::disk('public')->put("{$this->dir}/{$convFile}", 'FAKE-DOCX');
        Storage::disk('public')->put("{$this->dir}/{$clubFile}", 'FAKE-DOCX');
        $this->createdFiles = ["{$this->dir}/{$convFile}", "{$this->dir}/{$clubFile}"];

        $convPath = storage_path("app/public/{$this->dir}/{$convFile}");
        $clubPath = storage_path("app/public/{$this->dir}/{$clubFile}");

        TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => ['convocation' => $convFile, 'club_letter' => $clubFile],
            'metadata'          => [
                'message'    => 'In allegato i documenti.',
                'recipients' => [
                    'club'          => true,
                    'referees'      => [$ref->id],
                    'institutional' => [],
                ],
            ],
        ]);

        return [$tournament, $ref, $convPath, $clubPath];
    }

    public function test_club_mail_includes_both_attachments(): void
    {
        [$tournament, , $convPath, $clubPath] = $this->setupWithRealDocuments();

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        app(NotificationService::class)->send($notification);

        Mail::assertQueued(ClubNotificationMail::class, function ($mail) use ($clubPath, $convPath) {
            return $mail->hasTo('circolo@example.test')
                && $mail->hasAttachment(Attachment::fromPath($clubPath)->as('Lettera_Circolo.docx'))
                && $mail->hasAttachment(Attachment::fromPath($convPath)->as('Convocazione.docx'));
        });
    }

    /**
     * L'arbitro è in CC (conoscenza) della stessa mail indirizzata al circolo.
     */
    public function test_referee_is_in_cc_of_club_mail(): void
    {
        [$tournament] = $this->setupWithRealDocuments();

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        app(NotificationService::class)->send($notification);

        Mail::assertQueued(ClubNotificationMail::class, function ($mail) {
            return $mail->hasTo('circolo@example.test')
                && $mail->hasCc('arbitro@example.test');
        });
        Mail::assertQueued(ClubNotificationMail::class, 1);
    }

    /**
     * Il flag attach_convocation del form (prima salvato e MAI letto) ora è
     * onorato: false → solo la lettera circolo, niente convocazione.
     */
    public function test_attach_convocation_false_skips_convocation(): void
    {
        [$tournament, , $convPath, $clubPath] = $this->setupWithRealDocuments();

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $metadata = $notification->metadata;
        $metadata['attach_convocation'] = false;
        $notification->update(['metadata' => $metadata]);

        app(NotificationService::class)->send($notification->fresh());

        Mail::assertQueued(ClubNotificationMail::class, function ($mail) use ($clubPath, $convPath) {
            return $mail->hasTo('circolo@example.test')
                && $mail->hasAttachment(Attachment::fromPath($clubPath)->as('Lettera_Circolo.docx'))
                && ! $mail->hasAttachment(Attachment::fromPath($convPath)->as('Convocazione.docx'));
        });
    }
}
