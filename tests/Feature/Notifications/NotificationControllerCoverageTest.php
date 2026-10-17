<?php

namespace Tests\Feature\Notifications;

use App\Mail\NationalNotificationMail;
use App\Models\NotificationClause;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\NotificationDocumentService;
use App\Services\NotificationPreparationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notifiche, parti finora senza test:
 *  - elenco: ricerca per nome
 *  - form: torneo senza arbitri
 *  - allegati dal modal: stato, crea, elimina, scarica, carica (tipi non
 *    validi, tornei nazionali, errori)
 *  - clausole salvate via AJAX
 *  - "trova notifica del torneo"
 *  - invio nazionale: senza indirizzo del Comitato Campionati non parte;
 *    osservatori: mittente = zona in cui si gioca (anche T.B.A.)
 */
class NotificationControllerCoverageTest extends TestCase
{
    private function type(bool $national): TournamentType
    {
        return TournamentType::where('is_national', $national)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $over
     */
    private function tournamentIn(int $zoneId, bool $national = false, array $over = [], bool $withReferee = true): Tournament
    {
        $t = $this->createTournament(array_merge([
            'club_id' => $this->createClub(['zone_id' => $zoneId, 'email' => 'circolo@example.test'])->id,
            'tournament_type_id' => $this->type($national)->id,
        ], $over));

        if ($withReferee) {
            $this->createAssignment([
                'tournament_id' => $t->id,
                'user_id' => $this->createReferee(['zone_id' => $zoneId, 'level' => $national ? 'Nazionale' : '1_livello'])->id,
            ]);
        }

        return $t;
    }

    private function draftFor(Tournament $t): TournamentNotification
    {
        return TournamentNotification::create([
            'tournament_id' => $t->id,
            'notification_type' => null,
            'status' => 'pending',
        ]);
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function test_index_search_by_tournament_name(): void
    {
        $match = $this->tournamentIn(1, false, ['name' => 'Trofeo Cercato']);
        $this->tournamentIn(1, false, ['name' => 'Coppa Altra']);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.index', ['cerca' => 'Cercato']));

        $response->assertOk();
        $page = $response->viewData('tournamentNotifications');
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $page);
        $ids = [];
        foreach ($page->items() as $row) {
            $this->assertInstanceOf(\stdClass::class, $row);
            $this->assertInstanceOf(Tournament::class, $row->tournament);
            $ids[] = $row->tournament->id;
        }
        $this->assertSame([$match->id], $ids);
    }

    public function test_index_ignores_malformed_month(): void
    {
        $this->tournamentIn(1);
        $this->tournamentIn(1);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.index', ['mese' => 'ottobre']));

        $page = $response->viewData('tournamentNotifications');
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $page);
        $this->assertCount(2, $page->items());
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public function test_form_without_assigned_referees_goes_back_with_error(): void
    {
        $t = $this->tournamentIn(1, false, [], false);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.tournaments.index'))
            ->get(route('admin.tournaments.show-assignment-form', $t))
            ->assertRedirect(route('admin.tournaments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_notifications', ['tournament_id' => $t->id]);
    }

    // ── Stato allegati ───────────────────────────────────────────────────────

    public function test_documents_status_lists_saved_attachments(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->attachDocumentsTo($n);

        $this->actingAs($this->createZoneAdmin(1))
            ->getJson(route('admin.tournament-notifications.documents-status', $n))
            ->assertOk()
            ->assertJsonPath('notification_id', $n->id)
            ->assertJsonPath('convocation.filename', "convocazione_test_{$n->id}.docx")
            ->assertJsonPath('club_letter.filename', "lettera_circolo_test_{$n->id}.docx");
    }

    public function test_documents_status_without_attachments(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->getJson(route('admin.tournament-notifications.documents-status', $n))
            ->assertOk()
            ->assertJsonPath('convocation', null)
            ->assertJsonPath('club_letter', null);
    }

    public function test_documents_status_other_zone_is_forbidden(): void
    {
        $n = $this->draftFor($this->tournamentIn(2));

        $this->actingAs($this->createZoneAdmin(1))
            ->getJson(route('admin.tournament-notifications.documents-status', $n))
            ->assertForbidden();
    }

    public function test_documents_status_error_is_reported(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->mock(NotificationDocumentService::class)
            ->shouldReceive('getDocumentsStatus')->andThrow(new \RuntimeException('disco non montato'));

        $this->actingAs($this->createZoneAdmin(1))
            ->getJson(route('admin.tournament-notifications.documents-status', $n))
            ->assertStatus(500)
            ->assertJsonPath('error', 'Errore nel caricamento dei documenti: disco non montato');
    }

    // ── Crea allegato ────────────────────────────────────────────────────────

    public function test_generate_rejects_unknown_type(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.generate-document', [$n, '..%2Fenv']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tipo documento non valido.');
    }

    public function test_generate_refused_on_national_tournament(): void
    {
        $n = $this->draftFor($this->tournamentIn(1, true));

        $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->postJson(route('admin.tournament-notifications.generate-document', [$n, 'convocation']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sui tornei nazionali non ci sono allegati.');
    }

    public function test_generate_creates_the_attachment_and_records_it(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.generate-document', [$n, 'club_letter']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Documento generato con successo');

        $saved = $n->fresh()?->documents['club_letter'] ?? null;
        $this->assertIsString($saved);
        $this->assertSame($saved, $response->json('status.club_letter.filename'));
        $this->assertArrayNotHasKey('convocation', (array) $n->fresh()?->documents);
    }

    public function test_generate_error_is_reported(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->mock(NotificationDocumentService::class)
            ->shouldReceive('generateDocument')->andThrow(new \RuntimeException('template mancante'));

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.generate-document', [$n, 'convocation']))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Errore nella generazione: template mancante');

        $this->assertEmpty($n->fresh()?->documents);
    }

    // ── Elimina allegato ─────────────────────────────────────────────────────

    public function test_delete_rejects_unknown_type(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->deleteJson(route('admin.tournament-notifications.delete-document', [$n, 'altro']))
            ->assertStatus(422);
    }

    public function test_delete_removes_file_and_key_keeping_the_other(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->attachDocumentsTo($n);
        $disk = \Illuminate\Support\Facades\Storage::disk(Config::string('golf.documents.disk', 'docs'));
        $path = Config::string('golf.documents.storage_path', 'convocazioni')."/SZR1/generated/convocazione_test_{$n->id}.docx";
        $this->assertTrue($disk->exists($path));

        $this->actingAs($this->createZoneAdmin(1))
            ->deleteJson(route('admin.tournament-notifications.delete-document', [$n, 'convocation']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status.convocation', null);

        $this->assertFalse($disk->exists($path));
        $documents = (array) $n->fresh()?->documents;
        $this->assertArrayNotHasKey('convocation', $documents);
        $this->assertSame("lettera_circolo_test_{$n->id}.docx", $documents['club_letter'] ?? null);
    }

    public function test_delete_missing_attachment_reports_error(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->deleteJson(route('admin.tournament-notifications.delete-document', [$n, 'convocation']))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Documento non trovato');
    }

    // ── Scarica allegato ─────────────────────────────────────────────────────

    public function test_download_rejects_unknown_type(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.download-document', [$n, 'altro']))
            ->assertStatus(422);
    }

    public function test_download_gives_the_word_file_with_a_clean_name(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->attachDocumentsTo($n);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.download-document', [$n, 'club_letter']));

        $response->assertOk();
        $this->assertSame('attachment; filename="Lettera_Circolo.docx"', $response->headers->get('Content-Disposition'));
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type')
        );
    }

    public function test_download_old_doc_format(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $disk = \Illuminate\Support\Facades\Storage::disk(Config::string('golf.documents.disk', 'docs'));
        $dir = Config::string('golf.documents.storage_path', 'convocazioni').'/SZR1/generated';
        $disk->put("{$dir}/vecchia_{$n->id}.doc", 'FAKE-DOC');
        $n->update(['documents' => ['convocation' => "vecchia_{$n->id}.doc"]]);

        $response = $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.download-document', [$n, 'convocation']));

        $this->assertSame('attachment; filename="Convocazione.doc"', $response->headers->get('Content-Disposition'));
        $this->assertSame('application/msword', $response->headers->get('Content-Type'));
        $disk->delete("{$dir}/vecchia_{$n->id}.doc");
    }

    public function test_download_missing_file_goes_back_with_error(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $n->update(['documents' => ['convocation' => 'sparito.docx']]);

        $this->actingAs($this->createZoneAdmin(1))
            ->from(route('admin.tournament-notifications.index'))
            ->get(route('admin.tournament-notifications.download-document', [$n, 'convocation']))
            ->assertRedirect(route('admin.tournament-notifications.index'))
            ->assertSessionHas('error', 'File non trovato sul server');
    }

    // ── Carica allegato ──────────────────────────────────────────────────────

    public function test_upload_rejects_unknown_type(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'altro']), [
                'document' => UploadedFile::fake()->create('x.docx', 5),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tipo documento non valido.');
    }

    public function test_upload_refused_on_national_tournament(): void
    {
        $n = $this->draftFor($this->tournamentIn(1, true));

        $this->actingAs($this->createSuperAdmin())
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'convocation']), [
                'document' => UploadedFile::fake()->create('x.docx', 5),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sui tornei nazionali non ci sono allegati.');
    }

