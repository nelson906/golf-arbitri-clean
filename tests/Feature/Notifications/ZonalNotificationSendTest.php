<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Mail\RefereeAssignmentMail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test di integrazione sul processo PIÙ IMPORTANTE del prodotto:
 * l'invio della notifica ZONALE a circolo + arbitri.
 *
 * Copre il buco principale segnalato in RISK_ASSESSMENT.md §3 (R1):
 * prima di questi test esisteva solo copertura sul ramo NAZIONALE e sulla
 * preparazione della notifica, ma NESSUN test asseriva *chi* riceve davvero
 * l'email nel flusso zonale (circolo + arbitri assegnati), né la regola di
 * business per cui si notifica solo agli arbitri ANCORA assegnati.
 *
 * Questi test esercitano NotificationService::send() reale (no mock del
 * builder destinatari) e asseriscono il targeting via Mail::assertSent.
 *
 * NOTA: i documenti sono lasciati vuoti di proposito (documents = [])
 * così getClubAttachments/getRefereeAttachments ritornano [] e non c'è
 * dipendenza da file .docx su disco — il test resta puro e deterministico.
 */
class ZonalNotificationSendTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    /**
     * Crea una notifica zonale "pronta da inviare" con destinatari espliciti.
     *
     * @param  int[]  $refereeIds
     */
    private function makeNotification(int $tournamentId, bool $club, array $refereeIds): TournamentNotification
    {
        return TournamentNotification::create([
            'tournament_id'     => $tournamentId,
            'notification_type' => null, // null = zonale
            'status'            => 'pending',
            'documents'         => [], // nessun allegato → nessun accesso al filesystem
            'metadata'          => ['message' => 'Si trasmette la convocazione per il torneo.'],
            'recipients'        => [
                'club'          => $club,
                'referees'      => $refereeIds,
                'institutional' => [],
            ],
        ]);
    }

    /**
     * Happy path: circolo + 2 arbitri assegnati ricevono l'email, stato = sent.
     * È il test che, se rompe, segnala che il cuore del prodotto è regredito.
     */
    public function test_zonal_send_reaches_club_and_assigned_referees(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $refA = $this->createReferee(['zone_id' => 1, 'email' => 'arbitroA@example.test']);
        $refB = $this->createReferee(['zone_id' => 1, 'email' => 'arbitroB@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $refA->id]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $refB->id]);

        $notification = $this->makeNotification($tournament->id, true, [$refA->id, $refB->id]);

        app(NotificationService::class)->send($notification);

        // Il circolo riceve la lettera all'indirizzo corretto
        Mail::assertSent(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));

        // Entrambi gli arbitri ricevono la convocazione
        Mail::assertSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('arbitroA@example.test'));
        Mail::assertSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('arbitroB@example.test'));
        Mail::assertSent(RefereeAssignmentMail::class, 2);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Regola di business critica (NotificationService::send riga ~150):
     * si notifica SOLO agli arbitri attualmente assegnati. Un id rimasto nei
     * recipients ma non più assegnato NON deve ricevere email.
     *
     * Senza questo test, una rimozione di assegnazione non riflessa nei
     * recipients invierebbe a un arbitro non più coinvolto.
     */
    public function test_zonal_send_ignores_referees_no_longer_assigned(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $assigned = $this->createReferee(['zone_id' => 1, 'email' => 'assegnato@example.test']);
        $ghost    = $this->createReferee(['zone_id' => 1, 'email' => 'fantasma@example.test']);
        // Solo "assigned" è realmente assegnato al torneo
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $assigned->id]);

        // ...ma i recipients includono anche il "fantasma" (stato non allineato)
        $notification = $this->makeNotification($tournament->id, false, [$assigned->id, $ghost->id]);

        app(NotificationService::class)->send($notification);

        Mail::assertSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('assegnato@example.test'));
        Mail::assertNotSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('fantasma@example.test'));
        Mail::assertSent(RefereeAssignmentMail::class, 1);
    }

    /**
     * Documenta un comportamento a rischio (RISK_ASSESSMENT.md §3 R2-bis):
     * sendToClub() NON è protetto da try/catch per-destinatario. Un circolo
     * senza email fa fallire l'INTERA notifica — gli arbitri NON ricevono nulla,
     * perché l'invio al circolo precede il loop arbitri.
     *
     * Se in futuro si avvolge sendToClub in un try/catch (fix consigliato),
     * questo test andrà aggiornato per asserire stato 'partial' e arbitri serviti.
     *
     * NOTA: la colonna clubs.email è NOT NULL a livello DB, quindi lo stato
     * "senza email" si presenta come stringa vuota (dato sporco realistico),
     * non come NULL. sendToClub usa `if (! $club->email)`, quindi '' è trattato
     * come mancante esattamente come NULL.
     */
    public function test_missing_club_email_blocks_entire_notification(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => '']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $notification = $this->makeNotification($tournament->id, true, [$ref->id]);

        $threw = false;
        try {
            app(NotificationService::class)->send($notification);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Un circolo senza email dovrebbe far lanciare send() (comportamento attuale).');

        $notification->refresh();
        $this->assertEquals('failed', $notification->status);

        // Conseguenza da evidenziare: nemmeno gli arbitri vengono notificati.
        Mail::assertNotSent(RefereeAssignmentMail::class);
    }
}
