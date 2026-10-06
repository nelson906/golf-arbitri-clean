<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentType;
use Tests\TestCase;

/**
 * Correzioni del 6 ottobre 2026 (documento «Esame del progetto — codice morto
 * e incongruenze»). Ogni test porta il numero del punto (D1...D14).
 */
class Pulizia20261006Test extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    private function nationalType(): TournamentType
    {
        return TournamentType::where('is_national', true)->firstOrFail();
    }

    private function futureTournament(int $zoneId, bool $national = false): Tournament
    {
        $club = $this->createClub(['zone_id' => $zoneId]);

        return $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => ($national ? $this->nationalType() : $this->zonalType())->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);
    }

    // ── D1: la prima assegnazione di un torneo zonale crea la bozza ─────────

    public function test_d1_first_assignment_on_zonal_tournament_creates_pending_notification(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->futureTournament(1);

        $this->actingAs($admin)->post(route('admin.assignments.storeMultiple', $tournament), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => 'Arbitro'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'pending',
        ]);
    }

    public function test_d1_first_assignment_on_national_tournament_creates_no_notification(): void
    {
        $crc = $this->createNationalAdmin();
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $tournament = $this->futureTournament(1, true);

        $this->actingAs($crc)->post(route('admin.assignments.storeMultiple', $tournament), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => 'Arbitro'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tournament_notifications', ['tournament_id' => $tournament->id]);
    }
}
