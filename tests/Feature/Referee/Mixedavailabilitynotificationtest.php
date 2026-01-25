<?php

namespace Tests\Feature\Referee;

use App\Models\Availability;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MixedAvailabilityNotificationTest extends TestCase
{
    /**
     * Test: ISSUE - Notifiche miste zonali/nazionali devono essere separate
     *
     * Un arbitro Nazionale può dichiarare disponibilità sia per tornei zonali
     * che nazionali. Le notifiche devono essere inviate solo ai destinatari
     * competenti:
     * - SZR (zona) riceve SOLO disponibilità per tornei zonali
     * - CRC riceve SOLO disponibilità per tornei nazionali
     *
     * @test
     */
    public function test_mixed_availabilities_send_separate_notifications(): void
    {
        Mail::fake();

        // Arbitro Nazionale in Zona 3
        $referee = $this->createReferee();
        $referee->update(['level' => 'Nazionale', 'zone_id' => 3]);

        // Setup tornei
        $zonalType = TournamentType::where('is_national', false)->first();
        $nationalType = TournamentType::where('is_national', true)->first();

        $clubZone3 = Club::factory()->create(['zone_id' => 3]);

        // Torneo Zonale Zona 3
        $zonalTournament = Tournament::factory()->create([
            'name' => 'Torneo Zonale SZR3',
            'club_id' => $clubZone3->id,
            'tournament_type_id' => $zonalType->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
            'status' => 'open',
        ]);

        // Torneo Nazionale
        $nationalTournament = Tournament::factory()->create([
            'name' => 'Campionato Italiano',
            'club_id' => $clubZone3->id, // Può essere ovunque
            'tournament_type_id' => $nationalType->id,
            'start_date' => now()->addDays(15),
            'end_date' => now()->addDays(17),
            'status' => 'open',
        ]);

        // L'arbitro dichiara disponibilità per ENTRAMBI
        $this->actingAs($referee);

        Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $zonalTournament->id,
            'submitted_at' => now(),
        ]);

        Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $nationalTournament->id,
            'submitted_at' => now(),
        ]);

        // TODO: Trigger notification logic
        // Questo dipende da come gestisci le notifiche di disponibilità
        // event(new AvailabilityDeclared($referee, [$zonalTournament, $nationalTournament]));

        // ASSERT: Verifica che le email siano separate

        // Email a SZR3 deve contenere SOLO torneo zonale
        Mail::assertQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) use ($zonalTournament, $nationalTournament) {
            return $mail->hasTo('szr3@federgolf.it') &&
                   str_contains($mail->render(), $zonalTournament->name) &&
                   !str_contains($mail->render(), $nationalTournament->name);
        });

        // Email a CRC deve contenere SOLO torneo nazionale
        Mail::assertQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) use ($zonalTournament, $nationalTournament) {
            return $mail->hasTo('crc@federgolf.it') &&
                   str_contains($mail->render(), $nationalTournament->name) &&
                   !str_contains($mail->render(), $zonalTournament->name);
        });

        // Email all'arbitro deve contenere ENTRAMBI
        Mail::assertQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) use ($referee, $zonalTournament, $nationalTournament) {
            return $mail->hasTo($referee->email) &&
                   str_contains($mail->render(), $zonalTournament->name) &&
                   str_contains($mail->render(), $nationalTournament->name);
        });
    }

    /**
     * Test: Notifica solo zonale va solo a SZR
     */
    public function test_only_zonal_availability_notifies_only_zone(): void
    {
        Mail::fake();

        $referee = $this->createReferee();
        $referee->update(['level' => 'Nazionale', 'zone_id' => 3]);

        $zonalType = TournamentType::where('is_national', false)->first();
        $clubZone3 = Club::factory()->create(['zone_id' => 3]);

        $zonalTournament = Tournament::factory()->create([
            'club_id' => $clubZone3->id,
            'tournament_type_id' => $zonalType->id,
            'status' => 'open',
        ]);

        Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $zonalTournament->id,
            'submitted_at' => now(),
        ]);

        // TODO: Trigger notification

        // SZR3 riceve email
        Mail::assertQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) {
            return $mail->hasTo('szr3@federgolf.it');
        });

        // CRC NON riceve email
        Mail::assertNotQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) {
            return $mail->hasTo('crc@federgolf.it');
        });
    }

    /**
     * Test: Notifica solo nazionale va solo a CRC
     */
    public function test_only_national_availability_notifies_only_crc(): void
    {
        Mail::fake();

        $referee = $this->createReferee();
        $referee->update(['level' => 'Nazionale', 'zone_id' => 3]);

        $nationalType = TournamentType::where('is_national', true)->first();
        $club = Club::factory()->create();

        $nationalTournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'tournament_type_id' => $nationalType->id,
            'status' => 'open',
        ]);

        Availability::create([
            'user_id' => $referee->id,
            'tournament_id' => $nationalTournament->id,
            'submitted_at' => now(),
        ]);

        // TODO: Trigger notification

        // CRC riceve email
        Mail::assertQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) {
            return $mail->hasTo('crc@federgolf.it');
        });

        // SZR3 NON riceve email
        Mail::assertNotQueued(\Illuminate\Mail\Mailable::class, function (\Illuminate\Mail\Mailable $mail) {
            return $mail->hasTo('szr3@federgolf.it');
        });
    }
}
