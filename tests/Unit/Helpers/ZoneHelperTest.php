<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ZoneHelper;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
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
        
        $this->assertIsString($result);
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
     * Test: getAllFolderCodes ritorna array di codici
     */
    public function test_get_all_folder_codes_returns_array(): void
    {
        $codes = ZoneHelper::getAllFolderCodes();

        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);
        $this->assertContains('CRC', $codes);
    }

    /**
     * Test: getZoneName ritorna nome zona esistente
     */
    public function test_get_zone_name_returns_existing_zone_name(): void
    {
        $zone = Zone::first();
        
        if ($zone) {
            $result = ZoneHelper::getZoneName($zone->id);
            $this->assertEquals($zone->name, $result);
        } else {
            $this->markTestSkipped('No zones in database');
        }
    }

    /**
     * Test: getZoneName gestisce null
     */
    public function test_get_zone_name_handles_null(): void
    {
        $result = ZoneHelper::getZoneName(null);
        
        $this->assertEquals('Zona Non Specificata', $result);
    }

    /**
     * Test: getZoneName gestisce zona inesistente
     */
    public function test_get_zone_name_handles_non_existent_zone(): void
    {
        $result = ZoneHelper::getZoneName(9999);
        
        $this->assertStringContainsString('9999', $result);
    }

    /**
     * Test: getEmailPattern genera pattern corretto
     */
    public function test_get_email_pattern_generates_correct_pattern(): void
    {
        $result = ZoneHelper::getEmailPattern(1);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('@', $result);
    }

    /**
     * Test: userHasAccessToZone - super admin ha accesso a tutte le zone
     */
    public function test_user_has_access_to_zone_super_admin_has_access_to_all(): void
    {
        $superAdmin = $this->createSuperAdmin();

        for ($zoneId = 1; $zoneId <= 7; $zoneId++) {
            $this->assertTrue(
                ZoneHelper::userHasAccessToZone($superAdmin, $zoneId),
                "Super admin should have access to zone {$zoneId}"
            );
        }
    }

    /**
     * Test: userHasAccessToZone - national admin ha accesso a tutte le zone
     */
    public function test_user_has_access_to_zone_national_admin_has_access_to_all(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        for ($zoneId = 1; $zoneId <= 7; $zoneId++) {
            $this->assertTrue(
                ZoneHelper::userHasAccessToZone($nationalAdmin, $zoneId),
                "National admin should have access to zone {$zoneId}"
            );
        }
    }

    /**
     * Test: userHasAccessToZone - zone admin ha accesso solo alla propria zona
     */
    public function test_user_has_access_to_zone_zone_admin_has_access_to_own_zone_only(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);

        $this->assertTrue(
            ZoneHelper::userHasAccessToZone($zoneAdmin, 1),
            "Zone admin should have access to their own zone"
        );

        $this->assertFalse(
            ZoneHelper::userHasAccessToZone($zoneAdmin, 2),
            "Zone admin should not have access to other zones"
        );
    }

    /**
     * Test: userHasAccessToZone - referee ha accesso solo alla propria zona
     */
    public function test_user_has_access_to_zone_referee_has_access_to_own_zone_only(): void
    {
        $referee = $this->createReferee(['zone_id' => 3]);

        $this->assertTrue(
            ZoneHelper::userHasAccessToZone($referee, 3),
            "Referee should have access to their own zone"
        );

        $this->assertFalse(
            ZoneHelper::userHasAccessToZone($referee, 4),
            "Referee should not have access to other zones"
        );
    }
}
