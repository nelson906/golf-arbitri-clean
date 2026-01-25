<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\User;
use App\Models\Zone;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: Zone ha molti clubs
     */
    public function test_zone_has_many_clubs(): void
    {
        $zone = Zone::first();

        Club::factory()->count(3)->create(['zone_id' => $zone->id]);

        $this->assertCount(3, $zone->fresh()->clubs);
    }

    /**
     * Test: Zone ha molti users (referees/admins)
     */
    public function test_zone_has_many_users(): void
    {
        $zone = Zone::first();

        User::factory()->referee()->inZone($zone->id)->count(2)->create();
        User::factory()->zoneAdmin()->inZone($zone->id)->create();

        $this->assertCount(3, $zone->fresh()->users);
    }

    // ==========================================
    // ATTRIBUTE TESTS
    // ==========================================

    /**
     * Test: Zone ha code
     */
    public function test_zone_has_code(): void
    {
        $zone = Zone::where('code', 'SZR1')->first();

        $this->assertEquals('SZR1', $zone->code);
        $this->assertNotEmpty($zone->name);
    }

    /**
     * Test: Zone CRC esiste
     */
    public function test_crc_zone_exists(): void
    {
        $crc = Zone::where('code', 'CRC')->first();

        $this->assertNotNull($crc);
        $this->assertEquals('CRC', $crc->code);
    }

    // ==========================================
    // SCOPE TESTS
    // ==========================================

    /**
     * Test: Tutte le zone sono attive di default
     */
    public function test_all_zones_are_active_by_default(): void
    {
        $zones = Zone::all();

        $zones->each(function ($zone) {
            // SQLite ritorna 1/0 non true/false
            $this->assertEquals(1, $zone->is_active);
        });
    }

    /**
     * Test: Zone sono ordinate correttamente
     */
    public function test_zones_can_be_ordered(): void
    {
        $zones = Zone::orderBy('name')->get();

        $this->assertGreaterThan(0, $zones->count());

        // Verifica che siano ordinate
        $names = $zones->pluck('name')->toArray();
        $sorted = $names;
        sort($sorted);

        $this->assertEquals($sorted, $names);
    }
}
