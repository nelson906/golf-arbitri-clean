<?php

namespace Tests\Feature;

use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Segnalati da Alberto il 7 ottobre 2026:
 *  1. dettaglio notifica in errore: leggeva lo stato del torneo, eliminato (P3)
 *  2. calendario: testo bianco sui colori chiari di Tipi Torneo (giallo) illeggibile
 */
class Segnalati20261007Test extends TestCase
{
    public function test_notification_detail_page_opens(): void
    {
        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament(['club_id' => $club->id]);
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_by' => $this->createSuperAdmin()->id,
            'details' => [],
        ]);

        $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.tournament-notifications.show', $notification))
            ->assertOk()
            ->assertDontSee('Stato Torneo');
    }

    public function test_text_color_is_dark_on_light_backgrounds(): void
    {
        $colors = app(TournamentColorService::class);

        foreach (['#FFFF00', '#ffff99', '#66FFFF', '#00FFFF', '#fabf8e', '#c4aeda', '#F59E0B', '#FFF', '#0070c080'] as $light) {
            $this->assertSame('#111827', $colors->textColorFor($light), $light);
        }
        foreach (['#000000', '#0070c0', '#39471D', '#953634', '#10B981', '#3B82F6', '#808080'] as $dark) {
            $this->assertSame('#FFFFFF', $colors->textColorFor($dark), $dark);
        }
        $this->assertSame('#FFFFFF', $colors->textColorFor('non-un-colore'));
    }

    public function test_admin_calendar_event_on_yellow_has_dark_text(): void
    {
        $type = TournamentType::where('is_national', false)->firstOrFail();
        $type->update(['calendar_color' => '#FFFF00']);
        $tournament = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => $type->id,
        ]);

        $event = app(CalendarDataService::class)
            ->prepareAdminCalendarData(collect([$tournament->refresh()]))
            ->first();

        $this->assertIsArray($event);
        $this->assertSame('#FFFF00', $event['color']);
        $this->assertSame('#111827', $event['textColor']);
    }

    public function test_club_delete_warning_counts_every_tournament(): void
    {
        $club = $this->createClub(['zone_id' => 1]);
        $this->createTournament(['club_id' => $club->id]);

        $html = Blade::render('<x-table-actions-club :club="$club" />', ['club' => $club]);

        $this->assertStringContainsString('Questo Circolo ha tornei associati', $html);
    }
}
