<?php

namespace Tests\Unit\Models;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    // ==========================================
    // RELATIONSHIP TESTS
    // ==========================================

    /**
     * Test: Tournament belongs to Club
     */
    public function test_tournament_belongs_to_club(): void
    {
        $club = Club::factory()->create();
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $this->assertInstanceOf(Club::class, $tournament->club);
        $this->assertEquals($club->id, $tournament->club->id);
    }

    /**
     * Test: Tournament belongs to TournamentType
     */
    public function test_tournament_belongs_to_tournament_type(): void
    {
        $type = TournamentType::first();
        $tournament = Tournament::factory()->create(['tournament_type_id' => $type->id]);

        $this->assertInstanceOf(TournamentType::class, $tournament->tournamentType);
        $this->assertEquals($type->id, $tournament->tournamentType->id);
    }

    /**
     * Test: Tournament type() è alias di tournamentType()
     */
    public function test_tournament_type_is_alias(): void
    {
        $tournament = Tournament::factory()->create();

        $this->assertEquals($tournament->tournamentType->id, $tournament->type->id);
    }

    /**
     * Test: Tournament ha zone attraverso club
     */
    public function test_tournament_has_zone_through_club(): void
    {
        $zone = Zone::first();
        $club = Club::factory()->create(['zone_id' => $zone->id]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $this->assertInstanceOf(Zone::class, $tournament->zone);
        $this->assertEquals($zone->id, $tournament->zone->id);
    }

    /**
     * Test: Tournament ha molti assignments
     */
    public function test_tournament_has_many_assignments(): void
    {
        $tournament = $this->createTournament();
        $user1 = $this->createRefereeWithZoneAndLevel();
        $user2 = $this->createRefereeWithZoneAndLevel();

        Assignment::factory()->forTournament($tournament)->forUser($user1)->create();
        Assignment::factory()->forTournament($tournament)->forUser($user2)->create();

        $this->assertCount(2, $tournament->fresh()->assignments);
    }

    /**
     * Test: Tournament ha molte availabilities
     */
    public function test_tournament_has_many_availabilities(): void
    {
        $tournament = $this->createTournament();
        $user1 = $this->createRefereeWithZoneAndLevel();
        $user2 = $this->createRefereeWithZoneAndLevel();

        Availability::create([
            'user_id' => $user1->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        Availability::create([
            'user_id' => $user2->id,
            'tournament_id' => $tournament->id,
            'submitted_at' => now(),
        ]);

        $this->assertCount(2, $tournament->fresh()->availabilities);
    }

    /**
     * Test: Tournament ha referees attraverso assignments
     */
    public function test_tournament_has_referees_through_assignments(): void
    {
        $tournament = $this->createTournament();
        $user1 = $this->createRefereeWithZoneAndLevel();
        $user2 = $this->createRefereeWithZoneAndLevel();

        Assignment::factory()->forTournament($tournament)->forUser($user1)->create();
        Assignment::factory()->forTournament($tournament)->forUser($user2)->create();

        $this->assertCount(2, $tournament->fresh()->referees);
        $this->assertTrue($tournament->referees->contains($user1));
        $this->assertTrue($tournament->referees->contains($user2));
    }

    // ==========================================
    // SCOPE TESTS
    // ==========================================

    /**
     * Test: scopeUpcoming filtra tornei futuri
     */
    public function test_scope_upcoming_filters_future_tournaments(): void
    {
        Tournament::factory()->past()->count(2)->create();
        Tournament::factory()->upcoming()->count(3)->create();

        $upcoming = Tournament::upcoming()->get();

        $this->assertCount(3, $upcoming);
    }

    /**
     * Test: scopeVisible - super admin vede tutto
     */
    public function test_scope_visible_super_admin_sees_all(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Tournament::factory()->count(5)->create();

        $visible = Tournament::visible($superAdmin)->get();

        $this->assertCount(5, $visible);
    }

    /**
     * Test: scopeVisible - national admin vede solo nazionali
     */
    public function test_scope_visible_national_admin_sees_national_only(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        // Prendo i tipi dal seeder
        $nationalType = TournamentType::where('is_national', true)->first();
        $zonalType = TournamentType::where('is_national', false)->first();

        Tournament::factory()->count(2)->create(['tournament_type_id' => $nationalType->id]);
        Tournament::factory()->count(3)->create(['tournament_type_id' => $zonalType->id]);

        $visible = Tournament::visible($nationalAdmin)->get();

        $this->assertCount(2, $visible);
    }

    /**
     * Test: scopeVisible - zone admin vede solo propria zona
     */
    public function test_scope_visible_zone_admin_sees_own_zone_only(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);

        $club1 = Club::factory()->create(['zone_id' => 1]);
        $club2 = Club::factory()->create(['zone_id' => 2]);

        Tournament::factory()->count(3)->create(['club_id' => $club1->id]);
        Tournament::factory()->count(2)->create(['club_id' => $club2->id]);

        $visible = Tournament::visible($zoneAdmin)->get();

        $this->assertCount(3, $visible);
    }

    /**
     * Test: scopeVisible - referee nazionale vede propria zona + nazionali
     */
    public function test_scope_visible_national_referee_sees_own_zone_and_national(): void
    {
        $nationalReferee = User::factory()->referee()->inZone(1)->withLevel('Nazionale')->create();

        // Prendo i tipi dal seeder
        $nationalType = TournamentType::where('is_national', true)->first();
        $zonalType = TournamentType::where('is_national', false)->first();

        $club1 = Club::factory()->create(['zone_id' => 1]); // stessa zona
        $club2 = Club::factory()->create(['zone_id' => 2]); // altra zona

        // 2 tornei nella propria zona (zonali)
        Tournament::factory()->count(2)->create([
            'club_id' => $club1->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        // 2 tornei nazionali (altra zona, ma visibili perché nazionali)
        Tournament::factory()->count(2)->create([
            'club_id' => $club2->id,
            'tournament_type_id' => $nationalType->id,
        ]);

        // 3 tornei zonali in altra zona (NON visibili)
        Tournament::factory()->count(3)->create([
            'club_id' => $club2->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        $visible = Tournament::visible($nationalReferee)->get();

        // Deve vedere: 2 propria zona + 2 nazionali = 4
        $this->assertCount(4, $visible);
    }

    /**
     * Test: scopeVisible - referee regionale vede solo propria zona
     */
    public function test_scope_visible_regional_referee_sees_own_zone_only(): void
    {
        $regionalReferee = User::factory()->referee()->inZone(1)->withLevel('Regionale')->create();

        // Prendo i tipi dal seeder
        $nationalType = TournamentType::where('is_national', true)->first();
        $zonalType = TournamentType::where('is_national', false)->first();

        $club1 = Club::factory()->create(['zone_id' => 1]); // stessa zona
        $club2 = Club::factory()->create(['zone_id' => 2]); // altra zona

        // 3 tornei nella propria zona
        Tournament::factory()->count(3)->create([
            'club_id' => $club1->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        // 2 tornei nazionali in altra zona (NON visibili per regionale)
        Tournament::factory()->count(2)->create([
            'club_id' => $club2->id,
            'tournament_type_id' => $nationalType->id,
        ]);

        // 2 tornei zonali in altra zona (NON visibili)
        Tournament::factory()->count(2)->create([
            'club_id' => $club2->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        $visible = Tournament::visible($regionalReferee)->get();

        // Deve vedere solo i 3 della propria zona
        $this->assertCount(3, $visible);
    }

    /**
     * Test: scopeVisible senza user ritorna vuoto
     */
    public function test_scope_visible_without_user_returns_empty(): void
    {
        Tournament::factory()->count(5)->create();

        $visible = Tournament::visible(null)->get();

        $this->assertCount(0, $visible);
    }

    // ==========================================
    // ACCESSOR TESTS
    // ==========================================

    /**
     * Test: zone_id accessor ritorna zone_id del club
     */
    public function test_zone_id_accessor_returns_club_zone_id(): void
    {
        $club = Club::factory()->create(['zone_id' => 3]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        // Carico il club per attivare l'accessor
        $tournament->load('club');

        $this->assertEquals(3, $tournament->zone_id);
    }

    // ==========================================
    // FACTORY TESTS
    // ==========================================

    /**
     * Test: TournamentFactory crea torneo valido
     */
    public function test_tournament_factory_creates_valid_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $this->assertNotNull($tournament->name);
        $this->assertNotNull($tournament->start_date);
        $this->assertNotNull($tournament->end_date);
        $this->assertNotNull($tournament->club_id);
        $this->assertNotNull($tournament->tournament_type_id);
        $this->assertNotNull($tournament->created_by);
        $this->assertEquals(Tournament::STATUS_OPEN, $tournament->status);
    }

    /**
     * Test: TournamentFactory upcoming state
     */
    public function test_tournament_factory_upcoming_state(): void
    {
        $tournament = Tournament::factory()->upcoming()->create();

        $this->assertGreaterThanOrEqual(now()->startOfDay(), $tournament->start_date);
    }

    /**
     * Test: TournamentFactory past state
     */
    public function test_tournament_factory_past_state(): void
    {
        $tournament = Tournament::factory()->past()->create();

        $this->assertLessThan(now()->startOfDay(), $tournament->start_date);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Crea referee con zona e livello specifici
     */
    protected function createRefereeWithZoneAndLevel(?int $zoneId = null, ?string $level = null): User
    {
        $user = User::factory()->referee();

        if ($zoneId) {
            $user->inZone($zoneId);
        }

        if ($level) {
            $user->withLevel($level);
        }

        return $user->create();
    }
}
