<?php

namespace Tests\Feature\Console;

use App\Models\RefereeCareerHistory;
use App\Models\Tournament;
use App\Models\User;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Comando `career:manage` (show / add / remove): correzioni allo storico
 * carriera di un arbitro da riga di comando.
 */
class ManageCareerEntryTest extends TestCase
{
    use CollectsIds;

    /**
     * Righe dell'anno dei test in una colonna dello storico.
     *
     * @param  array<array-key, mixed>|null  $byYear
     * @return list<array<array-key, mixed>>
     */
    private function rowsOfYear(?array $byYear): array
    {
        return $this->jsonRows(($byYear ?? [])[$this->year()] ?? []);
    }

    private function year(): int
    {
        return now()->year;
    }

    private function referee(): User
    {
        return $this->createReferee(['zone_id' => 1, 'name' => 'Rita Storico', 'email' => 'rita.storico@example.test']);
    }

    private function tournament(string $name = 'Coppa Console'): Tournament
    {
        return $this->createTournament(['name' => $name]);
    }

    public function test_invalid_action(): void
    {
        $this->artisanCommand('career:manage', ['action' => 'boh'])
            ->expectsOutput("Azione 'boh' non valida. Usa: show, add, remove")
            ->assertExitCode(1);
    }

    public function test_user_is_required_and_must_exist(): void
    {
        $this->artisanCommand('career:manage', ['action' => 'show'])
            ->expectsOutput('Devi specificare --user (ID o email)')
            ->assertExitCode(1);

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => 'nessuno@example.test'])
            ->expectsOutput("Utente 'nessuno@example.test' non trovato")
            ->assertExitCode(1);

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => '999999'])
            ->expectsOutput("Utente '999999' non trovato")
            ->assertExitCode(1);
    }

    public function test_show_without_history(): void
    {
        $r = $this->referee();

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => $r->email])
            ->expectsOutput('Nessun career history trovato per Rita Storico')
            ->assertExitCode(0);
    }

    public function test_add_then_show_summary_and_year(): void
    {
        $r = $this->referee();
        $t = $this->tournament();

        $this->artisanCommand('career:manage', [
            'action' => 'add',
            '--user' => (string) $r->id,
            '--year' => (string) $this->year(),
            '--tournament' => (string) $t->id,
            '--role' => 'Direttore di Torneo',
        ])
            ->expectsOutput("Torneo 'Coppa Console' aggiunto allo storico {$this->year()} di Rita Storico")
            ->assertExitCode(0);

        $history = RefereeCareerHistory::where('user_id', $r->id)->firstOrFail();
        $this->assertSame($t->id, $this->arrayAt($this->rowsOfYear($history->tournaments_by_year), 0)['id'] ?? null);
        $this->assertSame('Direttore di Torneo', $this->arrayAt($this->rowsOfYear($history->assignments_by_year), 0)['role'] ?? null);

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => $r->email])
            ->expectsOutput('=== Career History: Rita Storico ===')
            ->expectsOutput('Anni disponibili:')
            ->expectsTable(['Anno', 'Tornei', 'Assegnazioni', 'Disponibilità'], [[$this->year(), 1, 1, 0]])
            ->assertExitCode(0);

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => $r->email, '--year' => (string) $this->year()])
            ->expectsOutput("Tornei {$this->year()}:")
            ->expectsOutput("Assegnazioni {$this->year()}:")
            ->assertExitCode(0);
    }

    public function test_show_year_without_data(): void
    {
        $r = $this->referee();
        $this->artisanCommand('career:manage', [
            'action' => 'add',
            '--user' => $r->email,
            '--year' => (string) $this->year(),
            '--tournament' => (string) $this->tournament()->id,
        ])->assertExitCode(0);

        $this->artisanCommand('career:manage', ['action' => 'show', '--user' => $r->email, '--year' => '1999'])
            ->expectsOutput("Nessun dato per l'anno 1999")
            ->assertExitCode(0);
    }

    public function test_add_without_role_adds_no_assignment(): void
    {
        $r = $this->referee();

        $this->artisanCommand('career:manage', [
            'action' => 'add',
            '--user' => $r->email,
            '--year' => (string) $this->year(),
            '--tournament' => (string) $this->tournament()->id,
        ])->assertExitCode(0);

        $history = RefereeCareerHistory::where('user_id', $r->id)->firstOrFail();
        $this->assertCount(1, $this->rowsOfYear($history->tournaments_by_year));
        $this->assertSame([], $this->rowsOfYear($history->assignments_by_year));
    }

    public function test_add_needs_year_and_existing_tournament(): void
    {
        $r = $this->referee();

        $this->artisanCommand('career:manage', ['action' => 'add', '--user' => $r->email, '--tournament' => '1'])
            ->expectsOutput('Devi specificare --year e --tournament')
            ->assertExitCode(1);

        $this->artisanCommand('career:manage', ['action' => 'add', '--user' => $r->email, '--year' => '2025', '--tournament' => '999999'])
            ->expectsOutput('Torneo ID 999999 non trovato')
            ->assertExitCode(1);

        $this->assertNull(RefereeCareerHistory::where('user_id', $r->id)->first());
    }

    public function test_remove(): void
    {
        $r = $this->referee();
        $t = $this->tournament();
        $this->artisanCommand('career:manage', [
            'action' => 'add',
            '--user' => $r->email,
            '--year' => (string) $this->year(),
            '--tournament' => (string) $t->id,
        ])->assertExitCode(0);

        $this->artisanCommand('career:manage', ['action' => 'remove', '--user' => $r->email, '--year' => (string) $this->year()])
            ->expectsOutput('Devi specificare --year e --tournament')
            ->assertExitCode(1);

        $this->artisanCommand('career:manage', [
            'action' => 'remove',
            '--user' => $r->email,
            '--year' => (string) $this->year(),
            '--tournament' => (string) $t->id,
        ])
            ->expectsOutput("Torneo ID {$t->id} rimosso dallo storico {$this->year()} di Rita Storico")
            ->assertExitCode(0);

        $history = RefereeCareerHistory::where('user_id', $r->id)->firstOrFail();
        $this->assertSame([], $this->rowsOfYear($history->tournaments_by_year));
    }

    public function test_remove_from_year_not_in_history_warns(): void
    {
        $r = $this->referee();

        $this->artisanCommand('career:manage', [
            'action' => 'remove',
            '--user' => $r->email,
            '--year' => '1999',
            '--tournament' => '1',
        ])
            ->expectsOutput('Torneo non trovato nello storico')
            ->assertExitCode(0);
    }

    public function test_user_without_user_resolution_stops_add_and_remove(): void
    {
        $this->artisanCommand('career:manage', ['action' => 'add'])->assertExitCode(1);
        $this->artisanCommand('career:manage', ['action' => 'remove'])->assertExitCode(1);
    }
}
