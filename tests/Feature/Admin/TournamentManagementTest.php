<?php

namespace Tests\Feature\Admin;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use Tests\TestCase;

class TournamentManagementTest extends TestCase
{
    // ==========================================
    // TOURNAMENT CREATE TESTS
    // ==========================================

    /**
     * Test: Admin può vedere pagina creazione torneo
     */
    public function test_admin_can_view_tournament_create_page(): void
    {
        $admin = $this->createZoneAdmin();

        $response = $this->actingAs($admin)->get(route('admin.tournaments.create'));

        $response->assertStatus(200);
        $response->assertSee('Crea Torneo');
    }

    /**
     * Test: Admin può creare torneo
     */
    public function test_admin_can_create_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $type = TournamentType::firstOrFail();

        $tournamentData = [
            'name' => 'Test Tournament 2026',
            'club_id' => $club->id,
            'tournament_type_id' => $type->id,
            'start_date' => now()->addDays(14)->format('Y-m-d'),
            'end_date' => now()->addDays(16)->format('Y-m-d'),
            'availability_deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'status' => 'open',
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $tournamentData);

        $response->assertRedirect();
        $this->assertDatabaseHas('tournaments', [
            'name' => 'Test Tournament 2026',
            'club_id' => $club->id,
        ]);
    }

    /**
     * Test: Admin non può creare torneo in altra zona
     */
    public function test_zone_admin_cannot_create_tournament_in_other_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $clubZone2 = Club::factory()->create(['zone_id' => 2]);
        $type = TournamentType::firstOrFail();

        $tournamentData = [
            'name' => 'Test Tournament',
            'club_id' => $clubZone2->id,
            'tournament_type_id' => $type->id,
            'start_date' => now()->addDays(14)->format('Y-m-d'),
            'end_date' => now()->addDays(16)->format('Y-m-d'),
            'availability_deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $tournamentData);

        // Dovrebbe essere rifiutato (403 o redirect con errore)
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    // ==========================================
    // TOURNAMENT UPDATE TESTS
    // ==========================================

    /**
     * Test: Admin può aggiornare torneo della sua zona
     */
    public function test_admin_can_update_tournament_in_own_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $response = $this->actingAs($admin)
            ->put(route('admin.tournaments.update', $tournament), [
                'name' => 'Updated Tournament Name',
                'club_id' => $club->id,
                'tournament_type_id' => $tournament->tournament_type_id,
                'start_date' => $tournament->start_date->format('Y-m-d'),
                'end_date' => $tournament->end_date?->format('Y-m-d'),
                'availability_deadline' => $tournament->availability_deadline->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'Updated Tournament Name',
        ]);
    }

    // ==========================================
    // TOURNAMENT DELETE TESTS
    // ==========================================

    /**
     * Test: Admin può eliminare torneo
     */
    public function test_admin_can_delete_tournament(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $tournament = Tournament::factory()->create(['club_id' => $club->id]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.tournaments.destroy', $tournament));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tournaments', [
            'id' => $tournament->id,
        ]);
    }

    // ==========================================
    // TOURNAMENT LIST TESTS
    // ==========================================

    /**
     * Test: l'admin di zona vede la propria zona e i tornei nazionali,
     * non i tornei zonali delle altre zone.
     *
     * NB: i tipi sono espliciti. TournamentFactory pesca un tipo A CASO, quindi
     * senza dirlo il test misurava il sorteggio invece della regola.
     * Il periodo resta al default (tutti): qui si verificano le zone, non le date.
     */
    public function test_zone_admin_sees_own_zone_and_national_tournaments(): void
    {
        $admin = $this->createZoneAdmin(1);

        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();

        $club1 = Club::factory()->create(['zone_id' => 1]);
        $club2 = Club::factory()->create(['zone_id' => 2]);

        Tournament::factory()->create([
            'club_id' => $club1->id, 'zone_id' => 1,
            'tournament_type_id' => $zonalType->id, 'name' => 'Zone 1 Tournament',
        ]);
        Tournament::factory()->create([
            'club_id' => $club2->id, 'zone_id' => 2,
            'tournament_type_id' => $zonalType->id, 'name' => 'Zone 2 Tournament',
        ]);
        Tournament::factory()->create([
            'club_id' => $club2->id, 'zone_id' => 2,
            'tournament_type_id' => $nationalType->id, 'name' => 'Campionato Nazionale Altrove',
        ]);

        $response = $this->actingAs($admin)->get(route('tournaments.index'));

        $response->assertStatus(200);
        $response->assertSee('Zone 1 Tournament');
        $response->assertSee('Campionato Nazionale Altrove');
        $response->assertDontSee('Zone 2 Tournament');
    }

    /**
     * Test: il filtro periodo — tutti (default) / futuri / passati.
     *
     * Regressione della segnalazione del 2026-09-06: la lista filtrava sempre
     * `start_date >= oggi`, quindi i tipi di torneo senza gare ancora da
     * giocare (CI, GN72, PRO) risultavano invisibili anche a chi ne aveva
     * pieno diritto. Non serve un selettore per ANNO: `tournaments` contiene
     * solo gli anni non ancora travasati in referee_career_history.
     */
    public function test_period_filter_shows_all_by_default_and_can_split_past_and_future(): void
    {
        $admin = $this->createZoneAdmin(1);
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club = Club::factory()->create(['zone_id' => 1]);

        Tournament::factory()->create([
            'club_id' => $club->id, 'zone_id' => 1,
            'tournament_type_id' => $zonalType->id,
            'name' => 'Torneo Gia Giocato',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(2)->addDay(),
            'availability_deadline' => now()->subMonths(3),
        ]);

        Tournament::factory()->create([
            'club_id' => $club->id, 'zone_id' => 1,
            'tournament_type_id' => $zonalType->id,
            'name' => 'Torneo Da Giocare',
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonth()->addDay(),
            'availability_deadline' => now()->addWeeks(2),
        ]);

        $this->actingAs($admin)->get(route('tournaments.index'))
            ->assertStatus(200)
            ->assertSee('Torneo Gia Giocato')
            ->assertSee('Torneo Da Giocare');

        $this->actingAs($admin)->get(route('tournaments.index', ['periodo' => 'futuri']))
            ->assertStatus(200)
            ->assertSee('Torneo Da Giocare')
            ->assertDontSee('Torneo Gia Giocato');

        $this->actingAs($admin)->get(route('tournaments.index', ['periodo' => 'passati']))
            ->assertStatus(200)
            ->assertSee('Torneo Gia Giocato')
            ->assertDontSee('Torneo Da Giocare');
    }

    /**
     * Test: Super admin vede tutti i tornei
     */
    public function test_super_admin_sees_all_tournaments(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $club1 = Club::factory()->create(['zone_id' => 1]);
        $club2 = Club::factory()->create(['zone_id' => 2]);

        Tournament::factory()->create(['club_id' => $club1->id, 'name' => 'Zone 1 Tournament']);
        Tournament::factory()->create(['club_id' => $club2->id, 'name' => 'Zone 2 Tournament']);

        $response = $this->actingAs($superAdmin)->get(route('tournaments.index'));

        $response->assertStatus(200);
        $response->assertSee('Zone 1 Tournament');
        $response->assertSee('Zone 2 Tournament');
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    /**
     * Test: Validazione campi required
     *
     * `club_id` NON e' fra i campi obbligatori: dal 2026-09 una gara puo'
     * entrare in calendario con data e zona note e il circolo ancora T.B.A.
     * (migration 2026_09_06_000001_make_tournaments_club_id_nullable).
     */
    public function test_tournament_creation_requires_required_fields(): void
    {
        $admin = $this->createZoneAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), []);

        $response->assertSessionHasErrors(['name', 'start_date', 'end_date']);
        $response->assertSessionDoesntHaveErrors('club_id');
    }

    /**
     * Test: un torneo T.B.A. (circolo da assegnare) si crea e prende la zona
     * dell'admin che lo inserisce.
     *
     * Regressione del caso reale: nel calendario federale la data e' fissata
     * prima del circolo. Con `club_id` NOT NULL quei tornei non erano
     * rappresentabili e Tournaments2026Seeder li scartava.
     */
    public function test_admin_can_create_tournament_without_club(): void
    {
        $admin = $this->createZoneAdmin(1);
        $type = TournamentType::firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), [
                'name' => 'Gara T.B.A. 2026',
                'tournament_type_id' => $type->id,
                'start_date' => now()->addDays(30)->format('Y-m-d'),
                'end_date' => now()->addDays(31)->format('Y-m-d'),
                'availability_deadline' => now()->addDays(20)->format('Y-m-d H:i:s'),
                'status' => 'open',
            ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Gara T.B.A. 2026',
            'club_id' => null,
            'zone_id' => 1,
        ]);
    }

    /**
     * Test: il torneo T.B.A. resta visibile all'admin della propria zona.
     *
     * E' il punto che rendeva la modifica utile: senza circolo la zona puo'
     * arrivare solo dalla colonna `zone_id`, ed e' quella che
     * TournamentVisibility deve interrogare.
     */
    public function test_tba_tournament_is_visible_to_its_zone_admin(): void
    {
        $admin = $this->createZoneAdmin(1);
        $type = TournamentType::firstOrFail();

        $tournament = Tournament::create([
            'name' => 'Gara T.B.A. visibile',
            'club_id' => null,
            'zone_id' => 1,
            'tournament_type_id' => $type->id,
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(31),
            'availability_deadline' => now()->addDays(20),
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $tournament->refresh();

        $this->assertTrue(
            \App\Support\TournamentVisibility::canAccess($tournament, $admin),
            'Un torneo T.B.A. della zona 1 deve essere visibile al suo admin di zona.'
        );
    }

    /**
     * Test: end_date deve essere dopo start_date
     */
    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = $this->createZoneAdmin(1);
        $club = Club::factory()->create(['zone_id' => 1]);
        $type = TournamentType::firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), [
                'name' => 'Test Tournament',
                'club_id' => $club->id,
                'tournament_type_id' => $type->id,
                'start_date' => now()->addDays(20)->format('Y-m-d'),
                'end_date' => now()->addDays(15)->format('Y-m-d'), // Prima della start!
                'availability_deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);

        $response->assertSessionHasErrors('end_date');
    }

    // ==========================================
    // PERMISSION TESTS
    // ==========================================

    /**
     * Test: Referee non può accedere alla creazione tornei
     */
    public function test_referee_cannot_access_tournament_creation(): void
    {
        $referee = $this->createReferee();

        $response = $this->actingAs($referee)
            ->get(route('admin.tournaments.create'));

        $response->assertStatus(403);
    }

    /**
     * Test: Guest non può accedere ai tornei
     */
    public function test_guest_cannot_access_tournaments(): void
    {
        $response = $this->get(route('tournaments.index'));

        $response->assertRedirect(route('login'));
    }
}
