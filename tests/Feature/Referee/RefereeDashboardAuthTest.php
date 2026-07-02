<?php

namespace Tests\Feature\Referee;

use Tests\TestCase;

/**
 * Test di regressione — Bug: route /referee/dashboard era definita senza
 * middleware 'auth', esponendo potenzialmente le view a utenti non autenticati.
 *
 * Fix applicato: aggiunto middleware ['auth', 'referee_or_admin'] al gruppo
 * in routes/referee/dashboard.php.
 *
 * AGGIORNAMENTO (audit 2026-07): le route legacy /referee/quadranti/* sono
 * state RIMOSSE (mai referenziate; la versione attiva è user.quadranti.*).
 * I test relativi ora verificano che NON esistano più.
 *
 * @see routes/referee/dashboard.php
 */
class RefereeDashboardAuthTest extends TestCase
{
    // ============================================================
    // SEZIONE 1 — Accesso NON autenticato (deve redirectare a /login)
    // ============================================================

    /**
     * Regressione: GET /referee/dashboard senza autenticazione
     * deve redirectare al login, NON restituire 200.
     */
    public function test_unauthenticated_user_is_redirected_from_referee_dashboard(): void
    {
        $response = $this->get('/referee/dashboard');

        // Deve redirectare (302) — non 200 né 500
        $response->assertRedirect(route('login'));
    }

    // ============================================================
    // SEZIONE 2 — Accesso autenticato come arbitro (deve funzionare)
    // ============================================================

    /**
     * Un arbitro autenticato deve poter accedere alla propria dashboard.
     */
    public function test_authenticated_referee_can_access_referee_dashboard(): void
    {
        $referee = $this->createReferee();

        $response = $this->actingAs($referee)
            ->get('/referee/dashboard');

        $response->assertStatus(200);
    }

    // ============================================================
    // SEZIONE 3 — Accesso autenticato come admin (deve funzionare)
    // ============================================================

    /**
     * Un admin di zona autenticato deve poter accedere alla dashboard arbitro
     * (middleware referee_or_admin permette entrambi).
     */
    public function test_authenticated_zone_admin_can_access_referee_dashboard(): void
    {
        $admin = $this->createZoneAdmin();

        $response = $this->actingAs($admin)
            ->get('/referee/dashboard');

        $response->assertStatus(200);
    }

    // ============================================================
    // SEZIONE 4 — Named routes dopo il refactoring
    // ============================================================

    /**
     * Verifica che il nome di route 'referee.dashboard' sia ancora definito
     * dopo il refactoring (per non rompere i redirect in DashboardController).
     */
    public function test_referee_dashboard_named_route_is_defined(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('referee.dashboard'),
            "La named route 'referee.dashboard' deve esistere (usata in DashboardController)"
        );
    }

    /**
     * Regressione inversa (audit 2026-07): le route legacy quadranti
     * NON devono più esistere — la versione attiva è user.quadranti.*.
     */
    public function test_legacy_referee_quadranti_routes_are_removed(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('referee.quadranti.index'),
            "La route legacy 'referee.quadranti.index' deve essere stata rimossa"
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('user.quadranti.index'),
            "La route attiva 'user.quadranti.index' deve esistere"
        );
    }
}
