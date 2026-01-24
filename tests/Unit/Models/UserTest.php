<?php

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use Tests\TestCase;

class UserTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: User ha relazione zone
     */
    public function test_user_belongs_to_zone(): void
    {
        $zone = Zone::first();
        $user = User::factory()->referee()->create(['zone_id' => $zone->id]);

        $this->assertInstanceOf(Zone::class, $user->zone);
        $this->assertEquals($zone->id, $user->zone->id);
    }

    /**
     * Test: User può non avere zona
     */
    public function test_user_can_have_null_zone(): void
    {
        $user = User::factory()->create(['zone_id' => null]);

        $this->assertNull($user->zone);
    }

    /**
     * Test: User ha molti assignments
     */
    public function test_user_has_many_assignments(): void
    {
        $user = $this->createReferee();
        $tournament1 = $this->createTournament();
        $tournament2 = $this->createTournament();

        $assignment1 = Assignment::factory()->forUser($user)->forTournament($tournament1)->create();
        $assignment2 = Assignment::factory()->forUser($user)->forTournament($tournament2)->create();

        $this->assertCount(2, $user->assignments);
        $this->assertTrue($user->assignments->contains($assignment1));
        $this->assertTrue($user->assignments->contains($assignment2));
    }

    /**
     * Test: User ha molte availabilities
     */
    public function test_user_has_many_availabilities(): void
    {
        $user = $this->createReferee();

        // Le availabilities richiedono un tournament e submitted_at
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

        $this->assertCount(2, $user->fresh()->availabilities);
    }

    /**
     * Test: User ha tournaments attraverso assignments
     */
    public function test_user_has_tournaments_through_assignments(): void
    {
        $user = $this->createReferee();
        $tournament1 = $this->createTournament();
        $tournament2 = $this->createTournament();

        Assignment::factory()->forUser($user)->forTournament($tournament1)->create();
        Assignment::factory()->forUser($user)->forTournament($tournament2)->create();

        $this->assertCount(2, $user->tournaments);
        $this->assertTrue($user->tournaments->contains($tournament1));
        $this->assertTrue($user->tournaments->contains($tournament2));
    }

    /**
     * Test: User può avere career history
     */
    public function test_user_can_have_career_history(): void
    {
        $user = $this->createReferee();

        $careerHistory = RefereeCareerHistory::create([
            'user_id' => $user->id,
            'level_history' => json_encode([]),
            'assignments_count' => 0,
        ]);

        $this->assertInstanceOf(RefereeCareerHistory::class, $user->fresh()->careerHistory);
        $this->assertEquals($careerHistory->id, $user->careerHistory->id);
    }

    // ==========================================
    // SCOPE TESTS
    // ==========================================

    /**
     * Test: scopeReferees filtra solo referee
     */
    public function test_scope_referees_filters_only_referees(): void
    {
        User::factory()->referee()->count(3)->create();
        User::factory()->zoneAdmin()->create();
        User::factory()->superAdmin()->create();

        $referees = User::referees()->get();

        $this->assertCount(3, $referees);
        $referees->each(function ($user) {
            $this->assertEquals('referee', $user->user_type);
        });
    }

    /**
     * Test: scopeActive filtra utenti attivi
     */
    public function test_scope_active_filters_active_users(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->inactive()->create();

        $activeUsers = User::active()->get();

        $this->assertCount(3, $activeUsers);
        $activeUsers->each(function ($user) {
            $this->assertEquals(1, $user->is_active); // SQLite ritorna 1 invece di true
        });
    }

    /**
     * Test: scopeVisible - super admin vede tutto
     */
    public function test_scope_visible_super_admin_sees_all(): void
    {
        $superAdmin = $this->createSuperAdmin();

        User::factory()->referee()->count(5)->create();
        User::factory()->zoneAdmin()->count(2)->create();

        $visible = User::visible($superAdmin)->get();

        $this->assertGreaterThanOrEqual(7, $visible->count());
    }

    /**
     * Test: scopeVisible - national admin vede solo nazionali/internazionali
     */
    public function test_scope_visible_national_admin_sees_national_referees(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        User::factory()->referee()->withLevel('Nazionale')->count(2)->create();
        User::factory()->referee()->withLevel('Internazionale')->create();
        User::factory()->referee()->withLevel('Aspirante')->count(3)->create();

        $visible = User::visible($nationalAdmin)->get();

        $this->assertEquals(3, $visible->count());
        $visible->each(function ($user) {
            $this->assertContains($user->level, ['Nazionale', 'Internazionale']);
        });
    }

    /**
     * Test: scopeVisible - zone admin vede solo propria zona
     */
    public function test_scope_visible_zone_admin_sees_own_zone_only(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);

        User::factory()->referee()->inZone(1)->count(3)->create();
        User::factory()->referee()->inZone(2)->count(2)->create();

        $visible = User::visible($zoneAdmin)->get();

        // Lo scope filtra per zone_id ma include anche il zoneAdmin stesso
        $this->assertEquals(4, $visible->count()); // 3 referee + 1 admin
        $visible->each(function ($user) {
            $this->assertEquals(1, $user->zone_id);
        });
    }

    /**
     * Test: scopeVisible senza user ritorna vuoto
     */
    public function test_scope_visible_without_user_returns_empty(): void
    {
        User::factory()->count(5)->create();

        $visible = User::visible(null)->get();

        $this->assertCount(0, $visible);
    }

    // ==========================================
    // ACCESSOR TESTS
    // ==========================================

    /**
     * Test: is_active accessor funziona
     */
    public function test_is_active_accessor_works(): void
    {
        $activeUser = User::factory()->create(['is_active' => true]);
        $inactiveUser = User::factory()->inactive()->create();

        $this->assertTrue($activeUser->is_active);
        $this->assertFalse($inactiveUser->is_active);
    }

    // ==========================================
    // METHOD TESTS
    // ==========================================

    /**
     * Test: hasRole riconosce admin
     */
    public function test_has_role_recognizes_admin(): void
    {
        $zoneAdmin = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();
        $superAdmin = $this->createSuperAdmin();
        $referee = $this->createReferee();

        $this->assertTrue($zoneAdmin->hasRole('admin'));
        $this->assertTrue($nationalAdmin->hasRole('admin'));
        $this->assertTrue($superAdmin->hasRole('admin'));
        $this->assertFalse($referee->hasRole('admin'));
    }

    /**
     * Test: hasRole riconosce zone_admin
     */
    public function test_has_role_recognizes_zone_admin(): void
    {
        $zoneAdmin = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();

        $this->assertTrue($zoneAdmin->hasRole('zone_admin'));
        $this->assertFalse($nationalAdmin->hasRole('zone_admin'));
    }

    /**
     * Test: hasRole riconosce super_admin
     */
    public function test_has_role_recognizes_super_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $nationalAdmin = $this->createNationalAdmin();

        $this->assertTrue($superAdmin->hasRole('super_admin'));
        $this->assertFalse($nationalAdmin->hasRole('super_admin'));
    }

    /**
     * Test: hasRole riconosce referee
     */
    public function test_has_role_recognizes_referee(): void
    {
        $referee = $this->createReferee();
        $admin = $this->createZoneAdmin();

        $this->assertTrue($referee->hasRole('referee'));
        $this->assertFalse($admin->hasRole('referee'));
    }

    // ==========================================
    // FACTORY TESTS
    // ==========================================

    /**
     * Test: UserFactory crea referee valido
     */
    public function test_user_factory_creates_valid_referee(): void
    {
        $user = User::factory()->referee()->create();

        $this->assertEquals('referee', $user->user_type);
        $this->assertNotNull($user->level);
        $this->assertContains($user->level, ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale']);
    }

    /**
     * Test: UserFactory crea admin valido
     */
    public function test_user_factory_creates_valid_zone_admin(): void
    {
        $user = User::factory()->zoneAdmin()->create();

        $this->assertEquals('admin', $user->user_type);
        $this->assertNotNull($user->zone_id);
    }
}
