<?php

namespace Tests\Feature;

use App\Models\TournamentNotification;
use App\Models\TournamentType;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Gli script scritti dentro le pagine Blade non passano da ESLint: un errore
 * di sintassi (una graffa in piu') fa cadere TUTTO lo script della pagina
 * senza che nessun test PHP se ne accorga. E' successo il 7 ottobre 2026 sul
 * form della notifica zonale: "Invia Ora" salvava solo la bozza.
 *
 * Qui si renderizzano le pagine e si fa controllare la sintassi a node.
 */
class FormScriptsSyntaxTest extends TestCase
{
    private function assertInlineScriptsParse(string $html, string $page): void
    {
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#si', $html, $matches);
        $this->assertNotEmpty($matches[1], "Nessuno script in {$page}");

        $node = trim((string) shell_exec('command -v node'));
        $this->assertNotSame('', $node, 'node non trovato: serve per controllare gli script delle pagine');

        foreach ($matches[1] as $i => $script) {
            $file = tempnam(sys_get_temp_dir(), 'blade-js-').'.js';
            file_put_contents($file, $script);
            $result = Process::run([$node, '--check', $file]);
            @unlink($file);

            $this->assertTrue($result->successful(), "Script #{$i} di {$page} non valido:\n".$result->errorOutput());
        }
    }

    public function test_zonal_notification_form_scripts_parse(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        // Prima apertura (senza allegati) e dopo la creazione degli allegati
        $this->actingAsSuperAdmin();
        $html = (string) $this->get(route('admin.tournaments.show-assignment-form', $tournament))->assertOk()->getContent();
        $this->assertInlineScriptsParse($html, 'form zonale (senza allegati)');

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $this->attachDocumentsTo($notification);
        $html = (string) $this->get(route('admin.tournaments.show-assignment-form', $tournament))->assertOk()->getContent();
        $this->assertInlineScriptsParse($html, 'form zonale (con allegati)');
    }

    public function test_national_notification_forms_scripts_parse(): void
    {
        $tournament = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        foreach (['CRC' => $this->createNationalAdmin(), 'SZR' => $this->createZoneAdmin(1)] as $who => $user) {
            $html = (string) $this->actingAs($user)
                ->get(route('admin.tournaments.show-assignment-form', $tournament))
                ->assertOk()
                ->getContent();
            $this->assertInlineScriptsParse($html, "form nazionale {$who}");
        }
    }

    public function test_notifications_index_scripts_parse(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament(['club_id' => $club->id]);
        TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);

        $html = (string) $this->actingAsSuperAdmin()
            ->get(route('admin.tournament-notifications.index'))
            ->assertOk()
            ->getContent();
        $this->assertInlineScriptsParse($html, 'elenco notifiche');
    }

    /**
     * Tutte le pagine principali (senza parametri): si aprono senza errori e
     * i loro script sono validi. Rete di sicurezza per le pagine non coperte
     * da test dedicati.
     */
    public function test_main_pages_open_and_scripts_parse(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament(['club_id' => $club->id]);
        $referee = $this->createReferee(['zone_id' => 1, 'level' => 'Nazionale']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);

        $adminPages = [
            'admin.dashboard', 'admin.tournaments.index', 'admin.tournaments.calendar', 'admin.tournaments.create',
            'admin.assignments.index', 'admin.assignments.create', 'admin.assignment-validation.index',
            'admin.assignment-validation.conflicts', 'admin.assignment-validation.missing-requirements',
            'admin.assignment-validation.overassigned', 'admin.assignment-validation.underassigned',
            'admin.clubs.index', 'admin.clubs.create', 'admin.users.index', 'admin.users.create',
            'admin.referees.curricula', 'admin.career-history.index', 'admin.career-history.archive-form',
            'admin.statistics.dashboard', 'admin.statistics.arbitri', 'admin.statistics.assegnazioni',
            'admin.statistics.disponibilita', 'admin.statistics.performance', 'admin.statistics.tornei',
            'admin.statistics.zone', 'admin.tournament-notifications.index', 'admin.communications.index',
            'admin.documents.index', 'super-admin.clauses.index', 'super-admin.institutional-emails.index',
            'super-admin.tournament-types.index', 'super-admin.zones.index',
        ];
        $pages = [];
        foreach ($adminPages as $name) {
            $pages[] = [$this->createSuperAdmin(), route($name), $name];
        }
        $pages[] = [$this->createSuperAdmin(), route('admin.tournaments.show', $tournament), 'admin.tournaments.show'];
        $pages[] = [$this->createSuperAdmin(), route('admin.assignments.assign-referees', $tournament), 'admin.assignments.assign-referees'];
        $pages[] = [$this->createSuperAdmin(), route('admin.tournaments.availabilities.index', $tournament), 'admin.tournaments.availabilities.index'];
        foreach (['user.availability.index', 'user.availability.tournaments', 'user.availability.calendar', 'user.assignments.index', 'user.curriculum', 'referee.dashboard', 'tournaments.index'] as $name) {
            $pages[] = [$referee, route($name), $name];
        }
        $pages[] = [$referee, route('tournaments.show', $tournament), 'tournaments.show'];
        // Le stesse pagine principali viste dall'admin di zona e dal CRC
        foreach (['SZR' => $this->createZoneAdmin(1), 'CRC' => $this->createNationalAdmin()] as $who => $admin) {
            foreach (['admin.dashboard', 'admin.tournaments.index', 'admin.tournaments.calendar', 'admin.users.index', 'admin.assignment-validation.index', 'admin.tournament-notifications.index', 'admin.statistics.dashboard'] as $name) {
                $pages[] = [$admin, route($name), "{$name} ({$who})"];
            }
        }

        foreach ($pages as [$user, $url, $name]) {
            $response = $this->actingAs($user)->get($url);
            $this->assertSame(200, $response->getStatusCode(), "{$name} non si apre ({$response->getStatusCode()})");
            $html = (string) $response->getContent();
            if (preg_match('#<script(?![^>]*\bsrc=)[^>]*>#i', $html) === 1) {
                $this->assertInlineScriptsParse($html, $name);
            }
        }
    }

    /**
     * P20: nel form di preparazione si accende Notifiche, non Tornei; la
     * finta "Dashboard SuperAdmin" (apriva le Email Istituzionali) non c'e' piu'.
     */
    public function test_menu_highlights_notifications_on_the_preparation_form(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        $html = (string) $this->actingAsSuperAdmin()
            ->get(route('admin.tournaments.show-assignment-form', $tournament))
            ->assertOk()
            ->assertDontSee('Dashboard SuperAdmin')
            ->getContent();

        $active = 'bg-blue-900';
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admin.tournament-notifications.index'), '#').'"\s+class="[^"]*'.$active.'#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="'.preg_quote(route('admin.tournaments.index'), '#').'"\s+class="[^"]*'.$active.'#', $html);
    }
}
