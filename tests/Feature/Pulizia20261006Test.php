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

    // ── D2: Tipi Torneo — la sigla è short_name ─────────────────────────────

    public function test_d2_super_admin_creates_tournament_type_with_generated_short_name(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->post(route('super-admin.tournament-types.store'), [
                'name' => 'Gara Prova Nuova',
                'sort_order' => 1,
            ])->assertRedirect(route('super-admin.tournament-types.index'));

        $this->assertDatabaseHas('tournament_types', ['name' => 'Gara Prova Nuova', 'short_name' => 'GARA_PROVA_NUOVA']);
    }

    public function test_d2_super_admin_creates_and_edits_short_name(): void
    {
        $super = $this->createSuperAdmin();

        $this->actingAs($super)->post(route('super-admin.tournament-types.store'), [
            'name' => 'Trofeo Prova',
            'short_name' => 'TPX',
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();

        $type = TournamentType::where('short_name', 'TPX')->firstOrFail();

        $this->actingAs($super)->put(route('super-admin.tournament-types.update', $type), [
            'name' => 'Trofeo Prova',
            'short_name' => 'TPY',
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('TPY', $type->fresh()?->short_name);

        $this->actingAs($super)->get(route('super-admin.tournament-types.index'))
            ->assertOk()
            ->assertSee('TPY');
    }

    public function test_d2_duplicate_short_name_is_rejected(): void
    {
        $existing = $this->zonalType();

        $this->actingAs($this->createSuperAdmin())
            ->post(route('super-admin.tournament-types.store'), [
                'name' => 'Altro Nome',
                'short_name' => $existing->short_name,
                'sort_order' => 1,
            ])->assertSessionHasErrors('short_name');
    }

    // ── D6: scheda utente, contatori veri ───────────────────────────────────

    public function test_d6_user_page_shows_real_counters_without_confirmed(): void
    {
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1]);

        $a = $this->futureTournament(1);
        $b = $this->futureTournament(1);
        $c = $this->futureTournament(1);
        $this->createAssignment(['tournament_id' => $a->id, 'user_id' => $referee->id]);
        $this->createAssignment(['tournament_id' => $b->id, 'user_id' => $referee->id]);
        \App\Models\Availability::create(['user_id' => $referee->id, 'tournament_id' => $c->id, 'submitted_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $referee))->assertOk();

        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(2, $stats['total_assignments']);
        $this->assertSame(1, $stats['total_availabilities']);
        $response->assertDontSee('Confermate');
    }

    // ── D8/D9: calendario admin, giorni alla scadenza e colore del tipo ─────

    public function test_d8_d9_admin_calendar_has_deadline_days_and_type_color(): void
    {
        $admin = $this->createSuperAdmin();
        $type = $this->zonalType();
        $type->update(['calendar_color' => '#123456']);
        $tournament = $this->futureTournament(1);
        $tournament->update(['availability_deadline' => now()->addDays(5)->setTime(0, 0)]);

        $tournament->refresh();
        $data = app(\App\Services\CalendarDataService::class)
            ->prepareFullCalendarData(collect([$tournament]), $admin, 'admin');

        $event = $data['tournaments']->first();
        $this->assertIsArray($event);
        $this->assertSame('#123456', $event['color']);
        $props = $this->arrayAt($event, 'extendedProps');
        $this->assertSame(5, $props['days_until_deadline']);
        $this->assertSame('#123456', $data['legend'][$type->name] ?? null);
    }

    // ── D3: import guidato riservato a CRC e super admin ────────────────────

    /**
     * D3 (decisione 2026-10-08): l'import guidato, come Carica comitati FIG,
     * e' solo dell'account del .env. Per gli altri la pagina non esiste.
     */
    public function test_d3_guided_import_only_for_the_env_account(): void
    {
        config(['golf.fig.import_email' => 'importatore@example.test']);
        $importer = $this->createSuperAdmin(['email' => 'importatore@example.test']);

        $this->actingAs($importer)->get(route('admin.federgolf-import.index'))->assertOk();
        foreach ([$this->createZoneAdmin(1), $this->createNationalAdmin(), $this->createSuperAdmin()] as $other) {
            $this->actingAs($other)->get(route('admin.federgolf-import.index'))->assertNotFound();
        }

        // Senza la riga nel .env non esiste per nessuno
        config(['golf.fig.import_email' => null]);
        $this->actingAs($importer)->get(route('admin.federgolf-import.index'))->assertNotFound();
    }

    public function test_d3_zone_admin_cannot_replace_committee_of_own_zone_national(): void
    {
        config(['golf.fig.import_email' => 'importatore@example.test']);
        $tournament = $this->futureTournament(1, true);
        $crcReferee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $crcReferee->id, 'role' => 'Arbitro']);
        $other = $this->createReferee(['zone_id' => 1]);

        foreach ([$this->createZoneAdmin(1), $this->createNationalAdmin()] as $admin) {
            $this->actingAs($admin)
                ->postJson(route('admin.federgolf-import.execute'), [
                    'tournament_id' => $tournament->id,
                    'assegnazioni' => [['user_id' => $other->id, 'ruolo' => 'Arbitro']],
                ])->assertNotFound();
        }

        $this->assertDatabaseHas('assignments', ['tournament_id' => $tournament->id, 'user_id' => $crcReferee->id]);
        $this->assertDatabaseMissing('assignments', ['tournament_id' => $tournament->id, 'user_id' => $other->id]);
    }

    // ── Pulizia: rotte che davano errore 500 o non usate, concetti eliminati ─

    public function test_cleanup_broken_and_orphan_routes_are_gone(): void
    {
        foreach ([
            'admin.quick-stats',
            'admin.communications.expire',
            'super-admin.tournament-types.show',
            'admin.tournament-notifications.edit',
            'admin.admins.index',
            'admin.referees.show',
            'admin.statistics.api',
            'super-admin.monitoring.dashboard',
            'super-admin.monitoring.logs',
            'verification.notice',
        ] as $name) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($name), "La rotta {$name} non deve esistere");
        }

        $this->actingAs($this->createSuperAdmin())->get('/api/system/tournament-types')->assertNotFound();
    }

    public function test_cleanup_eliminated_concepts_left_no_code(): void
    {
        $this->assertFalse(enum_exists('App\\Enums\\TournamentStatus'), 'Lo stato del torneo e\' eliminato (P3)');
        $this->assertFalse(defined(Tournament::class.'::STATUS_OPEN'));
        $this->assertNotContains('is_confirmed', (new \App\Models\Assignment)->getFillable(), 'La conferma e\' eliminata (P10)');
        $this->assertFalse(class_exists('App\\Policies\\CommunicationPolicy'));
    }

    public function test_cleanup_admin_tournament_types_index_shows_without_show_route(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->get(route('super-admin.tournament-types.index'))
            ->assertOk();
    }

    // ── D10: i tornei senza circolo (T.B.A.) contano nella zona ─────────────

    private function tbaTournament(int $zoneId, bool $national = false): Tournament
    {
        return Tournament::factory()->create([
            'club_id' => null,
            'zone_id' => $zoneId,
            'tournament_type_id' => ($national ? $this->nationalType() : $this->zonalType())->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);
    }

    public function test_d10_zone_admin_dashboard_counts_tba_tournaments(): void
    {
        $this->tbaTournament(1);
        $this->tbaTournament(2);

        $response = $this->actingAs($this->createZoneAdmin(1))->get(route('admin.dashboard'))->assertOk();

        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(1, $stats['total_tournaments']);
    }

    public function test_d10_validation_sees_tba_tournaments_of_the_zone(): void
    {
        $tba = $this->tbaTournament(1);
        $this->tbaTournament(2);

        $issues = app(\App\Services\AssignmentValidationService::class)->findMissingRequirements(1);

        $ids = $issues->map(fn ($row) => $this->issueTournamentId($row))->values()->all();
        $this->assertSame([$tba->id], $ids);
    }

    // ── D11: Direttore di Torneo mancante solo sui tornei nazionali ─────────

    public function test_d11_missing_director_uses_is_national(): void
    {
        $zonalWithNationalLevel = $this->zonalType();
        $zonalWithNationalLevel->update(['level' => 'nazionale', 'min_referees' => 0]);
        $national = $this->nationalType();
        $national->update(['level' => 'zonale', 'min_referees' => 0]);

        $zonal = $this->futureTournament(1);
        $nat = $this->futureTournament(1, true);

        $issues = app(\App\Services\AssignmentValidationService::class)->findMissingRequirements();
        $missingDirector = $issues
            ->filter(fn ($row) => collect($this->arrayAt($row, 'issues'))->contains('type', 'missing_role'))
            ->map(fn ($row) => $this->issueTournamentId($row))
            ->values()
            ->all();

        $this->assertContains($nat->id, $missingDirector);
        $this->assertNotContains($zonal->id, $missingDirector);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function issueTournamentId(array $row): int
    {
        $tournament = $row['tournament'] ?? null;
        $this->assertInstanceOf(Tournament::class, $tournament);

        return $tournament->id;
    }

    // ── D12: Nuovo utente con scelta del tipo ───────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function newUserData(string $email, string $type): array
    {
        return [
            'first_name' => 'Mario',
            'last_name' => 'Prova',
            'email' => $email,
            'zone_id' => 1,
            'level' => 'Regionale',
            'user_type' => $type,
            'is_active' => '1',
        ];
    }

    public function test_d12_zone_admin_creates_zone_admin_but_not_national(): void
    {
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('name="user_type"', false)
            ->assertDontSee('value="national_admin"', false);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->newUserData('nuovo.szr@test.it', 'admin'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'nuovo.szr@test.it', 'user_type' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->newUserData('nuovo.crc@test.it', 'national_admin'))
            ->assertSessionHasErrors('user_type');
        $this->assertDatabaseMissing('users', ['email' => 'nuovo.crc@test.it']);
    }

    public function test_d12_crc_creates_national_admin_but_not_super_admin(): void
    {
        $crc = $this->createNationalAdmin();

        $this->actingAs($crc)->post(route('admin.users.store'), $this->newUserData('nuovo.crc@test.it', 'national_admin'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'nuovo.crc@test.it', 'user_type' => 'national_admin']);

        $this->actingAs($crc)->post(route('admin.users.store'), $this->newUserData('nuovo.super@test.it', 'super_admin'))
            ->assertSessionHasErrors('user_type');
    }

    public function test_d12_default_new_user_is_referee(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->post(route('admin.users.store'), $this->newUserData('nuovo.arbitro@test.it', 'referee'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nuovo.arbitro@test.it', 'user_type' => 'referee']);
    }
}
