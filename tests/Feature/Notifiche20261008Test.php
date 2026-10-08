<?php

namespace Tests\Feature;

use App\Models\TournamentNotification;
use App\Models\TournamentType;
use Tests\TestCase;

/**
 * Decisioni dell'8 ottobre 2026 sulla pagina Notifiche:
 * - elenca tutti i tornei con arbitri designati, anche mai notificati;
 * - filtro per mese al posto del filtro per tipo;
 * - niente targhetta SZR.
 */
class Notifiche20261008Test extends TestCase
{
    public function test_tournaments_with_referees_but_no_notification_are_listed(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $zonal = $this->createTournament([
            'club_id' => $club->id, 'name' => 'Zonale Mai Inviato',
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $national = $this->createTournament([
            'club_id' => $club->id, 'name' => 'Nazionale Caricato Da FIG',
            'tournament_type_id' => TournamentType::where('is_national', true)->firstOrFail()->id,
        ]);
        $empty = $this->createTournament(['club_id' => $club->id, 'name' => 'Senza Arbitri']);
        $referee = $this->createReferee(['zone_id' => 1, 'name' => 'Mario Designato']);
        foreach ([$zonal, $national] as $t) {
            $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $referee->id]);
        }
        // Le notifiche nate in automatico (bozza zonale) non servono qui: le togliamo
        TournamentNotification::query()->delete();

        $this->actingAs($this->createZoneAdmin(1))
            ->get(route('admin.tournament-notifications.index'))
            ->assertOk()
            ->assertSee('Zonale Mai Inviato')
            ->assertSee('Da inviare')
            ->assertSee('Mario Designato')
            ->assertDontSee('Senza Arbitri');

        $this->actingAs($this->createNationalAdmin())
            ->get(route('admin.tournament-notifications.index'))
            ->assertOk()
            ->assertSee('Nazionale Caricato Da FIG')
            ->assertDontSee('Zonale Mai Inviato');
    }

    public function test_month_filter(): void
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $referee = $this->createReferee(['zone_id' => 1]);
        $march = $this->createTournament(['club_id' => $club->id, 'name' => 'Gara Di Marzo',
            'start_date' => '2026-03-10', 'end_date' => '2026-03-11']);
        $may = $this->createTournament(['club_id' => $club->id, 'name' => 'Gara Di Maggio',
            'start_date' => '2026-05-10', 'end_date' => '2026-05-11']);
        foreach ([$march, $may] as $t) {
            $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $referee->id]);
        }

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin)->get(route('admin.tournament-notifications.index'))
            ->assertOk()
            ->assertSee('name="mese"', false)
            ->assertSee('Marzo 2026')
            ->assertSee('Maggio 2026');

        $this->actingAs($admin)->get(route('admin.tournament-notifications.index', ['mese' => '2026-03']))
            ->assertOk()
            ->assertSee('Gara Di Marzo')
            ->assertDontSee('Gara Di Maggio');
    }
}
