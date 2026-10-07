<?php

namespace Tests\Feature;

use App\Helpers\ZoneHelper;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notifiche zonali, decisioni di Alberto del 7 ottobre 2026:
 *  - senza circolo o senza la sua email il form non si apre e nulla parte
 *  - gli allegati si creano una volta sola ("Crea allegato") con le clausole;
 *    dopo vale sempre la versione salvata: niente rigenerazione
 *  - "Correggi allegato" = scarica, correggi e ricarica; il file ricaricato
 *    resta legato al suo torneo
 */
class Notifiche20261007Test extends TestCase
{
    /** @var list<string> */
    private array $uploaded = [];

    protected function tearDown(): void
    {
        $disk = Storage::disk(Config::string('golf.documents.disk', 'docs'));
        foreach ($this->uploaded as $path) {
            $disk->delete($path);
        }
        parent::tearDown();
    }

    private function zonalTournament(?string $clubEmail = 'circolo@example.test', bool $withClub = true): Tournament
    {
        $attributes = ['tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id];
        if ($withClub) {
            $attributes['club_id'] = $this->createClub(['zone_id' => 1, 'email' => $clubEmail ?? ''])->id;
        }
        $tournament = $this->createTournament($attributes);
        if (! $withClub) {
            // Torneo T.B.A.: circolo non ancora scelto
            $tournament->forceFill(['club_id' => null, 'zone_id' => 1])->save();
            $tournament->unsetRelation('club');
        }
        $referee = $this->createReferee(['zone_id' => 1]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);

        return $tournament;
    }

    public function test_form_does_not_open_without_club_or_club_email(): void
    {
        $this->actingAsSuperAdmin();

        foreach ([$this->zonalTournament(''), $this->zonalTournament(null, false)] as $tournament) {
            $this->get(route('admin.tournaments.show-assignment-form', $tournament))
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertDatabaseMissing('tournament_notifications', ['tournament_id' => $tournament->id]);
        }
    }

    public function test_opening_the_form_does_not_create_attachments(): void
    {
        $tournament = $this->zonalTournament();

        $this->actingAsSuperAdmin()
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('Crea allegati con le clausole selezionate');

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertEmpty($notification->documents);
    }

    public function test_saving_the_draft_keeps_the_saved_attachments(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'pending',
        ]);
        $this->attachDocumentsTo($notification);
        $before = $notification->fresh()?->documents;

        $this->actingAsSuperAdmin()
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
                'action' => 'save',
                'subject' => 'Bozza',
                'message' => 'Testo',
            ])
            ->assertRedirect();

        $this->assertSame($before, $notification->fresh()?->documents);
    }

    public function test_existing_attachment_is_not_regenerated(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'pending',
        ]);
        $this->attachDocumentsTo($notification);

        $this->actingAsSuperAdmin()
            ->postJson(route('admin.tournament-notifications.generate-document', [$notification, 'convocation']))
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_uploaded_attachment_belongs_to_its_tournament(): void
    {
        $this->actingAsSuperAdmin();
        $names = [];

        foreach ([$this->zonalTournament(), $this->zonalTournament()] as $tournament) {
            $notification = TournamentNotification::create([
                'tournament_id' => $tournament->id,
                'notification_type' => null,
                'status' => 'pending',
            ]);

            // Stesso nome di file scaricato per entrambi
            $file = UploadedFile::fake()->create('Convocazione.docx', 10);
            $this->post(route('admin.tournament-notifications.upload-document', [$notification, 'convocation']), [
                'document' => $file,
            ])->assertOk();

            $saved = $notification->fresh()?->documents['convocation'] ?? null;
            $this->assertSame("convocazione_{$tournament->id}_corretta.docx", $saved);
            $names[] = $saved;

            $zone = ZoneHelper::getFolderCodeForTournament($tournament);
            $this->uploaded[] = Config::string('golf.documents.storage_path', 'convocazioni')."/{$zone}/generated/{$saved}";
        }

        $this->assertNotSame($names[0], $names[1]);
    }

    public function test_each_institutional_address_appears_once_in_the_form(): void
    {
        $tournament = $this->zonalTournament();
        $email = \App\Models\InstitutionalEmail::create([
            'name' => 'Ufficio Convocazioni',
            'email' => 'convocazioni@example.test',
            'category' => 'convocazioni',
            'is_active' => true,
        ]);

        $html = $this->actingAsSuperAdmin()
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $count = preg_match_all('/name="fixed_addresses\[\]"\s+value="'.$email->id.'"/', $html);
        $this->assertSame(1, $count);
    }

    // ── Notifiche non inviate, ben visibili ─────────────────────────────────

    private function mailServerRefuses(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->andThrow(new \RuntimeException('550 indirizzo rifiutato'));
    }

    public function test_zonal_send_refused_by_mail_server_is_not_sent_and_shown(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'pending',
        ]);
        $this->attachDocumentsTo($notification);
        $this->mailServerRefuses();

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin)
            ->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
                'action' => 'send',
                'subject' => 'Convocazione',
                'message' => 'Testo',
            ])
            ->assertSessionHas('error');

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
        $this->assertNull($notification->sent_at);
        $this->assertNotNull($notification->lastAttemptAt());
        $this->assertStringContainsString('550', (string) $notification->lastError());

        // In vista: elenco notifiche, form e dashboard
        $this->actingAs($admin)->get(route('admin.tournament-notifications.index'))
            ->assertOk()->assertSee('1 notifica NON inviata');
        $this->actingAs($admin)->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()->assertSee('Notifica NON inviata.')->assertDontSee('Notifica già inviata');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('1 notifica NON inviata');
    }

    public function test_failed_resend_keeps_date_of_last_successful_send(): void
    {
        $tournament = $this->zonalTournament();
        $sentAt = now()->subDays(3)->startOfMinute();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'notification_type' => null,
            'status' => 'sent',
            'sent_at' => $sentAt,
            'metadata' => ['subject' => 'x', 'message' => 'y', 'recipients' => ['club' => true, 'referees' => []]],
        ]);
        $this->attachDocumentsTo($notification);
        $this->mailServerRefuses();

        try {
            app(\App\Services\NotificationService::class)->send($notification->refresh());
        } catch (\Throwable) {
            // l'errore del server di posta e' gestito dentro send()
        }

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
        $this->assertSame($sentAt->toDateTimeString(), $notification->sent_at?->toDateTimeString());
    }

    public function test_national_send_refused_by_mail_server_is_not_sent_and_shown(): void
    {
        $tournament = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);
        $this->mailServerRefuses();

        $crc = $this->createNationalAdmin();
        $this->actingAs($crc)
            ->post(route('admin.tournaments.send-national-notification', $tournament), [
                'notification_type' => 'crc_referees',
                'subject' => 'Designazione',
                'message' => 'Testo',
                'send_to_campionati' => 1,
            ])
            ->assertSessionHas('error');

        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->where('notification_type', 'crc_referees')->firstOrFail();
        $this->assertSame('failed', $notification->status);
        $this->assertNull($notification->sent_at);

        // La SZR della zona vede "NON inviata", non "Inviata"
        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('Arbitri (CRC): NON inviata')
            ->assertDontSee('Arbitri (CRC): Inviata');
    }
}
