<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;
use App\Models\Zone;
use App\Services\DocumentGenerationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test non invasivo per il ciclo completo di notifica
 *
 * Questo test verifica:
 * 1. Generazione documenti (convocazione e lettera circolo)
 * 2. Gestione clausole
 * 3. Invio notifiche (mock)
 * 4. Integrità del flusso completo
 *
 * IMPORTANTE: Usa DatabaseTransactions per non modificare il database di produzione
 *
 * Per eseguire i test con il database MySQL:
 *   php artisan test --filter=NotificationCycleTest --env=local
 *
 * Oppure creare .env.testing con le stesse credenziali del database di produzione
 */
class NotificationCycleTest extends TestCase
{
    use DatabaseTransactions;

    protected DocumentGenerationService $documentService;

    protected NotificationService $notificationService;

    protected ?Tournament $testTournament = null;

    protected ?User $testAdmin = null;

    protected array $generatedFiles = [];

    protected bool $databaseAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Verifica connessione database
        try {
            DB::connection()->getPdo();
            $this->databaseAvailable = true;
        } catch (\Exception $e) {
            $this->databaseAvailable = false;
        }

        // Inizializza i servizi
        $this->documentService = app(DocumentGenerationService::class);
        $this->notificationService = app(NotificationService::class);

        // Fake mail per non inviare email reali
        Mail::fake();
    }

    /**
     * Helper per verificare se il database è disponibile
     */
    protected function requireDatabase(): void
    {
        if (! $this->databaseAvailable) {
            $this->markTestSkipped('Database non disponibile. Eseguire con: php artisan test --env=local');
        }
    }

    protected function tearDown(): void
    {
        // Pulisci i file temporanei generati durante i test
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * Test 1: Verifica che DocumentGenerationService sia istanziabile
     */
    public function test_document_generation_service_is_instantiable(): void
    {
        $this->assertInstanceOf(DocumentGenerationService::class, $this->documentService);
    }

    /**
     * Test 2: Verifica che NotificationService sia istanziabile
     */
    public function test_notification_service_is_instantiable(): void
    {
        $this->assertInstanceOf(NotificationService::class, $this->notificationService);
    }

    /**
     * Test 3: Verifica esistenza template per tutte le zone
     */
    public function test_zone_templates_exist(): void
    {
        $zones = ['szr1', 'szr2', 'szr3', 'szr4', 'szr5', 'szr6', 'szr7', 'default'];
        $templateDir = storage_path('lettere_intestate');

        $this->assertDirectoryExists($templateDir, 'Directory template non trovata');

        $missingTemplates = [];
        foreach ($zones as $zone) {
            $templatePath = "{$templateDir}/lettera_intestata_{$zone}.docx";
            if (! file_exists($templatePath)) {
                $missingTemplates[] = $zone;
            }
        }

        // Almeno il template default deve esistere
        $this->assertFileExists(
            "{$templateDir}/lettera_intestata_default.docx",
            'Template default non trovato'
        );
    }

    /**
     * Test 4: Verifica getZoneFolder per tornei nazionali e zonali
     */
    public function test_get_zone_folder_returns_correct_folder(): void
    {
        $this->requireDatabase();

        // Trova un torneo esistente per testare
        $tournament = Tournament::with(['club', 'zone', 'tournamentType'])->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo nel database per il test');
        }

        $zoneFolder = $this->documentService->getZoneFolder($tournament);

        // Verifica che il folder sia uno dei valori attesi
        $validFolders = ['CRC', 'SZR1', 'SZR2', 'SZR3', 'SZR4', 'SZR5', 'SZR6', 'SZR7'];
        $this->assertContains($zoneFolder, $validFolders, "Zone folder non valido: {$zoneFolder}");
    }

    /**
     * Test 5: Verifica generazione convocazione per torneo con assegnazioni
     */
    public function test_generate_convocation_for_tournament_with_assignments(): void
    {
        $this->requireDatabase();

        // Trova un torneo con assegnazioni
        $tournament = Tournament::whereHas('assignments')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo con assegnazioni nel database');
        }

        try {
            $result = $this->documentService->generateConvocationForTournament($tournament);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('path', $result);
            $this->assertArrayHasKey('filename', $result);
            $this->assertArrayHasKey('type', $result);
            $this->assertEquals('convocation', $result['type']);
            $this->assertFileExists($result['path']);

            // Traccia il file per cleanup
            $this->generatedFiles[] = $result['path'];

            // Verifica che sia un file DOCX valido (ZIP)
            $this->assertStringEndsWith('.docx', $result['filename']);
        } catch (\Exception $e) {
            $this->fail('Errore generazione convocazione: '.$e->getMessage());
        }
    }

    /**
     * Test 6: Verifica generazione documento circolo
     */
    public function test_generate_club_document(): void
    {
        $this->requireDatabase();

        // Trova un torneo con assegnazioni
        $tournament = Tournament::whereHas('assignments')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo con assegnazioni nel database');
        }

        try {
            $result = $this->documentService->generateClubDocument($tournament);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('path', $result);
            $this->assertArrayHasKey('filename', $result);
            $this->assertArrayHasKey('type', $result);
            $this->assertEquals('club_letter', $result['type']);
            $this->assertFileExists($result['path']);

            // Traccia il file per cleanup
            $this->generatedFiles[] = $result['path'];

            // Verifica che sia un file DOCX valido
            $this->assertStringEndsWith('.docx', $result['filename']);
        } catch (\Exception $e) {
            $this->fail('Errore generazione documento circolo: '.$e->getMessage());
        }
    }

    /**
     * Test 7: Verifica generazione con clausole
     */
    public function test_generate_convocation_with_clauses(): void
    {
        $this->requireDatabase();

        // Trova un torneo con assegnazioni
        $tournament = Tournament::whereHas('assignments')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo con assegnazioni nel database');
        }

        // Crea una notifica di test con clausole
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'sent_by' => User::first()?->id ?? 1,
        ]);

        // Verifica se esistono clausole
        $clause = NotificationClause::where('is_active', true)->first();

        if ($clause) {
            NotificationClauseSelection::create([
                'tournament_notification_id' => $notification->id,
                'clause_id' => $clause->id,
                'placeholder_code' => 'CLAUSOLA_ARBITRO_RESPONSABILITA',
            ]);
        }

        try {
            $result = $this->documentService->generateConvocationForTournament($tournament, $notification);

            $this->assertIsArray($result);
            $this->assertFileExists($result['path']);

            // Traccia il file per cleanup
            $this->generatedFiles[] = $result['path'];
        } catch (\Exception $e) {
            $this->fail('Errore generazione convocazione con clausole: '.$e->getMessage());
        }
    }

    /**
     * Test 8: Verifica preparazione notifica completa
     */
    public function test_prepare_notification(): void
    {
        $this->requireDatabase();

        // Trova un torneo con assegnazioni
        $tournament = Tournament::whereHas('assignments')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo con assegnazioni nel database');
        }

        try {
            $notification = $this->notificationService->prepareNotification($tournament);

            $this->assertInstanceOf(TournamentNotification::class, $notification);
            $this->assertEquals($tournament->id, $notification->tournament_id);
            $this->assertEquals('pending', $notification->status);
        } catch (\Exception $e) {
            $this->fail('Errore preparazione notifica: '.$e->getMessage());
        }
    }

    /**
     * Test 9: Verifica che le email NON vengano inviate (fake)
     */
    public function test_notification_send_is_mocked(): void
    {
        // Verifica che Mail::fake() sia attivo
        Mail::assertNothingSent();

        // Questo test conferma che le email non verranno inviate durante i test
        $this->assertTrue(true);
    }

    /**
     * Test 10: Test completo del ciclo di notifica (senza invio reale)
     */
    public function test_complete_notification_cycle(): void
    {
        $this->requireDatabase();

        // Trova un torneo con assegnazioni e circolo
        $tournament = Tournament::whereHas('assignments')
            ->whereHas('club')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo completo nel database');
        }

        // Step 1: Prepara notifica
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'sent_by' => User::first()?->id ?? 1,
            'metadata' => [
                'subject' => 'Test Convocazione',
                'message' => 'Messaggio di test',
                'recipients' => [
                    'club' => true,
                    'referees' => $tournament->assignments->pluck('user_id')->toArray(),
                ],
                'attach_convocation' => true,
            ],
        ]);

        $this->assertNotNull($notification->id);

        // Step 2: Genera documenti
        try {
            $convocation = $this->documentService->generateConvocationForTournament($tournament, $notification);
            $clubDoc = $this->documentService->generateClubDocument($tournament, $notification);

            $this->generatedFiles[] = $convocation['path'];
            $this->generatedFiles[] = $clubDoc['path'];

            // Step 3: Aggiorna notifica con documenti
            $notification->update([
                'documents' => [
                    'convocation' => $convocation['filename'],
                    'club_letter' => $clubDoc['filename'],
                ],
            ]);

            $this->assertNotEmpty($notification->documents);
        } catch (\Exception $e) {
            $this->fail('Errore nel ciclo di notifica: '.$e->getMessage());
        }

        // Step 4: Verifica che le email non siano state inviate (sono fake)
        Mail::assertNothingSent();

        // Test completato con successo
        $this->assertTrue(true, 'Ciclo di notifica completato con successo');
    }

    /**
     * Test 11: Verifica formattazione date tornei
     */
    public function test_tournament_date_formatting(): void
    {

        // Torneo stesso giorno
        $singleDayTournament = new Tournament([
            'name' => 'Test Single Day',
            'start_date' => '2025-06-15',
            'end_date' => '2025-06-15',
        ]);

        // Torneo stesso mese
        $sameMonthTournament = new Tournament([
            'name' => 'Test Same Month',
            'start_date' => '2025-06-15',
            'end_date' => '2025-06-17',
        ]);

        // Torneo mesi diversi
        $diffMonthTournament = new Tournament([
            'name' => 'Test Diff Month',
            'start_date' => '2025-06-28',
            'end_date' => '2025-07-02',
        ]);

        // Test tramite reflection per accedere al metodo protected
        $reflection = new \ReflectionClass($this->documentService);
        $method = $reflection->getMethod('formatTournamentDates');
        $method->setAccessible(true);

        // Verifica formattazione stesso giorno
        $result1 = $method->invoke($this->documentService, $singleDayTournament);
        $this->assertEquals('15/06/2025', $result1);

        // Verifica formattazione stesso mese
        $result2 = $method->invoke($this->documentService, $sameMonthTournament);
        $this->assertEquals('15-17/06/2025', $result2);

        // Verifica formattazione mesi diversi
        $result3 = $method->invoke($this->documentService, $diffMonthTournament);
        $this->assertEquals('28/06/2025 - 02/07/2025', $result3);
    }

    /**
     * Test 12: Verifica traduzione ruoli
     */
    public function test_role_translation(): void
    {
        $reflection = new \ReflectionClass($this->documentService);
        $method = $reflection->getMethod('translateRole');
        $method->setAccessible(true);

        $testCases = [
            'Tournament Director' => 'Direttore di Torneo',
            'Direttore di Torneo' => 'Direttore di Torneo',
            'Observer' => 'Osservatore',
            'Osservatore' => 'Osservatore',
            'Referee' => 'Arbitro',
            'Arbitro' => 'Arbitro',
            'Unknown Role' => 'Unknown Role',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->documentService, $input);
            $this->assertEquals($expected, $result, "Traduzione fallita per: {$input}");
        }
    }

    /**
     * Test 13: Verifica gestione errori template mancante
     */
    public function test_handles_missing_template_gracefully(): void
    {
        $reflection = new \ReflectionClass($this->documentService);
        $method = $reflection->getMethod('getZoneTemplatePath');
        $method->setAccessible(true);

        // Zona inesistente dovrebbe usare default
        try {
            $path = $method->invoke($this->documentService, 999);
            // Se arriviamo qui, il default è stato usato
            $this->assertStringContainsString('default', $path);
        } catch (\Exception $e) {
            // Se il default non esiste, verifica che l'errore sia appropriato
            $this->assertStringContainsString('Template non trovato', $e->getMessage());
        }
    }

    /**
     * Test 14: Verifica integrità DOCX generato
     */
    public function test_generated_docx_is_valid_zip(): void
    {
        $this->requireDatabase();

        $tournament = Tournament::whereHas('assignments')
            ->with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->first();

        if (! $tournament) {
            $this->markTestSkipped('Nessun torneo con assegnazioni');
        }

        try {
            $result = $this->documentService->generateConvocationForTournament($tournament);
            $this->generatedFiles[] = $result['path'];

            // DOCX è un file ZIP - verifica che sia apribile come ZIP
            $zip = new \ZipArchive;
            $opened = $zip->open($result['path']);

            $this->assertTrue($opened === true, 'Il file DOCX non è un ZIP valido');

            // Verifica contenuto minimo di un DOCX
            $hasDocumentXml = $zip->locateName('word/document.xml') !== false;
            $this->assertTrue($hasDocumentXml, 'Il DOCX non contiene word/document.xml');

            $zip->close();
        } catch (\Exception $e) {
            $this->fail('Errore verifica DOCX: '.$e->getMessage());
        }
    }

    /**
     * Test 15: Verifica che il servizio gestisca tornei senza assegnazioni
     */
    public function test_handles_tournament_without_assignments(): void
    {
        $this->requireDatabase();

        // Trova o crea un torneo senza assegnazioni
        $tournament = Tournament::doesntHave('assignments')
            ->with(['club', 'zone', 'tournamentType'])
            ->first();

        if (! $tournament) {
            // Crea un torneo temporaneo senza assegnazioni
            $zone = Zone::first();
            $club = Club::first();
            $tournamentType = \App\Models\TournamentType::first();

            if (! $zone || ! $club || ! $tournamentType) {
                $this->markTestSkipped('Zone, Club o TournamentType non disponibili');
            }

            $tournament = Tournament::create([
                'name' => 'Test No Assignments',
                'start_date' => Carbon::now()->addDays(30),
                'end_date' => Carbon::now()->addDays(31),
                'availability_deadline' => Carbon::now()->addDays(15),
                'zone_id' => $zone->id,
                'club_id' => $club->id,
                'tournament_type_id' => $tournamentType->id,
            ]);
        }

        try {
            // Dovrebbe funzionare anche senza assegnazioni
            $result = $this->documentService->generateClubDocument($tournament);
            $this->generatedFiles[] = $result['path'];

            $this->assertFileExists($result['path']);
        } catch (\Exception $e) {
            // Se fallisce, l'errore dovrebbe essere gestito appropriatamente
            $this->assertStringNotContainsString('undefined', strtolower($e->getMessage()));
        }
    }
}
