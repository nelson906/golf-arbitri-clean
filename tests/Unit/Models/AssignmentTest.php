<?php

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: Assignment belongs to User
     */
    public function test_assignment_belongs_to_user(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();
        $assignment = Assignment::factory()->forUser($user)->forTournament($tournament)->create();

        $this->assertInstanceOf(User::class, $assignment->user);
        $this->assertEquals($user->id, $assignment->user->id);
    }

    /**
     * Test: Assignment belongs to Tournament
     */
    public function test_assignment_belongs_to_tournament(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();
        $assignment = Assignment::factory()->forUser($user)->forTournament($tournament)->create();

        $this->assertInstanceOf(Tournament::class, $assignment->tournament);
        $this->assertEquals($tournament->id, $assignment->tournament->id);
    }

    /**
     * Test: Assignment belongs to assignedBy (User)
     */
    public function test_assignment_belongs_to_assigned_by(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament();
        $admin = $this->createZoneAdmin();

        $assignment = Assignment::factory()
            ->forUser($referee)
            ->forTournament($tournament)
            ->create(['assigned_by' => $admin->id]);

        $this->assertInstanceOf(User::class, $assignment->assignedBy);
        $this->assertEquals($admin->id, $assignment->assignedBy->id);
    }

    /**
     * Test: referee() è alias di user()
     */
    public function test_referee_is_alias_of_user(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();
        $assignment = Assignment::factory()->forUser($user)->forTournament($tournament)->create();

        $this->assertEquals($assignment->user->id, $assignment->referee->id);
    }

    // ==========================================
    // CONSTRAINT TESTS
    // ==========================================

    /**
     * Test: UNIQUE constraint su (user_id, tournament_id)
     */
    public function test_unique_constraint_on_user_tournament(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();

        // Prima assegnazione OK
        Assignment::factory()->forUser($user)->forTournament($tournament)->create();

        // Seconda assegnazione stesso user + tournament deve fallire
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Assignment::factory()->forUser($user)->forTournament($tournament)->create();
    }

    /**
     * Test: Stesso user può avere più assignments su tornei diversi
     */
    public function test_same_user_can_have_multiple_assignments_on_different_tournaments(): void
    {
        $user = $this->createReferee();
        $tournament1 = $this->createTournament();
        $tournament2 = $this->createTournament();

        Assignment::factory()->forUser($user)->forTournament($tournament1)->create();
        Assignment::factory()->forUser($user)->forTournament($tournament2)->create();

        $this->assertCount(2, $user->fresh()->assignments);
    }

    /**
     * Test: Stesso tournament può avere più assignments per user diversi
     */
    public function test_same_tournament_can_have_multiple_assignments_for_different_users(): void
    {
        $user1 = $this->createReferee();
        $user2 = $this->createReferee();
        $tournament = $this->createTournament();

        Assignment::factory()->forUser($user1)->forTournament($tournament)->create();
        Assignment::factory()->forUser($user2)->forTournament($tournament)->create();

        $this->assertCount(2, $tournament->fresh()->assignments);
    }

    // ==========================================
    // FACTORY STATE TESTS
    // ==========================================

    /**
     * Test: Factory confirmed state
     */
    public function test_factory_confirmed_state(): void
    {
        $assignment = Assignment::factory()->confirmed()->create();

        $this->assertTrue($assignment->is_confirmed);
        $this->assertNotNull($assignment->confirmed_at);
        $this->assertEquals('confirmed', $assignment->status);
    }

    /**
     * Test: Factory completed state
     */
    public function test_factory_completed_state(): void
    {
        $assignment = Assignment::factory()->completed()->create();

        $this->assertTrue($assignment->is_confirmed);
        $this->assertNotNull($assignment->confirmed_at);
        $this->assertEquals('completed', $assignment->status);
    }

    /**
     * Test: Factory asTournamentDirector state
     */
    public function test_factory_as_tournament_director_state(): void
    {
        $assignment = Assignment::factory()->asTournamentDirector()->create();

        $this->assertEquals('Direttore di Torneo', $assignment->role);
    }

    /**
     * Test: Factory asReferee state
     */
    public function test_factory_as_referee_state(): void
    {
        $assignment = Assignment::factory()->asReferee()->create();

        $this->assertEquals('Arbitro', $assignment->role);
    }

    /**
     * Test: Factory asObserver state
     */
    public function test_factory_as_observer_state(): void
    {
        $assignment = Assignment::factory()->asObserver()->create();

        $this->assertEquals('Osservatore', $assignment->role);
    }

    // ==========================================
    // FIELD VALIDATION TESTS
    // ==========================================

    /**
     * Test: Assignment ha campi required
     */
    public function test_assignment_has_required_fields(): void
    {
        $assignment = Assignment::factory()->create();

        $this->assertNotNull($assignment->user_id);
        $this->assertNotNull($assignment->tournament_id);
        $this->assertNotNull($assignment->role);
        $this->assertNotNull($assignment->assigned_at);
        $this->assertNotNull($assignment->assigned_by);
    }

    /**
     * Test: Assignment ruoli validi
     */
    public function test_assignment_has_valid_roles(): void
    {
        $validRoles = ['Direttore di Torneo', 'Arbitro', 'Osservatore'];

        $assignment = Assignment::factory()->create();

        $this->assertContains($assignment->role, $validRoles);
    }

    /**
     * Test: is_confirmed è boolean
     */
    public function test_is_confirmed_is_boolean(): void
    {
        $assignment = Assignment::factory()->create(['is_confirmed' => true]);

        $this->assertTrue(is_bool($assignment->is_confirmed) || $assignment->is_confirmed === 1);
    }
}
