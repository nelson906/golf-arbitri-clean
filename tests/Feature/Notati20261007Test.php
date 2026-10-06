<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\AssignmentValidationService;
use App\Services\CalendarDataService;
use Tests\TestCase;

/**
 * Le tre cose notate il 6 ottobre 2026 e corrette il 7:
 *  1. Validazione Assegnazioni per il CRC: solo tornei nazionali e arbitri nazionali
 *  2. anteprima dell'archiviazione: assegnazioni contate per anno del torneo
 *  3. calendari React: campi che il server ora manda davvero
 */
class Notati20261007Test extends TestCase
{
    private function tournament(int $zoneId, bool $national, int $daysFromNow = 30): Tournament
    {
        $club = $this->createClub(['zone_id' => $zoneId]);
        $type = TournamentType::where('is_national', $national)->firstOrFail();
        $type->update(['min_referees' => 1]);

        return $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => $type->id,
            'start_date' => now()->addDays($daysFromNow)->startOfDay(),
            'end_date' => now()->addDays($daysFromNow + 1)->startOfDay(),
            'availability_deadline' => now()->addDays($daysFromNow - 10)->startOfDay(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function tournamentIds($rows): array
    {
        return $rows->map(function (array $row): int {
            $t = $row['tournament'] ?? null;
            $this->assertInstanceOf(Tournament::class, $t);

            return $t->id;
        })->values()->all();
    }

    // ── 1. Validazione per il CRC ───────────────────────────────────────────

    public function test_crc_validation_shows_only_national_tournaments(): void
    {
        $zonal = $this->tournament(1, false);
        $national = $this->tournament(1, true);

        $response = $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.missing-requirements'))
            ->assertOk();

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
        $rows = $this->viewObject($response, 'tournaments', \Illuminate\Support\Collection::class);
        $ids = $this->tournamentIds($rows);

        $this->assertContains($national->id, $ids);
        $this->assertNotContains($zonal->id, $ids);
    }

    public function test_super_admin_validation_still_shows_everything(): void
    {
        $zonal = $this->tournament(1, false);
        $national = $this->tournament(1, true);

        $ids = $this->tournamentIds(app(AssignmentValidationService::class)->findMissingRequirements(null, false));

        $this->assertContains($national->id, $ids);
        $this->assertContains($zonal->id, $ids);
    }

    public function test_crc_underassigned_lists_only_national_level_referees(): void
    {
        $national = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $regional = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);

        $rows = app(AssignmentValidationService::class)->findUnderassignedReferees(null, 2, true);
        $ids = $rows->map(function (array $row): int {
            $referee = $row['referee'] ?? null;
            $this->assertInstanceOf(\App\Models\User::class, $referee);

            return $referee->id;
        })->values()->all();

        $this->assertContains($national->id, $ids);
        $this->assertNotContains($regional->id, $ids);
    }

    // ── 2. Anteprima archiviazione per anno del torneo ──────────────────────

    public function test_archive_preview_counts_assignments_by_tournament_year(): void
    {
        $thisYear = $this->tournament(1, false, 1);
        $thisYear->update([
            'start_date' => now()->startOfYear()->addDays(200),
            'end_date' => now()->startOfYear()->addDays(201),
        ]);
        $referee = $this->createReferee(['zone_id' => 1]);
        $assignment = $this->createAssignment(['tournament_id' => $thisYear->id, 'user_id' => $referee->id]);
        // Designazione registrata l'anno prima del torneo
        $assignment->forceFill(['assigned_at' => now()->subYear()])->save();

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.career-history.archive-form'))
            ->assertOk();

        $stats = $this->viewArray($response, 'stats');
        // Il super admin vede tutte le zone, anche se ha una zona sul profilo
        $this->assertNull($stats['zone_id']);
        $this->assertSame(1, $stats['total_assignments']);
    }

    // ── 3. Calendario arbitro: scadenza e giorni mancanti ───────────────────

    public function test_referee_calendar_sends_deadline_and_days(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->tournament(1, false);
        $tournament->update(['availability_deadline' => now()->addDays(4)->setTime(0, 0)]);
        $tournament->refresh();

        $data = app(CalendarDataService::class)
            ->prepareFullCalendarData(collect([$tournament]), $referee, 'referee');

        $event = $data['tournaments']->first();
        $this->assertIsArray($event);
        $props = $this->arrayAt($event, 'extendedProps');
        $this->assertSame(4, $props['days_until_deadline']);
        $this->assertSame($tournament->availability_deadline->format('d/m/Y'), $props['deadline']);
    }
}
