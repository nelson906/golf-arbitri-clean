<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Services\AssignmentValidationService;
use Carbon\Carbon;
use Tests\TestCase;

class AssignmentValidationServiceTest extends TestCase
{

    /**
     * Data di test a 6 mesi da oggi, spostata di $offset giorni.
     *
     * Le date fisse invecchiano: prima erano hardcoded a marzo 2026 e sarebbero
     * uscite dall'anno corrente il 2027-01-01. Gli offset conservano le
     * sovrapposizioni volute fra i tornei (0-2 vs 1-3 = conflitto, 0-2 vs 5-7 = no).
     */
    private function futureDate(int $offset = 0): Carbon
    {
        return now()->addMonths(6)->startOfDay()->addDays($offset);
    }
    protected AssignmentValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssignmentValidationService;
    }

    // ==========================================
    // DATE CONFLICT TESTS
    // ==========================================

    /**
     * Test: Rileva conflitti di date
     */
    public function test_detects_date_conflicts(): void
    {
        $referee = $this->createReferee();

        // Torneo 1: 10-12 Marzo
        $tournament1 = Tournament::factory()->create([
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(2),
            'status' => 'open',
        ]);

        // Torneo 2: 11-13 Marzo (SOVRAPPOSTO!)
        $tournament2 = Tournament::factory()->create([
            'start_date' => $this->futureDate(1),
            'end_date' => $this->futureDate(3),
            'status' => 'open',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($tournament1)->create();
        Assignment::factory()->forUser($referee)->forTournament($tournament2)->create();

        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertEquals($referee->id, $conflicts->firstOrFail()['referee']->id);
    }

    /**
     * Test: Non rileva conflitti se date non si sovrappongono
     */
    public function test_does_not_detect_conflicts_for_non_overlapping_dates(): void
    {
        $referee = $this->createReferee();

        // Torneo 1: 10-12 Marzo
        $tournament1 = Tournament::factory()->create([
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(2),
            'status' => 'open',
        ]);

        // Torneo 2: 15-17 Marzo (SEPARATO)
        $tournament2 = Tournament::factory()->create([
            'start_date' => $this->futureDate(5),
            'end_date' => $this->futureDate(7),
            'status' => 'open',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($tournament1)->create();
        Assignment::factory()->forUser($referee)->forTournament($tournament2)->create();

        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(0, $conflicts);
    }

    /**
     * Test: Filtra conflitti per zona
     */
    public function test_filters_conflicts_by_zone(): void
    {
        $referee = $this->createReferee();
        $club1 = $this->createClub(['zone_id' => 1]);
        $club2 = $this->createClub(['zone_id' => 2]);

        // Due tornei sovrapposti zona 1 (zone_id must be set explicitly for DB query filtering)
        $t1 = Tournament::factory()->create([
            'club_id' => $club1->id,
            'zone_id' => 1,
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(2),
            'status' => 'open',
        ]);

        $t2 = Tournament::factory()->create([
            'club_id' => $club1->id,
            'zone_id' => 1,
            'start_date' => $this->futureDate(1),
            'end_date' => $this->futureDate(3),
            'status' => 'open',
        ]);

        // Torneo zona 2
        $t3 = Tournament::factory()->create([
            'club_id' => $club2->id,
            'zone_id' => 2,
            'start_date' => $this->futureDate(1),
            'end_date' => $this->futureDate(3),
            'status' => 'open',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($t1)->create();
        Assignment::factory()->forUser($referee)->forTournament($t2)->create();
        Assignment::factory()->forUser($referee)->forTournament($t3)->create();

        // Filtra solo zona 1
        $conflictsZone1 = $this->service->detectDateConflicts(1);

        // Dovrebbe trovare solo il conflitto in zona 1
        $this->assertGreaterThan(0, $conflictsZone1->count());
    }

    // ==========================================
    // VALIDATION SUMMARY TESTS
    // ==========================================

    /**
     * Test: getValidationSummary ritorna struttura corretta
     */
    public function test_validation_summary_returns_correct_structure(): void
    {
        $summary = $this->service->getValidationSummary();

        $this->assertArrayHasKey('conflicts', $summary);
        $this->assertArrayHasKey('missing_requirements', $summary);
        $this->assertArrayHasKey('overassigned', $summary);
        $this->assertArrayHasKey('underassigned', $summary);
        $this->assertArrayHasKey('total_issues', $summary);
    }

    /**
     * Test: getValidationSummary funziona con zona specifica
     */
    public function test_validation_summary_works_with_specific_zone(): void
    {
        $summary = $this->service->getValidationSummary(1);

        $this->assertIsInt($summary['total_issues']);
    }

    // ==========================================
    // HELPER METHOD TESTS
    // ==========================================

    /**
     * Test: datesOverlap correttamente
     *
     * (Questo test richiede che il metodo sia public o che testiamo indirettamente)
     */
    public function test_dates_overlap_logic_through_detect_conflicts(): void
    {
        $referee = $this->createReferee();

        // Case 1: Start dentro, end fuori
        $t1 = Tournament::factory()->create([
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(5),
            'status' => 'open',
        ]);

        $t2 = Tournament::factory()->create([
            'start_date' => $this->futureDate(2),
            'end_date' => $this->futureDate(8),
            'status' => 'open',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($t1)->create();
        Assignment::factory()->forUser($referee)->forTournament($t2)->create();

        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(1, $conflicts, 'Dovrebbe rilevare sovrapposizione parziale');
    }

    /**
     * Test: Conflitti con date consecutive non contano come overlap
     */
    public function test_consecutive_dates_do_not_conflict(): void
    {
        $referee = $this->createReferee();

        // Torneo 1: 10-12 Marzo (fine 12)
        $t1 = Tournament::factory()->create([
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(2),
            'status' => 'open',
        ]);

        // Torneo 2: 13-15 Marzo (inizio 13 - giorno dopo)
        $t2 = Tournament::factory()->create([
            'start_date' => $this->futureDate(3),
            'end_date' => $this->futureDate(5),
            'status' => 'open',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($t1)->create();
        Assignment::factory()->forUser($referee)->forTournament($t2)->create();

        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(0, $conflicts, 'Date consecutive non dovrebbero essere conflitto');
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    /**
     * Test: Service funziona senza assegnazioni
     */
    public function test_works_with_no_assignments(): void
    {
        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(0, $conflicts);
    }

    /**
     * Test: Service funziona con un solo assignment
     */
    public function test_works_with_single_assignment(): void
    {
        $referee = $this->createReferee();
        $tournament = $this->createTournament(['status' => 'open']);

        Assignment::factory()->forUser($referee)->forTournament($tournament)->create();

        $conflicts = $this->service->detectDateConflicts();

        $this->assertCount(0, $conflicts);
    }

    /**
     * Test: Ignora tornei completed/cancelled
     */
    public function test_ignores_completed_tournaments(): void
    {
        $referee = $this->createReferee();

        // Due tornei sovrapposti ma completed
        $t1 = Tournament::factory()->create([
            'start_date' => $this->futureDate(0),
            'end_date' => $this->futureDate(2),
            'status' => 'completed',
        ]);

        $t2 = Tournament::factory()->create([
            'start_date' => $this->futureDate(1),
            'end_date' => $this->futureDate(3),
            'status' => 'completed',
        ]);

        Assignment::factory()->forUser($referee)->forTournament($t1)->create();
        Assignment::factory()->forUser($referee)->forTournament($t2)->create();

        $conflicts = $this->service->detectDateConflicts();

        // Non dovrebbe rilevare conflitti perché completed
        $this->assertCount(0, $conflicts);
    }
}
