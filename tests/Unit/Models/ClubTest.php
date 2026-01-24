<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\Zone;
use Tests\TestCase;

class ClubTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: Club belongs to Zone
     */
    public function test_club_belongs_to_zone(): void
    {
        $zone = Zone::first();
        $club = Club::factory()->create(['zone_id' => $zone->id]);

        $this->assertInstanceOf(Zone::class, $club->zone);
        $this->assertEquals($zone->id, $club->zone->id);
    }

    /**
     * Test: Club ha molti tournaments
     */
    public function test_club_has_many_tournaments(): void
    {
        $club = Club::factory()->create();

        Tournament::factory()->count(3)->create(['club_id' => $club->id]);

        $this->assertCount(3, $club->fresh()->tournaments);
    }

    // ==========================================
    // SCOPE TESTS
    // ==========================================

    /**
     * Test: scopeActive filtra club attivi
     */
    public function test_scope_active_filters_active_clubs(): void
    {
        Club::factory()->count(3)->create(['is_active' => true]);
        Club::factory()->count(2)->inactive()->create();

        $activeClubs = Club::active()->get();

        $this->assertCount(3, $activeClubs);
        $activeClubs->each(function ($club) {
            $this->assertEquals(1, $club->is_active); // SQLite ritorna 1
        });
    }

    /**
     * Test: scopeOrdered ordina per nome
     */
    public function test_scope_ordered_sorts_by_name(): void
    {
        Club::factory()->create(['name' => 'Zebra Club']);
        Club::factory()->create(['name' => 'Alpha Club']);
        Club::factory()->create(['name' => 'Beta Club']);

        $clubs = Club::ordered()->get();

        $this->assertEquals('Alpha Club', $clubs->first()->name);
        $this->assertEquals('Zebra Club', $clubs->last()->name);
    }

    /**
     * Test: scopeVisible - super admin vede tutto
     */
    public function test_scope_visible_super_admin_sees_all(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Club::factory()->count(5)->create();

        $visible = Club::visible($superAdmin)->get();

        $this->assertGreaterThanOrEqual(5, $visible->count());
    }

    /**
     * Test: scopeVisible - national admin vede tutto
     */
    public function test_scope_visible_national_admin_sees_all(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        Club::factory()->count(5)->create();

        $visible = Club::visible($nationalAdmin)->get();

        $this->assertGreaterThanOrEqual(5, $visible->count());
    }

    /**
     * Test: scopeVisible - zone admin vede solo propria zona
     */
    public function test_scope_visible_zone_admin_sees_own_zone_only(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);

        Club::factory()->inZone(1)->count(3)->create();
        Club::factory()->inZone(2)->count(2)->create();

        $visible = Club::visible($zoneAdmin)->get();

        $this->assertEquals(3, $visible->count());
        $visible->each(function ($club) {
            $this->assertEquals(1, $club->zone_id);
        });
    }

    /**
     * Test: scopeVisible senza user ritorna vuoto
     */
    public function test_scope_visible_without_user_returns_empty(): void
    {
        Club::factory()->count(5)->create();

        $visible = Club::visible(null)->get();

        $this->assertCount(0, $visible);
    }

    // ==========================================
    // FACTORY TESTS
    // ==========================================

    /**
     * Test: ClubFactory crea club valido
     */
    public function test_club_factory_creates_valid_club(): void
    {
        $club = Club::factory()->create();

        $this->assertNotNull($club->name);
        $this->assertNotNull($club->zone_id);
        $this->assertNotNull($club->code);
        $this->assertTrue($club->is_active);
    }

    /**
     * Test: ClubFactory inZone state
     */
    public function test_club_factory_in_zone_state(): void
    {
        $club = Club::factory()->inZone(3)->create();

        $this->assertEquals(3, $club->zone_id);
    }

    /**
     * Test: ClubFactory inactive state
     */
    public function test_club_factory_inactive_state(): void
    {
        $club = Club::factory()->inactive()->create();

        $this->assertFalse((bool) $club->is_active);
    }

    // ==========================================
    // FIELD VALIDATION TESTS
    // ==========================================

    /**
     * Test: Club ha campi required
     */
    public function test_club_has_required_fields(): void
    {
        $club = Club::factory()->create();

        $this->assertNotNull($club->name);
        $this->assertNotNull($club->zone_id);
        $this->assertNotNull($club->code);
    }

    /**
     * Test: Club code è unique
     */
    public function test_club_code_is_unique(): void
    {
        $code = 'GC123XY';

        Club::factory()->create(['code' => $code]);

        // Tentativo di creare con stesso code deve fallire
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Club::factory()->create(['code' => $code]);
    }

    /**
     * Test: is_active è boolean
     */
    public function test_is_active_is_boolean(): void
    {
        $club = Club::factory()->create(['is_active' => true]);

        $this->assertTrue(is_bool($club->is_active) || $club->is_active === 1);
    }
}
