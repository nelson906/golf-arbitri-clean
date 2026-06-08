<?php

namespace Tests\Feature\Admin;

use App\Mail\ClubNotificationMail;
use App\Mail\RefereeAssignmentMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Copertura end-to-end (HTTP) di NotificationController::sendAssignmentWithConvocation(),
 * il punto d'ingresso del form di preparazione notifica zonale. Copre le tre azioni:
 *   - preview → JSON con anteprima email
 *   - save    → salva la bozza (metadata + is_prepared) senza inviare
 *   - send    → invia a circolo + arbitri e reindirizza alla lista notifiche
 *
 * documents non vengono forzati: getClub/RefereeAttachments ritornano [] se i file
 * non esistono, quindi nessuna dipendenza dal filesystem.
 */
class SendAssignmentWithConvocationHttpTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    /**
     * @return array{0: Tournament, 1: User}
     */
    private function setupZonalTournamentWithDraft(): array
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);
        $ref = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $ref->id]);

        // Il controller fa firstOrFail() sulla notifica del torneo.
        TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'documents'         => [],
        ]);

        return [$tournament, $ref];
    }

    public function test_preview_action_returns_json_preview(): void
    {
        Mail::fake();
        [$tournament, $ref] = $this->setupZonalTournamentWithDraft();

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-assignment-with-convocation', $tournament),
            [
                'action'        => 'preview',
                'subject'       => 'Anteprima Convocazione',
                'message'       => 'Testo anteprima.',
                'recipients'    => [$ref->id],
                'send_to_club'  => 1,
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('preview.subject', 'Anteprima Convocazione');

        Mail::assertNothingSent();
    }

    public function test_save_action_persists_draft_without_sending(): void
    {
        Mail::fake();
        [$tournament, $ref] = $this->setupZonalTournamentWithDraft();

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-assignment-with-convocation', $tournament),
            [
                'action'        => 'save',
                'subject'       => 'Bozza Convocazione',
                'message'       => 'Testo bozza.',
                'recipients'    => [$ref->id],
                'send_to_club'  => 1,
            ]
        );

        $response->assertRedirect(route('admin.tournaments.index'));
        $response->assertSessionHas('success');

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertEquals('Bozza Convocazione', $notification->metadata['subject'] ?? null);
        $this->assertTrue((bool) $notification->is_prepared);

        Mail::assertNothingSent();
    }

    public function test_send_action_dispatches_and_redirects_to_index(): void
    {
        Mail::fake();
        [$tournament, $ref] = $this->setupZonalTournamentWithDraft();

        $this->actingAsSuperAdmin();

        $response = $this->post(
            route('admin.tournaments.send-assignment-with-convocation', $tournament),
            [
                'action'        => 'send',
                'subject'       => 'Invio Convocazione',
                'message'       => 'Testo invio.',
                'recipients'    => [$ref->id],
                'send_to_club'  => 1,
            ]
        );

        $response->assertRedirect(route('admin.tournament-notifications.index'));

        Mail::assertSent(ClubNotificationMail::class, fn ($mail) => $mail->hasTo('circolo@example.test'));
        Mail::assertSent(RefereeAssignmentMail::class, fn ($mail) => $mail->hasTo('arbitro@example.test'));
    }
}
