<?php

namespace Tests\Feature\Admin;

use App\Mail\NationalNotificationMail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Copertura end-to-end (HTTP) di NotificationController::sendNationalNotification().
 *
 * Il test esistente NationalNotificationMailDispatchTest verifica solo il
 * dispatch della Mailable nel caso happy. Qui copriamo i rami non testati:
 *  - GUARD: un torneo zonale NON può ricevere notifiche CRC/SZR (riga ~594)
 *  - "Nessun destinatario selezionato" quando il builder è vuoto (riga ~629)
 *  - SUCCESS: crea il record nazionale e cancella la bozza zonale (null type)
 */
class SendNationalNotificationHttpTest extends TestCase
{
    private function nationalType(): TournamentType
    {
        return TournamentType::where('is_national', true)->firstOrFail();
    }

    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    public function test_zonal_tournament_is_rejected_for_crc_notification(): void
    {
        Mail::fake();

        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type'  => 'crc_referees',
                'subject'            => 'Tentativo su torneo zonale',
                'message'            => 'Non deve partire.',
                'send_to_campionati' => 1,
            ]
        );

        $response->assertSessionHas('error');
        Mail::assertNotSent(NationalNotificationMail::class);

        // Nessun record nazionale creato per questo torneo.
        $this->assertDatabaseMissing('tournament_notifications', [
            'tournament_id'     => $tournament->id,
            'notification_type' => 'crc_referees',
        ]);
    }

    public function test_no_recipients_returns_error(): void
    {
        Mail::fake();

        $nationalType = $this->nationalType();
        $tournament = $this->createTournament([
            'tournament_type_id' => $nationalType->id,
        ]);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $this->createReferee()->id,
        ]);

        $this->actingAsSuperAdmin();

        // Nessun send_to_campionati, nessuna zona, nessun CC → builder vuoto.
        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type' => 'crc_referees',
                'subject'           => 'Senza destinatari',
                'message'           => 'Nessuno selezionato.',
            ]
        );

        $response->assertSessionHas('error');
        Mail::assertNotSent(NationalNotificationMail::class);
    }

    public function test_success_creates_national_record_and_deletes_zonal_draft(): void
    {
        Mail::fake();

        $nationalType = $this->nationalType();
        $tournament = $this->createTournament([
            'tournament_type_id' => $nationalType->id,
        ]);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $this->createReferee()->id,
        ]);

        // Bozza zonale (null type, non inviata) che deve essere cancellata.
        $draft = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'sent_at'           => null,
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type'  => 'crc_referees',
                'subject'            => 'Designazione Arbitri',
                'message'            => 'Si comunicano i nominativi.',
                'send_to_campionati' => 1,
            ]
        );

        $this->assertNotEquals(500, $response->status());
        Mail::assertSent(NationalNotificationMail::class);

        // La bozza zonale è stata eliminata.
        $this->assertDatabaseMissing('tournament_notifications', ['id' => $draft->id]);

        // Esiste il record nazionale, marcato come tale e inviato.
        $record = TournamentNotification::where('tournament_id', $tournament->id)
            ->where('notification_type', 'crc_referees')
            ->first();

        $this->assertNotNull($record, 'Deve esistere il record CRC nazionale.');
        $this->assertEquals('sent', $record->status);
        $this->assertTrue(($record->metadata['is_national'] ?? false) === true);
    }
}
