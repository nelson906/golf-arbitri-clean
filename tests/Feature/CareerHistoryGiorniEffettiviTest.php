<?php

namespace Tests\Feature;

use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use App\Services\CareerHistoryService;
use App\Services\RefereeCareerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerHistoryGiorniEffettiviTest extends TestCase
{
    use RefreshDatabase;

    protected User $referee;
    protected User $admin;
    protected Zone $zone;
    protected Tournament $tournament;
    protected CareerHistoryService $careerHistoryService;
    protected RefereeCareerService $refereeCareerService;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup zone - crea una zona unica per ogni test con timestamp
        $this->zone = Zone::create([
            'name' => 'Test Zone ' . now()->timestamp,
            'code' => 'TEST' . now()->timestamp,
            'is_national' => false,
        ]);

        // Setup referee nazionale
        $this->referee = User::factory()->create([
            'user_type' => 'referee',
            'level' => 'Nazionale',
            'zone_id' => $this->zone->id,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'referee_code' => 'TEST' . now()->timestamp,
        ]);

        // Setup admin
        $this->admin = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        // Setup tournament
        $this->tournament = Tournament::factory()->create([
            'name' => "Torneo Test " . now()->timestamp,
            'zone_id' => $this->zone->id,
            'start_date' => '2025-04-04',
            'end_date' => '2025-04-08', // 5 giorni
        ]);

        $this->careerHistoryService = app(CareerHistoryService::class);
        $this->refereeCareerService = app(RefereeCareerService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_referee_career_history_on_first_tournament_add()
    {
        $this->assertDatabaseMissing('referee_career_history', [
            'user_id' => $this->referee->id,
        ]);

        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];

        $result = $this->careerHistoryService->addTournamentEntry(
            $this->referee->id,
            2025,
            $tournamentData,
            3 // giorni effettivi
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('referee_career_history', [
            'user_id' => $this->referee->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_tournament_with_days_count()
    {
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];

        $this->careerHistoryService->addTournamentEntry(
            $this->referee->id,
            2025,
            $tournamentData,
            3 // giorni effettivi
        );

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();

        $this->assertNotNull($history);
        $this->assertArrayHasKey('2025', $history->tournaments_by_year);
        $this->assertCount(1, $history->tournaments_by_year['2025']);
        $this->assertEquals(3, $history->tournaments_by_year['2025'][0]['days_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_tournament_days()
    {
        // Aggiungi torneo
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];

        $this->careerHistoryService->addTournamentEntry(
            $this->referee->id,
            2025,
            $tournamentData,
            3
        );

        // Aggiorna giorni
        $updated = $this->careerHistoryService->updateTournamentDays(
            $this->referee->id,
            2025,
            $this->tournament->id,
            4 // cambio da 3 a 4
        );

        $this->assertTrue($updated);

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $this->assertEquals(4, $history->tournaments_by_year['2025'][0]['days_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_tournament_complete_data()
    {
        // Aggiungi torneo
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => 'Nome Originale',
            'club_id' => $this->tournament->club_id,
            'club_name' => 'Club Originale',
            'start_date' => '2025-04-04',
            'end_date' => '2025-04-08',
        ];

        $this->careerHistoryService->addTournamentEntry(
            $this->referee->id,
            2025,
            $tournamentData,
            3
        );

        // Aggiorna dati completi
        $updateData = [
            'name' => 'Nome Modificato',
            'club_name' => 'Club Modificato',
            'days_count' => 4,
        ];

        $updated = $this->careerHistoryService->updateTournamentEntry(
            $this->referee->id,
            2025,
            $this->tournament->id,
            $updateData
        );

        $this->assertTrue($updated);

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $tournament = $history->tournaments_by_year['2025'][0];

        $this->assertEquals('Nome Modificato', $tournament['name']);
        $this->assertEquals('Club Modificato', $tournament['club_name']);
        $this->assertEquals(4, $tournament['days_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_recalculates_career_stats_after_adding_tournament()
    {
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];

        $this->careerHistoryService->addTournamentEntry(
            $this->referee->id,
            2025,
            $tournamentData,
            3
        );

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();

        $this->assertNotNull($history->career_stats);
        $this->assertArrayHasKey('total_tournaments', $history->career_stats);
        $this->assertArrayHasKey('total_assignments', $history->career_stats);
        $this->assertArrayHasKey('first_year', $history->career_stats);
        $this->assertEquals(1, $history->career_stats['total_tournaments']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_recalculates_career_stats_after_updating_tournament()
    {
        // Aggiungi 2 tornei
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];

        $this->careerHistoryService->addTournamentEntry($this->referee->id, 2025, $tournamentData, 3);

        $tournament2 = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-03',
        ]);

        $tournamentData2 = [
            'id' => $tournament2->id,
            'name' => $tournament2->name,
            'club_id' => $tournament2->club_id,
            'start_date' => $tournament2->start_date->format('Y-m-d'),
            'end_date' => $tournament2->end_date->format('Y-m-d'),
        ];

        $this->careerHistoryService->addTournamentEntry($this->referee->id, 2025, $tournamentData2, 2);

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $this->assertEquals(2, $history->career_stats['total_tournaments']);

        // Aggiorna uno
        $this->careerHistoryService->updateTournamentEntry(
            $this->referee->id,
            2025,
            $this->tournament->id,
            ['name' => 'Nome Aggiornato']
        );

        $history->refresh();
        // Deve ancora essere 2, non deve cancellare tornei
        $this->assertEquals(2, $history->career_stats['total_tournaments']);
        $this->assertCount(2, $history->tournaments_by_year['2025']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_stats_summary_includes_all_required_fields()
    {
        $history = RefereeCareerHistory::create([
            'user_id' => $this->referee->id,
            'tournaments_by_year' => [
                '2025' => [
                    [
                        'id' => 1,
                        'name' => 'Test',
                        'start_date' => '2025-04-04',
                        'end_date' => '2025-04-08',
                    ],
                ],
            ],
            'assignments_by_year' => [
                '2025' => [
                    [
                        'id' => 1,
                        'tournament_id' => 1,
                        'role' => 'Arbitro',
                    ],
                ],
            ],
            'level_changes_by_year' => [],
            'career_stats' => [],
        ]);

        $stats = $history->generateStatsSummary();

        $this->assertArrayHasKey('total_years', $stats);
        $this->assertArrayHasKey('total_tournaments', $stats);
        $this->assertArrayHasKey('total_assignments', $stats);
        $this->assertArrayHasKey('first_year', $stats);
        $this->assertArrayHasKey('roles_summary', $stats);
        $this->assertArrayHasKey('most_active_year', $stats);
        $this->assertArrayHasKey('avg_tournaments_per_year', $stats);

        $this->assertEquals(1, $stats['total_tournaments']);
        $this->assertEquals(1, $stats['total_assignments']);
        $this->assertEquals(2025, $stats['first_year']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_career_data_includes_current_year_level()
    {
        $careerData = $this->refereeCareerService->getCareerData($this->referee);

        $currentYear = now()->year;

        $this->assertArrayHasKey('career_levels', $careerData);
        $this->assertArrayHasKey($currentYear, $careerData['career_levels']);
        $this->assertEquals('Nazionale', $careerData['career_levels'][$currentYear]['level']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_year_data_returns_correct_level_from_history()
    {
        // Crea history con level changes
        $history = RefereeCareerHistory::create([
            'user_id' => $this->referee->id,
            'tournaments_by_year' => [
                '2023' => [['id' => 1, 'name' => 'Test']],
            ],
            'assignments_by_year' => [],
            'level_changes_by_year' => [
                '2023' => [
                    ['old_level' => 'Regionale', 'new_level' => 'Nazionale', 'effective_date' => '2023-01-15'],
                ],
            ],
            'career_stats' => [],
        ]);

        $yearData = $this->refereeCareerService->getYearData($this->referee, 2023);

        $this->assertEquals('Nazionale', $yearData['level']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function batch_add_preserves_existing_data()
    {
        // Aggiungi primo torneo
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];
        $this->careerHistoryService->addTournamentEntry($this->referee->id, 2025, $tournamentData, 3);

        // Crea assignment manuale
        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $assignments = $history->assignments_by_year ?? [];
        $assignments['2024'] = [
            ['id' => 99, 'tournament_id' => 99, 'role' => 'Arbitro'],
        ];
        $history->assignments_by_year = $assignments;
        $history->save();

        // Batch add nuovo torneo
        $tournament2 = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-03',
        ]);

        $result = $this->careerHistoryService->addBatchTournaments(
            $this->referee->id,
            2025,
            [
                ['tournament_id' => $tournament2->id, 'days_count' => 2],
            ]
        );

        $this->assertEquals(1, $result['added']);

        $history->refresh();

        // Verifica che il torneo precedente sia ancora presente
        $this->assertCount(2, $history->tournaments_by_year['2025']);

        // Verifica che gli assignments del 2024 siano ancora presenti
        $this->assertArrayHasKey('2024', $history->assignments_by_year);
        $this->assertCount(1, $history->assignments_by_year['2024']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function remove_tournament_recalculates_stats()
    {
        // Aggiungi torneo
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => $this->tournament->name,
            'club_id' => $this->tournament->club_id,
            'start_date' => $this->tournament->start_date->format('Y-m-d'),
            'end_date' => $this->tournament->end_date->format('Y-m-d'),
        ];
        $this->careerHistoryService->addTournamentEntry($this->referee->id, 2025, $tournamentData, 3);

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $this->assertEquals(1, $history->career_stats['total_tournaments']);

        // Rimuovi
        $removed = $this->careerHistoryService->removeTournamentEntry(
            $this->referee->id,
            2025,
            $this->tournament->id
        );

        $this->assertTrue($removed);

        $history->refresh();
        $this->assertEquals(0, $history->career_stats['total_tournaments']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function controller_can_add_tournament_with_days()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.career-history.add-tournament', $this->referee), [
            'year' => 2025,
            'tournament_id' => $this->tournament->id,
            'days_count' => 3,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(3, $history->tournaments_by_year['2025'][0]['days_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function controller_can_update_tournament_complete()
    {
        $this->actingAs($this->admin);

        // Aggiungi prima
        $tournamentData = [
            'id' => $this->tournament->id,
            'name' => 'Nome Originale',
            'club_id' => $this->tournament->club_id,
            'start_date' => '2025-04-04',
            'end_date' => '2025-04-08',
        ];
        $this->careerHistoryService->addTournamentEntry($this->referee->id, 2025, $tournamentData, 3);

        // Aggiorna via controller
        $response = $this->post(route('admin.career-history.update-tournament', $this->referee), [
            'year' => 2025,
            'tournament_id' => $this->tournament->id,
            'name' => 'Nome Modificato',
            'days_count' => 4,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $history = RefereeCareerHistory::where('user_id', $this->referee->id)->first();
        $tournament = $history->tournaments_by_year['2025'][0];

        $this->assertEquals('Nome Modificato', $tournament['name']);
        $this->assertEquals(4, $tournament['days_count']);
    }
}
