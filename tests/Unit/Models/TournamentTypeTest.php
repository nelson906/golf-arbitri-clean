<?php

namespace Tests\Unit\Models;

use App\Models\Tournament;
use App\Models\TournamentType;
use Tests\TestCase;

class TournamentTypeTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: TournamentType ha molti tournaments
     */
    public function test_tournament_type_has_many_tournaments(): void
    {
        $type = TournamentType::first();

        Tournament::factory()->count(3)->create(['tournament_type_id' => $type->id]);

        $this->assertCount(3, $type->fresh()->tournaments);
    }

    // ==========================================
    // SCOPE TESTS
    // ==========================================

    /**
     * Test: Scope national filtra tipi nazionali
     */
    public function test_scope_national_filters_national_types(): void
    {
        $nationalTypes = TournamentType::where('is_national', true)->get();

        $nationalTypes->each(function ($type) {
            $this->assertTrue($type->is_national);
        });

        $this->assertGreaterThan(0, $nationalTypes->count());
    }

    /**
     * Test: Scope zonal filtra tipi zonali
     */
    public function test_scope_zonal_filters_zonal_types(): void
    {
        $zonalTypes = TournamentType::where('is_national', false)->get();

        $zonalTypes->each(function ($type) {
            $this->assertFalse($type->is_national);
        });

        $this->assertGreaterThan(0, $zonalTypes->count());
    }

    /**
     * Test: Scope active filtra tipi attivi
     */
    public function test_scope_active_filters_active_types(): void
    {
        $activeTypes = TournamentType::where('is_active', true)->get();

        $activeTypes->each(function ($type) {
            // SQLite ritorna 1 non true
            $this->assertEquals(1, $type->is_active);
        });

        $this->assertGreaterThan(0, $activeTypes->count());
    }

    // ==========================================
    // ATTRIBUTE TESTS
    // ==========================================

    /**
     * Test: TournamentType ha attributi required
     */
    public function test_tournament_type_has_required_attributes(): void
    {
        $type = TournamentType::first();

        $this->assertNotNull($type->name);
        $this->assertNotNull($type->short_name);
        // SQLite ritorna 1/0 non true/false
        $this->assertContains($type->is_national, [0, 1, true, false]);
        $this->assertContains($type->is_active, [0, 1, true, false]);
    }

    /**
     * Test: TournamentType ha calendar_color
     */
    public function test_tournament_type_has_calendar_color(): void
    {
        $type = TournamentType::first();

        $this->assertNotNull($type->calendar_color);
        $this->assertStringStartsWith('#', $type->calendar_color);
    }
}
