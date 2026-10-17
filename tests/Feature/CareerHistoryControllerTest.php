<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\CareerHistoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Pagine "Storico carriera" (admin): elenco, scheda, archiviazione di un
 * anno e correzioni a mano dello storico (aggiungi/togli/modifica tornei,
 * inserimento a blocchi).
 *
 * Regole di visibilita' come l'elenco utenti: la SZR la sua zona, il CRC gli
 * arbitri di livello nazionale (li vede, non modifica le schede), il super
 * admin tutti.
 */
class CareerHistoryControllerTest extends TestCase
{
    use CollectsIds;

    /**
     * Anno usato dai test. L'archiviazione accetta al massimo l'anno in corso,
     * quindi si lavora sull'anno in corso, con date ancorate al 1 marzo.
     */
    private function year(): int
    {
        return now()->year;
    }

    private function dateInYear(int $offset = 0): Carbon
    {
        return now()->setDate($this->year(), 3, 1)->startOfDay()->addDays($offset);
    }

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
            'start_date' => $this->dateInYear(),
            'end_date' => $this->dateInYear(2),
        ], $over));
    }

    private function zonalReferee(int $zoneId = 1, string $name = 'Mario Rossi'): User
    {
        return $this->createReferee(['zone_id' => $zoneId, 'level' => '1_livello', 'name' => $name]);
    }

    private function nationalReferee(int $zoneId = 1, string $name = 'Nino Nazionale'): User
    {
        return $this->createReferee(['zone_id' => $zoneId, 'level' => 'Nazionale', 'name' => $name]);
    }

    /**
     * Storico con un torneo (e la sua assegnazione) nell'anno dei test.
     */
    private function historyWith(User $referee, Tournament $tournament, string $role = 'Arbitro'): RefereeCareerHistory
    {
        $y = (string) $this->year();

        return RefereeCareerHistory::create([
            'user_id' => $referee->id,
            'tournaments_by_year' => [$y => [[
                'id' => $tournament->id,
                'name' => $tournament->name,
                'club_id' => $tournament->club_id,
                'club_name' => 'Circolo storico',
                'start_date' => $tournament->start_date->format('Y-m-d'),
                'end_date' => $tournament->end_date?->format('Y-m-d'),
            ]]],
            'assignments_by_year' => [$y => [[
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'role' => $role,
                'assigned_at' => null,
                'status' => 'completed',
            ]]],
            'availabilities_by_year' => [],
            'level_changes_by_year' => [],
            'career_stats' => [],
        ]);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function storedTournaments(User $referee): array
    {
        $history = RefereeCareerHistory::where('user_id', $referee->id)->firstOrFail();

        return $this->jsonRows($history->tournaments_by_year[$this->year()] ?? []);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function storedAssignments(User $referee): array
    {
        $history = RefereeCareerHistory::where('user_id', $referee->id)->firstOrFail();

        return $this->jsonRows($history->assignments_by_year[$this->year()] ?? []);
    }

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<int>
     */
    private function idsIn($response, string $key): array
    {
        return $this->sortedIds($response->viewData($key));
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function test_index_zone_admin_sees_only_referees_of_own_zone(): void
    {
        $mine = $this->zonalReferee(1, 'Anna Mia');
        $other = $this->zonalReferee(2, 'Bruno Altro');

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.index'));

        $response->assertOk();
        $this->assertSame([$mine->id], $this->idsIn($response, 'referees'));
        $this->assertNotContains($other->id, $this->idsIn($response, 'referees'));
        $this->assertFalse($response->viewData('canArchiveAll'));
        $this->assertCount(0, $this->viewObject($response, 'zones', Collection::class));
    }

    public function test_index_crc_sees_only_national_level_referees(): void
    {
        $national = $this->nationalReferee(2);
        $this->zonalReferee(2);

        $response = $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->get(route('admin.career-history.index'));

        $this->assertSame([$national->id], $this->idsIn($response, 'referees'));
    }

    public function test_index_super_admin_sees_all_and_can_filter_by_zone(): void
    {
        $a = $this->zonalReferee(1);
        $b = $this->zonalReferee(2);
        $admin = $this->createSuperAdmin();

        $all = $this->actingAs($admin)->get(route('admin.career-history.index'));
        $this->assertSame(collect([$a->id, $b->id])->sort()->values()->all(), $this->idsIn($all, 'referees'));
        $this->assertTrue($all->viewData('canArchiveAll'));
        $this->assertCount(8, $this->viewObject($all, 'zones', Collection::class));

        $zone2 = $this->actingAs($admin)->get(route('admin.career-history.index', ['zone_id' => 2]));
        $this->assertSame([$b->id], $this->idsIn($zone2, 'referees'));
    }

    public function test_index_zone_filter_is_ignored_for_zone_admin(): void
    {
        $mine = $this->zonalReferee(1);
        $this->zonalReferee(2);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.index', ['zone_id' => 2]));

        $this->assertSame([$mine->id], $this->idsIn($response, 'referees'));
    }

    public function test_index_search_by_name_or_email(): void
    {
        $mario = $this->createReferee(['zone_id' => 1, 'name' => 'Mario Bianchi', 'email' => 'mb@example.test']);
        $luca = $this->createReferee(['zone_id' => 1, 'name' => 'Luca Verdi', 'email' => 'cercami@example.test']);
        $admin = $this->createSuperAdmin();

        $byName = $this->actingAs($admin)->get(route('admin.career-history.index', ['search' => 'Bianchi']));
        $this->assertSame([$mario->id], $this->idsIn($byName, 'referees'));

        $byEmail = $this->actingAs($admin)->get(route('admin.career-history.index', ['search' => 'cercami']));
        $this->assertSame([$luca->id], $this->idsIn($byEmail, 'referees'));
    }

    public function test_index_filter_with_and_without_history(): void
    {
        $with = $this->zonalReferee(1);
        $without = $this->zonalReferee(1);
        $this->historyWith($with, $this->tournamentIn(1));
        $admin = $this->createSuperAdmin();

        $yes = $this->actingAs($admin)->get(route('admin.career-history.index', ['has_history' => '1']));
        $this->assertSame([$with->id], $this->idsIn($yes, 'referees'));

        $no = $this->actingAs($admin)->get(route('admin.career-history.index', ['has_history' => '0']));
        $this->assertSame([$without->id], $this->idsIn($no, 'referees'));
    }

    // ── Scheda ───────────────────────────────────────────────────────────────

    public function test_show_zone_admin_opens_own_referee_with_history(): void
    {
        $referee = $this->zonalReferee(1);
        $history = $this->historyWith($referee, $this->tournamentIn(1));

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.show', $referee));

        $response->assertOk();
        $this->assertTrue($this->viewObject($response, 'history', RefereeCareerHistory::class)->is($history));
    }

    public function test_show_referee_without_history_opens(): void
    {
        $referee = $this->zonalReferee(1);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.show', $referee));

        $response->assertOk();
        $this->assertNull($response->viewData('history'));
    }

    public function test_show_referee_of_other_zone_is_forbidden(): void
    {
        $referee = $this->zonalReferee(2);

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.show', $referee))
            ->assertForbidden();
    }

    public function test_show_crc_cannot_open_zonal_referee(): void
    {
        $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->get(route('admin.career-history.show', $this->zonalReferee(1)))
            ->assertForbidden();
    }

    // ── Archiviazione: form ──────────────────────────────────────────────────

    public function test_archive_form_zone_admin_counts_only_own_zone(): void
    {
        $mine = $this->zonalReferee(1);
        $other = $this->zonalReferee(2);
        $t1 = $this->tournamentIn(1);
        $t2 = $this->tournamentIn(2);
        $this->createAssignment(['user_id' => $mine->id, 'tournament_id' => $t1->id]);
        $this->createAssignment(['user_id' => $other->id, 'tournament_id' => $t2->id]);
        Availability::create(['user_id' => $mine->id, 'tournament_id' => $t1->id, 'submitted_at' => now()]);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.archive-form'));

        $response->assertOk();
        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(1, $stats['zone_id']);
        $this->assertSame(1, $stats['total_assignments']);
        $this->assertSame(1, $stats['referees_with_assignments']);
        $this->assertSame(1, $stats['total_availabilities']);
        $this->assertSame(1, $stats['referees_with_availabilities']);
        $this->assertSame(1, $stats['tournaments_count']);
        $this->assertSame([$mine->id], $this->idsIn($response, 'referees'));
        $this->assertFalse($response->viewData('canArchiveAll'));
        $this->assertSame($this->year(), $response->viewData('currentYear'));
    }

    public function test_archive_form_super_admin_counts_every_zone_even_with_own_zone(): void
    {
        $a = $this->zonalReferee(1);
        $b = $this->zonalReferee(2);
        $this->createAssignment(['user_id' => $a->id, 'tournament_id' => $this->tournamentIn(1)->id]);
        $this->createAssignment(['user_id' => $b->id, 'tournament_id' => $this->tournamentIn(2)->id]);

        $response = $this->actingAs($this->createSuperAdmin(['zone_id' => 1]))
            ->get(route('admin.career-history.archive-form'));

        $stats = $this->viewArray($response, 'stats');
        $this->assertNull($stats['zone_id']);
        $this->assertSame(2, $stats['total_assignments']);
        $this->assertSame(2, $stats['referees_with_assignments']);
        $this->assertTrue($response->viewData('canArchiveAll'));
    }

    public function test_archive_form_counts_by_tournament_year_not_other_years(): void
    {
        $referee = $this->zonalReferee(1);
        $lastYear = $this->tournamentIn(1, false, [
            'start_date' => $this->dateInYear()->subYear(),
            'end_date' => $this->dateInYear(1)->subYear(),
        ]);
        $this->createAssignment(['user_id' => $referee->id, 'tournament_id' => $lastYear->id]);

        $stats = $this->viewArray(
            $this->actingAs($this->createSuperAdmin())->get(route('admin.career-history.archive-form')),
            'stats'
        );

        $this->assertSame(0, $stats['total_assignments']);
        $this->assertSame(0, $stats['tournaments_count']);
    }

    // ── Archiviazione: esecuzione ────────────────────────────────────────────

    public function test_archive_zone_admin_cannot_clear_tables(): void
    {
        $referee = $this->zonalReferee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.career-history.archive-form'))
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'user_id' => $referee->id,
                'clear_data' => '1',
            ])
            ->assertRedirect(route('admin.career-history.archive-form'))
            ->assertSessionHas('error', 'Solo il super admin puo svuotare le tabelle');

        $this->assertDatabaseMissing('referee_career_history', ['user_id' => $referee->id]);
    }

    public function test_archive_zone_admin_must_choose_a_referee(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.career-history.archive-form'))
            ->post(route('admin.career-history.process-archive'), ['year' => $this->year()])
            ->assertRedirect(route('admin.career-history.archive-form'))
            ->assertSessionHas('error', 'Devi selezionare un arbitro specifico');
    }

    public function test_archive_zone_admin_cannot_archive_referee_of_other_zone(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'user_id' => $this->zonalReferee(2)->id,
            ])
            ->assertForbidden();
    }

    public function test_archive_future_year_is_rejected(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.career-history.process-archive'), ['year' => $this->year() + 1])
            ->assertSessionHasErrors('year');
    }

    public function test_archive_single_referee_writes_history_and_keeps_tables(): void
    {
        $referee = $this->zonalReferee(1, 'Paolo Archivio');
        $t = $this->tournamentIn(1);
        $this->createAssignment(['user_id' => $referee->id, 'tournament_id' => $t->id, 'role' => 'Arbitro']);
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'user_id' => $referee->id,
            ])
            ->assertRedirect(route('admin.career-history.show', $referee))
            ->assertSessionHas('success', "Anno {$this->year()} archiviato per Paolo Archivio: 1 tornei, 1 assegnazioni");

        $this->assertSame($t->id, $this->storedTournaments($referee)[0]['id']);
        $this->assertSame('Arbitro', $this->storedAssignments($referee)[0]['role']);
        $this->assertDatabaseHas('tournaments', ['id' => $t->id]);
        $this->assertDatabaseHas('assignments', ['tournament_id' => $t->id]);
    }

    public function test_archive_all_by_super_admin_without_clearing(): void
    {
        $a = $this->zonalReferee(1);
        $b = $this->zonalReferee(2);
        $t1 = $this->tournamentIn(1);
        $t2 = $this->tournamentIn(2);
        $this->createAssignment(['user_id' => $a->id, 'tournament_id' => $t1->id]);
        $this->createAssignment(['user_id' => $b->id, 'tournament_id' => $t2->id]);

        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.career-history.process-archive'), ['year' => $this->year()])
            ->assertRedirect(route('admin.career-history.index'))
            ->assertSessionHas('success', "Anno {$this->year()} archiviato: 2 arbitri, 2 assegnazioni");

        $this->assertSame(2, RefereeCareerHistory::count());
        $this->assertSame(2, Assignment::count());
    }

    public function test_archive_all_with_clear_data_empties_the_year(): void
    {
        $referee = $this->zonalReferee(1);
        $t = $this->tournamentIn(1);
        $kept = $this->tournamentIn(1, false, [
            'start_date' => $this->dateInYear()->subYear(),
            'end_date' => $this->dateInYear(1)->subYear(),
        ]);
        $this->createAssignment(['user_id' => $referee->id, 'tournament_id' => $t->id]);
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);

        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'clear_data' => '1',
            ])
            ->assertSessionHas('success', "Anno {$this->year()} archiviato: 1 arbitri, 1 assegnazioni. "
                .'Tabelle svuotate: 1 tornei, 1 assegnazioni, 1 disponibilita eliminate.');

        $this->assertDatabaseMissing('tournaments', ['id' => $t->id]);
        $this->assertSame(0, Assignment::count());
        $this->assertSame(0, Availability::count());
        $this->assertDatabaseHas('tournaments', ['id' => $kept->id]);
        // Lo storico resta
        $this->assertSame($t->id, $this->storedTournaments($referee)[0]['id']);
    }

    public function test_archive_all_with_errors_reports_count_and_does_not_clear(): void
    {
        $this->mock(CareerHistoryService::class, function (\Mockery\MockInterface $mock): void {
            $mock->shouldReceive('archiveYear')->once()->andReturn([
                'referees_processed' => 1,
                'assignments_archived' => 3,
                'availabilities_archived' => 0,
                'tournaments_archived' => 3,
                'errors' => ['User 9: rotto'],
            ]);
            $mock->shouldNotReceive('clearSourceData');
        });

        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'clear_data' => '1',
            ])
            ->assertSessionHas('success', "Anno {$this->year()} archiviato: 1 arbitri, 3 assegnazioni (1 errori)");
    }

    public function test_archive_failure_goes_back_with_the_error(): void
    {
        $referee = $this->zonalReferee(1);
        $this->mock(CareerHistoryService::class, function (\Mockery\MockInterface $mock): void {
            $mock->shouldReceive('archiveYearForUser')->once()->andThrow(new \RuntimeException('disco pieno'));
        });

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.career-history.archive-form'))
            ->post(route('admin.career-history.process-archive'), [
                'year' => $this->year(),
                'user_id' => $referee->id,
            ])
            ->assertRedirect(route('admin.career-history.archive-form'))
            ->assertSessionHas('error', "Errore durante l'archiviazione: disco pieno");
    }

    // ── Modifica anno ────────────────────────────────────────────────────────

    public function test_edit_year_without_history_goes_back_to_the_card(): void
    {
        $referee = $this->zonalReferee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.edit-year', [$referee, $this->year()]))
            ->assertRedirect(route('admin.career-history.show', $referee))
            ->assertSessionHas('error', 'Nessuno storico trovato per questo arbitro');
    }

    public function test_edit_year_other_zone_is_forbidden(): void
    {
        $referee = $this->zonalReferee(2);
        $this->historyWith($referee, $this->tournamentIn(2));

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.edit-year', [$referee, $this->year()]))
            ->assertForbidden();
    }

    public function test_edit_year_zonal_referee_can_add_only_own_zone_tournaments_not_already_there(): void
    {
        $referee = $this->zonalReferee(1);
        $already = $this->tournamentIn(1);
        $ownZone = $this->tournamentIn(1);
        $ownNational = $this->tournamentIn(1, true);
        $this->tournamentIn(2);
        $this->tournamentIn(2, true);
        $this->historyWith($referee, $already);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.edit-year', [$referee, $this->year()]));

        $response->assertOk();
        $this->assertSame(
            collect([$ownZone->id, $ownNational->id])->sort()->values()->all(),
            $this->idsIn($response, 'availableTournaments')
        );
        $this->assertCount(1, $this->viewArray($response, 'tournaments'));
        $this->assertCount(1, $this->viewArray($response, 'assignments'));
        $this->assertSame([], $this->viewArray($response, 'availabilities'));
    }

    public function test_edit_year_national_referee_can_add_national_tournaments_of_every_zone(): void
    {
        $referee = $this->nationalReferee(1);
        $ownZone = $this->tournamentIn(1);
        $otherNational = $this->tournamentIn(3, true);
        $this->tournamentIn(3);
        $this->historyWith($referee, $this->tournamentIn(1));

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.career-history.edit-year', [$referee, $this->year()]));

        $this->assertSame(
            collect([$ownZone->id, $otherNational->id])->sort()->values()->all(),
            $this->idsIn($response, 'availableTournaments')
        );
    }

    public function test_edit_year_includes_tba_tournament_of_the_zone(): void
    {
        $referee = $this->zonalReferee(1);
        $this->historyWith($referee, $this->tournamentIn(1));
        $tba = $this->tournamentIn(1);
        DB::table('tournaments')->where('id', $tba->id)->update(['club_id' => null, 'zone_id' => 1]);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.edit-year', [$referee, $this->year()]));

        $this->assertContains($tba->id, $this->idsIn($response, 'availableTournaments'));
    }

    public function test_edit_year_referee_without_zone_gets_only_national_or_nothing(): void
    {
        $zonal = $this->zonalReferee(1);
        $national = $this->nationalReferee(1);
        DB::table('users')->whereIn('id', [$zonal->id, $national->id])->update(['zone_id' => null]);
        $zonal->refresh();
        $national->refresh();
        $nationalTournament = $this->tournamentIn(2, true);
        $this->tournamentIn(2);
        $this->historyWith($zonal, $this->tournamentIn(3));
        $this->historyWith($national, $this->tournamentIn(3));
        $admin = $this->createSuperAdmin();

        $z = $this->actingAs($admin)->get(route('admin.career-history.edit-year', [$zonal, $this->year()]));
        $this->assertSame([], $this->idsIn($z, 'availableTournaments'));

        $n = $this->actingAs($admin)->get(route('admin.career-history.edit-year', [$national, $this->year()]));
        $this->assertSame([$nationalTournament->id], $this->idsIn($n, 'availableTournaments'));
    }

    // ── Aggiungi / togli / modifica ──────────────────────────────────────────

    public function test_add_tournament_other_zone_is_forbidden(): void
    {
        $referee = $this->zonalReferee(2);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $this->tournamentIn(2)->id,
            ])
            ->assertForbidden();
    }

    public function test_add_tournament_crc_cannot_edit_cards(): void
    {
        $referee = $this->nationalReferee(1);

        $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->post(route('admin.career-history.add-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $this->tournamentIn(1)->id,
            ])
            ->assertForbidden();
    }

    public function test_add_tournament_with_role_and_days(): void
    {
        $referee = $this->zonalReferee(1);
        $t = $this->tournamentIn(1, false, ['name' => 'Coppa Primavera']);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $t->id,
                'role' => 'Direttore di Torneo',
                'days_count' => 2,
            ])
            ->assertRedirect(route('admin.career-history.edit-year', [$referee, $this->year()]))
            ->assertSessionHas('success', "Torneo 'Coppa Primavera' aggiunto");

        $stored = $this->storedTournaments($referee);
        $this->assertCount(1, $stored);
        $this->assertSame($t->id, $stored[0]['id']);
        $this->assertSame(2, $stored[0]['days_count']);
        $this->assertSame($t->start_date->format('Y-m-d'), $stored[0]['start_date']);

        $assignments = $this->storedAssignments($referee);
        $this->assertSame('Direttore di Torneo', $assignments[0]['role']);
        $this->assertSame('manual_entry', $assignments[0]['status']);
        $this->assertSame(now()->format('Y-m-d'), $assignments[0]['assigned_at']);
    }

    public function test_add_tournament_without_role_or_days_adds_only_the_tournament(): void
    {
        $referee = $this->zonalReferee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $this->tournamentIn(1)->id,
            ]);

        $stored = $this->storedTournaments($referee);
        $this->assertCount(1, $stored);
        $this->assertArrayNotHasKey('days_count', $stored[0]);
        $this->assertSame([], $this->storedAssignments($referee));
    }

    public function test_add_tournament_unknown_id_is_rejected(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-tournament', $this->zonalReferee(1)), [
                'year' => $this->year(),
                'tournament_id' => 999999,
            ])
            ->assertSessionHasErrors('tournament_id');
    }

    public function test_add_multiple_tournaments_with_role(): void
    {
        $referee = $this->zonalReferee(1);
        $t1 = $this->tournamentIn(1);
        $t2 = $this->tournamentIn(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-multiple-tournaments', $referee), [
                'year' => $this->year(),
                'tournament_ids' => [$t1->id, $t2->id],
                'role' => 'Arbitro',
            ])
            ->assertRedirect(route('admin.career-history.edit-year', [$referee, $this->year()]))
            ->assertSessionHas('success', '2 tornei aggiunti allo storico');

        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], array_column($this->storedTournaments($referee), 'id'));
        $assignments = $this->storedAssignments($referee);
        $this->assertCount(2, $assignments);
        $this->assertSame(['Arbitro', 'Arbitro'], array_column($assignments, 'role'));

        $history = RefereeCareerHistory::where('user_id', $referee->id)->firstOrFail();
        $this->assertNotEmpty($history->career_stats);
    }

    public function test_add_multiple_tournaments_without_role(): void
    {
        $referee = $this->zonalReferee(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-multiple-tournaments', $referee), [
                'year' => $this->year(),
                'tournament_ids' => [$this->tournamentIn(1)->id],
            ])
            ->assertSessionHas('success', '1 tornei aggiunti allo storico');

        $this->assertCount(1, $this->storedTournaments($referee));
        $this->assertSame([], $this->storedAssignments($referee));
    }

    public function test_add_multiple_tournaments_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.add-multiple-tournaments', $this->zonalReferee(2)), [
                'year' => $this->year(),
                'tournament_ids' => [$this->tournamentIn(2)->id],
            ])
            ->assertForbidden();
    }

    public function test_remove_tournament_also_removes_its_assignment(): void
    {
        $referee = $this->zonalReferee(1);
        $t = $this->tournamentIn(1);
        $this->historyWith($referee, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.remove-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $t->id,
            ])
            ->assertRedirect(route('admin.career-history.edit-year', [$referee, $this->year()]))
            ->assertSessionHas('success', 'Torneo rimosso');

        $this->assertSame([], $this->storedTournaments($referee));
        $this->assertSame([], $this->storedAssignments($referee));
    }

    public function test_remove_tournament_without_history_warns(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.remove-tournament', $this->zonalReferee(1)), [
                'year' => $this->year(),
                'tournament_id' => 1,
            ])
            ->assertSessionHas('warning', 'Torneo non trovato');
    }

    public function test_remove_tournament_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.remove-tournament', $this->zonalReferee(2)), [
                'year' => $this->year(),
                'tournament_id' => 1,
            ])
            ->assertForbidden();
    }

    public function test_update_tournament_changes_only_given_fields(): void
    {
        $referee = $this->zonalReferee(1);
        $t = $this->tournamentIn(1);
        $this->historyWith($referee, $t);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.update-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => $t->id,
                'name' => 'Nome corretto',
                'club_name' => 'Circolo corretto',
                'start_date' => $this->dateInYear(10)->format('Y-m-d'),
                'end_date' => $this->dateInYear(11)->format('Y-m-d'),
                'days_count' => 2,
            ])
            ->assertSessionHas('success', 'Torneo aggiornato');

        $stored = $this->storedTournaments($referee)[0];
        $this->assertSame('Nome corretto', $stored['name']);
        $this->assertSame('Circolo corretto', $stored['club_name']);
        $this->assertSame($this->dateInYear(10)->format('Y-m-d'), $stored['start_date']);
        $this->assertSame($this->dateInYear(11)->format('Y-m-d'), $stored['end_date']);
        $this->assertSame(2, $stored['days_count']);
        $this->assertSame($t->club_id, $stored['club_id']);
    }

    public function test_update_tournament_not_in_history_warns(): void
    {
        $referee = $this->zonalReferee(1);
        $this->historyWith($referee, $this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.update-tournament', $referee), [
                'year' => $this->year(),
                'tournament_id' => 999999,
                'name' => 'X',
            ])
            ->assertSessionHas('warning', 'Torneo non trovato');
    }

    public function test_update_tournament_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.update-tournament', $this->zonalReferee(2)), [
                'year' => $this->year(),
                'tournament_id' => 1,
            ])
            ->assertForbidden();
    }

    // ── Inserimento a blocchi ────────────────────────────────────────────────

    public function test_batch_entry_form_lists_tournaments_not_yet_in_history(): void
    {
        $referee = $this->zonalReferee(1);
        $already = $this->tournamentIn(1);
        $free = $this->tournamentIn(1);
        $this->tournamentIn(2);
        $this->historyWith($referee, $already);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.batch-entry', [$referee, $this->year()]));

        $response->assertOk();
        $this->assertSame([$free->id], $this->idsIn($response, 'availableTournaments'));
    }

    public function test_batch_entry_form_national_referee_sees_national_of_other_zones(): void
    {
        $referee = $this->nationalReferee(1);
        $national = $this->tournamentIn(4, true);
        $own = $this->tournamentIn(1);
        $this->tournamentIn(4);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.batch-entry', [$referee, $this->year()]));

        $this->assertSame(
            collect([$national->id, $own->id])->sort()->values()->all(),
            $this->idsIn($response, 'availableTournaments')
        );
    }

    public function test_batch_entry_form_without_zone(): void
    {
        $zonal = $this->zonalReferee(1);
        $national = $this->nationalReferee(1);
        DB::table('users')->whereIn('id', [$zonal->id, $national->id])->update(['zone_id' => null]);
        $nationalTournament = $this->tournamentIn(2, true);
        $this->tournamentIn(2);
        $admin = $this->createSuperAdmin();

        $z = $this->actingAs($admin)->get(route('admin.career-history.batch-entry', [$zonal->fresh(), $this->year()]));
        $this->assertSame([], $this->idsIn($z, 'availableTournaments'));

        $n = $this->actingAs($admin)->get(route('admin.career-history.batch-entry', [$national->fresh(), $this->year()]));
        $this->assertSame([$nationalTournament->id], $this->idsIn($n, 'availableTournaments'));
    }

    public function test_batch_entry_form_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.career-history.batch-entry', [$this->zonalReferee(2), $this->year()]))
            ->assertForbidden();
    }

    public function test_batch_save_adds_tournaments_days_and_roles(): void
    {
        $referee = $this->zonalReferee(1);
        $t1 = $this->tournamentIn(1);
        $t2 = $this->tournamentIn(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.batch-save', $referee), [
                'year' => $this->year(),
                'tournaments' => [
                    ['tournament_id' => $t1->id, 'days_count' => 3, 'role' => 'Arbitro'],
                    ['tournament_id' => $t2->id, 'days_count' => 1],
                ],
            ])
            ->assertRedirect(route('admin.career-history.show', $referee))
            ->assertSessionHas('success', "Aggiunti 2 tornei per l'anno {$this->year()}");

        $days = array_column($this->storedTournaments($referee), 'days_count', 'id');
        $this->assertSame(3, $days[$t1->id] ?? null);
        $this->assertSame(1, $days[$t2->id] ?? null);

        $assignments = $this->storedAssignments($referee);
        $this->assertCount(1, $assignments);
        $this->assertSame($t1->id, $assignments[0]['tournament_id']);
        $this->assertSame('completed', $assignments[0]['status']);
    }

    public function test_batch_save_keeps_what_was_already_there(): void
    {
        $referee = $this->zonalReferee(1);
        $old = $this->tournamentIn(1);
        $this->historyWith($referee, $old);
        $new = $this->tournamentIn(1);

        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.batch-save', $referee), [
                'year' => $this->year(),
                'tournaments' => [['tournament_id' => $new->id, 'days_count' => 2]],
            ]);

        $this->assertEqualsCanonicalizing([$old->id, $new->id], array_column($this->storedTournaments($referee), 'id'));
        $this->assertCount(1, $this->storedAssignments($referee));
    }

    public function test_batch_save_requires_days(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.batch-save', $this->zonalReferee(1)), [
                'year' => $this->year(),
                'tournaments' => [['tournament_id' => $this->tournamentIn(1)->id]],
            ])
            ->assertSessionHasErrors('tournaments.0.days_count');
    }

    public function test_batch_save_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.career-history.batch-save', $this->zonalReferee(2)), [
                'year' => $this->year(),
                'tournaments' => [['tournament_id' => $this->tournamentIn(2)->id, 'days_count' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_batch_save_with_service_errors_goes_back_with_warning(): void
    {
        $referee = $this->zonalReferee(1);
        $t = $this->tournamentIn(1);
        $this->mock(CareerHistoryService::class, function (\Mockery\MockInterface $mock): void {
            $mock->shouldReceive('addBatchTournaments')->once()->andReturn([
                'added' => 0,
                'errors' => ['Torneo ID 5 non trovato'],
            ]);
        });

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.career-history.batch-entry', [$referee, $this->year()]))
            ->post(route('admin.career-history.batch-save', $referee), [
                'year' => $this->year(),
                'tournaments' => [['tournament_id' => $t->id, 'days_count' => 1]],
            ])
            ->assertRedirect(route('admin.career-history.batch-entry', [$referee, $this->year()]))
            ->assertSessionHas('warning', 'Aggiunti 0 tornei con alcuni errori')
            ->assertSessionHasErrors();
    }
}
