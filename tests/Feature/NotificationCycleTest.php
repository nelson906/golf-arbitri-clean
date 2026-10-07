<?php

namespace Tests\Feature;

use App\Helpers\ZoneHelper;
use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\DocumentGenerationService;
use App\Services\NotificationPreparationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ciclo della notifica zonale con i Word VERI (template in
 * storage/lettere_intestate): convocazione con clausole, lettera al circolo,
 * creazione degli allegati dal form ("Crea allegati").
 *
 * Prima questi test cercavano un torneo gia' presente nel database e, nel
 * database dei test vuoto, si saltavano tutti: la creazione dei Word non era
 * mai verificata. Ora ogni test crea i propri dati (7 ottobre 2026).
 */
class NotificationCycleTest extends TestCase
{
    private DocumentGenerationService $documentService;

    private NotificationPreparationService $preparationService;

    /** @var list<string> file assoluti da cancellare */
    private array $generatedFiles = [];

    /** @var list<string> file relativi al disk documenti da cancellare */
    private array $docsFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentService = app(DocumentGenerationService::class);
        $this->preparationService = app(NotificationPreparationService::class);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $disk = Storage::disk(Config::string('golf.documents.disk', 'docs'));
        foreach ($this->docsFiles as $path) {
            $disk->delete($path);
        }

