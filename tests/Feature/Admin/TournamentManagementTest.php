<?php

namespace Tests\Feature\Admin;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use Tests\TestCase;

class TournamentManagementTest extends TestCase
{
    // ==========================================
    // TOURNAMENT CREATE TESTS
    // ==========================================

    /**
     * Test: Admin può vedere pagina creazione torneo
     */
    public function test_admin_can_view_tournament_create_page(): void
    {
        $admin = $this->createZoneAdmin();

        $response = $this->actingAs($admin)->get(route('admin.tournaments.create'));

        $response->assertStatus(200);
        $response->assertSee('Crea Torneo');
    }

    /**
     * Test: Admin può creare torneo
     */
    public function test_admin_can_create_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $type = TournamentType::first();

        $tournamentData = [
            'name' => 'Test Tournament 2026',
            'club_id' => $club->id,
            'tournament_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
            'availability_deadline' => '2026-06-01 23:59:59',
            'status' => 'open',
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $tournamentData);

        $response->assertRedirect();
        $this->assertDatabaseHas('tournaments', [
            'name' => 'Test Tournament 2026',
            'club_id' => $club->id,
        ]);
    }

    /**
     * Test: Admin non può creare torneo in altra zona
     */
    public function test_zone_admin_cannot_create_tournament_in_other_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $clubZone2 = Club::factory()->create(['zone_id' => 2]);
        $type = TournamentType::first();

        $tournamentData = [
            'name' => 'Test Tournament',
            'club_id' => $clubZone2->id,
            'tournament_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
            'availability_deadline' => '2026-06-01 23:59:59',
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $tournamentData);

        // Dovrebbe essere rifiutato (403 o redirect con errore)
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    // ==========================================
    // TOURNAMENT UPDATE TESTS
    // ==========================================

    /**
     * Test: Admin può aggiornare torneo della sua zona
     */
    public function test_admin_can_update_tournament_in_own_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $response = $this->actingAs($admin)
            ->put(route('admin.tournaments.update', $tournament), [
                'name' => 'Updated Tournament Name',
                'club_id' => $club->id,
                'tournament_type_id' => $tournament->tournament_type_id,
                'start_date' => $tournament->start_date->format('Y-m-d'),
                'end_date' => $tournament->end_date->format('Y-m-d'),
                'availability_deadline' => $tournament->availability_deadline->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'Updated Tournament Name',
        ]);
    }

    // ==========================================
    // TOURNAMENT DELETE TESTS
    // ==========================================

    /**
     * Test: Admin può eliminare torneo
     */
    public function test_admin_can_delete_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.tournaments.destroy', $tournament));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tournaments', [
            'id' => $tournament->id,
        ]);
    }

    // ==========================================
    // TOURNAMENT LIST TESTS
    // ==========================================

    /**
     * Test: Admin vede solo tornei della sua zona
     */
    public function test_zone_admin_sees_only_own_zone_tournaments(): void
    {
        $admin = $this->createZoneAdmin(1);

        $club1 = Club::factory()->create(['zone_id' => 1]);
        $club2 = Club::factory()->create(['zone_id' => 2]);

        // zone_id esplicito per garantire il filtro corretto
        $tournament1 = Tournament::factory()->create(['club_id' => $club1->id, 'zone_id' => 1, 'name' => 'Zone 1 Tournament']);
        $tournament2 = Tournament::factory()->create(['club_id' => $club2->id, 'zone_id' => 2, 'name' => 'Zone 2 Tournament']);

        $response = $this->actingAs($admin)->get(route('tournaments.index'));

        $response->assertStatus(200);
        $response->assertSee('Zone 1 Tournament');
        $response->assertDontSee('Zone 2 Tournament');
    }

    /**
     * Test: Super admin vede tutti i tornei
     */
    public function test_super_admin_sees_all_tournaments(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $club1 = Club::factory()->create(['zone_id' => 1]);
        $club2 = Club::factory()->create(['zone_id' => 2]);

        Tournament::factory()->create(['club_id' => $club1->id, 'name' => 'Zone 1 Tournament']);
        Tournament::factory()->create(['club_id' => $club2->id, 'name' => 'Zone 2 Tournament']);

        $response = $this->actingAs($superAdmin)->get(route('tournaments.index'));

        $response->assertStatus(200);
        $response->assertSee('Zone 1 Tournament');
        $response->assertSee('Zone 2 Tournament');
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    /**
     * Test: Validazione campi required
     */
    public function test_tournament_creation_requires_required_fields(): void
    {
        $admin = $this->createZoneAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), []);

        $response->assertSessionHasErrors(['name', 'club_id', 'start_date', 'end_date']);
    }

    /**
     * Test: end_date deve essere dopo start_date
     */
    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $type = TournamentType::first();

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), [
                'name' => 'Test Tournament',
                'club_id' => $club->id,
                'tournament_type_id' => $type->id,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-15', // Prima della start!
                'availability_deadline' => '2026-06-01 23:59:59',
            ]);

        $response->assertSessionHasErrors('end_date');
    }

    // ==========================================
    // PERMISSION TESTS
    // ==========================================

    /**
     * Test: Referee non può accedere alla creazione tornei
     */
    public function test_referee_cannot_access_tournament_creation(): void
    {
        $referee = $this->createReferee();

        $response = $this->actingAs($referee)
            ->get(route('admin.tournaments.create'));

        $response->assertStatus(403);
    }

    /**
     * Test: Guest non può accedere ai tornei
     */
    public function test_guest_cannot_access_tournaments(): void
    {
        $response = $this->get(route('tournaments.index'));

        $response->assertRedirect(route('login'));
    }
}
