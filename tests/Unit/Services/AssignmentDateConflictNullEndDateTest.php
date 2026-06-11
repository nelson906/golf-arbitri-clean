<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Services\AssignmentValidationService;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Test di REGRESSIONE per il fix del bug "Carbon::parse(null)" in
 * AssignmentValidationService::datesOverlap() (vedi docs/STORICO.md, DeepTest 2026-03-22, Mutante 1).
 *
 * IMPORTANTE — precisazione emersa eseguendo i test:
 * la colonna `tournaments.end_date` è `date()` NOT NULL a livello di schema,
 * quindi un end_date null NON può arrivare dal DB. Il ramo difensivo del fix
 * ("se end_date è null usa start_date a fine giornata") protegge quindi solo
 * stati IN-MEMORY (modelli non persistiti / costruiti a mano), non un caso
 * raggiungibile da detectDateConfligts() che legge dal DB.
 *
 * Per questo il test agisce direttamente sul metodo `datesOverlap` (privato,
 * via reflection) con modelli in-memory: è il livello corretto per guardare un
 * comportamento difensivo. Documenta anche, nel codice, che la severità reale
 * del finding originale è più bassa di quanto il DeepTest implicasse.
 *
 * Se questo test rompe, il fallback su end_date null è regredito: il bug
 * originale (end_date → "adesso") farebbe PERDERE un conflitto reale nello
 * stesso giorno quando uno dei due tornei ha end_date null in memoria.
 */
class AssignmentDateConflictNullEndDateTest extends TestCase
{
    private function overlap(Assignment $a, Assignment $b): bool
    {
        $method = new ReflectionMethod(AssignmentValidationService::class, 'datesOverlap');
        $method->setAccessible(true);

        return $method->invoke(app(AssignmentValidationService::class), $a, $b);
    }

    /**
     * Costruisce un'assegnazione in-memory con un torneo dalle date date.
     * Niente persistenza: si esercita solo la logica di sovrapposizione.
     */
    private function assignmentWithDates(Carbon $start, ?Carbon $end): Assignment
    {
        $tournament = new Tournament();
        $tournament->start_date = $start;
        $tournament->end_date = $end;

        $assignment = new Assignment();
        $assignment->setRelation('tournament', $tournament);

        return $assignment;
    }

    /**
     * Due tornei lo STESSO giorno, il primo con end_date null (in memoria).
     * Con il fix → overlap rilevato. Con il vecchio bug (end_date → "adesso",
     * nel passato rispetto a date future) → overlap PERSO, test fallisce.
     */
    public function test_same_day_overlap_detected_with_null_end_date(): void
    {
        $day = Carbon::now()->addDays(20)->startOfDay();

        $a = $this->assignmentWithDates($day->copy(), null);
        $b = $this->assignmentWithDates($day->copy(), $day->copy()->addDay());

        $this->assertTrue(
            $this->overlap($a, $b),
            'Conflitto stesso-giorno con end_date null non rilevato: fix Carbon regredito.'
        );
    }

    /**
     * Controprova: due tornei ben distanti, il primo con end_date null,
     * NON devono sovrapporsi (nessun falso conflitto).
     */
    public function test_distant_tournaments_with_null_end_date_do_not_overlap(): void
    {
        $a = $this->assignmentWithDates(Carbon::now()->addDays(10)->startOfDay(), null);
        $b = $this->assignmentWithDates(
            Carbon::now()->addDays(60)->startOfDay(),
            Carbon::now()->addDays(61)->startOfDay()
        );

        $this->assertFalse(
            $this->overlap($a, $b),
            'Due tornei distanti (uno con end_date null) non devono sovrapporsi.'
        );
    }

    /**
     * Caso base senza null: sovrapposizione classica tra intervalli che
     * si accavallano. Protegge la logica lte/lte di datesOverlap.
     */
    public function test_overlapping_ranges_detected(): void
    {
        $a = $this->assignmentWithDates(
            Carbon::now()->addDays(10)->startOfDay(),
            Carbon::now()->addDays(13)->startOfDay()
        );
        $b = $this->assignmentWithDates(
            Carbon::now()->addDays(12)->startOfDay(),
            Carbon::now()->addDays(15)->startOfDay()
        );

        $this->assertTrue($this->overlap($a, $b));
    }
}
