<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\AssignmentValidationService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Secondo riesame del 7 ottobre 2026 (parti non di notifica).
 */
class SecondoRiesame20261007Test extends TestCase
{
    /** @return array<string, mixed> */
    private function tournamentData(int $clubId, int $typeId, string $name): array
    {
        return [
            'name' => $name,
            'club_id' => $clubId,
            'tournament_type_id' => $typeId,
            'start_date' => now()->addDays(30)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'availability_deadline' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ];
    }

    public function test_zone_admin_cannot_create_a_national_tournament(): void
    {
        $club = $this->createClub(['zone_id' => 1]);
        $national = TournamentType::where('is_national', true)->firstOrFail();

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.tournaments.store'), $this->tournamentData($club->id, $national->id, 'Nazionale da SZR'))
            ->assertSessionHasErrors('tournament_type_id');

        $this->assertDatabaseMissing('tournaments', ['name' => 'Nazionale da SZR']);
    }

    public function test_crc_cannot_create_a_zonal_tournament(): void
    {
        $club = $this->createClub(['zone_id' => 1]);
        $zonal = TournamentType::where('is_national', false)->firstOrFail();

        $this->actingAs($this->createNationalAdmin())
            ->post(route('admin.tournaments.store'), $this->tournamentData($club->id, $zonal->id, 'Zonale da CRC'))
            ->assertSessionHasErrors('tournament_type_id');

        $this->assertDatabaseMissing('tournaments', ['name' => 'Zonale da CRC']);
    }

    /**
     * Un'assegnazione fuori ordine di data faceva confrontare un'assegnazione
     * con se stessa e il conflitto vero si perdeva.
     */
    public function test_date_conflicts_are_found_when_assignments_are_out_of_order(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $club = $this->createClub(['zone_id' => 1]);
        $zonal = TournamentType::where('is_national', false)->firstOrFail()->id;

        // Creata per prima ma piu' avanti nel tempo
        $far = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $zonal,
            'start_date' => now()->addDays(60), 'end_date' => now()->addDays(61)]);
        $first = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $zonal,
            'start_date' => now()->addDays(10), 'end_date' => now()->addDays(12)]);
        $second = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $zonal,
            'start_date' => now()->addDays(11), 'end_date' => now()->addDays(13)]);

        foreach ([$far, $first, $second] as $t) {
            $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $referee->id]);
        }

        $conflicts = app(AssignmentValidationService::class)->detectDateConflicts(1);

        $this->assertCount(1, $conflicts);
        $conflict = $conflicts->firstOrFail();
        $pair = [$conflict['assignment1']->tournament_id, $conflict['assignment2']->tournament_id];
        sort($pair);
        $this->assertSame([$first->id, $second->id], $pair);
    }

    public function test_zone_admin_creates_clubs_only_in_his_zone(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.clubs.store'), ['name' => 'Circolo Altrove', 'zone_id' => 2])
            ->assertSessionHasErrors('zone_id');

        $this->assertDatabaseMissing('clubs', ['name' => 'Circolo Altrove']);
    }

    public function test_tournaments_follow_their_club_to_the_new_zone(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'name' => 'Circolo Mobile']);
        $tournament = $this->createTournament(['club_id' => $club->id]);
        $tournament->forceFill(['zone_id' => 1])->save();

        $this->actingAs($this->createSuperAdmin())
            ->put(route('admin.clubs.update', $club), ['name' => 'Circolo Mobile', 'zone_id' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) Club::findOrFail($club->id)->zone_id);
        $this->assertSame(2, (int) Tournament::findOrFail($tournament->id)->zone_id);
    }

    public function test_user_pages_follow_the_users_list_visibility(): void
    {
        $zonalReferee = $this->createReferee(['zone_id' => 2, 'level' => '1_livello']);
        $nationalReferee = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale']);
        $otherCrc = $this->createNationalAdmin();

        // SZR zona 1: niente schede di altre zone
        $szr = $this->createZoneAdmin(1);
        $this->actingAs($szr)->get(route('admin.users.show', $zonalReferee))->assertForbidden();

        // CRC: arbitri nazionali e admin nazionali si', arbitri zonali no
        $crc = $this->createNationalAdmin();
        $this->actingAs($crc)->get(route('admin.users.show', $nationalReferee))->assertOk();
        $this->actingAs($crc)->get(route('admin.users.show', $otherCrc))->assertOk();
        $this->actingAs($crc)->get(route('admin.users.show', $zonalReferee))->assertForbidden();

        // Storico carriera: stessa regola
        $this->actingAs($crc)->get(route('admin.career-history.show', $zonalReferee))->assertForbidden();
        $this->actingAs($crc)->get(route('admin.career-history.show', $nationalReferee))->assertOk();
        $this->actingAs($crc)->get(route('admin.career-history.index'))
            ->assertOk()
            ->assertSee($nationalReferee->name)
            ->assertDontSee($zonalReferee->name);
    }

    public function test_unchecking_active_deactivates_the_tournament_type(): void
    {
        $type = TournamentType::where('is_national', false)->firstOrFail();
        $type->update(['is_active' => true]);

        $this->actingAs($this->createSuperAdmin())
            ->put(route('super-admin.tournament-types.update', $type), [
                'name' => $type->name,
                'short_name' => $type->short_name,
                'sort_order' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) TournamentType::findOrFail($type->id)->is_active);
    }

    public function test_removed_pages_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.statistics.performance'));
        $this->assertFalse(Route::has('profile.destroy'));
    }
}