        parent::tearDown();
    }

    /**
     * Torneo zonale della zona 1 con Direttore, Arbitro e Osservatore.
     *
     * @return array{0: Tournament, 1: array<string, string>}
     */
    private function tournamentWithReferees(): array
    {
        $club = $this->createClub(['zone_id' => 1, 'name' => 'Circolo Prova Verde', 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'name' => 'Trofeo Della Prova',
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
            'start_date' => now()->addDays(40)->startOfDay(),
            'end_date' => now()->addDays(41)->startOfDay(),
            'availability_deadline' => now()->addDays(20)->startOfDay(),
        ]);

        $names = [];
        foreach (['Direttore di Torneo' => 'Dario Direttore', 'Arbitro' => 'Arturo Arbitro', 'Osservatore' => 'Osvaldo Osservatore'] as $role => $name) {
            [$first, $last] = explode(' ', $name);
            $referee = $this->createReferee(['zone_id' => 1, 'name' => $name, 'first_name' => $first, 'last_name' => $last]);
            $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id, 'role' => $role]);
            $names[$role] = $name;
        }

        return [$tournament->refresh(), $names];
    }

    private function clause(string $code, string $category, string $appliesTo, string $content): NotificationClause
    {
        return NotificationClause::create([
            'code' => $code,
            'category' => $category,
            'title' => 'Clausola '.$code,
            'content' => $content,
            'applies_to' => $appliesTo,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /** Testo del documento Word (word/document.xml senza i tag). */
    private function docxText(string $path): string
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Il file non è un DOCX (zip) valido: '.$path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertIsString($xml, 'Il DOCX non contiene word/document.xml');

        return html_entity_decode(strip_tags($xml));
    }

    public function test_zone_templates_exist(): void
    {
        $templateDir = storage_path('lettere_intestate');

        $this->assertDirectoryExists($templateDir, 'Directory template non trovata');
        foreach (['szr1', 'szr2', 'szr3', 'szr4', 'szr5', 'szr6', 'szr7', 'crc', 'default'] as $zone) {
            $this->assertFileExists("{$templateDir}/lettera_intestata_{$zone}.docx", "Template {$zone} mancante");
        }
    }

    public function test_convocation_contains_tournament_club_and_all_referees(): void
    {
        [$tournament, $names] = $this->tournamentWithReferees();

        $result = $this->documentService->generateConvocationForTournament($tournament);
        $this->generatedFiles[] = $result['path'];

        $this->assertSame('convocation', $result['type']);
        $this->assertStringEndsWith('.docx', $result['filename']);
        $this->assertStringContainsString((string) $tournament->id, $result['filename']);

        $text = $this->docxText($result['path']);
        $this->assertStringContainsString('Trofeo Della Prova', $text);
        $this->assertStringContainsString('Circolo Prova Verde', $text);
        foreach ($names as $role => $name) {
            $this->assertStringContainsString($name, $text, "Manca {$role} nella convocazione");
        }
        // Nessun segnaposto rimasto nel documento
        $this->assertStringNotContainsString('${', $text);
    }

    public function test_convocation_includes_selected_clauses_and_drops_the_others(): void
    {
        [$tournament] = $this->tournamentWithReferees();
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
        ]);

        $referee = $this->clause('ARB_RESP', 'responsabilita', 'referee', 'Testo clausola arbitri: assicurazione obbligatoria.');
        $club = $this->clause('CLUB_SPESE', 'spese', 'club', 'Testo clausola circolo: spese a carico del circolo.');
        NotificationClauseSelection::create([
            'tournament_notification_id' => $notification->id,
            'clause_id' => $referee->id,
            'placeholder_code' => 'CLAUSOLA_ARBITRO_RESPONSABILITA',
        ]);
        NotificationClauseSelection::create([
            'tournament_notification_id' => $notification->id,
            'clause_id' => $club->id,
            'placeholder_code' => 'CLAUSOLA_CLUB_SPESE',
        ]);

        $result = $this->documentService->generateConvocationForTournament($tournament, $notification);
        $this->generatedFiles[] = $result['path'];

        $text = $this->docxText($result['path']);
        $this->assertStringContainsString('assicurazione obbligatoria', $text);
        $this->assertStringContainsString('spese a carico del circolo', $text);
        // Blocchi delle clausole non scelte tolti, segnaposto compresi
        $this->assertStringNotContainsString('BLOCCO_CLAUSOLA', $text);
        $this->assertStringNotContainsString('CLAUSOLA_ARBITRO_COMUNICAZIONI', $text);
        $this->assertStringNotContainsString('${', $text);
    }

    public function test_club_letter_contains_tournament_and_referees(): void
    {
        [$tournament, $names] = $this->tournamentWithReferees();

        $result = $this->documentService->generateClubDocument($tournament);
        $this->generatedFiles[] = $result['path'];

        $this->assertSame('club_letter', $result['type']);
        $text = $this->docxText($result['path']);
        // Fac-simile da stampare sulla carta intestata del circolo: il nome del
        // circolo non c'e', c'e' la sua email per le conferme
        $this->assertStringContainsString('FAC SIMILE', $text);
        $this->assertStringContainsString('Trofeo Della Prova', $text);
        $this->assertStringContainsString('circolo@example.test', $text);
        foreach ($names as $name) {
            $this->assertStringContainsString($name, $text);
        }
    }

    public function test_club_letter_works_without_assignments(): void
    {
        $club = $this->createClub(['zone_id' => 2, 'email' => 'c2@example.test']);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);

        $result = $this->documentService->generateClubDocument($tournament);
        $this->generatedFiles[] = $result['path'];

        $this->docxText($result['path']);
    }

    public function test_create_attachments_from_the_form_saves_both_word_files(): void
    {
        [$tournament] = $this->tournamentWithReferees();
        $clause = $this->clause('ARB_COM', 'comunicazioni', 'referee', 'Comunicare il rapporto entro 48 ore.');
        $this->actingAsSuperAdmin();

        // Apertura del form: nessun allegato
        $this->get(route('admin.tournaments.show-assignment-form', $tournament))->assertOk();
        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertEmpty($notification->documents);

        // "Crea allegati": salva le clausole, poi crea convocazione e lettera
        $this->postJson(route('admin.tournament-notifications.save-clauses', $notification), [
            'clauses' => ['CLAUSOLA_ARBITRO_COMUNICAZIONI' => $clause->id],
        ])->assertOk();
        foreach (['convocation', 'club_letter'] as $type) {
            $this->postJson(route('admin.tournament-notifications.generate-document', [$notification, $type]))
                ->assertOk()
                ->assertJson(['success' => true]);
        }

        $documents = $notification->fresh()?->documents;
        $this->assertIsArray($documents);
        $disk = Storage::disk(Config::string('golf.documents.disk', 'docs'));
        $dir = Config::string('golf.documents.storage_path', 'convocazioni').'/'.ZoneHelper::getFolderCodeForTournament($tournament).'/generated';
        foreach (['convocation', 'club_letter'] as $type) {
            $this->assertIsString($documents[$type] ?? null);
            $this->docsFiles[] = "{$dir}/{$documents[$type]}";
            $this->generatedFiles[] = storage_path(Config::string('golf.documents.temp_path', 'app/temp').'/'.$documents[$type]);
            $this->assertTrue($disk->exists("{$dir}/{$documents[$type]}"), "{$type} non salvato");
        }
        $this->assertStringContainsString('rapporto entro 48 ore', $this->docxText($disk->path("{$dir}/{$documents['convocation']}")));

        // Una seconda volta non si rigenera
        $this->postJson(route('admin.tournament-notifications.generate-document', [$notification, 'convocation']))
            ->assertStatus(422);
    }

    public function test_prepare_notification_creates_pending_draft(): void
    {
        [$tournament, $names] = $this->tournamentWithReferees();

        $notification = $this->preparationService->prepareNotification($tournament);

        $this->assertSame($tournament->id, $notification->tournament_id);
        $this->assertSame('pending', $notification->status);
        foreach ($names as $name) {
            $this->assertStringContainsString($name, (string) $notification->referee_list);
        }
    }

    public function test_tournament_date_formatting(): void
    {
        // Le tre casistiche di formatTournamentDates(): stesso giorno, stesso
        // mese, mesi diversi (date relative, non fisse).
        $base = now()->addMonths(6)->startOfMonth();
        $single = $base->copy()->addDays(14);
        $sameStart = $base->copy()->addDays(14);
        $sameEnd = $base->copy()->addDays(16);
        $diffStart = $base->copy()->endOfMonth()->subDays(2)->startOfDay();
        $diffEnd = $base->copy()->addMonth()->startOfMonth()->addDays(1);

        $method = (new \ReflectionClass($this->documentService))->getMethod('formatTournamentDates');
        $method->setAccessible(true);

        $make = fn ($start, $end) => new Tournament(['name' => 'T', 'start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d')]);

        $this->assertEquals($single->format('d/m/Y'), $method->invoke($this->documentService, $make($single, $single)));
        $this->assertEquals($sameStart->format('d').'-'.$sameEnd->format('d/m/Y'), $method->invoke($this->documentService, $make($sameStart, $sameEnd)));
        $this->assertEquals($diffStart->format('d/m/Y').' - '.$diffEnd->format('d/m/Y'), $method->invoke($this->documentService, $make($diffStart, $diffEnd)));
    }

    public function test_role_translation(): void
    {
        $method = (new \ReflectionClass($this->documentService))->getMethod('translateRole');
        $method->setAccessible(true);

        $testCases = [
            'Tournament Director' => 'Direttore di Torneo',
            'Direttore di Torneo' => 'Direttore di Torneo',
            'Observer' => 'Osservatore',
            'Osservatore' => 'Osservatore',
            'Referee' => 'Arbitro',
            'Arbitro' => 'Arbitro',
            'Unknown Role' => 'Arbitro',
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, $method->invoke($this->documentService, $input), "Traduzione fallita per: {$input}");
        }
    }

    public function test_zone_template_path_uses_zone_and_falls_back_to_default(): void
    {
        $method = (new \ReflectionClass($this->documentService))->getMethod('getZoneTemplatePath');
        $method->setAccessible(true);

        $path = function ($zone) use ($method): string {
            $result = $method->invoke($this->documentService, $zone);
            $this->assertIsString($result);

            return $result;
        };

        $this->assertStringEndsWith('lettera_intestata_szr3.docx', $path(3));
        $this->assertStringEndsWith('lettera_intestata_default.docx', $path(999));
        $this->assertStringEndsWith('lettera_intestata_default.docx', $path(null));
    }
}
