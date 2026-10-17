<?php

namespace Tests\Feature\Notifications;

use App\Mail\ClubNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationDocumentService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notifica zonale: indirizzi aggiunti a mano in copia e casi limite degli
 * allegati.
 */
class ZonalExtrasTest extends TestCase
{
    private function zonal(): Tournament
    {
        $t = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test'])->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $t->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        return $t;
    }

    public function test_additional_addresses_go_in_copy_and_empty_ones_are_skipped(): void
    {
        $t = $this->zonal();
        $n = TournamentNotification::create([
            'tournament_id' => $t->id,
            'status' => 'pending',
            'metadata' => [
                'subject' => 'Convocazione',
                'message' => 'Testo',
                'recipients' => [
                    'club' => true,
                    'referees' => [],
                    'additional' => [
                        ['email' => 'extra@example.test', 'name' => 'Ospite'],
                        ['email' => '', 'name' => 'Vuoto'],
                        'non-una-riga',
                    ],
                ],
            ],
        ]);
        $this->attachDocumentsTo($n);

        app(NotificationService::class)->send($n->refresh());

        $this->assertSame('sent', $n->refresh()->status);
        Mail::assertQueued(ClubNotificationMail::class, fn (ClubNotificationMail $m): bool => $m->hasTo('circolo@example.test')
            && $m->hasCc('extra@example.test')
            && count($m->cc) === 1);
    }

    public function test_unknown_document_type_cannot_be_generated(): void
    {
        $n = TournamentNotification::create(['tournament_id' => $this->zonal()->id, 'status' => 'pending']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document type: altro');

        app(NotificationDocumentService::class)->generateDocument($n, 'altro');
    }

    public function test_path_of_unregistered_document_is_an_error(): void
    {
        $n = TournamentNotification::create(['tournament_id' => $this->zonal()->id, 'status' => 'pending']);

        $this->expectExceptionMessage('Documento non trovato');

        app(NotificationDocumentService::class)->getDocumentPath($n, 'club_letter');
    }

    public function test_delete_all_documents_tolerates_files_already_gone(): void
    {
        $n = TournamentNotification::create([
            'tournament_id' => $this->zonal()->id,
            'status' => 'pending',
            'documents' => ['convocation' => 'gia_cancellato.docx'],
        ]);

        app(NotificationDocumentService::class)->deleteAllDocuments($n);

        // Nessun errore e il registro della notifica non cambia
        $this->assertSame(['convocation' => 'gia_cancellato.docx'], TournamentNotification::findOrFail($n->id)->documents);
    }

    public function test_documents_stored_as_json_string_are_read(): void
    {
        $n = TournamentNotification::create(['tournament_id' => $this->zonal()->id, 'status' => 'pending']);
        $this->attachDocumentsTo($n);
        \Illuminate\Support\Facades\DB::table('tournament_notifications')->where('id', $n->id)
            ->update(['documents' => json_encode(json_encode(['convocation' => "convocazione_test_{$n->id}.docx"]))]);

        $status = app(NotificationDocumentService::class)->checkDocumentsExist(TournamentNotification::findOrFail($n->id));

        $this->assertTrue($status['hasConvocation']);
        $this->assertFalse($status['hasClubLetter']);
    }
}
