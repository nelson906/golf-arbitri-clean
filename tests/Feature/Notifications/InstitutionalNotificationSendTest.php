<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Models\InstitutionalEmail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Copre gli indirizzi ISTITUZIONALI ("indirizzi che si ritiene opportuno
 * inserire", cfr. istruzioni di progetto) nel MODELLO A MAIL SINGOLA 2026-06:
 * gli istituzionali selezionati viaggiano in CC della mail al circolo
 * (primo CC promosso a TO se il circolo manca).
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

    private function makeNotification(int $tournamentId, bool $club, array $refereeIds, array $institutionalIds): TournamentNotification
    {
        return TournamentNotification::create([
            'tournament_id'     => $tournamentId,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => [],
            'metadata'          => [
                'message'    => 'Comunicazione di servizio.',
                'recipients' => [
                    'club'          => $club,
                    'referees'      => $refereeIds,
                    'institutional' => $institutionalIds,
                ],
            ],
        ]);
    }

    /**
     * Un istituzionale selezionato (per ID nei recipients del form) riceve
     * la copia conoscenza.
     */
    public function test_send_dispatches_mail_to_configured_institutional_address(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $istituzionale = $this->makeInstitutional('ufficio@example.test');

        $notification = $this->makeNotification($tournament->id, false, [], [$istituzionale->id]);

        app(NotificationService::class)->send($notification);

        // Unico destinatario → promosso a TO
        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('ufficio@example.test'));
        Mail::assertQueued(ClubNotificationMail::class, 1);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Invio completo: circolo (mail dedicata con TO proprio) + arbitro e
     * istituzionale nella stessa copia conoscenza.
     */
    public function test_institutional_sent_together_with_club_and_referees(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $istituzionale = $this->makeInstitutional('ufficio@example.test');

        $notification = $this->makeNotification($tournament->id, true, [$ref->id], [$istituzionale->id]);

        app(NotificationService::class)->send($notification);

        // Mail unica: TO circolo
        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));

        // Stessa mail: arbitro + istituzionale in conoscenza (TO o CC)
        Mail::assertQueued(ClubNotificationMail::class, function ($mail) {
            return ($mail->hasTo('arbitro@example.test') || $mail->hasCc('arbitro@example.test'))
                && ($mail->hasTo('ufficio@example.test') || $mail->hasCc('ufficio@example.test'));
        });

        Mail::assertQueued(ClubNotificationMail::class, 1);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Un istituzionale inesistente o disattivato viene semplicemente saltato
     * dal builder (con dedupe/validazione): il circolo è servito comunque e
     * l'invio resta pieno (nessun destinatario fantasma = nessun errore).
     */
    public function test_invalid_institutional_id_does_not_block_other_recipients(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $notification = $this->makeNotification($tournament->id, true, [], [999999]); // id inesistente

        app(NotificationService::class)->send($notification);

        // Solo il circolo in TO: l'istituzionale fantasma è skippato dal builder
        Mail::assertQueued(ClubNotificationMail::class, 1);
        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }
}
