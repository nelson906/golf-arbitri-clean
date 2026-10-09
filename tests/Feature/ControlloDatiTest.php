<?php

namespace Tests\Feature;

use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\DataConsistencyService;
use Tests\TestCase;

/** Controllo dati (2026-10-09): ogni controllo trova il suo caso e solo quello. */
class ControlloDatiTest extends TestCase
{
    /** @return array<string, int> */
    private function counts(): array
    {
        $out = [];
        foreach (app(DataConsistencyService::class)->run() as $check) {
            $out[$check['key']] = count($check['rows']);
        }

        return $out;
    }

    public function test_clean_data_has_no_anomalies(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $t = $this->createTournament([
            'club_id' => $club->id, 'name' => 'Trofeo Tranquillo',
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
            'start_date' => now()->addDays(10), 'end_date' => now()->addDays(11), 'availability_deadline' => now()->addDays(3),
        ]);
        $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $this->createReferee(['zone_id' => 1, 'level' => 'Regionale'])->id, 'role' => 'Arbitro']);

        $this->assertSame([], array_filter($this->counts()));
    }

    public function test_each_check_finds_its_case(): void
    {
        $zonal = TournamentType::where('is_national', false)->firstOrFail()->id;
        $national = TournamentType::where('is_national', true)->firstOrFail()->id;
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);

        // Nazionale con tipo zonale, e mai notificato pur essendo gia' giocato
        $wrong = $this->createTournament(['club_id' => $club->id, 'name' => 'CAMPIONATO NAZIONALE MEDAL', 'tournament_type_id' => $zonal,
            'start_date' => now()->subDays(20), 'end_date' => now()->subDays(19), 'availability_deadline' => now()->subDays(30)]);
        $this->createAssignment(['tournament_id' => $wrong->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id, 'role' => 'Arbitro']);
        TournamentNotification::where('tournament_id', $wrong->id)->delete();

        // Regionale come Arbitro su un nazionale
        $nat = $this->createTournament(['club_id' => $club->id, 'name' => 'Trofeo Nazionale Tale', 'tournament_type_id' => $national,
            'start_date' => now()->addDays(40), 'end_date' => now()->addDays(41), 'availability_deadline' => now()->addDays(30)]);
        $this->createAssignment(['tournament_id' => $nat->id, 'user_id' => $this->createReferee(['zone_id' => 1, 'level' => 'Regionale'])->id, 'role' => 'Arbitro']);

        // Arbitro attivo senza zona; due account con lo stesso nome
        $this->createReferee(['level' => 'Aspirante'])->forceFill(['zone_id' => null])->save();
        $this->createReferee(['zone_id' => 1, 'first_name' => 'Mario', 'last_name' => 'Doppio']);
        $this->createReferee(['zone_id' => 2, 'first_name' => 'Mario', 'last_name' => 'Doppio']);

        $counts = $this->counts();
        $this->assertSame(1, $counts['nome_nazionale_tipo_zonale']);
        $this->assertSame(1, $counts['mai_notificati']);
        $this->assertSame(1, $counts['nazionali_livello_basso']);
        $this->assertSame(1, $counts['arbitri_senza_zona_livello']);
        $this->assertSame(2, $counts['arbitri_doppi']);
    }

    public function test_page_and_command(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->get(route('super-admin.data-check.index'))
            ->assertOk()
            ->assertSee('Controllo dati')
            ->assertSee('Nome da gara nazionale ma tipo zonale');

        $this->actingAs($this->createNationalAdmin())->get(route('super-admin.data-check.index'))->assertForbidden();

        $this->artisanCommand('golf:controlla-dati')->assertExitCode(0);
    }

    public function test_page_tour_finds_no_broken_page(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $t = $this->createTournament(['club_id' => $club->id]);
        $referee = $this->createReferee(['zone_id' => 1]);
        $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $referee->id]);
        $this->createZoneAdmin(1);
        $this->createNationalAdmin();
        $this->createSuperAdmin();

        $this->artisanCommand('golf:giro-pagine')->expectsOutputToContain('Nessuna pagina in errore')->assertExitCode(0);
    }
}
