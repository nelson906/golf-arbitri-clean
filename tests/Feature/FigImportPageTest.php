<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\FedergolfCommitteeService;
use App\Support\FigImportAccess;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Decisione 2026-10-05: caricamento completo dei comitati FIG dalla pagina
 * web, riservato a un solo account super admin il cui indirizzo sta solo
 * nel .env (FIG_IMPORT_EMAIL): nei test lo si imposta in setUp().
 */
class FigImportPageTest extends TestCase
{
    private const RISERVATO = 'riservato@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['golf.fig.import_email' => self::RISERVATO]);
    }

    private function owner(): User
    {
        return $this->createSuperAdmin(['email' => self::RISERVATO]);
    }

    /**
     * @param  int  $quante  gare FIG simulate
     */
    private function fakeFig(int $quante, Tournament $tournament): void
    {
        $gare = [];
        for ($i = 1; $i <= $quante; $i++) {
            $gare[] = [
                'competition_id' => 'fig-'.$i,
                'nome' => $i === 1 ? 'CAMPIONATO NAZIONALE MATCH PLAY' : 'GARA DI PROVA NUMERO '.$i,
                'data' => $i === 1 ? $tournament->start_date->format('d/m/Y') : '01/01/'.now()->year,
                'club' => $i === 1 ? 'GOLF CLUB MONTICELLO' : 'CIRCOLO SCONOSCIUTO '.$i,
                'annullata' => false,
            ];
        }
        Http::fake(['*' => Http::response(['data' => $gare])]);

        $this->app->instance(FedergolfCommitteeService::class, new class extends FedergolfCommitteeService
        {
            public function __construct() {}

            public function fetchCommittee(string $competitionId): array
            {
                return [[
                    'nome' => 'LUCA',
                    'cognome' => 'BIANCHI',
                    'nome_completo' => 'BIANCHI, LUCA',
                    'ruolo' => 'Arbitro',
                    'ruolo_normalizzato' => 'Arbitro',
                ]];
            }
        });
    }

    private function nationalTournament(): Tournament
    {
        $club = $this->createClub(['zone_id' => 1, 'name' => 'Golf Club Monticello']);

        return $this->createTournament([
            'name' => 'Campionato Nazionale Match Play',
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
            'start_date' => now()->startOfYear()->addMonths(5)->startOfDay(),
            'end_date' => now()->startOfYear()->addMonths(5)->addDays(2)->startOfDay(),
        ]);
    }

    public function test_only_the_dedicated_account_sees_the_menu_and_opens_the_page(): void
    {
        $this->actingAs($this->owner())->get(route('super-admin.fig-import.index'))
            ->assertOk()
            ->assertSee('Carica comitati FIG')
            ->assertSee('Prova')
            ->assertSee('Esegui');

        $altroSuper = $this->createSuperAdmin(['email' => 'altro.super@example.test']);
        $this->actingAs($altroSuper)->get(route('super-admin.fig-import.index'))->assertNotFound();
        $this->actingAs($altroSuper)->get(route('super-admin.zones.index'))
            ->assertOk()
            ->assertDontSee(route('super-admin.fig-import.index'));

        // Fermato prima dal filtro super admin, uguale per tutte le pagine Sistema
        $this->actingAs($this->createNationalAdmin())->get(route('super-admin.fig-import.index'))->assertForbidden();
    }

    public function test_dedicated_email_without_super_admin_powers_is_refused(): void
    {
        $user = $this->createNationalAdmin(['email' => self::RISERVATO]);

        $this->assertFalse(FigImportAccess::allows($user));
    }

    public function test_other_accounts_cannot_run_a_block(): void
    {
        $this->actingAs($this->createSuperAdmin(['email' => 'altro.super@example.test']))
            ->postJson(route('super-admin.fig-import.block'), [
                'anno' => now()->year, 'offset' => 0, 'replace' => true, 'dry_run' => false, 'run' => 'abcdefgh-1234',
            ])
            ->assertNotFound();
    }

    public function test_blocks_run_in_sequence_and_replace_assignments(): void
    {
        $tournament = $this->nationalTournament();
        $vecchio = $this->createReferee(['name' => 'Carlo Verdi', 'first_name' => 'Carlo', 'last_name' => 'Verdi']);
        $bianchi = $this->createReferee(['name' => 'Luca Bianchi', 'first_name' => 'Luca', 'last_name' => 'Bianchi']);
        Assignment::factory()->forUser($vecchio)->forTournament($tournament)->asReferee()->create();
        $this->fakeFig(7, $tournament); // 7 gare -> 2 blocchi da 5

        $owner = $this->owner();
        $params = ['anno' => now()->year, 'replace' => true, 'dry_run' => false, 'run' => 'run-test-0001'];

        $primo = $this->actingAs($owner)->postJson(route('super-admin.fig-import.block'), $params + ['offset' => 0])
            ->assertOk()
            ->assertJson(['success' => true, 'done' => false, 'next_offset' => 5])
            ->json('stats');
        $this->assertIsArray($primo);
        $this->assertSame(7, $primo['gare_disponibili']);
        $this->assertSame(5, $primo['gare_elaborate']);
        $this->assertSame(0, $primo['tolte'], 'Le gare sono in ordine di data: il torneo arriva nel secondo blocco');

        $this->actingAs($owner)->postJson(route('super-admin.fig-import.block'), $params + ['offset' => 5])
            ->assertOk()
            ->assertJson(['success' => true, 'done' => true, 'stats' => ['gare_elaborate' => 2, 'tolte' => 1, 'create' => 1]]);

        $this->assertSame([$bianchi->id], Assignment::where('tournament_id', $tournament->id)->pluck('user_id')->all());
    }

    public function test_dry_run_block_writes_nothing(): void
    {
        $tournament = $this->nationalTournament();
        $vecchio = $this->createReferee(['name' => 'Carlo Verdi', 'first_name' => 'Carlo', 'last_name' => 'Verdi']);
        $this->createReferee(['name' => 'Luca Bianchi', 'first_name' => 'Luca', 'last_name' => 'Bianchi']);
        Assignment::factory()->forUser($vecchio)->forTournament($tournament)->asReferee()->create();
        $this->fakeFig(1, $tournament);

        $this->actingAs($this->owner())->postJson(route('super-admin.fig-import.block'), [
            'anno' => now()->year, 'offset' => 0, 'replace' => true, 'dry_run' => true, 'run' => 'run-test-0002',
        ])->assertOk()->assertJson(['success' => true, 'done' => true, 'stats' => ['tolte' => 1, 'create' => 1]]);

        $this->assertSame([$vecchio->id], Assignment::where('tournament_id', $tournament->id)->pluck('user_id')->all());
    }

    public function test_reserved_account_is_hidden_from_other_admins(): void
    {
        $riservato = $this->owner();
        $altroSuper = $this->createSuperAdmin(['email' => 'altro.super@example.test']);

        $this->actingAs($altroSuper)->get(route('admin.users.index', ['status' => 'all']))
            ->assertOk()
            ->assertDontSee(self::RISERVATO);
        $this->actingAs($altroSuper)->get(route('admin.users.show', $riservato))->assertNotFound();
        $this->actingAs($altroSuper)->get(route('admin.users.edit', $riservato))->assertNotFound();
        $this->actingAs($altroSuper)->patch(route('admin.users.toggle-active', $riservato))->assertNotFound();
        $this->actingAs($altroSuper)->delete(route('admin.users.destroy', $riservato))->assertNotFound();
        $this->assertTrue($riservato->fresh()?->is_active);

        $this->actingAs($this->createNationalAdmin())->get(route('admin.users.index', ['status' => 'all']))
            ->assertOk()
            ->assertDontSee(self::RISERVATO);

        // L'account vede se stesso
        $this->actingAs($riservato)->get(route('admin.users.show', $riservato))->assertOk();
    }

    public function test_without_env_line_the_feature_does_not_exist(): void
    {
        config(['golf.fig.import_email' => null]);
        $riservato = $this->owner();
        $altroSuper = $this->createSuperAdmin(['email' => 'altro.super@example.test']);

        $this->assertNull(FigImportAccess::email());
        $this->actingAs($riservato)->get(route('super-admin.fig-import.index'))->assertNotFound();
        $this->actingAs($riservato)->get(route('super-admin.zones.index'))
            ->assertOk()
            ->assertDontSee(route('super-admin.fig-import.index'));

        // Nessun account nascosto quando la funzione e' spenta
        $this->actingAs($altroSuper)->get(route('admin.users.show', $riservato))->assertOk();
    }

    public function test_reserved_address_is_not_in_the_code(): void
    {
        $sorgente = (string) file_get_contents(app_path('Support/FigImportAccess.php'));

        $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[a-z]{2,}/', $sorgente);
    }
}
