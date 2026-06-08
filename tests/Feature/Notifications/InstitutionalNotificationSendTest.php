<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Mail\InstitutionalNotificationMail;
use App\Mail\RefereeAssignmentMail;
use App\Models\InstitutionalEmail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Copre il TERZO ramo di NotificationService::send() — gli indirizzi
 * ISTITUZIONALI ("indirizzi che si ritiene opportuno inserire", cfr.
 * istruzioni di progetto). Prima esisteva copertura solo su circolo + arbitri:
 * nessun test asseriva che gli istituzionali ricevano davvero l'email.
 *
 * Documenti lasciati vuoti (documents = []) → nessuna dipendenza dal filesystem.
 */
class InstitutionalNotificationSendTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    private function makeInstitutional(string $email): InstitutionalEmail
    {
        return InstitutionalEmail::create([
            'name'      => 'Ufficio Test',
            'email'     => $email,
            'category'  => 'federazione',
            'is_active' => true,
        ]);
    }

    /**
     * Un istituzionale selezionato (per ID nei recipients) riceve la mail dedicata.
     */
    public function test_send_dispatches_mail_to_configured_institutional_address(): void
    {
        Mail::fake();

        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $istituzionale = $this->makeInstitutional('ufficio@example.test');

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => [],
            'metadata'          => ['message' => 'Comunicazione di servizio.'],
            'recipients'        => [
                'club'          => false,
                'referees'      => [],
                'institutional' => [$istituzionale->id],
            ],
        ]);

        app(NotificationService::class)->send($notification);

        Mail::assertSent(InstitutionalNotificationMail::class, fn ($mail) => $mail->hasTo('ufficio@example.test'));
        Mail::assertSent(InstitutionalNotificationMail::class, 1);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Invio completo: circolo + arbitro + istituzionale ricevono ciascuno la
     * propria mail nello stesso send(). Verifica che i tre rami coesistano.
     */
    public function test_institutional_sent_together_with_club_and_referees(): void
    {
        Mail::fake();

        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $istituzionale = $this->makeInstitutional('ufficio@example.test');

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => [],
            'metadata'          => ['message' => 'Comunicazione di servizio.'],
            'recipients'        => [
                'club'          => true,
                'referees'      => [$ref->id],
                'institutional' => [$istituzionale->id],
            ],
        ]);

        app(NotificationService::class)->send($notification);

        Mail::assertSent(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));
        Mail::assertSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('arbitro@example.test'));
        Mail::assertSent(InstitutionalNotificationMail::class, fn ($mail) => $mail->hasTo('ufficio@example.test'));

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Un istituzionale inesistente (ID non valido) non blocca l'invio: il ramo
     * è protetto da try/catch per-destinatario → stato 'partial', circolo servito.
     */
    public function test_invalid_institutional_id_does_not_block_other_recipients(): void
    {
        Mail::fake();

        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => [],
            'metadata'          => ['message' => 'Comunicazione di servizio.'],
            'recipients'        => [
                'club'          => true,
                'referees'      => [],
                'institutional' => [999999], // id inesistente
            ],
        ]);

        app(NotificationService::class)->send($notification);

        Mail::assertSent(ClubNotificationMail::class, 1);
        Mail::assertNotSent(InstitutionalNotificationMail::class);

        $notification->refresh();
        $this->assertEquals('partial', $notification->status);
    }
}
