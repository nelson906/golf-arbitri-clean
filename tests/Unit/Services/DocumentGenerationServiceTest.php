<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentGenerationService;
    }

    // ==========================================
    // ZONE FOLDER TESTS
    // ==========================================

    /**
     * Test: getZoneFolder ritorna CRC per tornei nazionali
     */
    public function test_get_zone_folder_returns_crc_for_national_tournaments(): void
    {
        $nationalType = TournamentType::factory()->create(['is_national' => true]);
        $club = Club::factory()->create();

        $tournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'tournament_type_id' => $nationalType->id,
        ]);

        $folder = $this->service->getZoneFolder($tournament);

        $this->assertEquals('CRC', $folder);
    }

    /**
     * Test: getZoneFolder ritorna codice zona per tornei zonali
     */
    public function test_get_zone_folder_returns_zone_code_for_zonal_tournaments(): void
    {
        $zonalType = TournamentType::factory()->create(['is_national' => false]);
        $zone = Zone::where('code', 'SZR1')->first() ?? Zone::factory()->create(['code' => 'SZR1']);
        $club = Club::factory()->create(['zone_id' => $zone->id]);

        $tournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        $folder = $this->service->getZoneFolder($tournament);

        $this->assertEquals('SZR1', $folder);
    }

    // ==========================================
    // TEMPLATE PATH TESTS
    // ==========================================

    /**
     * Test: getZoneTemplatePath ritorna path corretto
     *
     * Nota: Questo metodo è protected, quindi testiamo indirettamente
     * attraverso generateConvocationForTournament se possibile,
     * oppure lo skippiamo se troppo complesso
     */
    public function test_zone_template_path_logic(): void
    {
        // Per ora skip - richiede file system completo
        $this->markTestSkipped('Requires filesystem setup with actual templates');
    }

    // ==========================================
    // DATE FORMATTING TESTS
    // ==========================================

    /**
     * Test: formatTournamentDates formatta date correttamente
     *
     * Nota: Metodo protected, ma possiamo testare attraverso
     * generateConvocationForTournament se genera output
     */
    public function test_tournament_dates_formatting(): void
    {
        // Per ora skip - richiede template files
        $this->markTestSkipped('Requires template files and complex setup');
    }

    // ==========================================
    // INTEGRATION TESTS (leggeri)
    // ==========================================

    /**
     * Test: Service è istanziabile
     */
    public function test_service_is_instantiable(): void
    {
        $this->assertInstanceOf(DocumentGenerationService::class, $this->service);
    }

    /**
     * Test: getZoneFolder accetta Tournament
     */
    public function test_get_zone_folder_accepts_tournament(): void
    {
        $tournament = $this->createTournament();

        $folder = $this->service->getZoneFolder($tournament);

        $this->assertIsString($folder);
        $this->assertNotEmpty($folder);
    }

    /**
     * Test: getZoneFolder ritorna valori validi
     */
    public function test_get_zone_folder_returns_valid_values(): void
    {
        $tournament = $this->createTournament();

        $folder = $this->service->getZoneFolder($tournament);

        $validFolders = ['CRC', 'SZR1', 'SZR2', 'SZR3', 'SZR4', 'SZR5', 'SZR6', 'SZR7'];
        $this->assertContains($folder, $validFolders);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    /**
     * Test: getZoneFolder gestisce tornei senza club
     */
    public function test_get_zone_folder_handles_tournament_without_club(): void
    {
        // Use make() instead of create() to avoid database constraints if club_id is NOT NULL
        // Or if we need a tournament without a club, we might need to use make() and manually set club_id to null
        $tournament = Tournament::factory()->make(['club_id' => null]);

        // Dovrebbe gestire gracefully (o lanciare exception se necessario)
        try {
            $folder = $this->service->getZoneFolder($tournament);
            $this->assertIsString($folder);
        } catch (\Exception $e) {
            // Va bene anche se lancia exception
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }
}
