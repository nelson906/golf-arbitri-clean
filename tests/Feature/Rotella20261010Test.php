<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Rotella di caricamento su tutte le pagine (2026-10-10).
 */
class Rotella20261010Test extends TestCase
{
    public function test_every_layout_has_the_loading_indicator(): void
    {
        // Ospite (login), amministrazione, arbitro
        $this->get(route('login'))->assertOk()->assertSee('id="page-loading"', false);

        $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.referees.curricula'))
            ->assertOk()
            ->assertSee('id="page-loading"', false);

        $this->actingAs($this->createReferee())
            ->get(route('user.curriculum'))
            ->assertOk()
            ->assertSee('id="page-loading"', false);
    }
}