    public function test_upload_replaces_previous_file(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->attachDocumentsTo($n);
        $disk = \Illuminate\Support\Facades\Storage::disk(Config::string('golf.documents.disk', 'docs'));
        $dir = Config::string('golf.documents.storage_path', 'convocazioni').'/SZR1/generated';

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.upload-document', [$n, 'club_letter']), [
                'document' => UploadedFile::fake()->create('Lettera.doc', 5),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $t = $n->tournament;
        $this->assertSame("lettera_circolo_{$t->id}_corretta.doc", $n->fresh()?->documents['club_letter'] ?? null);
        $this->assertTrue($disk->exists("{$dir}/lettera_circolo_{$t->id}_corretta.doc"));
        $this->assertFalse($disk->exists("{$dir}/lettera_circolo_test_{$n->id}.docx"), 'la versione precedente va tolta');
        $disk->delete("{$dir}/lettera_circolo_{$t->id}_corretta.doc");
    }

    // ── Clausole via AJAX ────────────────────────────────────────────────────

    public function test_save_clauses_reports_how_many_were_saved(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->mock(NotificationPreparationService::class)
            ->shouldReceive('saveClauseSelections')->once()->andReturn(2);

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.save-clauses', $n), ['clauses' => []])
            ->assertOk()
            ->assertJsonPath('message', 'Salvate 2 clausole')
            ->assertJsonPath('saved_count', 2);
    }

