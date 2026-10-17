<?php

namespace Tests\Feature\Admin;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Support\Collection;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Assegnazioni lato admin: elenco, form singolo, pagina "Assegna Arbitri",
 * modifica, rimozione e pagine di Validazione Assegnazioni.
 *
 * Regole (decisioni 2026-10-03 e 2026-10-07):
 *  - la SZR sui nazionali della sua zona designa solo osservatori e non toglie
 *    le designazioni del CRC;
 *  - il CRC vede solo arbitri di livello nazionale;
 *  - solo i tornei zonali hanno una bozza di notifica automatica.
 */
class AssignmentControllerCoverageTest extends TestCase
{
    use CollectsIds;

    private function type(bool $national): TournamentType
    {
        return TournamentType::where('is_national', $national)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $over
     */
    private function tournamentIn(int $zoneId, bool $national = false, array $over = []): Tournament
    {
        return $this->createTournament(array_merge([
            'club_id' => $this->createClub(['zone_id' => $zoneId])->id,
            'tournament_type_id' => $this->type($national)->id,
            'start_date' => now()->addDays(20)->startOfDay(),
            'end_date' => now()->addDays(22)->startOfDay(),
            'availability_deadline' => now()->addDays(10)->startOfDay(),
        ], $over));
    }

    private function referee(int $zoneId = 1, string $level = '1_livello', string $name = 'Mario Rossi'): User
    {
        return $this->createReferee(['zone_id' => $zoneId, 'level' => $level, 'name' => $name, 'is_active' => true]);
    }

    private ?User $assigner = null;

    /**
     * Chi ha fatto la designazione (colonna obbligatoria): di default un
     * admin di zona, che non blocca niente.
     */
    private function assign(User $referee, Tournament $t, string $role = 'Arbitro', ?User $by = null): Assignment
    {
        $by ??= $this->assigner ??= $this->createZoneAdmin(1);

        return Assignment::create([
            'user_id' => $referee->id,
            'tournament_id' => $t->id,
            'role' => $role,
            'assigned_by' => $by->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<int>
     */
    private function ids($response, string $key): array
    {
        return $this->idsInOrder($response->viewData($key));
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function test_index_filters_by_tournament_and_referee(): void
    {
        $t1 = $this->tournamentIn(1);
        $t2 = $this->tournamentIn(1);
        $r1 = $this->referee(1);
        $r2 = $this->referee(1);
        $a = $this->assign($r1, $t1);
        $b = $this->assign($r2, $t1);
        $c = $this->assign($r1, $t2);
        $admin = $this->createZoneAdmin(1);

        $byTournament = $this->actingAs($admin)->get(route('admin.assignments.index', ['tournament_id' => $t1->id]));
        $byTournament->assertOk();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->ids($byTournament, 'assignments'));

        $byReferee = $this->actingAs($admin)->get(route('admin.assignments.index', ['user_id' => $r1->id]));
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $this->ids($byReferee, 'assignments'));
    }

    public function test_index_sorts_by_surname_both_ways_and_by_newest_otherwise(): void
    {
        $t = $this->tournamentIn(1);
        $bianchi = $this->createReferee(['zone_id' => 1, 'last_name' => 'Bianchi', 'first_name' => 'Anna']);
        $zanetti = $this->createReferee(['zone_id' => 1, 'last_name' => 'Zanetti', 'first_name' => 'Ugo']);
        $first = $this->assign($zanetti, $t);
        $second = $this->assign($bianchi, $t);
        $admin = $this->createZoneAdmin(1);

        $asc = $this->actingAs($admin)->get(route('admin.assignments.index', ['sort' => 'surname_asc']));
        $this->assertSame([$second->id, $first->id], $this->ids($asc, 'assignments'));

        $desc = $this->actingAs($admin)->get(route('admin.assignments.index', ['sort' => 'surname_desc']));
        $this->assertSame([$first->id, $second->id], $this->ids($desc, 'assignments'));

        $other = $this->actingAs($admin)->get(route('admin.assignments.index', ['sort' => 'boh']));
        $this->assertSame([$second->id, $first->id], $this->ids($other, 'assignments'));
    }

    public function test_index_zone_admin_does_not_see_other_zones(): void
    {
        $mine = $this->assign($this->referee(1), $this->tournamentIn(1));
        $this->assign($this->referee(2), $this->tournamentIn(2));

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.index'));

        $this->assertSame([$mine->id], $this->ids($response, 'assignments'));
        $this->assertFalse($response->viewData('isNationalAdmin'));
    }

    // ── Form singolo ─────────────────────────────────────────────────────────

    public function test_create_with_tournament_splits_available_and_other_referees(): void
    {
        $t = $this->tournamentIn(1);
        $declared = $this->referee(1, '1_livello', 'Anna Disponibile');
        $assigned = $this->referee(1, '1_livello', 'Bruno Assegnato');
        $other = $this->referee(1, '1_livello', 'Carlo Altro');
        $otherZone = $this->referee(2, '1_livello', 'Dario Fuorizona');
        $inactive = $this->createReferee(['zone_id' => 1, 'is_active' => false]);
        Availability::create(['user_id' => $declared->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);
        Availability::create(['user_id' => $assigned->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);
        $this->assign($assigned, $t);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.create', ['tournament_id' => $t->id]));

        $response->assertOk();
        $this->assertSame([$declared->id], $this->ids($response, 'availableReferees'));
        $others = $this->ids($response, 'otherReferees');
        $this->assertContains($other->id, $others);
        $this->assertNotContains($otherZone->id, $others, 'la SZR vede solo la sua zona');
        $this->assertNotContains($inactive->id, $others);
        $this->assertNotContains($assigned->id, $others);
        $this->assertTrue($this->viewObject($response, 'tournament', Tournament::class)->is($t));
    }

    public function test_create_crc_lists_only_national_level_referees_of_every_zone(): void
    {
        $t = $this->tournamentIn(1, true);
        $nationalElsewhere = $this->referee(3, 'Nazionale');
        $international = $this->referee(2, 'Internazionale');
        $zonal = $this->referee(1, '1_livello');
        $declaredZonal = $this->referee(1, 'Regionale');
        Availability::create(['user_id' => $declaredZonal->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);

        $response = $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->get(route('admin.assignments.create', ['tournament_id' => $t->id]));

        $response->assertOk();
        $this->assertSame([], $this->ids($response, 'availableReferees'));
        $this->assertEqualsCanonicalizing(
            [$nationalElsewhere->id, $international->id],
            array_values(array_intersect($this->ids($response, 'otherReferees'), [$nationalElsewhere->id, $international->id, $zonal->id]))
        );
    }

    public function test_create_super_admin_sees_every_referee(): void
    {
        $t = $this->tournamentIn(1);
        $a = $this->referee(1);
        $b = $this->referee(5);

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.assignments.create', ['tournament_id' => $t->id]));

        $others = $this->ids($response, 'otherReferees');
        $this->assertContains($a->id, $others);
        $this->assertContains($b->id, $others);
    }

    public function test_create_with_tournament_of_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.create', ['tournament_id' => $this->tournamentIn(2)->id]))
            ->assertForbidden();
    }

    public function test_create_with_unknown_tournament_shows_empty_form(): void
    {
        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.create', ['tournament_id' => 999999]));

        $response->assertOk();
        $this->assertNull($response->viewData('tournament'));
        $this->assertSame([], $this->ids($response, 'availableReferees'));
    }

    public function test_create_lists_only_tournaments_not_yet_ended(): void
    {
        $future = $this->tournamentIn(1);
        $past = $this->tournamentIn(1, false, [
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(8),
            'availability_deadline' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.create'));

        $ids = $this->ids($response, 'tournaments');
        $this->assertContains($future->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }

    public function test_store_zone_admin_on_national_tournament_cannot_use_referee_role(): void
    {
        $t = $this->tournamentIn(1, true);
        $referee = $this->referee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.assignments.create'))
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $t->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ])
            ->assertRedirect(route('admin.assignments.create'))
            ->assertSessionHas('error', "Sui tornei nazionali l'admin di zona designa solo osservatori.");

        $this->assertDatabaseMissing('assignments', ['tournament_id' => $t->id]);
    }

    public function test_store_zone_admin_on_national_tournament_can_designate_observer(): void
    {
        $t = $this->tournamentIn(1, true);
        $referee = $this->referee(1);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), [
                'tournament_id' => $t->id,
                'user_id' => $referee->id,
                'role' => 'Osservatore',
            ])
            ->assertRedirect(route('admin.assignments.index'))
            ->assertSessionHas('success', 'Assegnazione creata con successo');

        $this->assertDatabaseHas('assignments', [
            'tournament_id' => $t->id,
            'user_id' => $referee->id,
            'role' => 'Osservatore',
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_store_refuses_inactive_referee_and_non_referee(): void
    {
        $t = $this->tournamentIn(1);
        $inactive = $this->createReferee(['zone_id' => 1, 'is_active' => false]);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'tournament_id' => $t->id,
            'user_id' => $inactive->id,
            'role' => 'Arbitro',
        ])->assertSessionHasErrors('user_id');

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'tournament_id' => $t->id,
            'user_id' => $this->createZoneAdmin(1)->id,
            'role' => 'Arbitro',
        ])->assertSessionHasErrors('user_id');

        $this->assertSame(0, Assignment::where('tournament_id', $t->id)->count());
    }

    // ── Dettaglio e modifica ─────────────────────────────────────────────────

    public function test_show_unknown_assignment_is_404(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.show', 999999))
            ->assertNotFound();
    }

    public function test_show_assignment_of_other_zone_is_forbidden(): void
    {
        $a = $this->assign($this->referee(2), $this->tournamentIn(2));

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.show', $a))
            ->assertForbidden();
    }

    public function test_show_own_zone_assignment(): void
    {
        $a = $this->assign($this->referee(1), $this->tournamentIn(1));

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.show', $a));

        $response->assertOk();
        $this->assertTrue($this->viewObject($response, 'assignment', Assignment::class)->is($a));
    }

    public function test_edit_lists_visible_referees_roles_and_suggested_referee(): void
    {
        $a = $this->assign($this->referee(1), $this->tournamentIn(1));
        $suggested = $this->referee(1, '1_livello', 'Sergio Suggerito');
        $elsewhere = $this->referee(2);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.edit', ['assignment' => $a, 'suggested_referee' => $suggested->id]));

        $response->assertOk();
        $this->assertTrue($this->viewObject($response, 'suggestedReferee', User::class)->is($suggested));
        $this->assertSame(['Arbitro', 'Direttore di Torneo', 'Osservatore'], $response->viewData('roles'));
        $this->assertNotContains($elsewhere->id, $this->ids($response, 'referees'));
    }

    public function test_edit_without_suggestion(): void
    {
        $a = $this->assign($this->referee(1), $this->tournamentIn(1));

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.edit', $a));

        $response->assertOk();
        $this->assertNull($response->viewData('suggestedReferee'));
    }

    public function test_edit_other_zone_is_forbidden(): void
    {
        $a = $this->assign($this->referee(2), $this->tournamentIn(2));

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.edit', $a))
            ->assertForbidden();
    }

    public function test_update_to_referee_already_on_tournament_is_refused(): void
    {
        $t = $this->tournamentIn(1);
        $a = $this->assign($this->referee(1), $t);
        $busy = $this->referee(1);
        $this->assign($busy, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.assignments.edit', $a))
            ->put(route('admin.assignments.update', $a), ['user_id' => $busy->id, 'role' => 'Arbitro'])
            ->assertRedirect(route('admin.assignments.edit', $a))
            ->assertSessionHas('error', 'Questo arbitro è già assegnato a questo torneo');
    }

    public function test_update_swaps_referee_role_and_notes(): void
    {
        $a = $this->assign($this->referee(1), $this->tournamentIn(1));
        $new = $this->referee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->put(route('admin.assignments.update', $a), [
                'user_id' => $new->id,
                'role' => 'Direttore di Torneo',
                'notes' => 'cambio',
            ])
            ->assertRedirect(route('admin.assignments.show', $a))
            ->assertSessionHas('success', 'Assegnazione aggiornata con successo');

        $a->refresh();
        $this->assertSame($new->id, $a->user_id);
        $this->assertSame('Direttore di Torneo', $a->role);
        $this->assertSame('cambio', $a->notes);
    }

    public function test_update_keeping_same_referee_only_changes_role(): void
    {
        $referee = $this->referee(1);
        $a = $this->assign($referee, $this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->put(route('admin.assignments.update', $a), ['user_id' => $referee->id, 'role' => 'Osservatore'])
            ->assertSessionHas('success');

        $this->assertSame('Osservatore', $a->fresh()?->role);
    }

    public function test_update_zone_admin_cannot_turn_observer_into_referee_on_national(): void
    {
        $t = $this->tournamentIn(1, true);
        $admin = $this->createZoneAdmin(1);
        $a = $this->assign($this->referee(1), $t, 'Osservatore', $admin);

        $this->actingAs($admin)
            ->put(route('admin.assignments.update', $a), ['user_id' => $a->user_id, 'role' => 'Arbitro'])
            ->assertSessionHas('error', "Sui tornei nazionali l'admin di zona gestisce solo gli osservatori.");

        $this->assertSame('Osservatore', $a->fresh()?->role);
    }

    public function test_update_zone_admin_cannot_change_observer_designated_by_crc(): void
    {
        $t = $this->tournamentIn(1, true);
        $crc = $this->createNationalAdmin(['zone_id' => 8]);
        $a = $this->assign($this->referee(1), $t, 'Osservatore', $crc);
        $other = $this->referee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->put(route('admin.assignments.update', $a), ['user_id' => $other->id, 'role' => 'Osservatore'])
            ->assertSessionHas('error');

        $this->assertNotSame($other->id, $a->fresh()?->user_id);
    }

    public function test_update_requires_valid_role(): void
    {
        $a = $this->assign($this->referee(1), $this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->put(route('admin.assignments.update', $a), ['user_id' => $a->user_id, 'role' => 'Caddie'])
            ->assertSessionHasErrors('role');
    }

    // ── Assegna Arbitri (multipla) ───────────────────────────────────────────

    public function test_assign_page_lists_national_referees_on_national_tournament(): void
    {
        $t = $this->tournamentIn(1, true);
        $declared = $this->referee(1, 'Nazionale', 'Anna Dichiarata');
        $national = $this->referee(4, 'Nazionale', 'Nico Nazionale');
        $assigned = $this->referee(2, 'Internazionale', 'Ivo Assegnato');
        Availability::create(['user_id' => $declared->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);
        $this->assign($assigned, $t);

        $response = $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->get(route('admin.assignments.assign-referees', $t));

        $response->assertOk();
        $nationalIds = $this->ids($response, 'nationalReferees');
        $this->assertContains($national->id, $nationalIds);
        $this->assertNotContains($declared->id, $nationalIds, 'gia\' tra i disponibili');
        $this->assertNotContains($assigned->id, $nationalIds, 'gia\' assegnato');
        $this->assertSame([], $response->viewData('lockedAssignmentIds'));
        $this->assertFalse($response->viewData('observerOnly'));
    }

    public function test_assign_page_zone_admin_on_national_is_observer_only_and_crc_rows_are_locked(): void
    {
        $t = $this->tournamentIn(1, true);
        $crc = $this->createNationalAdmin(['zone_id' => 8]);
        $admin = $this->createZoneAdmin(1);
        $crcReferee = $this->assign($this->referee(2, 'Nazionale'), $t, 'Arbitro', $crc);
        $crcObserver = $this->assign($this->referee(1), $t, 'Osservatore', $crc);
        $ownObserver = $this->assign($this->referee(1), $t, 'Osservatore', $admin);

        $response = $this->actingAs($admin)->get(route('admin.assignments.assign-referees', $t));

        $response->assertOk();
        $this->assertTrue($response->viewData('observerOnly'));
        $locked = $this->viewArray($response, 'lockedAssignmentIds');
        $this->assertEqualsCanonicalizing([$crcReferee->id, $crcObserver->id], $locked);
        $this->assertNotContains($ownObserver->id, $locked);
    }

    public function test_assign_page_zonal_tournament_has_no_national_list_for_zone_admin(): void
    {
        $t = $this->tournamentIn(1);
        $this->referee(3, 'Nazionale');

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.assign-referees', $t));

        $this->assertCount(0, $this->viewObject($response, 'nationalReferees', Collection::class));
    }

    public function test_assign_page_tba_tournament_without_club_opens(): void
    {
        $t = $this->tournamentIn(1);
        \Illuminate\Support\Facades\DB::table('tournaments')->where('id', $t->id)->update(['club_id' => null, 'zone_id' => 1]);

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignments.assign-referees', $t->fresh()))
            ->assertOk();
    }

    public function test_assign_page_load_counts_same_season_only(): void
    {
        $t = $this->tournamentIn(1);
        $referee = $this->referee(1);
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);
        $sameDay = $this->tournamentIn(1, false, [
            'start_date' => $t->start_date->copy()->addDay(),
            'end_date' => $t->start_date->copy()->addDay(),
            'name' => 'Gara Un Giorno',
        ]);
        $nextYear = $this->tournamentIn(1, false, [
            'start_date' => $t->start_date->copy()->addYear(),
            'end_date' => $t->start_date->copy()->addYear()->addDay(),
        ]);
        $this->assign($referee, $sameDay);
        $this->assign($referee, $nextYear);

        $load = $this->viewArray(
            $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignments.assign-referees', $t)),
            'refereeLoad'
        );

        $entry = $this->arrayAt($load, $referee->id);
        $this->assertSame(1, $entry['count']);
        $conflicts = $this->arrayAt($entry, 'conflicts');
        $this->assertCount(1, $conflicts);
        $row = $this->arrayAt($conflicts, 0);
        $this->assertSame('Gara Un Giorno', $row['name']);
        $this->assertSame($sameDay->start_date->format('d/m'), $row['dates'], 'un giorno: nessun trattino');
    }

    public function test_store_multiple_skips_already_assigned_and_reports_it(): void
    {
        $t = $this->tournamentIn(1);
        $old = $this->referee(1);
        $new = $this->referee(1);
        $this->assign($old, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $t), [
                'referee_ids' => [$old->id, $new->id],
                'roles' => [$new->id => 'Direttore di Torneo'],
            ])
            ->assertRedirect(route('admin.assignments.assign-referees', $t))
            ->assertSessionHas('success', 'Assegnati 1 arbitri al torneo. 1 erano già assegnati.')
            ->assertSessionHas('show_next_step_modal', true);

        $this->assertDatabaseHas('assignments', ['tournament_id' => $t->id, 'user_id' => $new->id, 'role' => 'Direttore di Torneo']);
        $this->assertSame(2, Assignment::where('tournament_id', $t->id)->count());
    }

    public function test_store_multiple_without_role_uses_referee_on_zonal(): void
    {
        $t = $this->tournamentIn(1);
        $r = $this->referee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $t), ['referee_ids' => [$r->id]]);

        $this->assertDatabaseHas('assignments', ['tournament_id' => $t->id, 'user_id' => $r->id, 'role' => 'Arbitro']);
    }

    public function test_store_multiple_creates_draft_notification_once_for_zonal(): void
    {
        $t = $this->tournamentIn(1);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)->post(route('admin.assignments.storeMultiple', $t), ['referee_ids' => [$this->referee(1)->id]]);
        $this->actingAs($admin)->post(route('admin.assignments.storeMultiple', $t), ['referee_ids' => [$this->referee(1)->id]]);

        $this->assertSame(1, TournamentNotification::where('tournament_id', $t->id)->count());
        $notification = TournamentNotification::where('tournament_id', $t->id)->firstOrFail();
        $this->assertSame('pending', $notification->status);
        $this->assertNull($notification->notification_type);
        $details = $notification->details;
        $this->assertIsArray($details);
        $this->assertSame(1, $details['referees_count'] ?? null);
        $this->assertTrue($details['auto_created'] ?? false);
    }

    public function test_store_multiple_with_only_already_assigned_creates_no_draft(): void
    {
        $t = $this->tournamentIn(1);
        $r = $this->referee(1);
        $this->assign($r, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $t), ['referee_ids' => [$r->id]])
            ->assertSessionHas('success', 'Assegnati 0 arbitri al torneo. 1 erano già assegnati.');

        $this->assertSame(0, TournamentNotification::where('tournament_id', $t->id)->count());
    }

    public function test_store_multiple_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $this->tournamentIn(2)), ['referee_ids' => [$this->referee(2)->id]])
            ->assertForbidden();
    }

    public function test_store_multiple_requires_referees(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $this->tournamentIn(1)), [])
            ->assertSessionHasErrors('referee_ids');
    }

    public function test_store_multiple_observer_refused_midway_rolls_back_everything(): void
    {
        $t = $this->tournamentIn(1, true);
        $obs = $this->referee(1);
        $ref = $this->referee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.assignments.storeMultiple', $t), [
                'referee_ids' => [$obs->id, $ref->id],
                'roles' => [$obs->id => 'Osservatore', $ref->id => 'Arbitro'],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Assignment::where('tournament_id', $t->id)->count(), 'anche l\'osservatore non resta');
    }

    // ── Rimozione ────────────────────────────────────────────────────────────

    public function test_remove_from_tournament(): void
    {
        $t = $this->tournamentIn(1);
        $r = $this->referee(1);
        $this->assign($r, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.assignments.assign-referees', $t))
            ->delete(route('admin.assignments.removeFromTournament', [$t, $r]))
            ->assertRedirect(route('admin.assignments.assign-referees', $t))
            ->assertSessionHas('success', 'Arbitro rimosso dal torneo');

        $this->assertSame(0, Assignment::where('tournament_id', $t->id)->count());
    }

    public function test_remove_from_tournament_not_assigned(): void
    {
        $t = $this->tournamentIn(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->delete(route('admin.assignments.removeFromTournament', [$t, $this->referee(1)]))
            ->assertSessionHas('error', 'Assegnazione non trovata');
    }

    public function test_remove_from_tournament_zone_admin_cannot_remove_crc_designation(): void
    {
        $t = $this->tournamentIn(1, true);
        $r = $this->referee(1);
        $this->assign($r, $t, 'Osservatore', $this->createNationalAdmin(['zone_id' => 8]));

        $this->actingAs($this->createZoneAdmin(1))
            ->delete(route('admin.assignments.removeFromTournament', [$t, $r]))
            ->assertSessionHas('error', "Sui tornei nazionali l'admin di zona può rimuovere solo gli osservatori designati dalla zona.");

        $this->assertSame(1, Assignment::where('tournament_id', $t->id)->count());
    }

    public function test_remove_from_tournament_zone_admin_removes_own_observer(): void
    {
        $t = $this->tournamentIn(1, true);
        $admin = $this->createZoneAdmin(1);
        $r = $this->referee(1);
        $this->assign($r, $t, 'Osservatore', $admin);

        $this->actingAs($admin)
            ->delete(route('admin.assignments.removeFromTournament', [$t, $r]))
            ->assertSessionHas('success');

        $this->assertSame(0, Assignment::where('tournament_id', $t->id)->count());
    }

    public function test_remove_from_tournament_other_zone_is_forbidden(): void
    {
        $t = $this->tournamentIn(2);
        $r = $this->referee(2);
        $this->assign($r, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->delete(route('admin.assignments.removeFromTournament', [$t, $r]))
            ->assertForbidden();
    }

    public function test_destroy_redirects_to_tournament_with_names(): void
    {
        $t = $this->tournamentIn(1, false, ['name' => 'Trofeo Rimozione']);
        $a = $this->assign($this->referee(1, '1_livello', 'Lia Rimossa'), $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->delete(route('admin.assignments.destroy', $a))
            ->assertRedirect(route('admin.tournaments.show', $t->id))
            ->assertSessionHas('success', 'Assegnazione di Lia Rimossa rimossa dal torneo Trofeo Rimozione');

        $this->assertDatabaseMissing('assignments', ['id' => $a->id]);
    }

    public function test_destroy_other_zone_is_refused(): void
    {
        $a = $this->assign($this->referee(2), $this->tournamentIn(2));

        $this->actingAs($this->createZoneAdmin(1))
            ->delete(route('admin.assignments.destroy', $a))
            ->assertSessionHas('error', 'Non hai i permessi per rimuovere questa assegnazione');

        $this->assertDatabaseHas('assignments', ['id' => $a->id]);
    }

    // ── Validazione Assegnazioni ─────────────────────────────────────────────

    public function test_validation_dashboard_counts_and_percentage(): void
    {
        $t = $this->tournamentIn(1);
        $this->assign($this->referee(1), $t);
        $this->assign($this->referee(1), $t);

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignment-validation.index'));

        $response->assertOk();
        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(2, $stats['total_assignments']);
        $this->assertSame(1, $stats['active_tournaments']);
        $this->assertIsNumeric($response->viewData('issuePercentage'));
    }

    public function test_validation_dashboard_without_assignments_has_zero_percentage(): void
    {
        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignment-validation.index'));

        $this->assertSame(0, $response->viewData('issuePercentage'));
    }

    public function test_conflicts_page_counts_severities(): void
    {
        $r = $this->referee(1);
        $a = $this->tournamentIn(1);
        $b = $this->tournamentIn(1, false, ['start_date' => $a->start_date, 'end_date' => $a->end_date]);
        $this->assign($r, $a);
        $this->assign($r, $b);

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignment-validation.conflicts'));

        $response->assertOk();
        $stats = $this->viewArray($response, 'conflictStats');
        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertSame(
            $stats['total'],
            array_sum([$stats['high_severity'], $stats['medium_severity'], $stats['low_severity']])
        );
    }

    public function test_missing_requirements_page(): void
    {
        $this->tournamentIn(1);

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.assignment-validation.missing-requirements'));

        $response->assertOk();
        $stats = $this->viewArray($response, 'stats');
        $this->assertArrayHasKey('issue_types', $stats);
        $this->assertSame($stats['total_tournaments'], $this->viewObject($response, 'tournaments', Collection::class)->count());
    }

    public function test_overassigned_page_threshold_never_below_one(): void
    {
        $r = $this->referee(1);
        $this->assign($r, $this->tournamentIn(1));
        $this->assign($r, $this->tournamentIn(1, false, ['start_date' => now()->addDays(40), 'end_date' => now()->addDays(41)]));

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignment-validation.overassigned', ['threshold' => -3]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('threshold'));
        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(1, $stats['total_overassigned']);
        $this->assertSame(2, $stats['max_assignments']);
    }

    public function test_underassigned_page_only_available_filter(): void
    {
        $declared = $this->referee(1);
        $silent = $this->referee(1);
        Availability::create(['user_id' => $declared->id, 'tournament_id' => $this->tournamentIn(1)->id, 'submitted_at' => now()]);
        $admin = $this->createZoneAdmin(1);

        $all = $this->actingAs($admin)->get(route('admin.assignment-validation.underassigned', ['threshold' => 0]));
        $all->assertOk();
        $this->assertSame(1, $all->viewData('threshold'));
        $allStats = $this->viewArray($all, 'stats');

        $only = $this->actingAs($admin)->get(route('admin.assignment-validation.underassigned', ['only_available' => 1]));
        $onlyStats = $this->viewArray($only, 'stats');

        $this->assertSame($onlyStats['available'], $onlyStats['total_underassigned']);
        $this->assertSame(0, array_sum([$onlyStats['unavailable'], $onlyStats['unknown']]));
        $this->assertGreaterThanOrEqual($onlyStats['total_underassigned'], $allStats['total_underassigned']);
        $this->assertNotNull($silent->id);
    }

    public function test_workload_thresholds_and_stats(): void
    {
        $busy = $this->referee(1);
        $this->assign($busy, $this->tournamentIn(1));
        $this->assign($busy, $this->tournamentIn(1, false, ['start_date' => now()->addDays(40), 'end_date' => now()->addDays(41)]));
        $this->referee(1);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignment-validation.workload', ['min' => 5, 'max' => 2]));

        $response->assertOk();
        $this->assertSame(5, $response->viewData('min'));
        $this->assertSame(5, $response->viewData('max'), 'max non scende sotto min');
        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(2, $stats['top']);
        $this->assertSame($stats['referees'], $stats['below']);
        $this->assertSame(0, $stats['above']);
    }
}
