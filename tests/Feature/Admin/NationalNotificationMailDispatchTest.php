<?php

namespace Tests\Feature\Admin;

use App\Mail\NationalNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test di regressione per il refactor Mail::raw → NationalNotificationMail
 * in NotificationController::sendNationalNotification().
 *
 * Garantisce che:
 * - La classe Mailable esiste e viene istanziata correttamente
 * - Il subject e il body passano integri al Mailable
 * - L'invio non crasha con setup minimale (Mail::fake)
 *
 * Se questo test rompe, il flusso di invio nazionale è regredito.
 */
class NationalNotificationMailDispatchTest extends TestCase
{
    public function test_send_national_notification_dispatches_mailable(): void
    {
        Mail::fake();

        // Setup: torneo nazionale con un arbitro assegnato
        $nationalType = TournamentType::where('is_national', true)->first();
        $tournament = Tournament::factory()->create([
            'tournament_type_id' => $nationalType->id,
        ]);

        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $this->createReferee()->id,
        ]);

        // Login come super_admin (passa middleware admin_or_superadmin)
        $this->actingAsSuperAdmin();

        // POST sulla route di invio nazionale
        $response = $this->post(
            route('admin.tournaments.send-national-notification', $tournament),
            [
                'notification_type'    => 'crc_referees',
                'subject'              => 'Test Designazione Arbitri',
                'message'              => "Si comunicano i nominativi:\nArbitro Test",
                'send_to_campionati'   => 1,
            ]
        );

        // La response può essere redirect (302) — verifica che NON sia 500/422
        $this->assertNotEquals(500, $response->status(), 'sendNationalNotification ha lanciato un 500.');
        $this->assertNotEquals(422, $response->status(), 'sendNationalNotification ha fallito la validation.');

        // Asserzione principale: la NationalNotificationMail è stata dispatched
        $this->assertTrue(
            Mail::queued(NationalNotificationMail::class)->isNotEmpty(),
            'NationalNotificationMail non è stata dispatched. '
            .'Il refactor Mail::raw → Mailable è regredito.'
        );

        // Verifica subject + body integri
        Mail::assertQueued(NationalNotificationMail::class, function ($mail) {
            return $mail->subjectLine === 'Test Designazione Arbitri'
                && str_contains($mail->body, 'Si comunicano i nominativi');
        });
    }

    /**
     * Smoke test: la classe Mailable e la view esistono e si istanziano
     * senza errori (utile per catturare problemi di rendering view).
     */
    public function test_mailable_can_be_instantiated_and_rendered(): void
    {
        $mailable = new NationalNotificationMail(
            subjectLine: 'Test Subject',
            body: 'Test body con\nnewline'
        );

        // L'envelope deve avere il subject corretto
        $this->assertEquals('Test Subject', $mailable->envelope()->subject);

        // Render della view non deve lanciare eccezioni
        $rendered = $mailable->render();
        $this->assertStringContainsString('Test Subject', $rendered);
        $this->assertStringContainsString('Test body', $rendered);
    }
}
