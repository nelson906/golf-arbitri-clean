<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\FedergolfCommitteeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Decisione 2026-10-04: caricamento completo da FIG, da comando, che
 * sovrascrive i precedenti (--replace). Federgolf e' simulato.
 */
class FedergolfBatchReplaceTest extends TestCase
{
    private Tournament $tournament;

    private User $manuale;

    private User $rossi;

    private User $bianchi;

    protected function setUp(): void
    {
        parent::setUp();

        $club = $this->createClub(['zone_id' => 1, 'name' => 'Golf Club Monticello']);
        $this->tournament = $this->createTournament([
            'name' => 'Campionato Nazionale Match Play',
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
            'start_date' => now()->startOfYear()->addMonths(5)->startOfDay(),
            'end_date' => now()->startOfYear()->addMonths(5)->addDays(2)->startOfDay(),
        ]);

        $this->manuale = $this->createReferee(['name' => 'Carlo Verdi', 'first_name' => 'Carlo', 'last_name' => 'Verdi']);
        $this->rossi = $this->createReferee(['name' => 'Mario Rossi', 'first_name' => 'Mario', 'last_name' => 'Rossi']);
        $this->bianchi = $this->createReferee(['name' => 'Luca Bianchi', 'first_name' => 'Luca', 'last_name' => 'Bianchi']);

        Assignment::factory()->forUser($this->manuale)->forTournament($this->tournament)->asReferee()->create();
        Assignment::factory()->forUser($this->rossi)->forTournament($this->tournament)->asObserver()->create();

        Http::fake(['*' => Http::response(['data' => [[
            'competition_id' => 'fig-1',
            'nome' => 'CAMPIONATO NAZIONALE MATCH PLAY',
            'data' => $this->tournament->start_date->format('d/m/Y'),
            'club' => 'GOLF CLUB MONTICELLO',
            'annullata' => false,
        ]]])]);
    }

    /**
     * @param  list<array{nome: string, cognome: string, ruolo: string}>  $membri
     */
    private function figCommittee(array $membri): void
    {
        $comitato = array_map(fn (array $m) => [
            'nome' => $m['nome'],
            'cognome' => $m['cognome'],
            'nome_completo' => $m['cognome'].', '.$m['nome'],
            'ruolo' => $m['ruolo'],
            'ruolo_normalizzato' => $m['ruolo'],
        ], $membri);

        // Solo il recupero da federgolf.it e' simulato; il match dei nomi e' quello vero
        $this->app->instance(FedergolfCommitteeService::class, new class($comitato) extends FedergolfCommitteeService
        {
            /** @param  list<array{nome: string, cognome: string, nome_completo: string, ruolo: string, ruolo_normalizzato: string}>  $comitato */
            public function __construct(private readonly array $comitato) {}

            public function fetchCommittee(string $competitionId): array
            {
                return $this->comitato;
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function assegnazioni(): array
    {
        /** @var array<int, string> $righe */
        $righe = Assignment::where('tournament_id', $this->tournament->id)->pluck('role', 'user_id')->all();

        return $righe;
    }

    public function test_replace_swaps_all_assignments_with_fig_committee(): void
    {
        $this->figCommittee([
            ['nome' => 'MARIO', 'cognome' => 'ROSSI', 'ruolo' => 'Direttore di Torneo'],
            ['nome' => 'LUCA', 'cognome' => 'BIANCHI', 'ruolo' => 'Arbitro'],
        ]);

        $this->artisanCommand('federgolf:import-committees', ['--anno' => now()->year, '--replace' => true])
            ->assertSuccessful();

        $this->assertSame([
            $this->rossi->id => 'Direttore di Torneo',
            $this->bianchi->id => 'Arbitro',
        ], $this->assegnazioni());
    }

    public function test_replace_dry_run_changes_nothing(): void
    {
        $this->figCommittee([['nome' => 'LUCA', 'cognome' => 'BIANCHI', 'ruolo' => 'Arbitro']]);
        $prima = $this->assegnazioni();

        $this->artisanCommand('federgolf:import-committees', ['--anno' => now()->year, '--replace' => true, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($prima, $this->assegnazioni());
    }

    public function test_replace_leaves_tournament_alone_when_no_name_matches(): void
    {
        $this->figCommittee([['nome' => 'ZEBEDEO', 'cognome' => 'QUAGLIARULO', 'ruolo' => 'Arbitro']]);
        $prima = $this->assegnazioni();

        $this->artisanCommand('federgolf:import-committees', ['--anno' => now()->year, '--replace' => true])
            ->assertSuccessful();

        $this->assertSame($prima, $this->assegnazioni());
    }

    public function test_without_replace_existing_assignments_are_kept(): void
    {
        $this->figCommittee([['nome' => 'LUCA', 'cognome' => 'BIANCHI', 'ruolo' => 'Arbitro']]);

        $this->artisanCommand('federgolf:import-committees', ['--anno' => now()->year])->assertSuccessful();

        $this->assertArrayHasKey($this->manuale->id, $this->assegnazioni());
        $this->assertArrayHasKey($this->bianchi->id, $this->assegnazioni());
    }
}
