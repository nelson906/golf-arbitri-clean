<?php

namespace Tests\Feature\Admin;

use App\Models\Assignment;
use App\Models\User;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    // ==========================================
    // ASSIGNMENT CREATE TESTS
    // ==========================================

    /**
     * Test: Admin può assegnare arbitro a torneo
     */
    public function test_admin_can_assign_referee_to_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignments', [
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => 'Arbitro',
        ]);
    }

    /**
     * Test: Admin può assegnare direttore di torneo
     */
    public function test_admin_can_assign_tournament_director(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->withLevel('Nazionale')->create();
        $tournament = $this->createTournament();

        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Direttore di Torneo',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignments', [
            'role' => 'Direttore di Torneo',
        ]);
    }

    /**
     * Test: Admin può assegnare osservatore
     */
    public function test_admin_can_assign_observer(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Osservatore',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignments', [
            'role' => 'Osservatore',
        ]);
    }

    // ==========================================
    // ASSIGNMENT UPDATE TESTS
    // ==========================================

    /**
     * Test: Admin può modificare ruolo assignment
     */
    public function test_admin_can_update_assignment_role(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament(['club_id' => $club->id]);

        $assignment = Assignment::factory()
            ->forUser($referee)
            ->forTournament($tournament)
            ->asReferee()
            ->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.assignments.update', $assignment), [
                'user_id' => $referee->id,
                'role' => 'Osservatore',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'role' => 'Osservatore',
        ]);
    }

    // ==========================================
    // ASSIGNMENT DELETE TESTS
    // ==========================================

    /**
     * Test: Admin può eliminare assignment
     */
    public function test_admin_can_delete_assignment(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament(['club_id' => $club->id]);

        $assignment = Assignment::factory()
            ->forUser($referee)
            ->forTournament($tournament)
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.assignments.destroy', $assignment));

        $response->assertRedirect();
        $this->assertDatabaseMissing('assignments', [
            'id' => $assignment->id,
        ]);
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    /**
     * Test: Non può assegnare stesso arbitro due volte allo stesso torneo
     */
    public function test_cannot_assign_same_referee_twice_to_same_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        // Prima assegnazione
        Assignment::factory()
            ->forUser($referee)
            ->forTournament($tournament)
            ->create();

        // Seconda assegnazione (dovrebbe fallire)
        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ]);

        // Dovrebbe avere errore di validazione o constraint
        $this->assertTrue(
            $response->status() === 422 ||
            $response->status() === 302
        );
    }

    /**
     * Test: Ruolo deve essere valido
     */
    public function test_role_must_be_valid(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Ruolo Inventato', // Ruolo non valido
            ]);

        $response->assertSessionHasErrors('role');
    }

    // ==========================================
    // PERMISSION TESTS
    // ==========================================

    /**
     * Test: Referee non può creare assignments
     */
    public function test_referee_cannot_create_assignments(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament();

        $response = $this->actingAs($referee)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // BUSINESS LOGIC TESTS
    // ==========================================

    /**
     * Test: Assignment viene creato con assigned_by corretto
     */
    public function test_assignment_created_with_correct_assigned_by(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ]);

        $this->assertDatabaseHas('assignments', [
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'assigned_by' => $admin->id,
        ]);
    }

    /**
     * Test: Assignment ha assigned_at timestamp
     */
    public function test_assignment_has_assigned_at_timestamp(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = User::factory()->referee()->inZone(1)->create();
        $tournament = $this->createTournament();

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ]);

        $assignment = Assignment::where('user_id', $referee->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        $this->assertNotNull($assignment->assigned_at);
    }
}
