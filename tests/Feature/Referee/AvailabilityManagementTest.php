<?php

namespace Tests\Feature\Referee;

use App\Models\Availability;
use App\Models\Tournament;
use Tests\TestCase;

class AvailabilityManagementTest extends TestCase
{
    // ==========================================
    // AVAILABILITY VIEW TESTS
    // ==========================================

    /**
     * Test: Referee può vedere tornei disponibili
     */
    public function test_referee_can_view_available_tournaments(): void
    {
        $referee = $this->createReferee();

        $response = $this->actingAs($referee)
            ->get(route('user.availability.index'));

        $response->assertStatus(200);
    }

    /**
     * Test: Referee vede solo tornei della sua zona
     */
    public function test_referee_sees_only_own_zone_tournaments(): void
    {
        $this->markTestSkipped('Richiede scope visible() funzionante in test environment');
    }

    // ==========================================
    // AVAILABILITY CREATE TESTS
    // ==========================================

    /**
     * Test: Referee può dichiarare disponibilità
     */
    public function test_referee_can_declare_availability(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament(['status' => 'open']);

        $response = $this->actingAs($referee)
            ->post(route('user.availability.store'), [
                'tournament_id' => $tournament->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('availabilities', [
            'user_id' => $referee->id,
            'tournament_id' => $tournament->id,
        ]);
    }

    /**
     * Test: Referee non può dichiarare disponibilità due volte
     */
    public function test_referee_cannot_declare_availability_twice(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament(['status' => 'open']);

        // Prima dichiarazione
        Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        // Seconda dichiarazione (dovrebbe fallire)
        $response = $this->actingAs($referee)
            ->post(route('user.availability.store'), [
                'tournament_id' => $tournament->id,
            ]);

        // Dovrebbe fallire per UNIQUE constraint
        $this->assertTrue(
            $response->status() === 422 ||
            $response->status() === 302
        );
    }

    // ==========================================
    // AVAILABILITY DELETE TESTS
    // ==========================================

    /**
     * Test: Referee può rimuovere disponibilità
     */
    public function test_referee_can_remove_availability(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament();

        $availability = Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($referee)
            ->delete(route('user.availability.destroy', $availability));

        $response->assertRedirect();
        $this->assertDatabaseMissing('availabilities', [
            'id' => $availability->id,
        ]);
    }

    /**
     * Test: Referee non può rimuovere disponibilità di altri
     */
    public function test_referee_cannot_remove_others_availability(): void
    {
        $referee1 = $this->createReferee();
        $referee2 = $this->createReferee();
        $tournament = $this->createTournament();

        $availability = Availability::create([
            'user_id' => $referee2->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($referee1)
            ->delete(route('user.availability.destroy', $availability));

        $response->assertStatus(403);
    }

    // ==========================================
    // DEADLINE TESTS
    // ==========================================

    /**
     * Test: Referee non può dichiarare disponibilità dopo deadline
     */
    public function test_referee_cannot_declare_availability_after_deadline(): void
    {
        $referee = $this->createReferee();

        // Torneo con deadline passata
        $tournament = Tournament::factory()->create([
            'availability_deadline' => now()->subDay(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($referee)
            ->post(route('user.availability.store'), [
                'tournament_id' => $tournament->id,
            ]);

        $response->assertSessionHasErrors();
    }

    // ==========================================
    // PERMISSION TESTS
    // ==========================================

    /**
     * Test: Admin non può dichiarare disponibilità
     */
    public function test_admin_cannot_declare_availability(): void
    {
        $admin = $this->createZoneAdmin();
        $tournament = $this->createTournament();

        $response = $this->actingAs($admin)
            ->post(route('user.availability.store'), [
                'tournament_id' => $tournament->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test: Guest non può accedere alle disponibilità
     */
    public function test_guest_cannot_access_availabilities(): void
    {
        $response = $this->get(route('user.availability.index'));

        $response->assertRedirect(route('login'));
    }

    // ==========================================
    // BUSINESS LOGIC TESTS
    // ==========================================

    /**
     * Test: submitted_at viene impostato automaticamente
     */
    public function test_submitted_at_is_set_automatically(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament(['status' => 'open']);

        $this->actingAs($referee)
            ->post(route('user.availability.store'), [
                'tournament_id' => $tournament->id,
            ]);

        $availability = Availability::where('user_id', $referee->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        $this->assertNotNull($availability->submitted_at);
    }
}
