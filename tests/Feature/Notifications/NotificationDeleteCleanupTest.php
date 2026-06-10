<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationTransactionService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Copre l'eliminazione delle notifiche con cleanup:
 *  - NotificationTransactionService::deleteWithCleanup() rimuove documenti su
 *    disco + record notifica (e, per cascade FK, le clausole selezionate)
 *  - endpoint HTTP destroy (singola) e destroy-tournament (tutte le notifiche
 *    di un torneo)
 *
 * deleteAllDocuments usa Storage::disk('public'), quindi Storage::fake è sufficiente.
 */
class NotificationDeleteCleanupTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    private function nationalType(): TournamentType
    {
        return TournamentType::where('is_national', true)->firstOrFail();
    }

    public function test_delete_with_cleanup_removes_documents_record_and_clauses(): void
    {
        Storage::fake('public');

        $club = $this->createClub(['zone_id' => 1]);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
        ]);

        $zone = \App\Helpers\ZoneHelper::getFolderCodeForTournament($tournament);
        $dir  = config('golf.documents.storage_path')."/{$zone}/generated";
        Storage::disk('public')->put("$dir/Convocazione_x.docx", 'FAKE');
        Storage::disk('public')->put("$dir/Lettera_x.docx", 'FAKE');

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'sent',
            'documents'         => [
                'convocation' => 'Convocazione_x.docx',
                'club_letter' => 'Lettera_x.docx',
            ],
        ]);

        $clause = NotificationClause::create([
            'code'       => 'TEST_CLEANUP',
            'category'   => 'altro',
            'title'      => 'Clausola test',
            'content'    => 'Contenuto.',
            'applies_to' => 'club',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
        $selection = NotificationClauseSelection::create([
            'tournament_notification_id' => $notification->id,
            'clause_id'                  => $clause->id,
            'placeholder_code'           => 'PH1',
        ]);

        app(NotificationTransactionService::class)->deleteWithCleanup($notification);

        $this->assertDatabaseMissing('tournament_notifications', ['id' => $notification->id]);
        // Cascade FK su tournament_notification_id elimina anche le selezioni clausole.
        $this->assertDatabaseMissing('notification_clause_selections', ['id' => $selection->id]);

        Storage::disk('public')->assertMissing("$dir/Convocazione_x.docx");
        Storage::disk('public')->assertMissing("$dir/Lettera_x.docx");
    }

    public function test_destroy_endpoint_deletes_single_notification(): void
    {
        Storage::fake('public');

        $tournament = $this->createTournament([
            'tournament_type_id' => $this->zonalType()->id,
        ]);
        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'sent',
            'documents'         => [],
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->delete(route('admin.tournament-notifications.destroy', $notification));

        $response->assertRedirect(route('admin.tournament-notifications.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('tournament_notifications', ['id' => $notification->id]);
    }

    public function test_destroy_tournament_endpoint_deletes_all_notifications(): void
    {
        Storage::fake('public');

        $tournament = $this->createTournament([
            'tournament_type_id' => $this->nationalType()->id,
        ]);

        // Gara nazionale: CRC + Zona nello stesso torneo.
        TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => 'crc_referees',
            'status'            => 'sent',
            'documents'         => [],
        ]);
        TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => 'zone_observers',
            'status'            => 'sent',
            'documents'         => [],
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->delete(route('admin.tournament-notifications.destroy-tournament', $tournament));

        $response->assertSessionHas('success');
        $this->assertSame(
            0,
            TournamentNotification::where('tournament_id', $tournament->id)->count(),
            'Tutte le notifiche del torneo devono essere eliminate.'
        );
    }
}
