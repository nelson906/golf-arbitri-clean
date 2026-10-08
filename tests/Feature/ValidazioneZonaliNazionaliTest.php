<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\AssignmentValidationService;
use App\Models\User;
use Tests\TestCase;

/**
 * Decisione 2026-10-08: Validazione Assegnazioni conta separatamente le
 * designazioni zonali e nazionali (osservatori nei nazionali). Il CRC vede e
 * giudica solo i nazionali; zona e super admin vedono le due colonne e
 * giudicano sul totale.
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

        // SZR: si giudica sul totale (3): con soglia 2 non e' sottoutilizzato
        $row = $this->rowFor($service->findOverassignedReferees(1, 2, false), $referee);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['assignments_count']);
        $this->assertNull($this->rowFor($service->findUnderassignedReferees(1, 2, false), $referee));

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

        // CRC: solo la colonna Nazionali
        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.underassigned', ['threshold' => 5]))
            ->assertOk()
            ->assertSee('Nora Nazionale')
            ->assertDontSee('>Zonali<', false)
            ->assertSee('Nazionali')
            ->assertSee('di cui 1 da osservatore')
            ->assertSee('designazioni nazionali');

        // SZR: Zonali, Nazionali e Totale
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.assignment-validation.overassigned', ['threshold' => 1]))
            ->assertOk()
            ->assertSee('Nora Nazionale')
            ->assertSeeInOrder(['Zonali', 'Nazionali', 'Totale'])
            ->assertSee('designazioni in totale');
    }

    public function test_availability_count_and_cards_always_open(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale', 'name' => 'Dina Dichiarata']);
        foreach ([10, 20] as $day) {
            \App\Models\Availability::create(['user_id' => $referee->id, 'tournament_id' => $this->tournament(true, $day)->id, 'submitted_at' => now()]);
        }
        \App\Models\Availability::create(['user_id' => $referee->id, 'tournament_id' => $this->tournament(false, 30)->id, 'submitted_at' => now()]);

        $service = app(AssignmentValidationService::class);
        $this->assertSame(2, $this->rowFor($service->findUnderassignedReferees(null, 2, true), $referee)['availabilities_count'] ?? null, 'CRC: solo nazionali');
        $this->assertSame(3, $this->rowFor($service->findUnderassignedReferees(1, 2, false), $referee)['availabilities_count'] ?? null, 'Zona: tutte');

        // Nessun sovrassegnato: la scheda si apre lo stesso per cambiare soglia
        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.assignment-validation.index'))
            ->assertOk()
            ->assertSee(route('admin.assignment-validation.overassigned'), false)
            ->assertSee('Apri e cambia soglia');
    }
}
