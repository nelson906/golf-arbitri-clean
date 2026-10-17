<?php

namespace Tests\Feature\Referee;

use App\Mail\BatchAvailabilityAdminNotification;
use App\Mail\BatchAvailabilityNotification;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\CalendarDataService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Disponibilita' lato arbitro, parti finora senza test:
 *  - filtri della pagina "Dichiara Disponibilita'" (zona, tipo, mese)
 *  - messaggi di rifiuto (torneo passato, scadenza passata) anche senza AJAX
 *  - profilo senza email: disponibilita' salvata, avviso all'arbitro
 *  - calendario che non si carica: la pagina si apre lo stesso
 *  - contenuto delle due email (riepilogo arbitro, avviso admin)
 */
class AvailabilityCoverageTest extends TestCase
{
    use CollectsIds;

    private function type(bool $national): TournamentType
    {
        return TournamentType::where('is_national', $national)->firstOrFail();
    }

    /**
     * Torneo che accetta disponibilita': fra $days giorni, scadenza fra 5.
     *
     * @param  array<string, mixed>  $over
     */
    private function openTournament(int $zoneId, int $days = 30, bool $national = false, array $over = []): Tournament
    {
        return $this->createTournament(array_merge([
            'club_id' => $this->createClub(['zone_id' => $zoneId])->id,
            'tournament_type_id' => $this->type($national)->id,
            'start_date' => now()->addDays($days)->startOfDay(),
            'end_date' => now()->addDays($days + 1)->startOfDay(),
            'availability_deadline' => now()->addDays(5)->startOfDay(),
        ], $over));
    }

    private function referee(int $zoneId = 1, string $level = '1_livello'): User
    {
        return $this->createReferee(['zone_id' => $zoneId, 'level' => $level, 'is_active' => true]);
    }

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<int>
     */
    private function listedIds($response): array
    {
        $page = $response->viewData('tournaments');
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $page);

