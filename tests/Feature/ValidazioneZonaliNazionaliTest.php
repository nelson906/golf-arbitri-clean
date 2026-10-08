<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\AssignmentValidationService;
use App\Models\User;
use Tests\TestCase;

/**
 * Decisione 2026-10-08: Validazione Assegnazioni conta separatamente le
 * designazioni zonali e nazionali (osservatori nei nazionali). Sovrassegnati
 * e sottoutilizzati si giudicano: CRC sui nazionali, SZR sugli zonali, super
 * admin sul totale.
 */
class ValidazioneZonaliNazionaliTest extends TestCase
{
    private function tournament(bool $national, int $day): Tournament
    {
        return $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => TournamentType::where('is_national', $national)->firstOrFail()->id,
            'start_date' => now()->startOfYear()->addDays($day),
            'end_date' => now()->startOfYear()->addDays($day + 1),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function rowFor(\Illuminate\Support\Collection $rows, User $referee): ?array
    {
        return $rows->first(fn (array $r) => ($r['referee'] ?? null) instanceof User && $r['referee']->id === $referee->id);
    }

    public function test_crc_counts_only_national_designations(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        foreach ([10, 20] as $day) {
            $this->createAssignment(['tournament_id' => $this->tournament(false, $day)->id, 'user_id' => $referee->id]);
        }
        $this->createAssignment(['tournament_id' => $this->tournament(true, 30)->id, 'user_id' => $referee->id, 'role' => 'Osservatore']);

        $service = app(AssignmentValidationService::class);

        // CRC: 1 nazionale (da osservatore), non 3
        $row = $this->rowFor($service->findUnderassignedReferees(null, 2, true), $referee);
        $this->assertNotNull($row);
        $this->assertSame([1, 2, 1, 1], [$row['assignments_count'], $row['zonal_count'], $row['national_count'], $row['national_observers']]);
        $this->assertNull($this->rowFor($service->findOverassignedReferees(null, 1, true), $referee));

        // SZR: si giudica sugli zonali (2)
        $row = $this->rowFor($service->findOverassignedReferees(1, 1, false), $referee);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['assignments_count']);

        // Super admin: totale (3)
        $row = $this->rowFor($service->findOverassignedReferees(null, 2, false), $referee);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['assignments_count']);
    }

    public function test_pages_show_zonal_and_national_columns(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Nora Nazionale']);
        $this->createAssignment(['tournament_id' => $this->tournament(false, 10)->id, 'user_id' => $referee->id]);
        $this->createAssignment(['tournament_id' => $this->tournament(true, 20)->id, 'user_id' => $referee->id, 'role' => 'Osservatore']);

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.underassigned', ['threshold' => 5]))
            ->assertOk()
            ->assertSee('Nora Nazionale')
            ->assertSee('Zonali')
            ->assertSee('Nazionali')
            ->assertSee('di cui 1 da osservatore')
            ->assertSee('designazioni nazionali');

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignment-validation.overassigned', ['threshold' => 0]))
            ->assertOk()
            ->assertSee('designazioni zonali');
    }
}
