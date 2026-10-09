<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\AssignmentValidationService;
use App\Models\User;
use Tests\TestCase;

/**
 * Validazione Assegnazioni conta separatamente designazioni e disponibilita'
 * zonali e nazionali (osservatori nei nazionali). Decisione 2026-10-09 (dal
 * Regolamento Arbitri): per SZR e CRC si giudica Nazionali/Internazionali
 * sulla colonna Nazionali, gli altri livelli sulla colonna Zonali.
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
     * @param  \Illuminate\Support\Collection<int, covariant array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function rowFor(\Illuminate\Support\Collection $rows, User $referee): ?array
    {
        return $rows->first(fn (array $r) => ($r['referee'] ?? null) instanceof User && $r['referee']->id === $referee->id);
    }

    public function test_count_depends_on_referee_level_for_crc_and_zone(): void
    {
        $national = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $regional = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);
        foreach ([$national, $regional] as $referee) {
            foreach ([10, 20] as $day) {
                $this->createAssignment(['tournament_id' => $this->tournament(false, $day)->id, 'user_id' => $referee->id]);
            }
            $this->createAssignment(['tournament_id' => $this->tournament(true, 30)->id, 'user_id' => $referee->id, 'role' => 'Osservatore']);
        }

        $service = app(AssignmentValidationService::class);

        foreach (['CRC' => [null, true], 'SZR' => [1, false]] as $who => [$zone, $crc]) {
            // Nazionale: si conta sulla colonna Nazionali (1), non sulle zonali (2)
            $row = $this->rowFor($service->findUnderassignedReferees($zone, 2, $crc), $national);
            $this->assertNotNull($row, $who);
            $this->assertSame(['national', 1, 2, 1, 1], [$row['basis'], $row['assignments_count'], $row['zonal_count'], $row['national_count'], $row['national_observers']], $who);
        }

        // Regionale (solo la zona lo vede): si conta sulle Zonali (2)
        $row = $this->rowFor($service->findOverassignedReferees(1, 1, false), $regional);
        $this->assertNotNull($row);
        $this->assertSame(['zonal', 2], [$row['basis'], $row['assignments_count']]);
        $this->assertNull($this->rowFor($service->findUnderassignedReferees(1, 2, false), $regional));
    }

    public function test_pages_show_both_columns_for_crc_and_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Nora Nazionale']);
        $this->createAssignment(['tournament_id' => $this->tournament(false, 10)->id, 'user_id' => $referee->id]);
        $this->createAssignment(['tournament_id' => $this->tournament(true, 20)->id, 'user_id' => $referee->id, 'role' => 'Osservatore']);
        $this->createAssignment(['tournament_id' => $this->tournament(true, 50)->id, 'user_id' => $referee->id, 'role' => 'Arbitro']);

        foreach ([$this->createNationalAdmin(), $this->createZoneAdmin(1)] as $admin) {
            foreach (['underassigned' => ['threshold' => 5], 'overassigned' => ['threshold' => 1], 'workload' => []] as $page => $params) {
                $response = $this->actingAs($admin)->get(route('admin.assignment-validation.'.$page, $params));
                $response
                    ->assertOk()
                    ->assertSee('Nora Nazionale')
                    ->assertSeeInOrder(['Zonali', 'Nazionali', 'Disp. zonali', 'Disp. nazionali'])
                    ->assertSee('di cui 1 da osservatore')
                    ->assertSee('per gli arbitri Nazionali e Internazionali');
            }
        }
    }

    public function test_availabilities_are_split_and_cards_always_open(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Dina Dichiarata']);
        foreach ([10, 20] as $day) {
            \App\Models\Availability::create(['user_id' => $referee->id, 'tournament_id' => $this->tournament(true, $day)->id, 'submitted_at' => now()]);
        }
        \App\Models\Availability::create(['user_id' => $referee->id, 'tournament_id' => $this->tournament(false, 30)->id, 'submitted_at' => now()]);

        $row = $this->rowFor(app(AssignmentValidationService::class)->findUnderassignedReferees(null, 2, true), $referee);
        $this->assertNotNull($row);
        $this->assertSame([1, 2], [$row['zonal_availabilities'], $row['national_availabilities']]);

        // Nessun sovrassegnato: la scheda si apre lo stesso per cambiare soglia
        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.index'))
            ->assertOk()
            ->assertSee(route('admin.assignment-validation.overassigned'), false)
            ->assertSee('Apri e cambia soglia');
    }

    /** Carico arbitri: tutti gli arbitri, ordinati, con soglie colorate (2026-10-08). */
    public function test_workload_page_lists_every_referee_by_load(): void
    {
        $busy = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Bruno Carico']);
        $idle = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Ivo Fermo']);
        $zonal = $this->createReferee(['zone_id' => 1, 'level' => '1_livello', 'name' => 'Zeno Zonale']);
        foreach ([10, 20, 30] as $day) {
            $this->createAssignment(['tournament_id' => $this->tournament(true, $day)->id, 'user_id' => $busy->id]);
        }
        $this->createAssignment(['tournament_id' => $this->tournament(false, 40)->id, 'user_id' => $zonal->id]);

        $rows = app(AssignmentValidationService::class)->refereeWorkload(null, true);
        $this->assertSame($busy->id, $rows->first()['referee']->id ?? null, 'Il piu\' carico in cima');
        $this->assertNotNull($this->rowFor($rows, $idle), 'Anche chi ha 0 designazioni');
        $this->assertNull($this->rowFor($rows, $zonal), 'CRC: solo livelli nazionali');

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.workload', ['min' => 1, 'max' => 2]))
            ->assertOk()
            ->assertSeeInOrder(['Bruno Carico', 'Ivo Fermo'])
            ->assertDontSee('Zeno Zonale')
            ->assertSee('bg-red-50', false)
            ->assertSee('bg-amber-50', false);

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignment-validation.workload'))
            ->assertOk()
            ->assertSee('Zeno Zonale');

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.index'))
            ->assertSee(route('admin.assignment-validation.workload'), false);
    }
}
