<?php

namespace Tests\Feature\User;

use App\Models\Communication;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Accesso dell'utente a documenti e comunicazioni (area /user).
 *
 * La protezione NON sta nel middleware referee_or_admin (il suo controllo di
 * proprieta' agisce su parametri {referee}/{availability}/{assignment} che
 * queste rotte non hanno) ma nei controller:
 * - User\DocumentController::authorizeDocumentAccess()
 * - User\CommunicationController::show()
 *
 * Comportamenti confermati (2026-10-15):
 * - una comunicazione scaduta non e' in elenco ma si apre dal link diretto;
 * - le statistiche dell'indice documenti contano tutti i documenti, anche
 *   quelli di zone che l'utente non vede.
 */
class DocumentCommunicationAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ============================================================
    // DOCUMENTI — download
    // ============================================================

    public function test_referee_downloads_document_of_own_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => 1]);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertOk();
    }

    public function test_referee_cannot_download_private_document_of_other_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => 2, 'is_public' => false]);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertForbidden();

        $this->assertSame(0, $document->fresh()?->download_count);
    }

    public function test_referee_downloads_public_document_of_other_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => 2, 'is_public' => true]);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertOk();
    }

    public function test_referee_downloads_own_upload_in_other_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument([
            'zone_id' => 2,
            'is_public' => false,
            'uploader_id' => $referee->id,
        ]);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertOk();
    }

    public function test_referee_downloads_document_without_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => null, 'is_public' => false]);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertOk();
    }

    public function test_zone_admin_cannot_download_private_document_of_other_zone(): void
    {
        $admin = $this->createZoneAdmin(1);
        $document = $this->makeDocument(['zone_id' => 2, 'is_public' => false]);

        $this->actingAs($admin)
            ->get(route('user.documents.download', $document))
            ->assertForbidden();
    }

    public function test_national_and_super_admin_download_any_document(): void
    {
        $document = $this->makeDocument(['zone_id' => 2, 'is_public' => false]);

        foreach ([$this->createNationalAdmin(['zone_id' => 8]), $this->createSuperAdmin(['zone_id' => 1])] as $user) {
            $this->actingAs($user)
                ->get(route('user.documents.download', $document))
                ->assertOk();
        }
    }

    public function test_download_increments_counter(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => 1]);

        $this->actingAs($referee)->get(route('user.documents.download', $document))->assertOk();
        $this->actingAs($referee)->get(route('user.documents.download', $document))->assertOk();

        $this->assertSame(2, $document->fresh()?->download_count);
    }

    public function test_missing_file_returns_404(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument(['zone_id' => 1], withFile: false);

        $this->actingAs($referee)
            ->get(route('user.documents.download', $document))
            ->assertNotFound();
    }

    public function test_office_file_gets_correct_mime_type(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $document = $this->makeDocument([
            'zone_id' => 1,
            'original_name' => 'convocazione.docx',
            'mime_type' => 'application/zip',
        ]);

        $response = $this->actingAs($referee)->get(route('user.documents.download', $document));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    public function test_guest_cannot_download(): void
    {
        $document = $this->makeDocument(['zone_id' => 1]);

        $this->get(route('user.documents.download', $document))
            ->assertRedirect(route('login'));
    }

    // ============================================================
    // DOCUMENTI — elenco
    // ============================================================

    public function test_index_lists_only_visible_documents_but_stats_count_all(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);

        $ownZone = $this->makeDocument(['zone_id' => 1, 'name' => 'Doc zona 1']);
        $noZone = $this->makeDocument(['zone_id' => null, 'name' => 'Doc senza zona']);
        $publicOther = $this->makeDocument(['zone_id' => 2, 'is_public' => true, 'name' => 'Doc pubblico zona 2']);
        $privateOther = $this->makeDocument(['zone_id' => 2, 'is_public' => false, 'name' => 'Doc privato zona 2']);

        $response = $this->actingAs($referee)->get(route('user.documents.index'));
        $response->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Document> $documents */
        $documents = $this->viewObject($response, 'documents', \Illuminate\Pagination\LengthAwarePaginator::class);
        $ids = collect($documents->items())->pluck('id')->all();

        $this->assertContains($ownZone->id, $ids);
        $this->assertContains($noZone->id, $ids);
        $this->assertContains($publicOther->id, $ids);
        $this->assertNotContains($privateOther->id, $ids);

        $stats = $this->viewArray($response, 'stats');
        $this->assertSame(4, $stats['total']);
    }

    // ============================================================
    // COMUNICAZIONI — dettaglio
    // ============================================================

    public function test_referee_opens_published_communication_of_own_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $communication = $this->makeCommunication(['zone_id' => 1]);

        $this->actingAs($referee)
            ->get(route('user.communications.show', $communication))
            ->assertOk()
            ->assertSee($communication->title);
    }

    public function test_referee_opens_published_communication_without_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $communication = $this->makeCommunication(['zone_id' => null]);

        $this->actingAs($referee)
            ->get(route('user.communications.show', $communication))
            ->assertOk();
    }

    public function test_referee_cannot_open_communication_of_other_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $communication = $this->makeCommunication(['zone_id' => 2]);

        $this->actingAs($referee)
            ->get(route('user.communications.show', $communication))
            ->assertForbidden();
    }

    public function test_unpublished_communication_returns_404(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);

        foreach (['draft', 'expired'] as $status) {
            $communication = $this->makeCommunication(['zone_id' => 1, 'status' => $status]);

            $this->actingAs($referee)
                ->get(route('user.communications.show', $communication))
                ->assertNotFound();
        }
    }

    public function test_expired_communication_is_hidden_from_list_but_opens_from_link(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $expired = $this->makeCommunication([
            'zone_id' => 1,
            'title' => 'Comunicazione scaduta',
            'expires_at' => now()->subDay(),
        ]);
        $active = $this->makeCommunication(['zone_id' => 1, 'title' => 'Comunicazione attiva']);

        $list = $this->actingAs($referee)->get(route('user.communications.index'));
        $list->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Communication> $communications */
        $communications = $this->viewObject($list, 'communications', \Illuminate\Pagination\LengthAwarePaginator::class);
        $ids = collect($communications->items())->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($expired->id, $ids);

        $this->actingAs($referee)
            ->get(route('user.communications.show', $expired))
            ->assertOk();
    }

    public function test_index_lists_own_zone_and_global_but_not_other_zone(): void
    {
        $referee = $this->createReferee(['zone_id' => 1]);
        $own = $this->makeCommunication(['zone_id' => 1]);
        $global = $this->makeCommunication(['zone_id' => null]);
        $other = $this->makeCommunication(['zone_id' => 2]);
        $draft = $this->makeCommunication(['zone_id' => 1, 'status' => 'draft']);

        $response = $this->actingAs($referee)->get(route('user.communications.index'));
        $response->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Communication> $communications */
        $communications = $this->viewObject($response, 'communications', \Illuminate\Pagination\LengthAwarePaginator::class);
        $ids = collect($communications->items())->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($global->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    // ============================================================
    // HELPER
    // ============================================================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeDocument(array $attributes = [], bool $withFile = true): Document
    {
        static $counter = 0;
        $counter++;

        $uploader = isset($attributes['uploader_id'])
            ? null
            : $this->createZoneAdmin(1);

        $path = "documents/test_{$counter}.pdf";

        if ($withFile) {
            Storage::disk('public')->put($path, 'FAKE-PDF');
        }

        return Document::create(array_merge([
            'name' => "Documento {$counter}",
            'original_name' => "documento_{$counter}.pdf",
            'file_path' => $path,
            'file_size' => 8,
            'mime_type' => 'application/pdf',
            'category' => 'general',
            'type' => 'pdf',
            'zone_id' => 1,
            'uploader_id' => $uploader instanceof User ? $uploader->id : null,
            'is_public' => false,
            'download_count' => 0,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCommunication(array $attributes = []): Communication
    {
        static $counter = 0;
        $counter++;

        return Communication::create(array_merge([
            'title' => "Comunicazione {$counter}",
            'content' => 'Testo di prova',
            'type' => 'info',
            'status' => 'published',
            'priority' => 'normal',
            'zone_id' => 1,
            'author_id' => $this->createZoneAdmin(1)->id,
            'published_at' => now(),
        ], $attributes));
    }
}
