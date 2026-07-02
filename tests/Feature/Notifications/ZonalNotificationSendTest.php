<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test di integrazione sul processo PIÙ IMPORTANTE del prodotto:
 * l'invio della notifica ZONALE.
 *
 * MODELLO A MAIL SINGOLA (refactor 2026-06):
 *   UNA email: TO (competenza) = circolo con allegati; CC (conoscenza) =
 *   arbitri + istituzionali + sezione zona + email aggiuntive.
 *   Senza circolo, primo CC promosso a TO (come il flusso CRC nazionale).
 *
 * FIX D1: i destinatari arrivano SOLO da metadata['recipients'] (l'intento
 * del form), mai dalla colonna `recipients` persistita.
 *
 * NOTA: i documenti sono lasciati vuoti di proposito (documents = [])
 * così gli allegati ritornano [] e non c'è dipendenza dal filesystem.
 */
class ZonalNotificationSendTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    /**
     * Crea una notifica zonale "pronta da inviare" con destinatari espliciti
     * nel METADATA (fonte di verità del nuovo modello).
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
            'metadata'          => [
                'subject'    => 'Convocazione torneo',
                'message'    => 'Si trasmette la convocazione per il torneo.',
                'recipients' => [
                    'club'          => $club,
                    'referees'      => $refereeIds,
                    'institutional' => [],
                ],
            ],
        ]);
    }

    /**
     * Happy path: UNA mail — TO il circolo, arbitri in CC.
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

        // TO: il circolo riceve la lettera al proprio indirizzo
        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));

        // Stessa mail: entrambi gli arbitri in conoscenza (CC)
        Mail::assertQueued(ClubNotificationMail::class, function ($mail) {
            return ($mail->hasTo('arbitroA@example.test') || $mail->hasCc('arbitroA@example.test'))
                && ($mail->hasTo('arbitroB@example.test') || $mail->hasCc('arbitroB@example.test'));
        });

        // UNA sola email: TO circolo + CC arbitri
        Mail::assertQueued(ClubNotificationMail::class, 1);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Regola di business critica: si notifica SOLO agli arbitri attualmente
     * assegnati. Un id rimasto nei recipients ma non più assegnato NON deve
     * ricevere email (né in TO né in CC).
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

        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('assegnato@example.test'));
        Mail::assertNotQueued(ClubNotificationMail::class, function ($mail) {
            return $mail->hasTo('fantasma@example.test') || $mail->hasCc('fantasma@example.test');
        });
        // Una sola mail (club = false → primo CC promosso a TO)
        Mail::assertQueued(ClubNotificationMail::class, 1);
    }

    /**
     * FIX D1 (regressione del bug "arriva solo agli arbitri"): la colonna
     * `recipients` stantia NON deve shadoware le scelte fresche del form
     * salvate in metadata.
     */
    public function test_stale_recipients_column_does_not_override_form_metadata(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $notification = $this->makeNotification($tournament->id, true, [$ref->id]);

        // Colonna avvelenata da un invio precedente: niente circolo, niente arbitri
        $notification->update([
            'recipients' => ['club' => false, 'referees' => [], 'institutional' => []],
        ]);

        app(NotificationService::class)->send($notification->fresh());

        // Il form (metadata) vince: circolo in TO E arbitro in CC
        Mail::assertQueued(ClubNotificationMail::class, function ($mail) {
            return $mail->hasTo('circolo@example.test')
                && ($mail->hasTo('arbitro@example.test') || $mail->hasCc('arbitro@example.test'));
        });
    }

    /**
     * FIX D2: metadata senza 'recipients' (es. record import FIG con
     * {source, command}) deve essere RIFIUTATO, non "inviato a nessuno".
     */
    public function test_metadata_without_recipients_is_rejected(): void
    {
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
            'metadata'          => ['source' => 'Import batch FIG 2025', 'command' => 'federgolf:mark-notified'],
        ]);

        $this->expectExceptionMessage(NotificationService::ERR_MISSING_RECIPIENTS);

        try {
            app(NotificationService::class)->send($notification);
        } finally {
            Mail::assertNothingOutgoing();
        }
    }

    /**
     * Un circolo senza email NON deve bloccare la copia conoscenza agli
     * arbitri: invio parziale (status 'partial'), arbitro raggiunto.
     *
     * NOTA: la colonna clubs.email è NOT NULL a livello DB, quindi lo stato
     * "senza email" si presenta come stringa vuota (dato sporco realistico).
     */
    public function test_missing_club_email_does_not_block_referees(): void
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

        // Non deve lanciare: l'errore circolo è assorbito per-destinatario.
        app(NotificationService::class)->send($notification);

        // L'arbitro riceve comunque (promosso a TO in assenza del circolo).
        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('arbitro@example.test'));
        Mail::assertQueued(ClubNotificationMail::class, 1);

        // Invio parziale: un errore (circolo) + un successo (copia conoscenza).
        $notification->refresh();
        $this->assertEquals('partial', $notification->status);
        $this->assertSame(1, $notification->metadata['error_count'] ?? null);
        $this->assertSame(1, $notification->metadata['success_count'] ?? null);
    }

    /**
     * FIX M5 (audit 2026-07): nessun destinatario valido → NESSUNA mail e
     * status 'failed'. Prima terminava 'sent' con success_count 0: lo storico
     * mentiva ("inviato a nessuno").
     */
    public function test_no_valid_recipients_ends_failed_not_sent(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        // club deselezionato, nessun arbitro selezionato → builder vuoto
        $notification = $this->makeNotification($tournament->id, false, []);

        app(NotificationService::class)->send($notification);

        Mail::assertNothingQueued();

        $notification->refresh();
        $this->assertEquals('failed', $notification->status,
            'REGRESSIONE M5: invio a zero destinatari non deve risultare "sent".');
        $this->assertSame(0, $notification->metadata['success_count'] ?? null);
        $this->assertStringContainsString('nessun destinatario',
            $notification->metadata['last_error'] ?? '');
    }
}
