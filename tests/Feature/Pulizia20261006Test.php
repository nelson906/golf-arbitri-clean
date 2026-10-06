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

    public function test_d3_zone_admin_cannot_open_guided_import(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.federgolf-import.index'))
            ->assertForbidden();
    }

    public function test_d3_zone_admin_cannot_replace_committee_of_own_zone_national(): void
    {
        $tournament = $this->futureTournament(1, true);
        $crcReferee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $crcReferee->id, 'role' => 'Arbitro']);
        $other = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.federgolf-import.execute'), [
                'tournament_id' => $tournament->id,
                'assegnazioni' => [['user_id' => $other->id, 'ruolo' => 'Arbitro']],
            ])->assertForbidden();

        $this->assertDatabaseHas('assignments', ['tournament_id' => $tournament->id, 'user_id' => $crcReferee->id]);
        $this->assertDatabaseMissing('assignments', ['tournament_id' => $tournament->id, 'user_id' => $other->id]);
    }

    public function test_d3_crc_and_super_admin_can_open_guided_import(): void
    {
        $this->actingAs($this->createNationalAdmin())->get(route('admin.federgolf-import.index'))->assertOk();
        $this->actingAs($this->createSuperAdmin())->get(route('admin.federgolf-import.index'))->assertOk();
    }
}
