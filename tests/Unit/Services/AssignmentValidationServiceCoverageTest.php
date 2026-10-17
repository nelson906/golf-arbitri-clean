<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\AssignmentValidationService;
use Illuminate\Support\Carbon;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Validazione Assegnazioni: gravita' dei conflitti di date, suggerimenti di
 * sostituzione, avviso "arbitri di altre zone" sui tornei zonali.
 * Sono solo indicazioni: niente blocca una designazione (2026-10-03).
 */
class AssignmentValidationServiceCoverageTest extends TestCase
{
    use CollectsIds;

    private AssignmentValidationService $service;

    private ?User $assigner = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssignmentValidationService::class);
    }

    private function day(int $offset): Carbon
    {
        return now()->addDays(20)->startOfDay()->addDays($offset);
    }

    private function tournament(int $from, int $to, int $zoneId = 1, bool $national = false, ?string $name = null): Tournament
    {
        return $this->createTournament(array_filter([
            'club_id' => $this->createClub(['zone_id' => $zoneId])->id,
            'tournament_type_id' => TournamentType::where('is_national', $national)->firstOrFail()->id,
            'start_date' => $this->day($from),
            'end_date' => $this->day($to),
            'availability_deadline' => $this->day($from - 5),
            'name' => $name,
        ], fn ($v) => $v !== null));
    }

    private function referee(int $zoneId = 1, string $level = 'Regionale'): User
    {
        return $this->createReferee(['zone_id' => $zoneId, 'level' => $level, 'is_active' => true]);
    }

    private function assign(User $referee, Tournament $t, string $role = 'Arbitro'): Assignment
    {
        $this->assigner ??= $this->createZoneAdmin(1);

        return Assignment::create([
            'user_id' => $referee->id,
            'tournament_id' => $t->id,
            'role' => $role,
            'assigned_by' => $this->assigner->id,
            'assigned_at' => now(),
        ]);
    }

    private function severityOf(int $aFrom, int $aTo, int $bFrom, int $bTo): string
    {
        $r = $this->referee();
        $this->assign($r, $this->tournament($aFrom, $aTo));
        $this->assign($r, $this->tournament($bFrom, $bTo));

        $conflicts = $this->service->detectDateConflicts()->where('referee.id', $r->id);
        $this->assertCount(1, $conflicts);
        $conflict = $conflicts->first();
        $this->assertNotNull($conflict);

        return $conflict['severity'];
    }

    /**
     * Suggerimenti per il conflitto dell'arbitro indicato.
     *
     * @return list<array<array-key, mixed>>
     */
    private function suggestionsFor(User $referee): array
    {
        $row = $this->service->suggestConflictResolutions($this->service->detectDateConflicts())
            ->firstWhere('referee.id', $referee->id);
        $this->assertIsArray($row, 'Nessun conflitto per questo arbitro.');

        return $this->jsonRows($row['suggestions'] ?? null);
    }

    /**
     * ID degli arbitri proposti in sostituzione.
     *
     * @param  list<array<array-key, mixed>>  $suggestions
     * @return list<int>
     */
    private function alternativeIds(array $suggestions): array
    {
        $replace = collect($suggestions)->firstWhere('action', 'replace_referee');
        $this->assertIsArray($replace, 'Nessuna proposta di sostituzione.');

        return $this->idsInOrder($replace['alternative_referees'] ?? null);
    }

    /**
     * Problemi trovati sul torneo indicato.
     *
     * @return list<array<array-key, mixed>>
     */
    private function issuesOf(Tournament $t, ?int $zoneId = null): array
    {
        $item = $this->service->findMissingRequirements($zoneId)
            ->first(fn (array $i): bool => ($i['tournament'] ?? null) instanceof Tournament && $i['tournament']->is($t));
        $this->assertIsArray($item, 'Torneo senza problemi.');
        $this->assertIsInt($item['total_severity'] ?? null);
        $this->lastSeverity = $item['total_severity'];

        return $this->jsonRows($item['issues'] ?? null);
    }

    private int $lastSeverity = 0;

    public function test_two_or_more_days_overlap_is_high(): void
    {
        $this->assertSame('high', $this->severityOf(0, 3, 0, 3));
    }

    public function test_one_day_overlap_is_medium(): void
    {
        $this->assertSame('medium', $this->severityOf(0, 2, 1, 3));
    }

    public function test_same_day_touch_is_low(): void
    {
        $this->assertSame('low', $this->severityOf(0, 1, 1, 2));
    }

    public function test_suggestions_offer_free_referees_and_timing_check_for_low(): void
    {
        $busy = $this->referee(1, 'Regionale');
        $a = $this->tournament(0, 1);
        $b = $this->tournament(1, 2);
        $this->assign($busy, $a);
        $this->assign($busy, $b);

        $free = $this->referee(1, 'Nazionale');
        $tooLow = $this->referee(1, 'Aspirante');
        $otherZone = $this->referee(2, 'Nazionale');
        $alsoBusy = $this->referee(1, 'Nazionale');
        $this->assign($alsoBusy, $this->tournament(1, 1));

        $suggestions = $this->suggestionsFor($busy);
        $alternatives = $this->alternativeIds($suggestions);
        $this->assertContains($free->id, $alternatives);
        $this->assertNotContains($tooLow->id, $alternatives, 'livello sotto il richiesto');
        $this->assertNotContains($otherZone->id, $alternatives, 'torneo zonale: stessa zona');
        $this->assertNotContains($alsoBusy->id, $alternatives, 'ha gia\' una gara in quelle date');

        $this->assertContains('verify_timing', array_column($suggestions, 'action'));
    }

    public function test_no_alternative_and_high_conflict_gives_no_suggestions(): void
    {
        $only = $this->referee(1, 'Regionale');
        $this->assign($only, $this->tournament(0, 3));
        $this->assign($only, $this->tournament(0, 3));
        User::where('user_type', 'referee')->where('id', '!=', $only->id)->update(['is_active' => false]);

        $this->assertSame([], $this->suggestionsFor($only));
    }

    public function test_national_tournament_alternatives_come_from_every_zone(): void
    {
        $busy = $this->referee(1, 'Nazionale');
        $this->assign($busy, $this->tournament(0, 3, 1, true));
        $this->assign($busy, $this->tournament(0, 3, 1, true));
        $farAway = $this->referee(5, 'Internazionale');

        $this->assertContains($farAway->id, $this->alternativeIds($this->suggestionsFor($busy)));
    }

    public function test_zonal_tournament_flags_referees_of_other_zones(): void
    {
        $t = $this->tournament(0, 1, 1, false, 'Gara Mista');
        $this->assign($this->referee(1, 'Nazionale'), $t);
        $this->assign($this->createReferee(['zone_id' => 2, 'level' => 'Nazionale', 'name' => 'Ospite Fuorizona']), $t);

        $wrongZone = collect($this->issuesOf($t, 1))->firstWhere('type', 'wrong_zone');
        $this->assertIsArray($wrongZone);
        $this->assertSame('1 arbitri appartengono a zone diverse', $wrongZone['message'] ?? null);
        $this->assertSame('medium', $wrongZone['severity'] ?? null);
        $names = $wrongZone['referees'] ?? null;
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $names);
        $this->assertSame(['Ospite Fuorizona'], $names->values()->all());
    }

    public function test_national_tournament_does_not_check_zone_but_wants_a_director(): void
    {
        $t = $this->tournament(0, 1, 1, true);
        $this->assign($this->referee(2, 'Nazionale'), $t);
        $this->assign($this->referee(3, 'Nazionale'), $t);

        $types = array_column($this->issuesOf($t), 'type');

        $this->assertNotContains('wrong_zone', $types);
        $this->assertContains('missing_role', $types);
        $this->assertGreaterThanOrEqual(3, $this->lastSeverity);
    }
}
