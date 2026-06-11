<?php

namespace Tests\Feature\Notifications;

use App\Mail\NationalNotificationMail;
use App\Models\Zone;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test di integrazione per il rischio "dati sporchi → notifica sbagliata"
 * (docs/STORICO.md, risk assessment 2026-05-30, R2).
 *
 * In produzione alcune righe di `zones` hanno il NOME al posto dell'email
 * (dato corrotto). Il bug del 10 maggio era esattamente questo: una stringa
 * non-email finiva nel parser RFC 2822 e faceva esplodere l'invio.
 *
 * Questo test percorre il flusso NAZIONALE reale via route HTTP
 * (NotificationController::sendNationalNotification → NotificationRecipientBuilder),
 * con una zona dall'email corrotta, e verifica che:
 *  - l'invio NON va in 500 (la difesa filter_var salta il destinatario cattivo)
 *  - la notifica parte comunque verso i destinatari validi
 *  - il destinatario corrotto NON compare tra i riceventi
 *
 * È il livello che `Mail::fake()` da solo non basta a garantire: qui si esercita
 * anche il builder reale con il dato sporco.
 */
class NationalNotificationCorruptZoneEmailTest extends TestCase
{
    public function test_corrupt_zone_email_is_skipped_and_send_does_not_crash(): void
    {
        Mail::fake();

        // Zona 2 con email CORROTTA (nome al posto dell'indirizzo)
        Zone::where('id', 2)->update(['email' => 'Sezione Zonale Regole 6']);

        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club = $this->createClub(['zone_id' => 2, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'status'             => 'open',
        ]);

        $referee = $this->createReferee(['zone_id' => 2, 'email' => 'arbitro.naz@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type'  => 'crc_referees',
                'subject'            => 'Designazione arbitri gara nazionale',
                'message'            => 'Si comunicano i nominativi designati.',
                'send_to_campionati' => 1,          // TO: Comitato Campionati (valido)
                'send_to_zone'       => 1,          // CC: zona 2 → email CORROTTA, va saltata
                'cc_referees'        => [$referee->id], // CC: arbitro (valido)
            ]
        );

        // Il dato sporco NON deve produrre un 500 / errore RFC 2822
        $this->assertNotEquals(500, $response->status(),
            'Una zona con email corrotta ha fatto crashare l\'invio nazionale (regressione del bug 10/05).');

        // La notifica parte comunque
        Mail::assertQueued(NationalNotificationMail::class);

        // Verso i destinatari validi, e MAI verso la stringa corrotta
        Mail::assertQueued(NationalNotificationMail::class, function ($mail) {
            return $mail->hasTo('campionati@federgolf.it')
                && $mail->hasCc('arbitro.naz@example.test')
                && ! $mail->hasCc('Sezione Zonale Regole 6');
        });
    }

    /**
     * Caso limite: una zona valida in CC viene effettivamente inclusa.
     * Controprova del test precedente — assicura che lo skip difensivo
     * non scarti per errore anche i destinatari buoni.
     */
    public function test_valid_zone_email_is_included_in_cc(): void
    {
        Mail::fake();

        // Zona 3 mantiene l'email valida del seed (szr3@federgolf.it)
        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club = $this->createClub(['zone_id' => 3, 'email' => 'circolo3@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'status'             => 'open',
        ]);

        $referee = $this->createReferee(['zone_id' => 3, 'email' => 'arbitro3@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type'  => 'crc_referees',
                'subject'            => 'Designazione arbitri',
                'message'            => 'Nominativi designati.',
                'send_to_campionati' => 1,
                'send_to_zone'       => 1,
                'cc_referees'        => [$referee->id],
            ]
        );

        $this->assertNotEquals(500, $response->status());
        Mail::assertQueued(NationalNotificationMail::class, fn ($mail) => $mail->hasCc('szr3@federgolf.it'));
    }
}
