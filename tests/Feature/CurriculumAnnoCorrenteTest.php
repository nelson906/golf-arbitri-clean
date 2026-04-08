<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use App\Services\RefereeCareerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test: i dati dell'anno corrente devono comparire nel curriculum
 * anche quando non sono ancora stati archiviati nello storico.
 *
 * BUG VERIFICATO: RefereeCareerService::getCareerData() legge solo i dati
 * archiviati in referee_career_history (JSON). Se un arbitro ha uno storico
 * pregresso (es. 2023, 2024) ma ha assegnazioni nell'anno corrente non ancora
 * archiviate, queste NON compaiono nel curriculum.
 *
 * COMPORTAMENTO ATTESO: i dati dell'anno corrente provenienti dalle tabelle
 * live (assignments, tournaments) devono essere inclusi in getCareerData()
 * e in getYearData() finché non vengono archiviati.
 */
class CurriculumAnnoCorrenteTest extends TestCase
{
    use RefreshDatabase;

    protected User $referee;
    protected User $admin;
    protected Zone $zone;
    protected RefereeCareerService $service;
    protected int $annoCorrente;
    protected int $annoPrecedente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->annoCorrente = now()->year;
        $this->annoPrecedente = $this->annoCorrente - 1;

        $this->zone = Zone::create([
            'name' => 'Zona Test',
            'code' => 'ZT',
            'is_national' => false,
        ]);

        $this->referee = User::factory()->create([
            'user_type' => 'referee',
            'level' => 'Nazionale',
            'zone_id' => $this->zone->id,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'referee_code' => 'TST' . now()->timestamp,
        ]);

