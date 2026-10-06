<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ZoneHelper;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use Tests\TestCase;

class ZoneHelperTest extends TestCase
{
    /**
     * Test: getFolderCode ritorna codice corretto per zona valida
     */
    public function test_get_folder_code_returns_correct_code_for_valid_zone(): void
    {
        $result = ZoneHelper::getFolderCode(1);

        $this->assertStringStartsWith('SZR', $result);
    }

    /**
     * Test: getFolderCode gestisce null
     */
    public function test_get_folder_code_handles_null(): void
    {
        $result = ZoneHelper::getFolderCode(null);

        $this->assertEquals('SZR0', $result);
    }

    /**
     * Test: getFolderCode per zone 1-7
     */
    public function test_get_folder_code_for_all_zones(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $result = ZoneHelper::getFolderCode($i);
            $this->assertNotEmpty($result);
            $this->assertStringStartsWith('SZR', $result);
        }
    }

    /**
     * Test: getFolderCodeForTournament ritorna CRC per tornei nazionali
     */
    public function test_get_folder_code_for_tournament_returns_crc_for_national(): void
    {
        // Crea tipo torneo nazionale
        $nationalType = TournamentType::factory()->national()->create();

        // Crea torneo nazionale
        $tournament = Tournament::factory()
            ->ofType($nationalType->id)
            ->create();

        $result = ZoneHelper::getFolderCodeForTournament($tournament);

        $this->assertEquals('CRC', $result);
    }

    /**
     * Test: getFolderCodeForTournament ritorna zona per tornei zonali
     */
    public function test_get_folder_code_for_tournament_returns_zone_for_zonal(): void
    {
        // Crea tipo torneo zonale
        $zonalType = TournamentType::factory()->zonal()->create();

        // Crea torneo zonale
        $tournament = Tournament::factory()
            ->inZone(1)
            ->ofType($zonalType->id)
            ->create();

        $result = ZoneHelper::getFolderCodeForTournament($tournament);

        $this->assertStringStartsWith('SZR', $result);
    }

    /**
     * Test: isTournamentNational identifica tornei nazionali
     */
    public function test_is_tournament_national_identifies_national_tournaments(): void
    {
        $nationalType = TournamentType::factory()->national()->create();
        $tournament = Tournament::factory()
            ->ofType($nationalType->id)
            ->create();

        $result = ZoneHelper::isTournamentNational($tournament);

        $this->assertTrue($result);
    }

    /**
     * Test: isTournamentNational identifica tornei non nazionali
     */
    public function test_is_tournament_national_identifies_non_national_tournaments(): void
    {
        $zonalType = TournamentType::factory()->zonal()->create();
        $tournament = Tournament::factory()
            ->ofType($zonalType->id)
            ->create();

        $result = ZoneHelper::isTournamentNational($tournament);

        $this->assertFalse($result);
    }

    /**
     * Test: getEmailPattern genera pattern corretto
     */
    public function test_get_email_pattern_generates_correct_pattern(): void
    {
        $result = ZoneHelper::getEmailPattern(1);

        $this->assertStringContainsString('@', $result);
    }

}
