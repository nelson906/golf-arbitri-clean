<?php

namespace Tests\Feature;

use App\Http\Middleware\RefereeOrAdmin;
use App\Models\NotificationClause;
use App\Models\RefereeCareerHistory;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\CalendarDataService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Correzioni del 17 ottobre 2026 (comportamenti strani trovati scrivendo i
 * test di copertura):
 *  1. "Carica allegato" senza file o non Word: errore chiaro, non 500
 *  2. messaggi di validazione e di accesso in italiano (prima le chiavi grezze)
 *  3. clausola eliminata: eliminazione definitiva, codice riutilizzabile
 *  4. calendario disponibilita' che non si carica: lo si dice in pagina
 *  5. area arbitro: tolti i controlli che non scattavano mai
 *  6. career:manage come la pagina Storico carriera
 *  7. singola assegnazione: tolti i rami irraggiungibili
 */
class Correzioni20261017Test extends TestCase
{
    private function zonalDraft(): TournamentNotification
    {
        $t = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test'])->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);

        return TournamentNotification::create(['tournament_id' => $t->id, 'status' => 'pending']);
    }

    // ── 1. Carica allegato ───────────────────────────────────────────────────

    public function test_upload_without_file_is_a_clear_error_not_500(): void
    {
        $n = $this->zonalDraft();

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'convocation']), [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scegli il file da caricare.');

        $this->assertEmpty(TournamentNotification::findOrFail($n->id)->documents);
    }

    public function test_upload_not_word_is_a_clear_error_not_500(): void
    {
        $n = $this->zonalDraft();

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'club_letter']), [
                'document' => UploadedFile::fake()->create('lettera.pdf', 5),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Si possono caricare solo file Word (.doc o .docx).');
    }

    public function test_upload_too_big_is_a_clear_error(): void
    {
        $n = $this->zonalDraft();

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'club_letter']), [
                'document' => UploadedFile::fake()->create('lettera.docx', 10241),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Il file supera i 10 MB.');
    }

    public function test_upload_modal_shows_the_server_message(): void
    {
        $modal = file_get_contents(resource_path('views/admin/tournament-notifications/_document_manager_modal.blade.php'));
        $this->assertIsString($modal);

        // Prima su risposta non OK buttava via il motivo del server
        $this->assertStringContainsString("then(data => { throw new Error(data.message || 'Errore nel caricamento del file'); })", $modal);
    }

    // ── 2. Messaggi in italiano ──────────────────────────────────────────────

    public function test_validation_messages_are_in_italian_with_field_names(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.clauses.store'), [
                'code' => 'ok_codice',
                'category' => 'spese',
                'content' => 'C',
                'applies_to' => 'club',
            ])
            ->assertSessionHasErrors(['title' => 'Il campo titolo è obbligatorio.']);
    }

    public function test_unique_message_is_in_italian(): void
    {
        \App\Models\InstitutionalEmail::create([
            'name' => 'Ufficio',
            'email' => 'doppio@example.test',
            'category' => 'altro',
            'is_active' => true,
        ]);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.institutional-emails.store'), [
                'name' => 'Altro',
                'email' => 'doppio@example.test',
                'category' => 'altro',
            ])
            ->assertSessionHasErrors(['email' => 'Il valore di email è già in uso.']);
    }

    public function test_wrong_password_message_is_in_italian(): void
    {
        $user = $this->createReferee(['email' => 'login@example.test']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'sbagliata'])
            ->assertSessionHasErrors(['email' => 'Credenziali non valide.']);
    }

    public function test_no_translation_key_is_left_raw(): void
    {
        foreach (['validation.required', 'validation.unique', 'validation.mimes', 'auth.failed', 'passwords.token', 'pagination.next'] as $key) {
            $this->assertNotSame($key, __($key), "Manca la traduzione italiana di {$key}");
        }
    }

    // ── 3. Clausole ──────────────────────────────────────────────────────────

    public function test_deleted_clause_code_can_be_used_again(): void
    {
        $clause = NotificationClause::create([
            'code' => 'riusabile',
            'category' => 'spese',
            'title' => 'Vecchia',
            'content' => 'C',
            'applies_to' => 'club',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->actingAsSuperAdmin();

        $this->delete(route('super-admin.clauses.destroy', $clause))
            ->assertSessionHas('success', 'Clausola eliminata con successo.');
        $this->assertDatabaseMissing('notification_clauses', ['id' => $clause->id]);

        $this->post(route('super-admin.clauses.store'), [
            'code' => 'riusabile',
            'category' => 'spese',
            'title' => 'Nuova',
            'content' => 'C',
            'applies_to' => 'club',
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Clausola creata con successo.');

        $this->assertDatabaseHas('notification_clauses', ['code' => 'riusabile', 'title' => 'Nuova']);
    }

    // ── 4. Calendario disponibilita' ─────────────────────────────────────────

    public function test_calendar_that_cannot_load_says_so_on_the_page(): void
    {
        $this->mock(CalendarDataService::class)
            ->shouldReceive('prepareFullCalendarData')->andThrow(new \RuntimeException('query rotta'));
        $referee = $this->createReferee(['zone_id' => 1]);

        $response = $this->actingAs($referee)->get(route('user.availability.calendar'));

        $response->assertOk()
            ->assertSee('Si è verificato un errore nel caricamento del calendario.')
            ->assertSee(route('user.availability.index'), false);
        $this->assertSame('referee', $this->viewArray($response, 'calendarData')['userType']);
    }

    public function test_calendar_that_loads_shows_no_error(): void
    {
        $this->actingAs($this->createReferee(['zone_id' => 1]))
            ->get(route('user.availability.calendar'))
            ->assertOk()
            ->assertDontSee('Si è verificato un errore nel caricamento del calendario.');
    }

    // ── 5. Area arbitro ──────────────────────────────────────────────────────

    private function runMiddleware(Request $request): Response
    {
        return (new RefereeOrAdmin)->handle($request, fn (): Response => new Response('avanti'));
    }

    public function test_area_lets_every_logged_user_through(): void
    {
        foreach ([$this->createReferee(), $this->createZoneAdmin(1), $this->createNationalAdmin(), $this->createSuperAdmin()] as $user) {
            Auth::login($user);

            $this->assertSame('avanti', $this->runMiddleware(Request::create('/user/availability'))->getContent());
        }
    }

    public function test_area_guest_json_gets_401_and_page_goes_to_login(): void
    {
        Auth::logout();

        $json = Request::create('/user/availability', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame(401, $this->runMiddleware($json)->getStatusCode());

        $page = $this->runMiddleware(Request::create('/user/availability'));
        $this->assertSame(302, $page->getStatusCode());
        $this->assertSame(route('login'), $page->headers->get('Location'));
    }

    public function test_area_dead_checks_are_gone(): void
    {
        $this->assertFalse(method_exists(RefereeOrAdmin::class, 'checkRefereeAccess'));
        $this->assertFalse(method_exists(RefereeOrAdmin::class, 'isRefereeRestrictedResource'));
    }

    public function test_user_pages_still_need_login(): void
    {
        $this->get(route('user.availability.index'))->assertRedirect(route('login'));
    }

    // ── 6. career:manage ─────────────────────────────────────────────────────

    public function test_career_manage_add_uses_manual_entry_and_remove_takes_the_assignment_too(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $t = $this->createTournament();
        $year = (string) now()->year;

        $this->artisanCommand('career:manage', [
            'action' => 'add', '--user' => $referee->email, '--year' => $year,
            '--tournament' => (string) $t->id, '--role' => 'Arbitro',
        ])->assertExitCode(0);

        $history = RefereeCareerHistory::where('user_id', $referee->id)->firstOrFail();
        $assignments = $history->assignments_by_year ?? [];
        $this->assertSame('manual_entry', $this->arrayAt($this->arrayAt($assignments, (int) $year), 0)['status'] ?? null);
        $stats = $history->career_stats ?? [];
        $this->assertSame(1, $stats['total_assignments'] ?? null, 'statistiche ricalcolate');

        $this->artisanCommand('career:manage', [
            'action' => 'remove', '--user' => $referee->email, '--year' => $year, '--tournament' => (string) $t->id,
        ])->assertExitCode(0);

        $history->refresh();
        $this->assertSame([], ($history->assignments_by_year ?? [])[(int) $year] ?? null);
        $this->assertSame([], ($history->tournaments_by_year ?? [])[(int) $year] ?? null);
        $this->assertSame(0, ($history->career_stats ?? [])['total_assignments'] ?? null);
    }

    // ── 7. Singola assegnazione ──────────────────────────────────────────────

    public function test_single_assignment_still_requires_role_and_refuses_duplicates(): void
    {
        $t = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $referee = $this->createReferee(['zone_id' => 1, 'is_active' => true]);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), ['tournament_id' => $t->id, 'user_id' => $referee->id])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), ['tournament_id' => $t->id, 'user_id' => $referee->id, 'role' => 'Arbitro'])
            ->assertSessionHas('success', 'Assegnazione creata con successo');

        $this->actingAs($admin)
            ->post(route('admin.assignments.store'), ['tournament_id' => $t->id, 'user_id' => $referee->id, 'role' => 'Arbitro'])
            ->assertSessionHasErrors(['user_id' => 'Questo arbitro è già stato assegnato a questo torneo.']);

        $this->assertSame(1, \App\Models\Assignment::where('tournament_id', $t->id)->count());
    }
}