        return $this->sortedIds($page);
    }

    /**
     * Valore di can_declare dell'evento del calendario con quell'id.
     */
    private function canDeclare(mixed $events, int $id): mixed
    {
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $events);
        $event = $events->firstWhere('id', $id);
        $this->assertIsArray($event, "Evento {$id} assente dal calendario.");

        return $this->arrayAt($event, 'extendedProps')['can_declare'] ?? null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tournament>
     */
    private function noTournaments(): \Illuminate\Support\Collection
    {
        return new \Illuminate\Support\Collection;
    }

    // ── Filtri ───────────────────────────────────────────────────────────────

    public function test_tournaments_filter_by_type(): void
    {
        $referee = $this->referee(1, 'Nazionale');
        $national = $this->openTournament(1, 30, true);
        $this->openTournament(1, 30, false);

        $response = $this->actingAs($referee)
            ->get(route('user.availability.tournaments', ['tournament_type_id' => $this->type(true)->id]));

        $response->assertOk();
        $this->assertSame([$national->id], $this->listedIds($response));
    }

    public function test_tournaments_filter_by_month(): void
    {
        $referee = $this->referee(1);
        $a = $this->openTournament(1, 30);
        $b = $this->openTournament(1, 30 + 62);

        $month = (int) $a->start_date->format('n');
        $response = $this->actingAs($referee)
            ->get(route('user.availability.tournaments', ['month' => $month]));

        $ids = $this->listedIds($response);
        $this->assertContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids);
    }

    public function test_tournaments_filter_by_zone_includes_tba_of_that_zone(): void
    {
        $referee = $this->referee(1, 'Nazionale');
        $own = $this->openTournament(1, 30);
        $tba = $this->openTournament(1, 31);
        $tba->forceFill(['club_id' => null, 'zone_id' => 1])->save();
        $elsewhere = $this->openTournament(2, 30, true);

        $response = $this->actingAs($referee)
            ->get(route('user.availability.tournaments', ['zone_id' => 1]));

        $ids = $this->listedIds($response);
        $this->assertContains($own->id, $ids);
        $this->assertContains($tba->id, $ids);
        $this->assertNotContains($elsewhere->id, $ids);
    }

    public function test_zone_list_national_referee_gets_all_zonal_only_own(): void
    {
        $national = $this->actingAs($this->referee(1, 'Nazionale'))->get(route('user.availability.tournaments'));
        $this->assertCount(8, $this->viewObject($national, 'zones', \Illuminate\Support\Collection::class));

        $zonal = $this->actingAs($this->referee(3))->get(route('user.availability.tournaments'));
        $zones = $this->viewObject($zonal, 'zones', \Illuminate\Support\Collection::class);
        $this->assertSame([3], $zones->pluck('id')->all());
    }

    // ── Rifiuti ──────────────────────────────────────────────────────────────

    public function test_past_tournament_is_refused_with_its_own_message(): void
    {
        $referee = $this->referee(1);
        $past = $this->openTournament(1, 30, false, [
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'availability_deadline' => now()->subDays(5),
        ]);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $past->id, 'available' => true])
            ->assertForbidden()
            ->assertJsonPath('error', 'Non puoi dichiarare disponibilità per tornei con date antecedenti a oggi.');
    }

    public function test_deadline_passed_without_ajax_goes_back_with_error(): void
    {
        $referee = $this->referee(1);
        $closed = $this->openTournament(1, 30, false, ['availability_deadline' => now()->subDay()]);

        $this->actingAs($referee)
            ->from(route('user.availability.tournaments'))
            ->post(route('user.availability.store'), ['tournament_id' => $closed->id, 'available' => '1'])
            ->assertRedirect(route('user.availability.tournaments'))
            ->assertSessionHasErrors(['availability' => 'Il termine per dichiarare disponibilità per questo torneo è scaduto.']);

        $this->assertDatabaseMissing('availabilities', ['user_id' => $referee->id]);
    }

    public function test_other_zone_tournament_is_refused_with_generic_message(): void
    {
        $referee = $this->referee(1);
        $elsewhere = $this->openTournament(2);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $elsewhere->id])
            ->assertForbidden()
            ->assertJsonPath('error', 'Non sei autorizzato a dichiarare disponibilità per questo torneo.');
    }

    public function test_store_without_ajax_goes_back_with_success(): void
    {
        $referee = $this->referee(1);
        $t = $this->openTournament(1);

        $this->actingAs($referee)
            ->from(route('user.availability.tournaments'))
            ->post(route('user.availability.store'), ['tournament_id' => $t->id, 'available' => '1', 'notes' => 'solo sabato'])
            ->assertRedirect(route('user.availability.tournaments'))
            ->assertSessionHas('success', 'Disponibilità dichiarata con successo.');

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $t->id, 'notes' => 'solo sabato']);
    }

    public function test_removing_availability_that_was_not_there_sends_no_email(): void
    {
        $referee = $this->referee(1);
        $t = $this->openTournament(1);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $t->id, 'available' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Disponibilità rimossa con successo.')
            ->assertJsonPath('email_sent', true);

        Mail::assertNothingQueued();
    }

    public function test_admin_cannot_use_the_referee_store(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('user.availability.store'), ['tournament_id' => $this->openTournament(1)->id])
            ->assertForbidden();
    }

    // ── Profilo senza email ──────────────────────────────────────────────────

    public function test_referee_without_email_still_saves_and_is_warned(): void
    {
        $referee = $this->referee(1);
        $referee->forceFill(['email' => ''])->save();
        $zoneAdmin = $this->createZoneAdmin(1);
        $t = $this->openTournament(1);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $t->id, 'available' => true])
            ->assertOk()
            ->assertJsonPath('email_sent', false)
            ->assertJsonPath('warning', 'Il tuo profilo non ha un indirizzo email: non riceverai il riepilogo. Le disponibilità sono comunque salvate.');

        $this->assertDatabaseHas('availabilities', ['user_id' => $referee->id, 'tournament_id' => $t->id]);
        Mail::assertNotQueued(BatchAvailabilityNotification::class);
        // L'avviso alla zona parte comunque
        Mail::assertQueued(BatchAvailabilityAdminNotification::class, fn (BatchAvailabilityAdminNotification $m): bool => $m->hasTo($zoneAdmin->email));
    }

    public function test_batch_save_without_email_shows_warning_on_list(): void
    {
        $referee = $this->referee(1);
        $referee->forceFill(['email' => ''])->save();
        $t = $this->openTournament(1);

        $this->actingAs($referee)
            ->post(route('user.availability.saveBatch'), [
                'availabilities' => [$t->id],
                'page_tournaments' => [$t->id],
            ])
            ->assertRedirect(route('user.availability.index'))
            ->assertSessionHas('warning', 'Il tuo profilo non ha un indirizzo email: non riceverai il riepilogo. Le disponibilità sono comunque salvate.');
    }

    public function test_batch_save_with_no_change_sends_nothing(): void
    {
        $referee = $this->referee(1);
        $t = $this->openTournament(1);
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $t->id, 'submitted_at' => now()]);

        $this->actingAs($referee)
            ->post(route('user.availability.saveBatch'), [
                'availabilities' => [$t->id],
                'page_tournaments' => [$t->id],
            ])
            ->assertSessionHas('success', 'Disponibilità aggiornate con successo!')
            ->assertSessionMissing('warning');

        Mail::assertNothingQueued();
    }

    public function test_crc_without_active_account_gets_no_national_notice(): void
    {
        $referee = $this->referee(1, 'Nazionale');
        $this->createNationalAdmin(['zone_id' => 8, 'is_active' => false]);
        $t = $this->openTournament(1, 30, true);
        // La factory dei tornei crea admin di zona (created_by) in zone a caso
        User::where('user_type', 'admin')->update(['is_active' => false]);

        $this->actingAs($referee)
            ->postJson(route('user.availability.store'), ['tournament_id' => $t->id])
            ->assertOk();

        // Nessun admin di zona attivo e nessun CRC attivo: solo il riepilogo
        Mail::assertQueued(BatchAvailabilityNotification::class, 1);
        Mail::assertNotQueued(BatchAvailabilityAdminNotification::class);
    }

    // ── Calendario ───────────────────────────────────────────────────────────

    public function test_calendar_marks_which_tournaments_accept_availability(): void
    {
        $referee = $this->referee(1);
        $open = $this->openTournament(1);
        $closed = $this->openTournament(1, 30, false, ['availability_deadline' => now()->subDay()]);

        $response = $this->actingAs($referee)->get(route('user.availability.calendar'));

        $response->assertOk();
        $data = $this->viewArray($response, 'calendarData');
        $this->assertTrue($this->canDeclare($data['tournaments'] ?? null, $open->id));
        $this->assertFalse($this->canDeclare($data['tournaments'] ?? null, $closed->id));
    }

    public function test_calendar_still_opens_when_data_cannot_be_loaded(): void
    {
        $this->mock(CalendarDataService::class)
            ->shouldReceive('prepareFullCalendarData')->andThrow(new \RuntimeException('query rotta'));

        $response = $this->actingAs($this->referee(1))->get(route('user.availability.calendar'));

        $response->assertOk()->assertSee('Si è verificato un errore nel caricamento del calendario.');
        $data = $this->viewArray($response, 'calendarData');
        $this->assertSame('Si è verificato un errore nel caricamento del calendario.', $data['error']);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $data['tournaments']);
        $this->assertCount(0, $data['tournaments']);
    }

    // ── Contenuto email ──────────────────────────────────────────────────────

    public function test_referee_summary_email_lists_added_and_removed(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Mario Riepilogo']);
        $added = $this->openTournament(1, 30, false, ['name' => 'Trofeo Aggiunto']);
        $removed = $this->openTournament(1, 40, false, ['name' => 'Coppa Tolta']);
        Availability::create(['user_id' => $referee->id, 'tournament_id' => $added->id, 'submitted_at' => now()]);

        $mail = new BatchAvailabilityNotification($referee, collect([$added->load('club')]), collect([$removed->load('club')]));

        $mail->assertHasSubject('Conferma aggiornamento disponibilità');
        $mail->assertSeeInHtml('Mario Riepilogo');
        $mail->assertSeeInHtml('Trofeo Aggiunto');
        $mail->assertSeeInHtml('Coppa Tolta');
    }

    public function test_admin_notice_email_has_referee_and_zone_in_subject(): void
    {
        $referee = $this->createReferee(['zone_id' => 2, 'name' => 'Luca Avviso', 'referee_code' => 'ARB-77']);
        $t = $this->openTournament(2, 30, false, ['name' => 'Gara Avvisata']);

        $mail = new BatchAvailabilityAdminNotification($referee, collect([$t->load('club')]), $this->noTournaments());

        $mail->assertHasSubject('[DISPONIBILITÀ] Luca Avviso - Zona Zona 2 - Nord Est');
        $mail->assertSeeInHtml('Luca Avviso');
        $mail->assertSeeInHtml('Gara Avvisata');
    }

    public function test_admin_notice_email_for_referee_without_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Senza Zona']);
        $referee->forceFill(['zone_id' => null])->save();

        $mail = new BatchAvailabilityAdminNotification($referee->fresh() ?? $referee, $this->noTournaments(), collect([$this->openTournament(1)->load('club')]));

        $mail->assertHasSubject('[DISPONIBILITÀ] Senza Zona - Zona N/A');
    }
}
