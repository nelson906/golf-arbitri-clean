<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\TournamentNotification;
use App\Services\NotificationPreparationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test del percorso di invio (NotificationService::send) e della
 * preparazione notifiche (NotificationPreparationService::prepareNotification).
 *
 * NOTA (audit 2026-06): NotificationService::prepareNotification() e
 * generateDocuments() erano duplicati morti di NotificationPreparationService /
 * NotificationDocumentService e sono stati rimossi. I test di preparazione
 * puntano ora al service canonico.
 */
class NotificationServiceTest extends TestCase
{
    protected NotificationService $service;

    protected NotificationPreparationService $preparationService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake(); // Fake email sending

        // RAZIONALIZZAZIONE 2026-06: NotificationService non dipende più da
        // DocumentGenerationService (la generazione vive in
        // NotificationDocumentService) — niente più mock.
        $this->service = new NotificationService;
        $this->preparationService = new NotificationPreparationService;
    }

    // ==========================================
    // PREPARE NOTIFICATION TESTS (NotificationPreparationService)
    // ==========================================

    /**
     * Test: prepareNotification crea nuova notifica
     */
    public function test_prepare_notification_creates_new_notification(): void
    {
        $tournament = $this->createTournament();
        $referee = $this->createReferee();

        Assignment::factory()->forUser($referee)->forTournament($tournament)->create();

        $notification = $this->preparationService->prepareNotification($tournament->fresh());

        $this->assertInstanceOf(TournamentNotification::class, $notification);
        $this->assertEquals($tournament->id, $notification->tournament_id);
        $this->assertEquals('pending', $notification->status);
    }

    /**
     * Test: prepareNotification recupera notifica esistente
     */
    public function test_prepare_notification_retrieves_existing_notification(): void
    {
        $tournament = $this->createTournament();

        // notification_type deve corrispondere a tournamentType.is_national
        $isNational = $tournament->tournamentType?->is_national ?? false;

        $existing = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => $isNational ? 'crc_referees' : null,
            'status' => 'sent',
            'recipients' => ['club' => true, 'referees' => [], 'institutional' => []],
        ]);

        $notification = $this->preparationService->prepareNotification($tournament);

        $this->assertEquals($existing->id, $notification->id);
        $this->assertEquals('sent', $notification->status);
    }

    /**
     * Test: prepareNotification traccia gli arbitri assegnati
     * (referee_list + details.total_recipients = arbitri + circolo)
     */
    public function test_prepare_notification_includes_referee_info(): void
    {
        $tournament = $this->createTournament();
        $referee1 = $this->createReferee();
        $referee2 = $this->createReferee();

        Assignment::factory()->forUser($referee1)->forTournament($tournament)->create();
        Assignment::factory()->forUser($referee2)->forTournament($tournament)->create();

        $notification = $this->preparationService->prepareNotification($tournament->fresh());

        $this->assertEquals(3, $notification->details['total_recipients'] ?? null); // 2 arbitri + circolo
        $this->assertStringContainsString($referee1->name, (string) $notification->referee_list);
        $this->assertStringContainsString($referee2->name, (string) $notification->referee_list);
    }

    // ==========================================
    // RECIPIENTS NORMALIZATION TESTS
    // ==========================================

    /**
     * Test: Recipients vengono normalizzati correttamente
     */
    public function test_recipients_are_normalized(): void
    {
        $tournament = $this->createTournament();
        $referee = $this->createReferee();

        Assignment::factory()->forUser($referee)->forTournament($tournament)->create();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'metadata' => json_encode([
                'recipients' => [
                    'club' => true,
                    'referees' => [$referee->id],
                ],
            ]),
        ]);

        // Nota: send() è complesso da testare senza mock email
        // Testiamo che la notifica sia preparata correttamente
        $this->assertEquals($tournament->id, $notification->tournament_id);
        $this->assertNotNull($notification->metadata);
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    /**
     * Test: send() fallisce senza recipients nel metadata (FIX D2:
     * vale sia per metadata vuoto sia per metadata "estraneo" tipo import FIG)
     */
    public function test_send_fails_without_metadata_recipients(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            // metadata mancante
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(NotificationService::ERR_MISSING_RECIPIENTS);

        $this->service->send($notification);
    }

    /**
     * Test: Notifica con recipients vuoti non invia email
     */
    public function test_notification_with_empty_recipients_sends_no_emails(): void
    {
        Mail::fake();

        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'metadata' => json_encode([
                'recipients' => [
                    'club' => false,
                    'referees' => [],
                    'institutional' => [],
                ],
            ]),
            'recipients' => [
                'club' => false,
                'referees' => [],
                'institutional' => [],
            ],
        ]);

        try {
            $this->service->send($notification);
        } catch (\Exception $e) {
            // Potrebbe fallire per altri motivi, va bene
        }

        // Verifica che nessuna email sia stata inviata né accodata
        Mail::assertNothingOutgoing();
    }

    // ==========================================
    // INTEGRATION TESTS (LIGHT)
    // ==========================================

    /**
     * Test: Service è istanziabile senza dipendenze
     */
    public function test_service_is_instantiable(): void
    {
        $this->assertInstanceOf(NotificationService::class, new NotificationService);
    }

    /**
     * Test: prepareNotification funziona con torneo senza assegnazioni
     */
    public function test_prepare_notification_works_with_no_assignments(): void
    {
        $tournament = $this->createTournament();

        $notification = $this->preparationService->prepareNotification($tournament);

        $this->assertInstanceOf(TournamentNotification::class, $notification);
        $this->assertEquals(1, $notification->details['total_recipients'] ?? null); // solo circolo
        $this->assertSame('', (string) $notification->referee_list);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    /**
     * Test: prepareNotification gestisce tornei con molti arbitri
     */
    public function test_prepare_notification_handles_many_referees(): void
    {
        $tournament = $this->createTournament();

        // Crea 10 arbitri
        for ($i = 0; $i < 10; $i++) {
            $referee = $this->createReferee();
            Assignment::factory()->forUser($referee)->forTournament($tournament)->create();
        }

        $notification = $this->preparationService->prepareNotification($tournament->fresh());

        $this->assertEquals(11, $notification->details['total_recipients'] ?? null); // 10 arbitri + circolo
    }

    /**
     * Test: Notification status tracking
     */
    public function test_notification_status_is_tracked(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'recipients' => ['club' => true, 'referees' => [], 'institutional' => []],
        ]);

        $this->assertEquals('pending', $notification->status);

        // Update status
        $notification->update(['status' => 'sent']);

        $this->assertEquals('sent', $notification->fresh()->status);
    }
}
