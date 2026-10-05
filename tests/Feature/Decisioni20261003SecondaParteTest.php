<?php

namespace Tests\Feature;

use App\Mail\NationalNotificationMail;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\TournamentNotification;
use App\Models\Tournament;
use App\Models\TournamentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Decisioni del 3 ottobre 2026, seconda parte (P4, P6, P17, P18).
 */
class Decisioni20261003SecondaParteTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function zonalTournament(array $attributes = []): Tournament
    {
        $club = $this->createClub(['zone_id' => 1]);

        return $this->createTournament(array_merge([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ], $attributes));
    }

    // ── P4: la scadenza vale fino alle 23:59 del giorno scritto ────────────

    public function test_p4_availability_accepted_during_the_whole_deadline_day(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->zonalTournament(['availability_deadline' => now()->addDays(5)->startOfDay()]);

        Carbon::setTestNow(now()->addDays(5)->setTime(23, 30));

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $tournament->id,
            'available' => 1,
        ]);

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $tournament->id]);
    }

    public function test_p4_availability_refused_the_day_after_the_deadline(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->zonalTournament(['availability_deadline' => now()->addDays(5)->startOfDay()]);

        Carbon::setTestNow(now()->addDays(6)->setTime(0, 1));

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $tournament->id,
            'available' => 1,
        ]);

        $this->assertDatabaseMissing('availabilities', ['user_id' => $referee->id, 'tournament_id' => $tournament->id]);
    }

    public function test_p4_withdrawal_allowed_on_deadline_day(): void
    {
        Mail::fake();
        $referee = $this->createReferee(['zone_id' => 1]);
        $tournament = $this->zonalTournament(['availability_deadline' => now()->addDays(5)->startOfDay()]);
        $availability = Availability::create(['user_id' => $referee->id, 'tournament_id' => $tournament->id, 'submitted_at' => now()]);

        Carbon::setTestNow(now()->addDays(5)->setTime(18, 0));

        $this->actingAs($referee)->delete(route('user.availability.destroy', $availability))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
    }

    // ── P6: limiti del tipo torneo solo indicativi ──────────────────────────

    public function test_p6_assignment_beyond_max_referees_is_allowed(): void
    {
        $admin = $this->createZoneAdmin(1);
        $tournament = $this->zonalTournament(); // tipo zonale di test: massimo 2 arbitri

        foreach (range(1, 3) as $i) {
            $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);

            $this->actingAs($admin)->post(route('admin.assignments.store'), [
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'role' => 'Arbitro',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $tournament->assignments()->count());
    }

    public function test_p6_referee_below_required_level_can_be_assigned(): void
    {
        $admin = $this->createZoneAdmin(1);
        $tournament = $this->zonalTournament(); // livello richiesto 1_livello
        $aspirante = $this->createReferee(['zone_id' => 1, 'level' => 'Aspirante']);

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'tournament_id' => $tournament->id,
            'user_id' => $aspirante->id,
            'role' => 'Arbitro',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assignments', ['tournament_id' => $tournament->id, 'user_id' => $aspirante->id]);
    }

    // ── P17: pulsante Vedi Disponibilità ────────────────────────────────────

    public function test_p17_tournament_detail_links_to_availabilities_page(): void
    {
        Mail::fake();
        $admin = $this->createZoneAdmin(1);
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Paolo Disponibile']);
        $tournament = $this->zonalTournament();
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $tournament->id, 'submitted_at' => now()]);

        $url = route('admin.tournaments.availabilities.index', $tournament);

        $this->actingAs($admin)->get(route('admin.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee('Vedi Disponibilità');

        $this->actingAs($admin)->get($url)
            ->assertOk()
            ->assertSee('Paolo Disponibile');
    }

    // ── P18: gestione zone per il super admin ───────────────────────────────

    public function test_p18_super_admin_lists_and_edits_zones(): void
    {
        $super = $this->createSuperAdmin();
        $zone = \App\Models\Zone::findOrFail(1);

        $this->actingAs($super)->get(route('super-admin.zones.index'))
            ->assertOk()
            ->assertSee($zone->name);

        $this->actingAs($super)->get(route('super-admin.zones.edit', $zone))->assertOk();

        $this->actingAs($super)->put(route('super-admin.zones.update', $zone), [
            'name' => 'Zona 1 - Nord Ovest',
            'email' => 'nuova-szr1@federgolf.it',
            'phone' => '011 123456',
        ])->assertRedirect(route('super-admin.zones.index'));

        $zone->refresh();
        $this->assertSame('nuova-szr1@federgolf.it', $zone->email);
        $this->assertSame('SZR1', $zone->code, 'Il codice non si modifica');
    }

    public function test_p18_zone_email_must_be_valid(): void
    {
        $super = $this->createSuperAdmin();

        $this->actingAs($super)->put(route('super-admin.zones.update', 1), [
            'name' => 'Zona 1',
            'email' => 'Sezione Zonale Regole 1',
        ])->assertSessionHasErrors('email');
    }

    public function test_p18_only_super_admin_manages_zones(): void
    {
        $this->actingAs($this->createNationalAdmin())->get(route('super-admin.zones.index'))->assertForbidden();
        $this->actingAs($this->createZoneAdmin(1))->get(route('super-admin.zones.index'))->assertForbidden();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('super-admin.zones.destroy'));
    }

    // ── P12/P13: tornei nazionali, due comunicazioni separate senza allegati ─

    private function nationalTournamentWithCommittee(): Tournament
    {
        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);

        $arbitro = $this->createReferee(['zone_id' => 2, 'level' => 'Nazionale', 'name' => 'Arbitro Nazionale']);
        $osservatore = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale', 'name' => 'Osservatore Zonale']);
        Assignment::factory()->forUser($arbitro)->forTournament($tournament)->asReferee()->create();
        Assignment::factory()->forUser($osservatore)->forTournament($tournament)->asObserver()->create();

        return $tournament;
    }

    public function test_p13_opening_national_form_creates_no_record_and_no_word_document(): void
    {
        $tournament = $this->nationalTournamentWithCommittee();
        $zoneAdmin = $this->createZoneAdmin(1);

        $this->actingAs($zoneAdmin)
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('Designazione Osservatori');

        $this->assertSame(0, TournamentNotification::where('tournament_id', $tournament->id)->count(),
            'Aprire il form non deve creare notifiche (tanto meno quella del CRC)');
    }

    public function test_p13_assigning_on_national_tournament_creates_no_draft(): void
    {
        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
        $referee = $this->createReferee(['zone_id' => 1]);

        $this->actingAs($this->createZoneAdmin(1))->post(route('admin.assignments.storeMultiple', $tournament), [
            'referee_ids' => [$referee->id],
            'roles' => [$referee->id => 'Osservatore'],
        ]);

        $this->assertSame(0, TournamentNotification::where('tournament_id', $tournament->id)->count());
    }

    public function test_p12_crc_message_asks_zone_to_communicate_observers(): void
    {
        $tournament = $this->nationalTournamentWithCommittee();

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('Designazione Arbitri')
            ->assertSee('a comunicare i nominativi degli osservatori')
            ->assertSee('Arbitro Nazionale');
    }

    public function test_p12_zone_admin_cannot_send_crc_communication(): void
    {
        Mail::fake();
        $tournament = $this->nationalTournamentWithCommittee();

        $this->actingAs($this->createZoneAdmin(1))->post(route('admin.tournaments.send-national-notification', $tournament), [
            'notification_type' => 'crc_referees',
            'subject' => 'Designazione Arbitri',
            'message' => 'Testo',
            'send_to_campionati' => 1,
        ])->assertSessionHas('error');

        Mail::assertNotQueued(NationalNotificationMail::class);
    }

    public function test_p12_zone_sends_observers_and_creates_only_its_own_record(): void
    {
        Mail::fake();
        $tournament = $this->nationalTournamentWithCommittee();

        $this->actingAs($this->createZoneAdmin(1))->post(route('admin.tournaments.send-national-notification', $tournament), [
            'notification_type' => 'zone_observers',
            'subject' => 'Designazione Osservatori',
            'message' => 'Testo',
            'send_to_campionati' => 1,
            'send_to_crc' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tournament_notifications', ['tournament_id' => $tournament->id, 'notification_type' => 'zone_observers']);
        $this->assertDatabaseMissing('tournament_notifications', ['tournament_id' => $tournament->id, 'notification_type' => 'crc_referees']);
    }

    public function test_p12_zonal_form_refuses_national_tournament(): void
    {
        Mail::fake();
        $tournament = $this->nationalTournamentWithCommittee();

        $this->actingAs($this->createNationalAdmin())->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
            'subject' => 'Lettera circolo',
            'message' => 'Testo',
            'action' => 'send',
        ])->assertSessionHas('error');

        $this->assertSame(0, TournamentNotification::where('tournament_id', $tournament->id)->count());
    }

    public function test_p12_national_form_works_without_club(): void
    {
        // createTournament() crea un circolo se club_id e' null: qui serve un T.B.A.
        $tournament = Tournament::factory()->create([
            'club_id' => null,
            'zone_id' => 1,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        Assignment::factory()->forUser($referee)->forTournament($tournament)->asReferee()->create();

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('da definire');
    }
    // ── Visibilita' arbitro zonale sui tornei nazionali (manuale cap. 6) ────

    public function test_zonal_referee_sees_and_declares_on_national_tournament_of_own_zone(): void
    {
        Mail::fake();
        $national = TournamentType::where('is_national', true)->firstOrFail()->id;
        $own = $this->zonalTournament(['name' => 'Nazionale In Zona', 'tournament_type_id' => $national]);
        $otherClub = $this->createClub(['zone_id' => 2]);
        $this->zonalTournament(['name' => 'Nazionale Altra Zona', 'club_id' => $otherClub->id, 'tournament_type_id' => $national]);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);

        $this->actingAs($referee)->get(route('user.availability.tournaments'))
            ->assertOk()
            ->assertSee('Nazionale In Zona')
            ->assertDontSee('Nazionale Altra Zona');

        $this->actingAs($referee)->post(route('user.availability.store'), [
            'tournament_id' => $own->id,
            'available' => 1,
        ]);

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $own->id]);
    }

    public function test_zonal_referee_sees_tba_tournament_of_own_zone(): void
    {
        $tba = Tournament::factory()->create([
            'name' => 'Torneo Circolo Da Definire',
            'club_id' => null,
            'zone_id' => 1,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Regionale']);

        $this->assertTrue(Tournament::visible($referee)->whereKey($tba->id)->exists());
        $this->assertFalse(Tournament::visible($this->createReferee(['zone_id' => 2, 'level' => 'Regionale']))
            ->whereKey($tba->id)->exists());
    }
}
