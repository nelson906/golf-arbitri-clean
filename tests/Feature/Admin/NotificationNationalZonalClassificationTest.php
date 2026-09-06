<?php

namespace Tests\Feature\Admin;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * Test di regressione per la classificazione nazionale/zonale delle notifiche.
 *
 * BUG RILEVATO: un torneo zonale ("Gara 36 Buche", is_national=false, gestito da SZR6)
 * compariva come "Nazionale" nella lista notifiche con CRC ✓ e Zona —.
 *
 * CAUSA RADICE: NotificationController::index() usava notification_type per decidere
 * se un torneo è nazionale, ma l'import batch FIG aveva assegnato notification_type='crc_referees'
 * anche a tornei zonali.
 *
 * CORREZIONE: la fonte di verità è SEMPRE tournament.tournamentType.is_national.
 * I record TournamentNotification con notification_type sbagliato vengono corretti dal
 * comando `federgolf:fix-notification-types`.
 *
 * Eseguire con:
 *   php artisan test --filter=NotificationNationalZonalClassificationTest
 */
class NotificationNationalZonalClassificationTest extends TestCase
{

    /**
     * Anno di riferimento: ~6 mesi nel futuro.
     *
     * Serve anche a `federgolf:mark-notified --anno`, che filtra con
     * whereYear('start_date', $anno) OLTRE che sul testo della nota:
     * se le date dei tornei si spostano, l'anno passato al comando
     * deve spostarsi con loro.
     */
    private function futureYear(): int
    {
        return now()->addMonths(6)->year;
    }

    /**
     * Data a $offset giorni dal 1 marzo dell'anno di riferimento.
     *
     * L'ancora al 1 marzo (non a "oggi + 6 mesi") tiene tutti gli offset usati
     * qui — fino a 88 giorni — dentro futureYear(), qualunque sia il giorno in
     * cui gira la suite. Le date fisse (erano marzo-giugno 2025) invecchiano.
     */
    private function futureDate(int $offset = 0): string
    {
        return now()->addMonths(6)->startOfYear()->addMonths(2)->addDays($offset)->format('Y-m-d');
    }
    // -------------------------------------------------------------------------
    // SCENARIO A — Torneo zonale con record notifica corrotto (is_national=false
    //              ma notification_type='crc_referees')
    // -------------------------------------------------------------------------

