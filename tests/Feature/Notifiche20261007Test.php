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
}