    public function test_save_clauses_with_real_clause(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $clause = NotificationClause::create([
            'code' => 'TEST_CL',
            'category' => 'spese',
            'title' => 'Rimborso',
            'content' => 'Testo clausola',
            'applies_to' => 'club',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.save-clauses', $n), [
                'clauses' => ['spese' => $clause->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notification_clause_selections', [
            'tournament_notification_id' => $n->id,
            'clause_id' => $clause->id,
        ]);
    }

    public function test_save_clauses_unknown_clause_is_rejected(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.save-clauses', $n), ['clauses' => ['x' => 999999]])
            ->assertStatus(422);
    }

    public function test_save_clauses_error_is_reported(): void
    {
        $n = $this->draftFor($this->tournamentIn(1));
        $this->mock(NotificationPreparationService::class)
            ->shouldReceive('saveClauseSelections')->andThrow(new \RuntimeException('db giu'));

        $this->actingAs($this->createZoneAdmin(1))
            ->postJson(route('admin.tournament-notifications.save-clauses', $n), ['clauses' => []])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Errore nel salvataggio delle clausole: db giu');
    }

    // ── Trova notifica del torneo ────────────────────────────────────────────

    public function test_find_by_tournament_returns_latest_or_null(): void
    {
        $t = $this->tournamentIn(1);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)
            ->getJson(route('admin.tournament-notifications.find-by-tournament', $t))
            ->assertOk()
            ->assertJsonPath('notification_id', null);

        $n = $this->draftFor($t);

        $this->actingAs($admin)
            ->getJson(route('admin.tournament-notifications.find-by-tournament', $t))
            ->assertJsonPath('notification_id', $n->id);
    }

