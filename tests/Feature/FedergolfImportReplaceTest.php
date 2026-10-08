<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentType;
use Tests\TestCase;

/**
 * Decisione 2026-10-04: il caricamento da FIG sovrascrive i precedenti.
 * Confermando il wizard, il comitato FIG sostituisce TUTTE le assegnazioni
 * del torneo locale (importate o fatte a mano).
 */
class FedergolfImportReplaceTest extends TestCase
{
    /** L'import guidato e' riservato all'account del .env (decisione 2026-10-08). */
    private function importer(): \App\Models\User
    {
        config(['golf.fig.import_email' => 'importatore@example.test']);

        return $this->createSuperAdmin(['email' => 'importatore@example.test']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nationalTournament(int $zoneId = 1, array $attributes = []): Tournament
    {
        $club = $this->createClub(['zone_id' => $zoneId]);

        return $this->createTournament(array_merge([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ], $attributes));
    }

    public function test_fig_committee_replaces_all_existing_assignments(): void
    {
        $tournament = $this->nationalTournament();
        $manuale = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $giaImportato = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $nuovo = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale']);

        Assignment::factory()->forUser($manuale)->forTournament($tournament)->asReferee()->create(['notes' => null]);
        Assignment::factory()->forUser($giaImportato)->forTournament($tournament)->asReferee()
            ->create(['notes' => 'Importato da federgolf.it']);

        $response = $this->actingAs($this->importer())
            ->postJson(route('admin.federgolf-import.execute'), [
                'tournament_id' => $tournament->id,
                'assegnazioni' => [
                    ['user_id' => $giaImportato->id, 'ruolo' => 'Direttore di Torneo'],
                    ['user_id' => $nuovo->id, 'ruolo' => 'Arbitro'],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'creati' => 2, 'rimossi' => 2]);

        $sostituite = $response->json('debug.assegnazioni_sostituite');
        $this->assertIsArray($sostituite);
        $this->assertCount(2, $sostituite);

        $rows = Assignment::where('tournament_id', $tournament->id)->pluck('role', 'user_id')->all();
        $this->assertSame([
            $giaImportato->id => 'Direttore di Torneo',
            $nuovo->id => 'Arbitro',
        ], $rows);
        $this->assertArrayNotHasKey($manuale->id, $rows, 'Anche le assegnazioni fatte a mano vengono sostituite');
    }

    public function test_duplicate_row_in_list_is_loaded_once(): void
    {
        $tournament = $this->nationalTournament();
        $arbitro = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);

        $this->actingAs($this->importer())
            ->postJson(route('admin.federgolf-import.execute'), [
                'tournament_id' => $tournament->id,
                'assegnazioni' => [
                    ['user_id' => $arbitro->id, 'ruolo' => 'Arbitro'],
                    ['user_id' => $arbitro->id, 'ruolo' => 'Osservatore'],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'creati' => 1, 'saltati' => 1]);

        $this->assertSame(1, Assignment::where('tournament_id', $tournament->id)->count());
    }

    public function test_zone_admin_cannot_replace_committees(): void
    {
        $tournament = $this->nationalTournament(2);
        $esistente = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale']);
        Assignment::factory()->forUser($esistente)->forTournament($tournament)->asReferee()->create();
        $altro = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.federgolf-import.execute'), [
                'tournament_id' => $tournament->id,
                'assegnazioni' => [['user_id' => $altro->id, 'ruolo' => 'Arbitro']],
            ])
            ->assertNotFound();

        $this->assertSame([$esistente->id], Assignment::where('tournament_id', $tournament->id)->pluck('user_id')->all());
    }

    public function test_wizard_lists_tournaments_whatever_their_old_status(): void
    {
        $this->nationalTournament(1, ['name' => 'Campionato Gia Giocato', 'status' => 'completed']);

        $this->actingAs($this->importer())
            ->get(route('admin.federgolf-import.index'))
            ->assertOk()
            ->assertSee('Campionato Gia Giocato');
    }
}
