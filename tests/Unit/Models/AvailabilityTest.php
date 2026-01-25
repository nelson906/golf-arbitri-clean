<?php

namespace Tests\Unit\Models;

use App\Models\Availability;
use App\Models\Tournament;
use App\Models\User;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: Availability belongs to User
     */
    public function test_availability_belongs_to_user(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();

        $availability = Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $availability->user);
        $this->assertEquals($user->id, $availability->user->id);
    }

    /**
     * Test: Availability belongs to Tournament
     */
    public function test_availability_belongs_to_tournament(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();

        $availability = Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        $this->assertInstanceOf(Tournament::class, $availability->tournament);
        $this->assertEquals($tournament->id, $availability->tournament->id);
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

        // Prima disponibilità OK
        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        // Seconda disponibilità stesso user + tournament deve fallire
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Test: Stesso user può dichiarare disponibilità per tornei diversi
     */
    public function test_same_user_can_declare_availability_for_different_tournaments(): void
    {
        $user = $this->createReferee();
        $tournament1 = $this->createTournament();
        $tournament2 = $this->createTournament();

        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament1->id,
            'submitted_at' => now(),
        ]);

        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament2->id,
            'submitted_at' => now(),
        ]);

        $this->assertCount(2, Availability::where('user_id', $user->id)->get());
    }

    // ==========================================
    // FIELD VALIDATION TESTS
    // ==========================================

    /**
     * Test: submitted_at è required
     */
    public function test_submitted_at_is_required(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();

        $this->expectException(\Exception::class);

        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            // submitted_at mancante
        ]);
    }

    /**
     * Test: user_id è required
     */
    public function test_user_id_is_required(): void
    {
        $tournament = $this->createTournament();

        $this->expectException(\Exception::class);

        Availability::create([
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
            // user_id mancante
        ]);
    }

    /**
     * Test: tournament_id è required
     */
    public function test_tournament_id_is_required(): void
    {
        $user = $this->createReferee();

        $this->expectException(\Exception::class);

        Availability::create([
            'user_id' => $user->id,
            'submitted_at' => now(),
            // tournament_id mancante
        ]);
    }

    // ==========================================
    // TIMESTAMP TESTS
    // ==========================================

    /**
     * Test: submitted_at viene salvato correttamente
     */
    public function test_submitted_at_is_saved_correctly(): void
    {
        $user = $this->createReferee();
        $tournament = $this->createTournament();
        $now = now();

        $availability = Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => $now,
        ]);

        $this->assertNotNull($availability->submitted_at);
        $this->assertEquals($now->toDateTimeString(), $availability->submitted_at->toDateTimeString());
    }
}