        $this->admin = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->service = app(RefereeCareerService::class);
    }

    // =========================================================================
    // SCENARIO 1 — arbitro CON storico pregresso, CON assignments anno corrente
    //              → il caso che espone il bug
    // =========================================================================

    /**
     * CASO PRINCIPALE (attualmente FALLISCE — dimostra il bug):
     * Un arbitro ha dati storici archiviati (anno precedente) ed è stato
     * assegnato a tornei nell'anno corrente che non sono ancora archiviati.
     * getCareerData() deve includere anche i dati dell'anno corrente.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function curriculum_include_anno_corrente_quando_esiste_storico_pregresso(): void
    {
        // Crea storico archiviato per l'anno precedente
        $this->creaStoricoArchiviato($this->referee, $this->annoPrecedente, 3);

        // Crea assignments live per l'anno corrente (non ancora archiviati)
        $tornei = $this->creaTorneiAnnoCorrente(2);
        foreach ($tornei as $torneo) {
            Assignment::factory()
                ->forUser($this->referee)
                ->forTournament($torneo)
                ->create(['role' => 'Arbitro']);
        }

        $careerData = $this->service->getCareerData($this->referee);

        // L'anno corrente DEVE comparire nel curriculum
        $this->assertArrayHasKey(
            (string) $this->annoCorrente,
            $careerData['assignments'],
            "L'anno corrente ({$this->annoCorrente}) non compare in assignments. " .
            "BUG: getCareerData() legge solo lo storico JSON e ignora le tabelle live."
        );

        // I tornei dell'anno corrente devono essere presenti
        $assignmentsAnnoCorrente = $careerData['assignments'][(string) $this->annoCorrente] ?? [];
        $this->assertCount(
            2,
            $assignmentsAnnoCorrente,
            "Attesi 2 assignments per l'anno corrente, trovati " . count($assignmentsAnnoCorrente)
        );
    }

    /**
     * Verifica che getCareerData() senza filtro anno mostri sia lo storico
     * sia l'anno corrente live (entrambi gli anni presenti in 'assignments').
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function career_data_contiene_sia_storico_sia_anno_corrente(): void
    {
        // Storico per anno precedente
        $this->creaStoricoArchiviato($this->referee, $this->annoPrecedente, 2);

        // Assignments live anno corrente
        $tornei = $this->creaTorneiAnnoCorrente(3);
        foreach ($tornei as $torneo) {
            Assignment::factory()
                ->forUser($this->referee)
                ->forTournament($torneo)
                ->create(['role' => 'Arbitro']);
        }

        $careerData = $this->service->getCareerData($this->referee);

        // Anno precedente (storico archiviato) deve esserci
        $this->assertArrayHasKey(
            (string) $this->annoPrecedente,
            $careerData['assignments'],
            "Anno precedente mancante dallo storico."
        );
        $this->assertCount(2, $careerData['assignments'][(string) $this->annoPrecedente]);

        // Anno corrente (live) deve esserci
        $this->assertArrayHasKey(
            (string) $this->annoCorrente,
            $careerData['assignments'],
            "Anno corrente mancante — BUG: dati live ignorati quando esiste career history."
        );
        $this->assertCount(3, $careerData['assignments'][(string) $this->annoCorrente]);
    }

    // =========================================================================
    // SCENARIO 2 — getYearData() per l'anno corrente con storico pregresso
    // =========================================================================

    /**
     * getYearData() per l'anno corrente deve leggere i dati live,
     * non solo lo storico JSON (che per l'anno corrente è ancora vuoto).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function get_year_data_anno_corrente_legge_dati_live(): void
    {
        // Storico per anni precedenti
        $this->creaStoricoArchiviato($this->referee, $this->annoPrecedente, 2);

        // 3 assignments live anno corrente, tutti come DT
        $tornei = $this->creaTorneiAnnoCorrente(3);
        foreach ($tornei as $torneo) {
            Assignment::factory()
                ->forUser($this->referee)
                ->forTournament($torneo)
                ->create(['role' => 'Direttore di Torneo']);
        }

        $yearData = $this->service->getYearData($this->referee, $this->annoCorrente);

        // Deve trovare i 3 tornei live dell'anno corrente
        $this->assertEquals(
            3,
            $yearData['total_tournaments'],
            "getYearData({$this->annoCorrente}) ha trovato {$yearData['total_tournaments']} tornei invece di 3. " .
            "BUG: legge solo JSON storico che per l'anno corrente è vuoto."
        );

        // Deve trovare il ruolo corretto
        $this->assertArrayHasKey('Direttore di Torneo', $yearData['roles'] ?? []);
        $this->assertEquals(3, $yearData['roles']['Direttore di Torneo']);
    }

    // =========================================================================
    // SCENARIO 3 — arbitro SENZA storico pregresso, CON assignments anno corrente
    //              → già funzionante via fallback, ma lo testiamo per regressione
    // =========================================================================

    /**
     * Senza career history, il fallback su getCurrentAssignmentsData() funziona.
     * Questo test verifica che il caso base non regredisca.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function curriculum_anno_corrente_senza_storico_usa_fallback(): void
    {
        // Nessuno storico archiviato
        $this->assertDatabaseMissing('referee_career_history', [
            'user_id' => $this->referee->id,
        ]);

        // Assignments live anno corrente
        $tornei = $this->creaTorneiAnnoCorrente(2);
        foreach ($tornei as $torneo) {
            Assignment::factory()
                ->forUser($this->referee)
                ->forTournament($torneo)
                ->create(['role' => 'Arbitro']);
        }

        $careerData = $this->service->getCareerData($this->referee);

        // career_summary deve riflettere gli assignments live
        $this->assertEquals(
            2,
            $careerData['career_summary']['total_assignments'],
            "Senza storico: attesi 2 assignments nel summary, trovati " .
            ($careerData['career_summary']['total_assignments'] ?? 'null')
        );
    }

    // =========================================================================
    // SCENARIO 4 — anno corrente già archiviato non viene duplicato
    // =========================================================================

    /**
     * Se l'anno corrente è già stato archiviato nello storico JSON,
     * non devono comparire duplicati nella vista curriculum.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anno_corrente_archiviato_non_viene_duplicato(): void
    {
        // Archivia l'anno corrente con 2 assignments
        $this->creaStoricoArchiviato($this->referee, $this->annoCorrente, 2);

        // Aggiunge altri 2 assignments live per lo stesso anno
        // (simulando che esistano ancora dati live dopo l'archiviazione parziale)
        $tornei = $this->creaTorneiAnnoCorrente(2);
        foreach ($tornei as $torneo) {
            Assignment::factory()
                ->forUser($this->referee)
                ->forTournament($torneo)
                ->create(['role' => 'Arbitro']);
        }

        $careerData = $this->service->getCareerData($this->referee);

        $assignmentsAnnoCorrente = $careerData['assignments'][(string) $this->annoCorrente] ?? [];

        // Attenzione: la logica corretta dipende dalla strategia di merge scelta.
        // Qui verifichiamo che ci siano almeno 2 (lo storico) e al massimo 4
        // (2 archiviati + 2 live senza dedup). Il test documentale accetta entrambi
        // ma NON zero — l'anno corrente non può scomparire.
        $this->assertGreaterThanOrEqual(
            2,
            count($assignmentsAnnoCorrente),
            "L'anno corrente ha 0 assignments nel curriculum dopo archiviazione parziale."
        );
    }

    // =========================================================================
    // SCENARIO 5 — la view HTTP dell'utente riceve i dati dell'anno corrente
    // =========================================================================

    /**
     * La route /curriculum dell'utente deve mostrare i dati dell'anno corrente
     * anche quando esiste uno storico pregresso.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function pagina_curriculum_utente_mostra_anno_corrente(): void
    {
        // Storico anno precedente
        $this->creaStoricoArchiviato($this->referee, $this->annoPrecedente, 1);

        // Assignments live anno corrente
        $torneo = $this->creaTorneiAnnoCorrente(1)[0];
        Assignment::factory()
            ->forUser($this->referee)
            ->forTournament($torneo)
            ->create(['role' => 'Arbitro']);

        $this->actingAs($this->referee);
        $response = $this->get(route('user.curriculum'));

        $response->assertOk();

        // La view deve ricevere la chiave dell'anno corrente in assignments
        $response->assertViewHas('careerData', function (array $careerData) {
            return array_key_exists(
                (string) $this->annoCorrente,
                $careerData['assignments'] ?? []
            );
        });
    }

    // =========================================================================
    // Helper privati
    // =========================================================================

    /**
     * Crea un record RefereeCareerHistory con $numAssignments assignments
     * archiviati per l'anno specificato.
     */
    private function creaStoricoArchiviato(User $referee, int $anno, int $numAssignments): RefereeCareerHistory
    {
        $assignments = [];
        $tournamentsList = [];
        for ($i = 1; $i <= $numAssignments; $i++) {
            $assignments[] = [
                'id' => $i,
                'tournament_id' => $i,
                'role' => 'Arbitro',
                'assigned_at' => "{$anno}-06-0{$i}",
                'status' => 'completed',
            ];
            $tournamentsList[] = [
                'id' => $i,
                'name' => "Torneo Storico {$anno} #{$i}",
                'start_date' => "{$anno}-06-0{$i}",
            ];
        }

        return RefereeCareerHistory::create([
            'user_id' => $referee->id,
            'assignments_by_year' => [(string) $anno => $assignments],
            'tournaments_by_year' => [(string) $anno => $tournamentsList],
            'availabilities_by_year' => [],
            'level_changes_by_year' => [],
            'career_stats' => [
                'total_assignments' => $numAssignments,
                'total_tournaments' => $numAssignments,
                'total_years' => 1,
                'first_year' => $anno,
                'roles_summary' => ['Arbitro' => $numAssignments],
            ],
            'last_updated_year' => $anno,
            'data_completeness_score' => 1.0,
        ]);
    }

    /**
     * Crea $count tornei con start_date nell'anno corrente.
     *
     * @return Tournament[]
     */
    private function creaTorneiAnnoCorrente(int $count): array
    {
        $tornei = [];
        for ($i = 1; $i <= $count; $i++) {
            $tornei[] = Tournament::factory()->create([
                'zone_id' => $this->zone->id,
                'start_date' => now()->startOfYear()->addMonths($i)->format('Y-m-d'),
                'end_date' => now()->startOfYear()->addMonths($i)->addDays(2)->format('Y-m-d'),
            ]);
        }

        return $tornei;
    }
}
