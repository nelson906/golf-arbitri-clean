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

    /**
     * Lo script SQL per Aruba (database/sql/fig-segna-notificati.sql) fa
     * quello che fa il comando, senza artisan.
     */
    public function test_sql_script_marks_fig_tournaments(): void
    {
        $admin = $this->createSuperAdmin();
        $fig = fn (Tournament $t, string $role = 'Arbitro', ?string $name = null) => Assignment::create([
            'tournament_id' => $t->id,
            'user_id' => $this->createReferee(['zone_id' => 1] + ($name ? ['name' => $name] : []))->id,
            'role' => $role, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'notes' => 'Importato da federgolf.it',
        ]);

        $nazionale = $this->tournament(true);
        $fig($nazionale, 'Arbitro', 'Anna Arbitro');
        $fig($nazionale, 'Osservatore', 'Oscar Osservatore');

        $zonaleBozza = $this->tournament(false);
        $fig($zonaleBozza);
        TournamentNotification::where('tournament_id', $zonaleBozza->id)->delete();
        $bozza = TournamentNotification::create(['tournament_id' => $zonaleBozza->id, 'status' => 'pending']);

        $giaInviato = $this->tournament(true);
        $fig($giaInviato);
        $inviata = TournamentNotification::create(['tournament_id' => $giaInviato->id, 'notification_type' => 'crc_referees',
            'status' => 'sent', 'metadata' => ['source' => 'form']]);

        $aMano = $this->tournament(true);
        $this->createAssignment(['tournament_id' => $aMano->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        $sql = (string) file_get_contents(database_path('sql/fig-segna-notificati.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql) ?: [])) as $statement) {
            $statement = trim((string) preg_replace('/^--.*$/m', '', $statement));
            if ($statement !== '') {
                \Illuminate\Support\Facades\DB::unprepared($statement);
            }
        }

        $n = TournamentNotification::where('tournament_id', $nazionale->id)->sole();
        $this->assertSame(['crc_referees', 'sent', 'Anna Arbitro'], [$n->notification_type, $n->status, $n->referee_list]);
        $this->assertTrue((bool) ($n->metadata['fig'] ?? false));

        $this->assertSame('sent', TournamentNotification::findOrFail($bozza->id)->status);
        $this->assertSame(1, TournamentNotification::where('tournament_id', $zonaleBozza->id)->count());

        $this->assertSame(['source' => 'form'], TournamentNotification::findOrFail($inviata->id)->metadata);
        $this->assertSame(1, TournamentNotification::where('tournament_id', $giaInviato->id)->count());

        $this->assertSame(0, TournamentNotification::where('tournament_id', $aMano->id)->count());
    }
}
