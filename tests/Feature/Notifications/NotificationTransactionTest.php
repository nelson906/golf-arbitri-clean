<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\NotificationDocumentService;
use App\Services\NotificationService;
use App\Services\NotificationTransactionService;
use App\Support\Untrusted;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * NotificationTransactionService: salvataggio bozza (con clausole), invio,
 * eliminazione — percorsi normali e di errore.
 *
 * Decisioni confermate (2026-10-15):
 * - le clausole del form sostituiscono le precedenti; un segnaposto vuoto
 *   toglie la clausola che c'era;
 * - una clausola inesistente o disattivata viene scartata, il resto del form
 *   si salva e l'admin vede un avviso;
 * - se l'invio solleva un'eccezione la bozza resta salvata e l'admin vede
 *   errore + "salvata come bozza".
 */
class NotificationTransactionTest extends TestCase
{
    // ============================================================
    // CLAUSOLE NEL FORM ZONALE
    // ============================================================

    public function test_save_with_clauses_stores_selections(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $club = $this->clause('C_CLUB');
        $arb = $this->clause('C_ARB');

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'clauses' => ['CLAUSOLA_CLUB_A' => $club->id, 'CLAUSOLA_ARBITRO_A' => $arb->id],
            ]))
            ->assertRedirect(route('admin.tournaments.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $this->assertSame(
            ['CLAUSOLA_ARBITRO_A' => $arb->id, 'CLAUSOLA_CLUB_A' => $club->id],
            $this->selections($notification)
        );
    }

    public function test_saved_clauses_replace_previous_and_empty_placeholder_removes(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $old1 = $this->clause('C_OLD1');
        $old2 = $this->clause('C_OLD2');
        $new = $this->clause('C_NEW');

        $this->select($notification, 'CLAUSOLA_CLUB_A', $old1);
        $this->select($notification, 'CLAUSOLA_CLUB_B', $old2);

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'clauses' => ['CLAUSOLA_CLUB_A' => $new->id, 'CLAUSOLA_CLUB_B' => ''],
            ]))
            ->assertRedirect(route('admin.tournaments.index'));

        $this->assertSame(['CLAUSOLA_CLUB_A' => $new->id], $this->selections($notification));
    }

    public function test_form_without_clauses_keeps_previous_selections(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $old = $this->clause('C_KEEP');
        $this->select($notification, 'CLAUSOLA_CLUB_A', $old);

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref))
            ->assertRedirect(route('admin.tournaments.index'));

        $this->assertSame(['CLAUSOLA_CLUB_A' => $old->id], $this->selections($notification));
    }

    public function test_missing_clause_is_discarded_rest_is_saved_with_warning(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $valid = $this->clause('C_VALID');

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'subject' => 'Oggetto nuovo',
                'clauses' => ['CLAUSOLA_CLUB_A' => $valid->id, 'CLAUSOLA_CLUB_B' => 999999],
            ]))
            ->assertRedirect(route('admin.tournaments.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $w): bool => str_contains($w, '{CLAUSOLA_CLUB_B}')
                && ! str_contains($w, 'CLAUSOLA_CLUB_A'));

        $notification->refresh();
        $this->assertSame('Oggetto nuovo', $notification->metadata['subject'] ?? null);
        $this->assertTrue((bool) $notification->is_prepared);
        $this->assertSame(['CLAUSOLA_CLUB_A' => $valid->id], $this->selections($notification));
    }

    public function test_inactive_clause_is_discarded_with_warning(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $inactive = $this->clause('C_OFF', active: false);

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'clauses' => ['CLAUSOLA_ARBITRO_A' => $inactive->id],
            ]))
            ->assertRedirect(route('admin.tournaments.index'))
            ->assertSessionHas('warning', fn (string $w): bool => str_contains($w, '{CLAUSOLA_ARBITRO_A}'));

        $this->assertSame([], $this->selections($notification));
    }

    public function test_two_discarded_clauses_use_plural_warning(): void
    {
        [$tournament, $ref] = $this->zonalTournamentWithDraft();
        $inactive = $this->clause('C_OFF2', active: false);

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'clauses' => ['CLAUSOLA_CLUB_A' => 999999, 'CLAUSOLA_CLUB_B' => $inactive->id],
            ]))
            ->assertSessionHas('warning', fn (string $w): bool => str_starts_with($w, 'Clausole per')
                && str_contains($w, '{CLAUSOLA_CLUB_A}')
                && str_contains($w, '{CLAUSOLA_CLUB_B}'));
    }

    public function test_send_with_discarded_clause_still_sends_and_warns(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'action' => 'send',
                'clauses' => ['CLAUSOLA_CLUB_A' => 999999],
            ]))
            ->assertRedirect(route('admin.tournament-notifications.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $w): bool => str_contains($w, '{CLAUSOLA_CLUB_A}'));

        Mail::assertQueued(ClubNotificationMail::class, 1);
        $this->assertSame('sent', $notification->refresh()->status);
    }

    public function test_preview_reports_discarded_clause(): void
    {
        [$tournament, $ref] = $this->zonalTournamentWithDraft();

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'action' => 'preview',
                'clauses' => ['CLAUSOLA_CLUB_A' => 999999],
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('warning', 'Clausola per {CLAUSOLA_CLUB_A} non più disponibile: ignorata.');
    }

    public function test_non_numeric_clause_is_rejected_by_validation(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $form = route('admin.tournaments.show-assignment-form', $tournament);

        $this->actingAsSuperAdmin()
            ->from($form)
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'clauses' => ['CLAUSOLA_CLUB_A' => 'abc'],
            ]))
            ->assertRedirect($form)
            ->assertSessionHasErrors('clauses.CLAUSOLA_CLUB_A');

        $this->assertFalse((bool) $notification->refresh()->is_prepared);
    }

    // ============================================================
    // ERRORI: invio, salvataggio, eliminazione
    // ============================================================

    public function test_send_exception_keeps_draft_and_shows_error(): void
    {
        [$tournament, $ref, $notification] = $this->zonalTournamentWithDraft();
        $form = route('admin.tournaments.show-assignment-form', $tournament);

        $this->partialMock(NotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Server di posta irraggiungibile'));
        });

        $this->actingAsSuperAdmin()
            ->from($form)
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), $this->form($ref, [
                'action' => 'send',
                'subject' => 'Oggetto da salvare',
            ]))
            ->assertRedirect($form)
            ->assertSessionHas('error', fn (string $e): bool => str_contains($e, 'Server di posta irraggiungibile'))
            ->assertSessionHas('warning', 'La notifica è stata salvata come bozza.');

        $notification->refresh();
        $this->assertSame('Oggetto da salvare', $notification->metadata['subject'] ?? null);
        $this->assertTrue((bool) $notification->is_prepared);
        Mail::assertNothingOutgoing();
    }

    public function test_draft_save_is_all_or_nothing_on_database_error(): void
    {
        [, , $notification] = $this->zonalTournamentWithDraft();
        $notification->update(['metadata' => ['subject' => 'Oggetto vecchio'], 'is_prepared' => false]);
        $old = $this->clause('C_PREV');
        $valid = $this->clause('C_OK');
        $this->select($notification, 'CLAUSOLA_CLUB_A', $old);

        // placeholder_code e' varchar(50): 60 caratteri fanno fallire l'insert
        // (MySQL strict) DOPO che metadati e vecchie selezioni sono gia' stati toccati
        $tooLong = str_repeat('X', 60);

        try {
            app(NotificationTransactionService::class)->saveAsDraft(
                $notification,
                ['subject' => 'Oggetto nuovo'],
                [$tooLong => $valid->id]
            );
            $this->fail('Il salvataggio doveva fallire');
        } catch (\Illuminate\Database\QueryException) {
            // atteso
        }

        $notification->refresh();
        $this->assertSame('Oggetto vecchio', $notification->metadata['subject'] ?? null);
        $this->assertFalse((bool) $notification->is_prepared);
        $this->assertSame(['CLAUSOLA_CLUB_A' => $old->id], $this->selections($notification));
    }

    public function test_draft_save_keeps_last_send_outcome(): void
    {
        [, , $notification] = $this->zonalTournamentWithDraft();
        $notification->update(['metadata' => [
            'subject' => 'Vecchio',
            'success_count' => 3,
            'last_error' => 'circolo non raggiungibile',
        ]]);

        app(NotificationTransactionService::class)->saveAsDraft($notification, ['subject' => 'Nuovo']);

        $metadata = $notification->refresh()->metadata;
        $this->assertSame('Nuovo', $metadata['subject'] ?? null);
        $this->assertSame(3, $metadata['success_count'] ?? null);
        $this->assertSame('circolo non raggiungibile', $metadata['last_error'] ?? null);
    }

    public function test_delete_failure_leaves_notification_and_clauses_in_place(): void
    {
        [, , $notification] = $this->zonalTournamentWithDraft();
        $clause = $this->clause('C_DEL');
        $this->select($notification, 'CLAUSOLA_CLUB_A', $clause);

        $this->mock(NotificationDocumentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteAllDocuments')->once()->andThrow(new \RuntimeException('Disco non scrivibile'));
        });

        $service = app(NotificationTransactionService::class);

        try {
            $service->deleteWithCleanup($notification);
            $this->fail("L'eliminazione doveva fallire");
        } catch (\RuntimeException $e) {
            $this->assertSame('Disco non scrivibile', $e->getMessage());
        }

        $this->assertDatabaseHas('tournament_notifications', ['id' => $notification->id]);
        $this->assertSame(['CLAUSOLA_CLUB_A' => $clause->id], $this->selections($notification));
    }

    public function test_destroy_endpoint_shows_error_when_cleanup_fails(): void
    {
        [, , $notification] = $this->zonalTournamentWithDraft();
        $index = route('admin.tournament-notifications.index');

        $this->mock(NotificationDocumentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteAllDocuments')->andThrow(new \RuntimeException('Disco non scrivibile'));
        });

        $this->actingAsSuperAdmin()
            ->from($index)
            ->delete(route('admin.tournament-notifications.destroy', $notification))
            ->assertRedirect($index)
            ->assertSessionHas('error', fn (string $e): bool => str_contains($e, 'Disco non scrivibile'));

        $this->assertDatabaseHas('tournament_notifications', ['id' => $notification->id]);
    }

    // ============================================================
    // HELPER
    // ============================================================

    /**
     * @return array{0: Tournament, 1: User, 2: TournamentNotification}
     */
    private function zonalTournamentWithDraft(): array
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        $draft = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'pending',
            'documents' => [],
        ]);
        $this->attachDocumentsTo($draft);

        return [$tournament, $ref, $draft];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(User $ref, array $overrides = []): array
    {
        return array_merge([
            'action' => 'save',
            'subject' => 'Convocazione',
            'message' => 'Testo.',
            'recipients' => [$ref->id],
            'send_to_club' => 1,
        ], $overrides);
    }

    private function clause(string $code, bool $active = true): NotificationClause
    {
        return NotificationClause::create([
            'code' => $code,
            'category' => 'altro',
            'title' => "Clausola {$code}",
            'content' => 'Contenuto.',
            'applies_to' => 'club',
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }

    private function select(TournamentNotification $notification, string $placeholder, NotificationClause $clause): void
    {
        NotificationClauseSelection::create([
            'tournament_notification_id' => $notification->id,
            'clause_id' => $clause->id,
            'placeholder_code' => $placeholder,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function selections(TournamentNotification $notification): array
    {
        $map = [];

        $rows = NotificationClauseSelection::where('tournament_notification_id', $notification->id)
            ->orderBy('placeholder_code')
            ->get();

        foreach ($rows as $row) {
            $map[Untrusted::string($row->placeholder_code)] = Untrusted::int($row->clause_id);
        }

        return $map;
    }
}