    public function test_find_by_tournament_other_zone_is_forbidden(): void
    {
        $this->actingAs($this->createZoneAdmin(1))
            ->getJson(route('admin.tournament-notifications.find-by-tournament', $this->tournamentIn(2)))
            ->assertForbidden();
    }

    // ── Invio nazionale ──────────────────────────────────────────────────────

    public function test_national_send_without_campionati_address_does_not_leave(): void
    {
        Config::set('golf.emails.ufficio_campionati', '');
        $t = $this->tournamentIn(1, true);

        $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->post(route('admin.tournaments.send-national-notification', $t), [
                'notification_type' => 'crc_referees',
                'subject' => 'Designazione',
                'message' => 'Testo',
            ])
            ->assertSessionHas('error', 'Manca un indirizzo valido del Comitato Campionati (GOLF_EMAIL_CAMPIONATI nel .env): la comunicazione non parte.');

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('tournament_notifications', ['tournament_id' => $t->id, 'notification_type' => 'crc_referees']);
    }

    public function test_national_observers_sender_falls_back_to_zone_pattern(): void
    {
        Config::set('golf.zones.default_email_pattern', 'sezione{zone_id}@example.test');
        Zone::whereKey(3)->update(['email' => 'non e una email']);
        $t = $this->tournamentIn(3, true);

        $this->actingAs($this->createZoneAdmin(3))
            ->post(route('admin.tournaments.send-national-notification', $t), [
                'notification_type' => 'zone_observers',
                'subject' => 'Osservatori',
                'message' => 'Testo',
            ])
            ->assertSessionHas('success');

        Mail::assertQueued(NationalNotificationMail::class, fn (NationalNotificationMail $m): bool => $m->replyToAddress === 'sezione3@example.test'
            && str_contains((string) $m->senderName, 'Zona 3 - Centro'));
    }

    public function test_national_observers_sender_for_tba_tournament_uses_tournament_zone(): void
    {
        $t = $this->tournamentIn(4, true);
        DB::table('tournaments')->where('id', $t->id)->update(['club_id' => null, 'zone_id' => 4]);

        $this->actingAs($this->createZoneAdmin(4))
            ->post(route('admin.tournaments.send-national-notification', $t->fresh()), [
                'notification_type' => 'zone_observers',
                'subject' => 'Osservatori',
                'message' => 'Testo',
            ])
            ->assertSessionHas('success');

        Mail::assertQueued(NationalNotificationMail::class, fn (NationalNotificationMail $m): bool => $m->replyToAddress === 'szr4@federgolf.it'
            && str_contains((string) $m->senderName, 'Zona 4 - Sud'));
    }

    public function test_national_crc_send_has_no_zone_sender(): void
    {
        $t = $this->tournamentIn(1, true);

        $this->actingAs($this->createNationalAdmin(['zone_id' => 8]))
            ->post(route('admin.tournaments.send-national-notification', $t), [
                'notification_type' => 'crc_referees',
                'subject' => 'Designazione',
                'message' => 'Testo',
                'send_to_zone' => 1,
            ])
            ->assertSessionHas('success');

        Mail::assertQueued(NationalNotificationMail::class, fn (NationalNotificationMail $m): bool => $m->replyToAddress === null
            && $m->senderName === null
            && $m->hasCc('szr1@federgolf.it'));
    }
}
