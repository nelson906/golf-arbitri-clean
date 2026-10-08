<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\FigNotificationMarker;
use Tests\TestCase;

/**
 * Decisione 2026-10-08: un comitato pubblicato da FIG vuol dire convocazioni
 * gia' fatte. Ogni caricamento da FIG segna il torneo come notificato.
 */
class FigNotificationMarkerTest extends TestCase
{
    private function tournament(bool $national): Tournament
    {
        return $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test'])->id,
            'tournament_type_id' => TournamentType::where('is_national', $national)->firstOrFail()->id,
        ]);
    }

    public function test_guided_import_marks_the_tournament_as_notified(): void
    {
        config(['golf.fig.import_email' => 'importatore@example.test']);
        $importer = $this->createSuperAdmin(['email' => 'importatore@example.test']);

        foreach ([true => 'crc_referees', false => null] as $national => $type) {
            $tournament = $this->tournament((bool) $national);
            $arbitro = $this->createReferee(['zone_id' => 1, 'name' => 'Arbitro '.($national ? 'Naz' : 'Zon')]);

            $this->actingAs($importer)->postJson(route('admin.federgolf-import.execute'), [
                'tournament_id' => $tournament->id,
                'assegnazioni' => [['user_id' => $arbitro->id, 'ruolo' => 'Arbitro']],
            ])->assertOk()->assertJson(['success' => true]);

            $notifica = TournamentNotification::where('tournament_id', $tournament->id)->sole();
            $this->assertSame($type, $notifica->notification_type);
            $this->assertSame('sent', $notifica->status);
            $this->assertSame($arbitro->name, $notifica->referee_list);
            $this->assertTrue((bool) ($notifica->metadata['fig'] ?? false));
        }
    }

    public function test_a_draft_becomes_sent_without_a_second_notification(): void
    {
        $tournament = $this->tournament(false);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);
        TournamentNotification::where('tournament_id', $tournament->id)->delete();
        $bozza = TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);

        $esito = app(FigNotificationMarker::class)->mark($tournament, 'Importato da federgolf.it');

        $this->assertSame(FigNotificationMarker::UPDATED, $esito);
        $this->assertSame(1, TournamentNotification::where('tournament_id', $tournament->id)->count());
        $this->assertSame('sent', TournamentNotification::findOrFail($bozza->id)->status);
    }

    public function test_an_already_sent_notification_is_left_alone(): void
    {
        $tournament = $this->tournament(true);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);
        $inviata = TournamentNotification::create([
            'tournament_id' => $tournament->id, 'notification_type' => 'crc_referees',
            'status' => 'sent', 'sent_at' => now()->subDays(3), 'metadata' => ['source' => 'form'],
        ]);

        $this->assertSame(FigNotificationMarker::SKIPPED, app(FigNotificationMarker::class)->mark($tournament, 'Importato da federgolf.it'));
        $this->assertSame(['source' => 'form'], TournamentNotification::findOrFail($inviata->id)->metadata);
        $this->assertSame(1, TournamentNotification::where('tournament_id', $tournament->id)->count());
    }

    public function test_tournament_without_referees_is_not_marked(): void
    {
        $tournament = $this->tournament(true);

        $this->assertSame(FigNotificationMarker::SKIPPED, app(FigNotificationMarker::class)->mark($tournament, 'x'));
        $this->assertSame(0, TournamentNotification::where('tournament_id', $tournament->id)->count());
    }

    /** Il comando per i tornei caricati prima prende anche l'import guidato. */
    public function test_mark_notified_command_includes_guided_import_assignments(): void
    {
        $tournament = $this->tournament(true);
        $arbitro = $this->createReferee(['zone_id' => 1]);
        Assignment::create([
            'tournament_id' => $tournament->id, 'user_id' => $arbitro->id, 'role' => 'Arbitro',
            'assigned_by' => $arbitro->id, 'assigned_at' => now(), 'notes' => 'Importato da federgolf.it',
        ]);

        $this->artisanCommand('federgolf:mark-notified', ['--anno' => $tournament->start_date->year])->assertExitCode(0);

        $this->assertSame('sent', TournamentNotification::where('tournament_id', $tournament->id)->sole()->status);
    }
}
