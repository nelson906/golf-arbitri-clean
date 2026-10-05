<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationRecipientBuilder;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Decisioni del 4 ottobre 2026 sui punti minori dell'audit comunicazioni:
 * casella «Allega convocazione», una sola copia per indirizzo, ritorno
 * sicuro all'arbitro sul riepilogo disponibilita'.
 */
class Decisioni20261004Test extends TestCase
{
    private const CHECKED_CONVOCATION = '/id="attach_convocation" value="1"\s+checked/';

    private function zonalTournament(): Tournament
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);

        return $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
            'start_date' => now()->addDays(30)->startOfDay(),
            'end_date' => now()->addDays(31)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);
    }

    // ── Allegato convocazione ───────────────────────────────────────────────

    public function test_zonal_form_shows_attach_convocation_checkbox_checked_by_default(): void
    {
        Mail::fake();
        $tournament = $this->zonalTournament();
        $referee = $this->createReferee(['zone_id' => 1]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);

        $html = (string) $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertSee('Allega convocazione')
            ->assertSee('<input type="hidden" name="attach_convocation" value="0">', false)
            ->getContent();

        $this->assertMatchesRegularExpression(self::CHECKED_CONVOCATION, $html);
    }

    public function test_unchecked_attach_convocation_is_saved_as_false(): void
    {
        Mail::fake();
        $tournament = $this->zonalTournament();
        $referee = $this->createReferee(['zone_id' => 1]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);
        $admin = $this->createZoneAdmin(1);

        $this->actingAs($admin)->get(route('admin.tournaments.show-assignment-form', $tournament))->assertOk();

        $this->actingAs($admin)->post(route('admin.tournaments.send-assignment-with-convocation', $tournament), [
            'action' => 'save',
            'subject' => 'Designazione',
            'message' => 'Testo',
            'recipients' => [$referee->id],
            'send_to_club' => 1,
            'attach_convocation' => 0,
        ]);

        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->whereNull('notification_type')->firstOrFail();
        $this->assertFalse($notification->metadata['attach_convocation'] ?? null);

        // Riaprendo il form la casella resta tolta
        $html = (string) $this->actingAs($admin)->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(self::CHECKED_CONVOCATION, $html);
    }

    // ── Una sola copia per indirizzo ────────────────────────────────────────

    public function test_club_address_repeated_in_cc_is_sent_once_as_main_recipient(): void
    {
        $tournament = $this->zonalTournament()->load('club');

        $built = (new NotificationRecipientBuilder)
            ->addClub($tournament)
            ->addCustomCc('CIRCOLO@example.test', 'Circolo ripetuto')
            ->addCustomCc('altro@example.test')
            ->build();

        $this->assertSame(['circolo@example.test'], array_column($built['to'], 'email'));
        $this->assertSame(['altro@example.test'], array_column($built['cc'], 'email'));
        $this->assertSame(2, $built['total']);
    }

    public function test_address_already_in_cc_moves_to_main_recipient(): void
    {
        $tournament = $this->zonalTournament()->load('club');

        $built = (new NotificationRecipientBuilder)
            ->addCustomCc('circolo@example.test')
            ->addClub($tournament)
            ->build();

        $this->assertSame(['circolo@example.test'], array_column($built['to'], 'email'));
        $this->assertSame([], $built['cc']);
    }

    // ── Ritorno sicuro all'arbitro ──────────────────────────────────────────

    public function test_referee_sees_warning_when_summary_email_fails(): void
    {
        $tournament = $this->zonalTournament();
        $referee = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP non raggiungibile'));

        $response = $this->actingAs($referee)->post(route('user.availability.saveBatch'), [
            'availabilities' => [$tournament->id],
            'page_tournaments' => [$tournament->id],
        ]);

        $response->assertSessionHas('success')
            ->assertSessionHas('warning', fn ($w) => is_string($w) && str_contains($w, 'non è partita'));
        $this->assertTrue(Availability::where('user_id', $referee->id)->where('tournament_id', $tournament->id)->exists());

        $this->get(route('user.availability.index'))->assertSee('non è partita');
    }

    public function test_referee_sees_only_success_when_summary_email_leaves(): void
    {
        Mail::fake();
        $tournament = $this->zonalTournament();
        $referee = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);

        $response = $this->actingAs($referee)->post(route('user.availability.saveBatch'), [
            'availabilities' => [$tournament->id],
            'page_tournaments' => [$tournament->id],
        ]);

        $response->assertSessionHas('success')->assertSessionMissing('warning');
        $this->get(route('user.availability.index'))->assertSee('Disponibilità aggiornate con successo');
    }

    public function test_single_declaration_reports_failed_summary_in_json(): void
    {
        $tournament = $this->zonalTournament();
        $referee = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro@example.test']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP non raggiungibile'));

        $this->actingAs($referee)->postJson(route('user.availability.store'), [
            'tournament_id' => $tournament->id,
            'available' => 1,
        ])->assertOk()
            ->assertJson(['success' => true, 'email_sent' => false]);
    }
}
