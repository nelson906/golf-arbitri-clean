<?php

namespace Tests\Feature\Admin;

use App\Enums\TournamentStatus;
use App\Enums\UserType;
use App\Models\Tournament;
use Tests\TestCase;

/**
 * Test di regressione per i middleware con Enum cast.
 *
 * I middleware AdminOrSuperAdmin e ZoneAdmin confrontavano il campo user_type
 * con stringhe tramite in_array(), che restituisce sempre false quando il valore
 * è un'istanza Enum (dopo l'introduzione del cast Eloquent).
 *
 * Effetti: tutti gli admin ricevevano 403 sulle route admin.
 *
 * Fix applicato: sostituzione di in_array() con $userType?->isAdmin() ?? false.
 */
class EnumMiddlewareRegressionTest extends TestCase
{
    // ============================================================
    // SEZIONE 1 — Accesso alle route admin
    // ============================================================

    /**
     * Regressione: lo ZoneAdmin otteneva 403 dopo l'introduzione del cast Enum.
     * Deve poter accedere all'elenco tornei admin.
     */
    public function test_zone_admin_can_access_admin_tournaments_index(): void
    {
        $zoneAdmin = $this->createZoneAdmin();

        $response = $this->actingAs($zoneAdmin)->get(route('admin.tournaments.index'));

        $response->assertStatus(200);
    }

    /**
     * Regressione: il NationalAdmin otteneva 403.
     */
    public function test_national_admin_can_access_admin_tournaments_index(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        $response = $this->actingAs($nationalAdmin)->get(route('admin.tournaments.index'));

        $response->assertStatus(200);
    }

    /**
     * Regressione: il SuperAdmin otteneva 403.
     */
    public function test_super_admin_can_access_admin_tournaments_index(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get(route('admin.tournaments.index'));

        $response->assertStatus(200);
    }

    /**
     * Il Referee non deve poter accedere alle route admin.
     */
    public function test_referee_cannot_access_admin_tournaments_index(): void
    {
        $referee = $this->createReferee();

        $response = $this->actingAs($referee)->get(route('admin.tournaments.index'));

        $this->assertContains($response->status(), [403, 302],
            'Il referee deve ricevere 403 o essere rediretto');
    }

    /**
     * Un utente non autenticato non deve poter accedere alle route admin.
     */
    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->get(route('admin.tournaments.index'));

        $this->assertContains($response->status(), [403, 302],
            "L'utente non autenticato deve essere rediretto o ricevere 403");
    }

    // ============================================================
    // SEZIONE 2 — Pagina creazione torneo
    // ============================================================

    /**
     * Regressione: lo ZoneAdmin deve poter vedere il form creazione torneo.
     */
    public function test_zone_admin_can_access_tournament_create_page(): void
    {
        $zoneAdmin = $this->createZoneAdmin();

        $response = $this->actingAs($zoneAdmin)->get(route('admin.tournaments.create'));

        $response->assertStatus(200);
    }

    /**
     * Il SuperAdmin deve poter vedere il form creazione torneo.
     */
    public function test_super_admin_can_access_tournament_create_page(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get(route('admin.tournaments.create'));

        $response->assertStatus(200);
    }

    // ============================================================
    // SEZIONE 3 — Accesso al dettaglio torneo
    // ============================================================

    /**
     * Regressione: lo ZoneAdmin deve poter vedere il dettaglio di un torneo.
     */
    public function test_zone_admin_can_view_tournament_detail(): void
    {
        $zoneAdmin  = $this->createZoneAdmin(1);
        $tournament = $this->createTournament();

        $response = $this->actingAs($zoneAdmin)
            ->get(route('admin.tournaments.show', $tournament));

        $response->assertStatus(200);
    }

    /**
     * Regressione: il NationalAdmin deve poter vedere il dettaglio di un torneo.
     */
    public function test_national_admin_can_view_tournament_detail(): void
    {
        $nationalAdmin = $this->createNationalAdmin();
        $tournament    = $this->createTournament();

        $response = $this->actingAs($nationalAdmin)
            ->get(route('admin.tournaments.show', $tournament));

        $response->assertStatus(200);
    }

    // ============================================================
    // SEZIONE 4 — Verifica stato Enum nel database
    // ============================================================

    /**
     * Regressione: dopo la creazione, il campo status nel DB deve essere una stringa,
     * e Eloquent deve ricaricarla come istanza Enum corretta.
     */
    public function test_tournament_status_is_persisted_and_reloaded_as_enum(): void
    {
        $tournament = $this->createTournament(['status' => 'open']);

        // Ricarica fresh dal DB
        $reloaded = Tournament::find($tournament->id);

        $this->assertInstanceOf(TournamentStatus::class, $reloaded->status,
            'Dopo il ricaricamento dal DB, status deve essere castato a TournamentStatus');
        $this->assertSame(TournamentStatus::Open, $reloaded->status);
        $this->assertSame('open', $reloaded->status->value);
    }

    /**
     * Regressione: il campo user_type nel DB deve essere una stringa,
     * e Eloquent deve ricaricarla come istanza Enum corretta.
     */
    public function test_user_type_is_persisted_and_reloaded_as_enum(): void
    {
        $zoneAdmin = $this->createZoneAdmin();

        // Ricarica fresh dal DB
        $reloaded = \App\Models\User::find($zoneAdmin->id);

        $this->assertInstanceOf(UserType::class, $reloaded->user_type,
            'Dopo il ricaricamento dal DB, user_type deve essere castato a UserType');
        $this->assertSame(UserType::ZoneAdmin, $reloaded->user_type);
        $this->assertSame('admin', $reloaded->user_type->value);
    }

    // ============================================================
    // SEZIONE 5 — Creazione torneo: status enum nel form
    // ============================================================

    /**
     * Regressione: creare un torneo con status stringa deve funzionare e
     * il torneo salvato deve avere l'Enum corretto.
     */
    public function test_creating_tournament_with_string_status_stores_correct_enum(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club  = $this->createClub(['zone_id' => 1]);
        $type  = \App\Models\TournamentType::first();

        $data = [
            'name'                   => 'Torneo Regression Test',
            'club_id'                => $club->id,
            'tournament_type_id'     => $type->id,
            'start_date'             => '2026-09-10',
            'end_date'               => '2026-09-12',
            'availability_deadline'  => '2026-09-01 23:59:59',
            'status'                 => 'open',
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $data);

        // Deve redirigere (non 422 o 500)
        $response->assertRedirect();

        $tournament = Tournament::where('name', 'Torneo Regression Test')->first();
        $this->assertNotNull($tournament);
        $this->assertInstanceOf(TournamentStatus::class, $tournament->status);
        $this->assertSame('open', $tournament->status->value);
    }
}
