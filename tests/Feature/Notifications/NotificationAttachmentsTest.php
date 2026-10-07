<?php

namespace Tests\Feature\Notifications;

use App\Helpers\ZoneHelper;
use App\Mail\ClubNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MODELLO A MAIL SINGOLA 2026-06: una sola email con TO = circolo e
 * CC = interessati; gli allegati (lettera circolo + convocazione) sono
 * sulla mail unica e raggiungono quindi anche i CC (limite del mezzo).
 *
 * NOTA: i Mailable usano file_exists(...) negli attachments(), quindi servono
 * file REALI sul disco documenti (Storage::fake non intercetta i path assoluti).
 * I file creati vengono rimossi in tearDown.
 *
 * FIX M2 (audit 2026-07): i documenti vivono sul disk PRIVATO
 * config('golf.documents.disk') (default 'docs') — non più sul disk public.
 */
class NotificationAttachmentsTest extends TestCase
{
    /** @var string[] percorsi relativi (disk documenti) da ripulire */
    private array $createdFiles = [];

    private string $dir = '';

    private function docsDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(Config::string('golf.documents.disk', 'docs'));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $relPath) {
            if ($this->docsDisk()->exists($relPath)) {
                $this->docsDisk()->delete($relPath);
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
        $this->dir = Config::string('golf.documents.storage_path')."/{$zone}/generated";

        $convFile = 'Convocazione_real.docx';
        $clubFile = 'Lettera_real.docx';
        $this->docsDisk()->put("{$this->dir}/{$convFile}", 'FAKE-DOCX');
        $this->docsDisk()->put("{$this->dir}/{$clubFile}", 'FAKE-DOCX');
        $this->createdFiles = ["{$this->dir}/{$convFile}", "{$this->dir}/{$clubFile}"];

        $convPath = $this->docsDisk()->path("{$this->dir}/{$convFile}");
        $clubPath = $this->docsDisk()->path("{$this->dir}/{$clubFile}");

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

        app(NotificationService::class)->send($notification->refresh());

        Mail::assertQueued(ClubNotificationMail::class, function ($mail) use ($clubPath, $convPath) {
            return $mail->hasTo('circolo@example.test')
                && $mail->hasAttachment(Attachment::fromPath($clubPath)->as('Lettera_Circolo.docx'))
                && ! $mail->hasAttachment(Attachment::fromPath($convPath)->as('Convocazione.docx'));
        });
    }

    /**
     * Documento registrato in `documents` ma assente dal disco: dal
     * 2026-10-07 la notifica non parte (prima partiva "parziale" senza
     * convocazione).
     */
    public function test_missing_attachment_file_blocks_the_send(): void
    {
        [$tournament] = $this->setupWithRealDocuments();

        // Rimuovi la convocazione dal disco (resta registrata in `documents`)
        $this->docsDisk()->delete("{$this->dir}/Convocazione_real.docx");

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();

        try {
            app(NotificationService::class)->send($notification);
            $this->fail('Con un allegato mancante la notifica non deve partire.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Convocazione.docx', $e->getMessage());
        }

        Mail::assertNothingOutgoing();
        $final = $notification->fresh();
        $this->assertNotNull($final);
        $this->assertSame('pending', $final->status);
    }
}