    /**
     * Un torneo zonale con notifica corrotta (type='crc_referees') deve comparire
     * come zonale nell'index, NON come nazionale.
     */
    public function test_zonal_tournament_with_corrupt_crc_notification_type_shows_as_zonal(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);

        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
            'zone_id'            => 1,
        ]);

        // Situazione post-import FIG sbagliato: tipo crc_referees su torneo zonale
        TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',   // ← CORROTTO
            'status'            => 'sent',
            'referee_list'      => 'Arbitro Test',
        ]);

        $response = $this->actingAs($zoneAdmin)
            ->get(route('admin.tournament-notifications.index'));

        $response->assertOk();

        // Verifica che il gruppo abbia is_national = false
        $groups = $this->viewObject($response, 'tournamentNotifications', LengthAwarePaginator::class);
        $group  = $groups->getCollection()
            ->firstWhere(fn ($g) => $g->tournament->id === $torneo->id);

        $this->assertInstanceOf(\stdClass::class, $group, 'Il torneo deve comparire nella lista notifiche.');
        $this->assertFalse(
            $group->is_national,
            'Un torneo con tournamentType.is_national=false deve essere classificato come ZONALE, ' .
            'anche se ha una notifica con type=crc_referees.'
        );
    }

    /**
     * Un torneo zonale con notifica corrotta deve avere `primary` puntato al record
     * null (zonale), non al record crc_referees — se presente.
     */
    public function test_zonal_tournament_primary_notification_is_null_type_not_crc(): void
    {
        $zoneAdmin = $this->createZoneAdmin(1);
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
            'zone_id'            => 1,
        ]);

        // Notifica corrotta (tipo sbagliato)
        $notifCorrupted = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
            'referee_list'      => 'Arbitro CRC',
        ]);

        // Notifica corretta (tipo null = zonale)
        $notifCorrect = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => null,
            'status'            => 'sent',
            'referee_list'      => 'Arbitro Zonale',
        ]);

        $response = $this->actingAs($zoneAdmin)
            ->get(route('admin.tournament-notifications.index'));

        $groups = $this->viewObject($response, 'tournamentNotifications', LengthAwarePaginator::class);
        $group  = $groups->getCollection()
            ->firstWhere(fn ($g) => $g->tournament->id === $torneo->id);

        $this->assertInstanceOf(\stdClass::class, $group);

        // La notifica principale per un torneo zonale deve essere quella con type=null
        $primary = $group->primary;
        $this->assertInstanceOf(TournamentNotification::class, $primary);
        $this->assertEquals(
            $notifCorrect->id,
            $primary->id,
            'Per un torneo zonale, la notifica PRIMARY deve essere quella con notification_type=null.'
        );
    }

    // -------------------------------------------------------------------------
    // SCENARIO B — Torneo nazionale classificato correttamente
    // -------------------------------------------------------------------------

    /**
     * Un torneo nazionale (is_national=true) con notifica CRC deve comparire
     * come nazionale nell'index.
     */
    public function test_national_tournament_shows_as_national(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club         = $this->createClub(['zone_id' => 1]);
        $torneo       = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'zone_id'            => 1,
        ]);

        TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
            'referee_list'      => 'Arbitro Nazionale',
        ]);

        $response = $this->actingAs($nationalAdmin)
            ->get(route('admin.tournament-notifications.index'));

        $response->assertOk();

        $groups = $this->viewObject($response, 'tournamentNotifications', LengthAwarePaginator::class);
        $group  = $groups->getCollection()
            ->firstWhere(fn ($g) => $g->tournament->id === $torneo->id);

        $this->assertInstanceOf(\stdClass::class, $group, 'Il torneo nazionale deve comparire nella lista notifiche.');
        $this->assertTrue(
            $group->is_national,
            'Un torneo con tournamentType.is_national=true deve essere classificato come NAZIONALE.'
        );
    }

    /**
     * Un torneo nazionale con entrambe le notifiche (CRC + Zona) deve avere
     * i riferimenti corretti in $group->crc e $group->zone.
     */
    public function test_national_tournament_with_both_notifications_has_crc_and_zone(): void
    {
        $nationalAdmin = $this->createNationalAdmin();

        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club         = $this->createClub(['zone_id' => 1]);
        $torneo       = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'zone_id'            => 1,
        ]);

        $notifCrc  = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
        ]);
        $notifZone = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'zone_observers',
            'status'            => 'sent',
        ]);

        $response = $this->actingAs($nationalAdmin)
            ->get(route('admin.tournament-notifications.index'));

        $groups = $this->viewObject($response, 'tournamentNotifications', LengthAwarePaginator::class);
        $group  = $groups->getCollection()
            ->firstWhere(fn ($g) => $g->tournament->id === $torneo->id);

        $this->assertInstanceOf(\stdClass::class, $group);
        $this->assertTrue($group->is_national);

        $crc = $group->crc;
        $zone = $group->zone;
        $primary = $group->primary;

        $this->assertInstanceOf(TournamentNotification::class, $crc);
        $this->assertInstanceOf(TournamentNotification::class, $zone);
        $this->assertInstanceOf(TournamentNotification::class, $primary);

        $this->assertEquals($notifCrc->id, $crc->id, 'group->crc deve puntare alla notifica crc_referees.');
        $this->assertEquals($notifZone->id, $zone->id, 'group->zone deve puntare alla notifica zone_observers.');
        $this->assertEquals($notifCrc->id, $primary->id, 'Per tornei nazionali, primary deve essere CRC.');
    }

    // -------------------------------------------------------------------------
    // SCENARIO C — MarkFigAssignmentsNotified con --type=auto
    // -------------------------------------------------------------------------

    /**
     * Il comando mark-notified con --type=auto deve creare notification_type=null
     * per tornei zonali.
     */
    public function test_mark_notified_auto_creates_null_type_for_zonal_tournament(): void
    {
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = Tournament::factory()->create([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
            'zone_id'            => 1,
            'start_date'         => $this->futureDate(0),
            'end_date'           => $this->futureDate(1),
        ]);

        $arbitro = $this->createReferee(['zone_id' => 1]);

        Assignment::create([
            'tournament_id' => $torneo->id,
            'user_id'       => $arbitro->id,
            'assigned_by'   => $arbitro->id,
            'assigned_at'   => now(),
            'notes'         => 'Import batch FIG '.$this->futureYear(),
            'role'          => 'Arbitro',
            'is_confirmed'  => false,
        ]);

        $this->artisanCommand('federgolf:mark-notified', [
            '--anno' => $this->futureYear(),
            '--type' => 'auto',
        ])->assertExitCode(0);

        $notif = TournamentNotification::where('tournament_id', $torneo->id)->first();

        $this->assertNotNull($notif, 'Deve essere creata una TournamentNotification.');
        $this->assertNull(
            $notif->notification_type,
            'Per un torneo zonale (is_national=false), notification_type deve essere NULL.'
        );
        $this->assertEquals('sent', $notif->status);
    }

    /**
     * Il comando mark-notified con --type=auto deve creare notification_type='crc_referees'
     * per tornei nazionali.
     */
    public function test_mark_notified_auto_creates_crc_type_for_national_tournament(): void
    {
        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club         = $this->createClub(['zone_id' => 1]);
        $torneo       = Tournament::factory()->create([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'zone_id'            => 1,
            'start_date'         => $this->futureDate(26),
            'end_date'           => $this->futureDate(27),
        ]);

        $arbitro = $this->createReferee(['zone_id' => 1, 'level' => 'nazionale']);

        Assignment::create([
            'tournament_id' => $torneo->id,
            'user_id'       => $arbitro->id,
            'assigned_by'   => $arbitro->id,
            'assigned_at'   => now(),
            'notes'         => 'Import batch FIG '.$this->futureYear(),
            'role'          => 'Arbitro',
            'is_confirmed'  => false,
        ]);

        $this->artisanCommand('federgolf:mark-notified', [
            '--anno' => $this->futureYear(),
            '--type' => 'auto',
        ])->assertExitCode(0);

        $notif = TournamentNotification::where('tournament_id', $torneo->id)->first();

        $this->assertNotNull($notif);
        $this->assertEquals(
            'crc_referees',
            $notif->notification_type,
            'Per un torneo nazionale (is_national=true), notification_type deve essere crc_referees.'
        );
    }

    /**
     * --type=auto su un batch misto (zonali + nazionali) assegna il tipo corretto
     * a ciascun torneo indipendentemente.
     */
    public function test_mark_notified_auto_assigns_correct_type_for_mixed_batch(): void
    {
        $zonalType    = TournamentType::where('is_national', false)->firstOrFail();
        $nationalType = TournamentType::where('is_national', true)->firstOrFail();

        $club = $this->createClub(['zone_id' => 1]);

        $torneoZonale = Tournament::factory()->create([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
            'zone_id'            => 1,
            'name'               => 'Gara Zonale Test',
            'start_date'         => $this->futureDate(56),
            'end_date'           => $this->futureDate(57),
        ]);

        $torneoNazionale = Tournament::factory()->create([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
            'zone_id'            => 1,
            'name'               => 'Gara Nazionale Test',
            'start_date'         => $this->futureDate(66),
            'end_date'           => $this->futureDate(67),
        ]);

        $arbitro = $this->createReferee(['zone_id' => 1, 'level' => 'nazionale']);

        foreach ([$torneoZonale, $torneoNazionale] as $t) {
            Assignment::create([
                'tournament_id' => $t->id,
                'user_id'       => $arbitro->id,
                'assigned_by'   => $arbitro->id,
                'assigned_at'   => now(),
                'notes'         => 'Import batch FIG '.$this->futureYear(),
                'role'          => 'Arbitro',
                'is_confirmed'  => false,
            ]);
        }

        $this->artisanCommand('federgolf:mark-notified', [
            '--anno' => $this->futureYear(),
            '--type' => 'auto',
        ])->assertExitCode(0);

        $notifZonale    = TournamentNotification::where('tournament_id', $torneoZonale->id)->firstOrFail();
        $notifNazionale = TournamentNotification::where('tournament_id', $torneoNazionale->id)->firstOrFail();

        $this->assertNull(
            $notifZonale->notification_type,
            'Il torneo zonale deve avere notification_type=null.'
        );
        $this->assertEquals(
            'crc_referees',
            $notifNazionale->notification_type,
            'Il torneo nazionale deve avere notification_type=crc_referees.'
        );
    }

    // -------------------------------------------------------------------------
    // SCENARIO D — Comando FixNotificationTypes
    // -------------------------------------------------------------------------

    /**
     * Il comando fix-notification-types corregge un record con tipo sbagliato
     * su un torneo zonale (crc_referees → null).
     */
    public function test_fix_notification_types_corrects_zonal_tournament_with_wrong_type(): void
    {
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        $notif = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',   // ← sbagliato per torneo zonale
            'status'            => 'sent',
        ]);

        $this->artisanCommand('federgolf:fix-notification-types')
             ->assertExitCode(0);

        $notif->refresh();

        $this->assertNull(
            $notif->notification_type,
            'Dopo fix-notification-types, un torneo zonale deve avere notification_type=null.'
        );
    }

    /**
     * Il comando fix-notification-types in --dry-run non modifica il DB.
     */
    public function test_fix_notification_types_dry_run_does_not_modify_db(): void
    {
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
        ]);

        $notif = TournamentNotification::create([
            'tournament_id'     => $torneo->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
        ]);

        $this->artisanCommand('federgolf:fix-notification-types', ['--dry-run' => true])
             ->assertExitCode(0);

        $notif->refresh();

        $this->assertEquals(
            'crc_referees',
            $notif->notification_type,
            'Con --dry-run il record NON deve essere modificato.'
        );
    }

    /**
     * Il comando fix-notification-types non tocca i record già corretti.
     */
    public function test_fix_notification_types_leaves_correct_records_unchanged(): void
    {
        $zonalType    = TournamentType::where('is_national', false)->firstOrFail();
        $nationalType = TournamentType::where('is_national', true)->firstOrFail();
        $club         = $this->createClub(['zone_id' => 1]);

        // Torneo zonale con tipo corretto (null)
        $torneoZ = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
        ]);
        $notifZ = TournamentNotification::create([
            'tournament_id'     => $torneoZ->id,
            'notification_type' => null,
            'status'            => 'sent',
        ]);

        // Torneo nazionale con tipo corretto (crc_referees)
        $torneoN = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $nationalType->id,
        ]);
        $notifN = TournamentNotification::create([
            'tournament_id'     => $torneoN->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
        ]);

        $this->artisanCommand('federgolf:fix-notification-types')
             ->assertExitCode(0);

        $notifZ->refresh();
        $notifN->refresh();

        $this->assertNull(
            $notifZ->notification_type,
            'Il record del torneo zonale (già corretto) deve rimanere null.'
        );
        $this->assertEquals(
            'crc_referees',
            $notifN->notification_type,
            'Il record del torneo nazionale (già corretto) deve rimanere crc_referees.'
        );
    }

    // -------------------------------------------------------------------------
    // SCENARIO E — is_confirmed aggiornato da mark-notified
    // -------------------------------------------------------------------------

    /**
     * Il comando mark-notified deve impostare is_confirmed=true sulle assegnazioni
     * interessate, indipendentemente dal tipo torneo.
     */
    public function test_mark_notified_sets_is_confirmed_on_assignments(): void
    {
        $zonalType = TournamentType::where('is_national', false)->firstOrFail();
        $club      = $this->createClub(['zone_id' => 1]);
        $torneo    = Tournament::factory()->create([
            'club_id'            => $club->id,
            'tournament_type_id' => $zonalType->id,
            'start_date'         => $this->futureDate(87),
            'end_date'           => $this->futureDate(88),
        ]);

        $arbitro = $this->createReferee(['zone_id' => 1]);
        $assignment = Assignment::create([
            'tournament_id' => $torneo->id,
            'user_id'       => $arbitro->id,
            'assigned_by'   => $arbitro->id,
            'assigned_at'   => now(),
            'notes'         => 'Import batch FIG '.$this->futureYear(),
            'role'          => 'Arbitro',
            'is_confirmed'  => false,
        ]);

        $this->artisanCommand('federgolf:mark-notified', [
            '--anno' => $this->futureYear(),
            '--type' => 'auto',
        ])->assertExitCode(0);

        $assignment->refresh();

        $this->assertTrue(
            (bool) $assignment->is_confirmed,
            'Dopo mark-notified, is_confirmed deve essere true sulle assegnazioni FIG.'
        );
    }
}
