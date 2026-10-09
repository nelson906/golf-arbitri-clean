<?php

namespace Tests\Feature;

use App\Mail\BatchAvailabilityAdminNotification;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Decisioni di Alberto del 3 ottobre 2026 (guida tecnica, sezione 10).
 * Ogni test porta il numero del punto (P1...P19) a cui si riferisce.
 */
class Decisioni20261003Test extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    private function nationalType(): TournamentType
    {
        return TournamentType::where('is_national', true)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function openTournament(int $zoneId, bool $national = false, array $attributes = []): Tournament
    {
        $club = $this->createClub(['zone_id' => $zoneId]);

        return $this->createTournament(array_merge([
            'club_id' => $club->id,
            'tournament_type_id' => ($national ? $this->nationalType() : $this->zonalType())->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ], $attributes));
    }

    private function expiredTournament(int $zoneId): Tournament
    {
        return $this->openTournament($zoneId, false, [
            'availability_deadline' => now()->subDay()->startOfDay(),
        ]);
    }

    // ── P1: il salvataggio non tocca i tornei non mostrati ──────────────────

    public function test_p1_save_batch_keeps_availabilities_of_tournaments_not_on_page(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);

        $shown = $this->openTournament(1);
        $notShown = $this->openTournament(1);

        Availability::create(['user_id' => $referee->id, 'tournament_id' => $notShown->id, 'submitted_at' => now()]);

        $this->actingAs($referee)->post(route('user.availability.saveBatch'), [
            'page_tournaments' => [$shown->id],
            'availabilities' => [$shown->id],
        ])->assertRedirect(route('user.availability.index'));

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $shown->id]);
        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $notShown->id]);
    }

    public function test_p1_unchecking_a_shown_tournament_removes_only_that_availability(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);

        $a = $this->openTournament(1);
        $b = $this->openTournament(1);

        foreach ([$a, $b] as $t) {
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);
        }

        $this->actingAs($referee)->post(route('user.availability.saveBatch'), [
            'page_tournaments' => [$a->id],
            'availabilities' => [],
        ]);

        $this->assertDatabaseMissing('availabilities', ['user_id' => $referee->id, 'tournament_id' => $a->id]);
        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $b->id]);
    }

    // ── P2: dopo la scadenza la disponibilita' resta ───────────────────────

    public function test_p2_save_batch_never_removes_availability_after_deadline(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $expired = $this->expiredTournament(1);

        Availability::create(['user_id' => $referee->id, 'tournament_id' => $expired->id, 'submitted_at' => now()]);

        // Anche se il form (manomesso) lo dichiara mostrato e non spuntato
        $this->actingAs($referee)->post(route('user.availability.saveBatch'), [
            'page_tournaments' => [$expired->id],
            'availabilities' => [],
        ]);

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $expired->id]);
    }

    public function test_p2_single_removal_refused_after_deadline(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $expired = $this->expiredTournament(1);

        $availability = Availability::create(['user_id' => $referee->id, 'tournament_id' => $expired->id, 'submitted_at' => now()]);

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $expired->id,
            'available' => 0,
        ])->assertSessionHasErrors('availability');

        $this->assertDatabaseHas('availabilities', ['id' => $availability->id]);
    }

    // ── P3: niente stato del torneo ─────────────────────────────────────────

    public function test_p3_tournament_status_route_is_gone_and_creation_needs_no_status(): void
    {
        $this->assertFalse(Route::has('admin.tournaments.change-status'));

        $admin = $this->createZoneAdmin(1);
        $club = $this->createClub(['zone_id' => 1]);

        $this->actingAs($admin)->post(route('admin.tournaments.store'), [
            'name' => 'Coppa Senza Stato',
            'tournament_type_id' => $this->zonalType()->id,
            'club_id' => $club->id,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'availability_deadline' => now()->addDays(20)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tournaments', ['name' => 'Coppa Senza Stato']);
    }

    public function test_p3_referee_can_declare_on_tournament_whatever_its_old_status(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->openTournament(1, false, ['status' => 'draft']);

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $tournament->id,
            'available' => 1,
        ]);

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $tournament->id]);
    }

    // ── P5: disponibilita' sui nazionali della zona anche alla SZR ─────────

    public function test_p5_national_tournament_availability_notifies_crc_and_zone_admins(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $zoneAdmin = $this->createZoneAdmin(1, ['email' => 'szr1-admin@example.com']);
        $otherZoneAdmin = $this->createZoneAdmin(2, ['email' => 'szr2-admin@example.com']);
        $crc = $this->createNationalAdmin(['email' => 'crc@example.com']);

        $national = $this->openTournament(1, true);

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $national->id,
            'available' => 1,
        ]);

        Mail::assertQueued(BatchAvailabilityAdminNotification::class, fn (BatchAvailabilityAdminNotification $m) => $m->hasTo($zoneAdmin->email));
        Mail::assertQueued(BatchAvailabilityAdminNotification::class, fn (BatchAvailabilityAdminNotification $m) => $m->hasTo($crc->email));
        Mail::assertNotQueued(BatchAvailabilityAdminNotification::class, fn (BatchAvailabilityAdminNotification $m) => $m->hasTo($otherZoneAdmin->email));
    }

    // ── P7: carico e conflitti nella pagina Assegna Arbitri ────────────────

    public function test_p7_assign_page_shows_other_designations_and_date_conflicts(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Mario Conflitto']);

        $target = $this->openTournament(1);
        $overlapping = $this->openTournament(1, false, ['name' => 'Trofeo Sovrapposto']);

        Availability::create(['user_id' => $referee->id, 'tournament_id' => $target->id, 'submitted_at' => now()]);
        Assignment::factory()->forUser($referee)->forTournament($overlapping)->create();

        $this->actingAs($admin)
            ->get(route('admin.assignments.assign-referees', $target))
            ->assertOk()
            ->assertSee('Mario Conflitto')
            ->assertSee('Date sovrapposte: Trofeo Sovrapposto', false);
    }

    // ── P8: niente "Risolvi automaticamente" ────────────────────────────────

    public function test_p8_automatic_conflict_fix_is_gone(): void
    {
        $this->assertFalse(Route::has('admin.assignment-validation.fix-conflicts'));
        $this->assertFalse(method_exists(\App\Services\AssignmentValidationService::class, 'applyAutomaticFixes'));
    }

    // ── P9: admin di zona e tornei nazionali ────────────────────────────────

    public function test_p9_zone_admin_cannot_open_national_tournament_of_other_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $elsewhere = $this->openTournament(2, true);

        $this->actingAs($admin)
            ->get(route('admin.assignments.assign-referees', $elsewhere))
            ->assertForbidden();
    }

    public function test_p9_zone_admin_cannot_assign_referee_role_on_national_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1]);
        $national = $this->openTournament(1, true);

        $this->actingAs($admin)->post(route('admin.assignments.storeMultiple', $national), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => 'Arbitro'],
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('assignments', ['tournament_id' => $national->id]);
    }

    public function test_p9_zone_admin_designates_observers_on_national_tournament_of_own_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1]);
        $national = $this->openTournament(1, true);

        // Ruolo non scelto = Osservatore
        $this->actingAs($admin)->post(route('admin.assignments.storeMultiple', $national), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => ''],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assignments', [
            'tournament_id' => $national->id,
            'user_id' => $referee->id,
            'role' => 'Osservatore',
        ]);
    }

    public function test_p9_zone_admin_cannot_remove_crc_referee_from_national_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $national = $this->openTournament(1, true);

        $assignment = Assignment::factory()->forUser($referee)->forTournament($national)->asReferee()->create();

        $this->actingAs($admin)->delete(route('admin.assignments.destroy', $assignment));

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
    }

    public function test_p9_crc_still_assigns_referees_on_national_tournaments(): void
    {
        $crc = $this->createNationalAdmin();
        $referee = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale']);
        $national = $this->openTournament(1, true);

        $this->actingAs($crc)->post(route('admin.assignments.storeMultiple', $national), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => 'Arbitro'],
        ]);

        $this->assertDatabaseHas('assignments', ['tournament_id' => $national->id, 'role' => 'Arbitro']);
    }

    // ── P10: niente conferma ────────────────────────────────────────────────

    public function test_p10_confirmation_route_is_gone(): void
    {
        $this->assertFalse(Route::has('admin.assignments.confirm'));
    }

    // ── P14: super admin ────────────────────────────────────────────────────

    public function test_p14_national_admin_cannot_promote_to_super_admin(): void
    {
        $crc = $this->createNationalAdmin();
        $user = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($crc)->put(route('admin.users.update', $user), [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => $user->email,
            'user_type' => 'super_admin',
            'zone_id' => 1,
        ])->assertForbidden(); // 2026-10-07: il CRC non modifica le schede degli arbitri

        $this->assertNotSame('super_admin', $user->fresh()?->user_type->value);
    }

    public function test_p14_super_admin_can_promote_to_super_admin(): void
    {
        $super = $this->createSuperAdmin();
        $user = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($super)->put(route('admin.users.update', $user), [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => $user->email,
            'user_type' => 'super_admin',
            'zone_id' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('super_admin', $user->fresh()?->user_type->value);
    }

    public function test_p14_zone_admin_can_promote_referee_to_zone_admin(): void
    {
        $admin = $this->createZoneAdmin(1);
        $user = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => $user->email,
            'user_type' => 'admin',
            'zone_id' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('admin', $user->fresh()?->user_type->value);
    }

    public function test_p14_only_super_admin_can_edit_a_super_admin(): void
    {
        $crc = $this->createNationalAdmin();
        $super = $this->createSuperAdmin(['zone_id' => 1]);

        $this->actingAs($crc)->get(route('admin.users.edit', $super))->assertForbidden();
    }

    // ── P16: Le Mie Assegnazioni ────────────────────────────────────────────

    public function test_p16_referee_sees_all_own_assignments(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $other = $this->createReferee(['zone_id' => 1]);

        $mine = $this->openTournament(1, false, ['name' => 'Gara Mia Futura']);
        $past = $this->openTournament(1, false, [
            'name' => 'Gara Mia Passata',
            'start_date' => now()->subDays(10)->startOfDay(),
            'end_date' => now()->subDays(9)->startOfDay(),
            'availability_deadline' => now()->subDays(20)->startOfDay(),
        ]);
        $notMine = $this->openTournament(1, false, ['name' => 'Gara Di Altri']);

        Assignment::factory()->forUser($referee)->forTournament($mine)->create();
        Assignment::factory()->forUser($referee)->forTournament($past)->create();
        Assignment::factory()->forUser($other)->forTournament($notMine)->create();

        $this->actingAs($referee)
            ->get(route('user.assignments.index'))
            ->assertOk()
            ->assertSee('Gara Mia Futura')
            ->assertSee('Gara Mia Passata')
            ->assertDontSee('Gara Di Altri');
    }

    public function test_p16_menu_links_to_my_assignments(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($referee)
            ->get(route('user.availability.index'))
            ->assertOk()
            ->assertSee(route('user.assignments.index'), false);
    }

    // ── P19: codice morto ───────────────────────────────────────────────────

    public function test_p19_unrouted_user_document_methods_are_gone(): void
    {
        $this->assertFalse(method_exists(\App\Http\Controllers\User\DocumentController::class, 'upload'));
        $this->assertFalse(method_exists(\App\Http\Controllers\User\DocumentController::class, 'destroy'));
    }
}
