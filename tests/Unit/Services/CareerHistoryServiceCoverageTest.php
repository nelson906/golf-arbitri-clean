<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\RefereeCareerHistory;
use App\Services\CareerHistoryService;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Storico carriera: i casi limite del servizio (archiviazione con
 * svuotamento, voci assenti, punteggio di completezza su piu' anni).
 */
class CareerHistoryServiceCoverageTest extends TestCase
{
    use CollectsIds;

    private CareerHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CareerHistoryService::class);
    }

    private function year(): int
    {
        return now()->year;
    }

    public function test_archive_year_with_clear_empties_source_tables(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $t = $this->createTournament([
            'start_date' => now()->setDate($this->year(), 3, 1),
            'end_date' => now()->setDate($this->year(), 3, 2),
        ]);
        $this->createAssignment(['user_id' => $referee->id, 'tournament_id' => $t->id]);

        $stats = $this->service->archiveYear($this->year(), true);

        $this->assertSame(1, $stats['referees_processed']);
        $this->assertSame([], $stats['errors']);
        $this->assertSame(0, Assignment::count());
        $this->assertDatabaseMissing('tournaments', ['id' => $t->id]);
        $this->assertNotNull(RefereeCareerHistory::where('user_id', $referee->id)->first());
    }

    public function test_archive_year_collects_errors_and_then_does_not_clear(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $t = $this->createTournament([
            'start_date' => now()->setDate($this->year(), 4, 1),
            'end_date' => now()->setDate($this->year(), 4, 2),
        ]);
        $this->createAssignment(['user_id' => $referee->id, 'tournament_id' => $t->id]);

        $service = $this->getMockBuilder(CareerHistoryService::class)
            ->onlyMethods(['archiveYearForUser', 'clearSourceData'])
            ->getMock();
        $service->method('archiveYearForUser')->willThrowException(new \RuntimeException('json troppo grande'));
        $service->expects($this->never())->method('clearSourceData');

        $stats = $service->archiveYear($this->year(), true);

        $this->assertSame(0, $stats['referees_processed']);
        $this->assertSame(["User {$referee->id}: json troppo grande"], $stats['errors']);
        $this->assertSame(1, Assignment::count());
    }

    public function test_update_and_remove_without_history_or_year_return_false(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);

        $this->assertFalse($this->service->updateTournamentEntry($referee->id, $this->year(), 1, ['name' => 'x']));
        $this->assertFalse($this->service->removeTournamentEntry($referee->id, $this->year(), 1));

        RefereeCareerHistory::create([
            'user_id' => $referee->id,
            'tournaments_by_year' => [(string) ($this->year() - 1) => [['id' => 1, 'name' => 'Vecchio']]],
            'assignments_by_year' => [],
            'availabilities_by_year' => [],
            'level_changes_by_year' => [],
            'career_stats' => [],
        ]);

        $this->assertFalse($this->service->updateTournamentEntry($referee->id, $this->year(), 1, ['name' => 'x']));
        $this->assertFalse($this->service->removeTournamentEntry($referee->id, $this->year(), 1));
    }

    public function test_batch_reports_missing_tournament_and_keeps_the_others(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $t = $this->createTournament();

        $result = $this->service->addBatchTournaments($referee->id, $this->year(), [
            ['tournament_id' => 999999, 'days_count' => 1],
            ['tournament_id' => $t->id, 'days_count' => 0, 'role' => 'Arbitro'],
        ]);

        $this->assertSame(1, $result['added']);
        $this->assertSame(['Torneo ID 999999 non trovato'], $result['errors']);

        $history = RefereeCareerHistory::where('user_id', $referee->id)->firstOrFail();
        $entry = $this->arrayAt($this->jsonRows(($history->tournaments_by_year ?? [])[$this->year()] ?? []), 0);
        $this->assertSame($t->id, $entry['id']);
        $this->assertArrayNotHasKey('days_count', $entry, 'zero giorni = non indicato');
    }

    public function test_completeness_score_grows_with_years_of_data(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $empty = ['tournaments' => [], 'assignments' => [], 'availabilities' => []];
        $full = [
            'tournaments' => [['id' => 1]],
            'assignments' => [['tournament_id' => 1, 'role' => 'Arbitro']],
            'availabilities' => [['tournament_id' => 1]],
        ];

        $h = $this->service->updateCareerHistory($referee->id, 2020, $full);
        $this->assertEquals(0.6, (float) $h->data_completeness_score);

        $this->service->updateCareerHistory($referee->id, 2021, $empty);
        $h = $this->service->updateCareerHistory($referee->id, 2022, $empty);
        $this->assertEquals(0.8, (float) $h->data_completeness_score, 'piu\' di un anno');

        $h = $this->service->updateCareerHistory($referee->id, 2023, $empty);
        $this->assertEquals(1.0, (float) $h->data_completeness_score, 'piu\' di tre anni');
        $this->assertSame('2023', $h->last_updated_year);
    }
}
