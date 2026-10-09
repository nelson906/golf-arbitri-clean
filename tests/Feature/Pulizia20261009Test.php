<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Decisioni del 9 ottobre 2026 sugli orfani trovati da OrfaniTest.
 */
class Pulizia20261009Test extends TestCase
{
    public function test_zone_statistics_are_linked_for_crc_only(): void
    {
        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.statistics.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.statistics.zone'), false);

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.statistics.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.statistics.zone'), false);
    }

    public function test_duplicate_availability_removal_route_is_gone(): void
    {
        // Si toglie da "Le mie disponibilita'", dalla pagina del torneo o
        // togliendo la spunta: tutti passano da store / saveBatch.
        $this->assertFalse(Route::has('user.availability.destroy'));
        $this->assertTrue(Route::has('user.availability.store'));
        $this->assertTrue(Route::has('user.availability.saveBatch'));
    }

    public function test_unused_breeze_components_are_gone(): void
    {
        foreach (['danger-button', 'modal', 'secondary-button'] as $component) {
            $this->assertFileDoesNotExist(resource_path("views/components/{$component}.blade.php"));
        }
    }
}
