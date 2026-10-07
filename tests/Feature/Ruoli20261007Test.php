<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\Statistics\AvailabilityStatsService;
use Tests\TestCase;

/**
 * Decisioni di Alberto del 7 ottobre 2026 (tutto tranne le notifiche):
 *  - super admin: puo' tutto se serve, non si disattiva, solo lui cambia zona
 *  - gli arbitri li gestisce la zona; il CRC li vede e li designa
 *  - il CRC designa anche osservatori; la SZR non toglie designazioni del CRC
 *  - Vedi Disponibilita' di un nazionale: CRC solo livelli nazionali, SZR la zona
 *  - la SZR non modifica ne' elimina i nazionali
 */
class Ruoli20261007Test extends TestCase
{
    private function type(bool $national): TournamentType
    {
        return TournamentType::where('is_national', $national)->firstOrFail();
    }

    private function tournamentIn(int $zoneId, bool $national): Tournament
    {
        return $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => $zoneId])->id,
            'tournament_type_id' => $this->type($national)->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function userPayload(User $user, array $over = []): array
    {
        return array_merge([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => $user->email,
            'user_type' => $user->user_type->value,
            'zone_id' => $user->zone_id,
            'level' => $user->level,
            'is_active' => '1',
        ], $over);
    }

    // ── Super admin e utenti ────────────────────────────────────────────────

    public function test_super_admin_cannot_be_deactivated_by_anyone(): void
    {
        $target = $this->createSuperAdmin(['zone_id' => 1]);

        foreach ([$this->createSuperAdmin(), $this->createNationalAdmin(), $this->createZoneAdmin(1)] as $actor) {
            $this->actingAs($actor)->patch(route('admin.users.toggle-active', $target));
            $target->refresh();
            $this->assertTrue($target->is_active, $actor->user_type->value);
        }

        // Neanche togliendo la spunta in Modifica
        $this->actingAs($this->createSuperAdmin())
            ->put(route('admin.users.update', $target), $this->userPayload($target, ['is_active' => null]));
        $target->refresh();
        $this->assertTrue($target->is_active);
    }

    public function test_only_super_admin_moves_a_referee_to_another_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($this->createZoneAdmin(1))
            ->put(route('admin.users.update', $referee), $this->userPayload($referee, ['zone_id' => 2]))
            ->assertRedirect();
        $referee->refresh();
        $this->assertSame(1, $referee->zone_id);

        $this->actingAs($this->createSuperAdmin())
            ->put(route('admin.users.update', $referee), $this->userPayload($referee, ['zone_id' => 2]))
            ->assertRedirect();
        $referee->refresh();
        $this->assertSame(2, $referee->zone_id);
    }

    public function test_zone_admin_creates_users_only_in_own_zone(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.users.store'), [
                'first_name' => 'Anna',
                'last_name' => 'Bianchi',
                'email' => 'anna.bianchi@example.test',
                'zone_id' => 2,
                'level' => 'Regionale',
                'user_type' => 'referee',
            ])
            ->assertSessionHasErrors('zone_id');
    }

    public function test_crc_does_not_edit_referees_but_manages_national_admins(): void
    {
        $crc = $this->createNationalAdmin();
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $otherCrc = $this->createNationalAdmin(['zone_id' => 1]);

        $this->actingAs($crc)->get(route('admin.users.show', $referee))->assertOk();
        $this->actingAs($crc)->get(route('admin.users.edit', $referee))->assertForbidden();
        $this->actingAs($crc)->patch(route('admin.users.toggle-active', $referee))->assertForbidden();
        $this->actingAs($crc)->get(route('admin.users.edit', $otherCrc))->assertOk();
    }

    // ── Designazioni sui nazionali ──────────────────────────────────────────

    public function test_zone_admin_cannot_remove_observer_designated_by_crc(): void
    {
        $tournament = $this->tournamentIn(1, true);
        $szr = $this->createZoneAdmin(1);
        $crc = $this->createNationalAdmin();

        $byCrc = $this->createReferee(['zone_id' => 1]);
        Assignment::create([
            'tournament_id' => $tournament->id, 'user_id' => $byCrc->id,
            'role' => 'Osservatore', 'assigned_by' => $crc->id, 'assigned_at' => now(),
        ]);
        $bySzr = $this->createReferee(['zone_id' => 1]);
        Assignment::create([
            'tournament_id' => $tournament->id, 'user_id' => $bySzr->id,
            'role' => 'Osservatore', 'assigned_by' => $szr->id, 'assigned_at' => now(),
        ]);

        $this->actingAs($szr)->delete(route('admin.assignments.removeFromTournament', [$tournament, $byCrc]));
        $this->assertDatabaseHas('assignments', ['tournament_id' => $tournament->id, 'user_id' => $byCrc->id]);

        $this->actingAs($szr)->delete(route('admin.assignments.removeFromTournament', [$tournament, $bySzr]));
        $this->assertDatabaseMissing('assignments', ['tournament_id' => $tournament->id, 'user_id' => $bySzr->id]);
    }

    public function test_crc_can_designate_an_observer(): void
    {
        $tournament = $this->tournamentIn(1, true);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);

        $this->actingAs($this->createNationalAdmin())
            ->post(route('admin.assignments.storeMultiple', $tournament), [
                'referee_ids' => [$referee->id],
                'roles' => [$referee->id => 'Osservatore'],
            ]);

        $this->assertDatabaseHas('assignments', [
            'tournament_id' => $tournament->id, 'user_id' => $referee->id, 'role' => 'Osservatore',
        ]);
    }

    public function test_single_assignment_accepts_referee_of_another_zone(): void
    {
        $tournament = $this->tournamentIn(1, false);
        $referee = $this->createReferee(['zone_id' => 2]);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ])
            ->assertSessionDoesntHaveErrors('user_id');

        $this->assertDatabaseHas('assignments', ['tournament_id' => $tournament->id, 'user_id' => $referee->id]);
    }

    public function test_super_admin_sees_zonal_declarers_in_assign_referees(): void
    {
        $tournament = $this->tournamentIn(1, false);
        $zonal = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);
        Availability::create(['user_id' => $zonal->id, 'tournament_id' => $tournament->id, 'submitted_at' => now()]);

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.assignments.assign-referees', $tournament))
            ->assertOk();

        /** @var \Illuminate\Support\Collection<int, User> $available */
        $available = $this->viewObject($response, 'availableReferees', \Illuminate\Support\Collection::class);
        $this->assertContains($zonal->id, $available->pluck('id')->all());
    }

    // ── Tornei nazionali e zona del torneo ──────────────────────────────────

    public function test_zone_admin_cannot_edit_or_delete_national_tournaments(): void
    {
        $national = $this->tournamentIn(1, true);
        $szr = $this->createZoneAdmin(1);

        $this->actingAs($szr)->get(route('admin.tournaments.show', $national))
            ->assertOk()
            ->assertDontSee(route('admin.tournaments.edit', $national));
        $this->actingAs($szr)->get(route('admin.tournaments.edit', $national))->assertForbidden();
        $this->actingAs($szr)->delete(route('admin.tournaments.destroy', $national))->assertForbidden();
        $this->assertDatabaseHas('tournaments', ['id' => $national->id]);

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.tournaments.edit', $national))->assertOk();
    }

    public function test_tournament_zone_follows_club_and_super_admin_tba_keeps_zone(): void
    {
        $base = [
            'name' => 'Gara di prova',
            'tournament_type_id' => $this->type(false)->id,
            'start_date' => now()->addDays(40)->format('Y-m-d'),
            'end_date' => now()->addDays(41)->format('Y-m-d'),
            'availability_deadline' => now()->addDays(30)->format('Y-m-d'),
        ];

        // Super admin, senza circolo: vale la zona scelta
        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.tournaments.store'), $base + ['zone_id' => 3])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, Tournament::where('name', 'Gara di prova')->firstOrFail()->getRawOriginal('zone_id'));

        // CRC che modifica la zona lasciando il circolo: vale la zona del circolo
        $tournament = $this->tournamentIn(1, true);
        $this->actingAs($this->createNationalAdmin())
            ->put(route('admin.tournaments.update', $tournament), [
                'name' => $tournament->name,
                'tournament_type_id' => $tournament->tournament_type_id,
                'club_id' => $tournament->club_id,
                'zone_id' => 2,
                'start_date' => $tournament->start_date->format('Y-m-d'),
                'end_date' => $tournament->end_date?->format('Y-m-d'),
                'availability_deadline' => $tournament->availability_deadline->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $tournament->fresh()?->getRawOriginal('zone_id'));
    }

    public function test_national_availability_page_eligible_list_depends_on_role(): void
    {
        $tournament = $this->tournamentIn(1, true);
        $zonal = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);
        $nationalElsewhere = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale']);

        $eligible = function (User $actor) use ($tournament): array {
            $response = $this->actingAs($actor)
                ->get(route('admin.tournaments.availabilities.index', $tournament))
                ->assertOk();
            /** @var \Illuminate\Support\Collection<int, User> $list */
            $list = $this->viewObject($response, 'eligibleReferees', \Illuminate\Support\Collection::class);

            return $list->pluck('id')->all();
        };

        $crc = $eligible($this->createNationalAdmin());
        $this->assertContains($nationalElsewhere->id, $crc);
        $this->assertNotContains($zonal->id, $crc);

        $szr = $eligible($this->createZoneAdmin(1));
        $this->assertContains($zonal->id, $szr);
        $this->assertNotContains($nationalElsewhere->id, $szr);
    }

    // ── Visibilita' ─────────────────────────────────────────────────────────

    public function test_club_detail_respects_zone_and_crc_sees_only_nationals(): void
    {
        $club = $this->createClub(['zone_id' => 2]);
        $zonal = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $this->type(false)->id]);
        $national = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $this->type(true)->id]);

        $this->actingAs($this->createZoneAdmin(1))->get(route('admin.clubs.show', $club))->assertForbidden();

        $response = $this->actingAs($this->createNationalAdmin())->get(route('admin.clubs.show', $club))->assertOk();
        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Tournament> $list */
        $list = $this->viewObject($response, 'tournaments', \Illuminate\Pagination\LengthAwarePaginator::class);
        $ids = collect($list->items())->pluck('id')->all();
        $this->assertContains($national->id, $ids);
        $this->assertNotContains($zonal->id, $ids);
    }

    public function test_curricula_zone_admin_sees_only_own_zone(): void
    {
        $mine = $this->createReferee(['zone_id' => 1]);
        $other = $this->createReferee(['zone_id' => 2]);
        $szr = $this->createZoneAdmin(1);

        $response = $this->actingAs($szr)
            ->get(route('admin.referees.curricula', ['zone' => '']))
            ->assertOk();
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $stats */
        $stats = $this->viewObject($response, 'stats', \Illuminate\Support\Collection::class);
        $ids = $stats->map(fn (array $row) => $row['referee'] instanceof User ? $row['referee']->id : 0)->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $this->actingAs($szr)->get(route('admin.referees.curriculum', $other))->assertForbidden();
    }

    public function test_availability_stats_count_only_visible_tournaments(): void
    {
        $mine = $this->tournamentIn(1, false);
        $other = $this->tournamentIn(2, false);
        Availability::create(['user_id' => $this->createReferee(['zone_id' => 1])->id, 'tournament_id' => $mine->id, 'submitted_at' => now()]);
        Availability::create(['user_id' => $this->createReferee(['zone_id' => 2])->id, 'tournament_id' => $other->id, 'submitted_at' => now()]);

        $stats = app(AvailabilityStatsService::class)->getGeneralStats($this->createZoneAdmin(1));

        $this->assertSame(1, $stats['referees_with_availability']);
        $this->assertSame(1, $stats['tournaments_with_availability']);
    }

    public function test_ajax_availability_leaves_message_for_the_reload(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->tournamentIn(1, false);
        $tournament->update([
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(31),
            'availability_deadline' => now()->addDays(10),
        ]);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $tournament->id, 'available' => true])
            ->assertOk()
            ->assertSessionHas('success');
    }
}
