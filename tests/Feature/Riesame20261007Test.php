<?php

namespace Tests\Feature;

use App\Mail\ClubNotificationMail;
use App\Mail\NationalNotificationMail;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Secondo giro di controllo sulle notifiche (7 ottobre 2026, sera).
 */
class Riesame20261007Test extends TestCase
{
    private function zonalTournament(string $clubEmail = 'circolo@example.test'): \App\Models\Tournament
    {
        $tournament = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1, 'email' => $clubEmail])->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1, 'name' => 'Mario Primo'])->id]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1, 'name' => 'Luca Secondo'])->id]);

        return $tournament;
    }

    private function nationalTournament(): \App\Models\Tournament
    {
        return $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
    }

    public function test_saving_after_a_failed_send_keeps_the_reason(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'failed',
            'metadata' => ['last_error' => '550 rifiutato', 'last_attempt_at' => now()->toDateTimeString()],
        ]);

        $this->actingAsSuperAdmin()->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
            'action' => 'save', 'subject' => 'Oggetto', 'message' => 'Testo',
        ])->assertRedirect();

        $this->assertSame('550 rifiutato', $notification->fresh()?->lastError());
    }

    public function test_form_proposes_saved_choices_again(): void
    {
        $tournament = $this->zonalTournament();
        TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);
        $keep = $tournament->assignments()->firstOrFail()->user_id;
        $this->actingAsSuperAdmin();

        $this->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
            'action' => 'save',
            'subject' => 'Oggetto salvato',
            'message' => 'Messaggio salvato',
            'recipients' => [$keep],
            'additional_emails' => ['extra@example.test'],
            'additional_names' => ['Extra'],
        ])->assertRedirect();

        $html = (string) $this->get(route('admin.tournaments.show-assignment-form', $tournament))->assertOk()->getContent();
        $this->assertStringContainsString('value="Oggetto salvato"', $html);
        $this->assertStringContainsString('Messaggio salvato', $html);
        $this->assertStringContainsString('value="extra@example.test"', $html);
        $excluded = $tournament->assignments()->where('user_id', '!=', $keep)->firstOrFail()->user_id;
        $this->assertMatchesRegularExpression('/id="referee_'.$keep.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="referee_'.$excluded.'"\s+checked/', $html);
    }

    public function test_club_email_with_spaces_still_receives_the_mail(): void
    {
        Mail::fake();
        $tournament = $this->zonalTournament('info@circolo.test ');
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'metadata' => ['subject' => 's', 'message' => 'm', 'recipients' => ['club' => true, 'referees' => $tournament->assignments()->pluck('user_id')->all()]],
        ]);
        $this->attachDocumentsTo($notification);

        app(NotificationService::class)->send($notification->refresh());

        Mail::assertQueued(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('info@circolo.test'));
    }

    public function test_super_admin_can_open_the_observers_form(): void
    {
        $tournament = $this->nationalTournament();
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        $this->actingAsSuperAdmin()
            ->get(route('admin.tournaments.show-assignment-form', [$tournament, 'comunicazione' => 'zone_observers']))
            ->assertOk()
            ->assertSee('value="zone_observers"', false);
    }

    public function test_each_side_deletes_only_its_own_national_communication(): void
    {
        $tournament = $this->nationalTournament();
        $crc = TournamentNotification::create(['tournament_id' => $tournament->id, 'notification_type' => 'crc_referees', 'status' => 'sent', 'sent_at' => now()]);
        $zone = TournamentNotification::create(['tournament_id' => $tournament->id, 'notification_type' => 'zone_observers', 'status' => 'sent', 'sent_at' => now()]);
        $szr = $this->createZoneAdmin(1);

        $this->actingAs($szr)->delete(route('admin.tournament-notifications.destroy', $crc))->assertSessionHas('error');
        $this->assertDatabaseHas('tournament_notifications', ['id' => $crc->id]);

        $this->actingAs($szr)->delete(route('admin.tournament-notifications.destroy-tournament', $tournament));
        $this->assertDatabaseHas('tournament_notifications', ['id' => $crc->id]);
        $this->assertDatabaseMissing('tournament_notifications', ['id' => $zone->id]);
    }

    public function test_observers_communication_comes_from_the_zone(): void
    {
        Mail::fake();
        $tournament = $this->nationalTournament();
        $observer = $this->createReferee(['zone_id' => 1, 'name' => 'Oscar Osservatore']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $observer->id, 'role' => 'Osservatore']);
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Arturo Arbitro']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id, 'role' => 'Arbitro']);

        $this->actingAs($this->createZoneAdmin(1))->post(route('admin.tournaments.send-national-notification', $tournament), [
            'notification_type' => 'zone_observers',
            'subject' => 'Osservatori',
            'message' => 'Testo',
            'cc_observers' => [$observer->id],
        ])->assertSessionHas('success');

        Mail::assertQueued(NationalNotificationMail::class, fn ($mail) => str_starts_with((string) $mail->senderName, 'SZR1'));

        // Elenco della comunicazione osservatori: solo gli osservatori
        $record = TournamentNotification::where('tournament_id', $tournament->id)->where('notification_type', 'zone_observers')->firstOrFail();
        $this->assertSame('Oscar Osservatore', $record->referee_list);
    }

    public function test_failed_national_resend_keeps_the_previous_send(): void
    {
        $tournament = $this->nationalTournament();
        $crc = $this->createNationalAdmin();
        $record = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => 'crc_referees',
            'status' => 'sent',
            'sent_at' => now()->subDay(),
            'sent_by' => $crc->id,
            'referee_list' => 'Elenco del primo invio',
            'metadata' => ['success_count' => 5],
        ]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('550 rifiutato'));

        $this->actingAs($this->createSuperAdmin())->post(route('admin.tournaments.send-national-notification', $tournament), [
            'notification_type' => 'crc_referees', 'subject' => 's', 'message' => 'm',
        ])->assertSessionHas('error');

        $record->refresh();
        $this->assertSame('failed', $record->status);
        $this->assertSame($crc->id, $record->sent_by);
        $this->assertSame('Elenco del primo invio', $record->referee_list);
        $this->assertSame(5, $record->recipientsReached());
        $this->assertStringContainsString('550', (string) $record->lastError());
    }

    public function test_admin_layout_shows_warnings_and_info(): void
    {
        $this->actingAsSuperAdmin()
            ->withSession(['warning' => 'Avviso di prova', 'info' => 'Informazione di prova'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Avviso di prova')
            ->assertSee('Informazione di prova');
    }

    public function test_mail_lists_the_attachments_it_really_carries(): void
    {
        $tournament = $this->zonalTournament();
        $mail = new ClubNotificationMail($tournament, 'Testo', [['path' => '/tmp/x.docx', 'name' => 'Lettera_Circolo.docx']]);

        $html = $mail->render();
        $this->assertStringContainsString('Lettera al circolo', $html);
        $this->assertStringNotContainsString('Convocazione in formato Word', $html);
    }
}
