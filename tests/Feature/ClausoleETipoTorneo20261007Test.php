<?php

namespace Tests\Feature;

use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\DocumentGenerationService;
use Tests\TestCase;

/**
 * Decisioni del 7 ottobre 2026 (sera):
 * - il fac-simile per il circolo porta le clausole scelte per il Circolo;
 * - Circolo Responsabilita' e Arbitro Altro hanno un posto nelle carte intestate;
 * - il tipo di torneo non cambia dopo la creazione (solo il super admin).
 */
class ClausoleETipoTorneo20261007Test extends TestCase
{
    private function docxText(string $path): string
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, "Non si apre {$path}");
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1);
    }

    private function select(TournamentNotification $notification, string $code, string $content): void
    {
        $clause = NotificationClause::create([
            'code' => 'T_'.uniqid(), 'category' => 'altro', 'title' => $code,
            'content' => $content, 'applies_to' => 'all', 'is_active' => true, 'sort_order' => 1,
        ]);
        NotificationClauseSelection::create([
            'tournament_notification_id' => $notification->id,
            'clause_id' => $clause->id,
            'placeholder_code' => $code,
        ]);
    }

    private function zonalTournament(): Tournament
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id' => $club->id,
            'tournament_type_id' => TournamentType::where('is_national', false)->firstOrFail()->id,
        ]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => 1])->id]);

        return $tournament;
    }

    public function test_club_letter_carries_the_club_clauses(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);
        $this->select($notification, 'CLAUSOLA_CLUB_SPESE', 'Spese a carico del circolo & rimborso "forfait".');
        $this->select($notification, 'CLAUSOLA_CLUB_LOGISTICA', "Alloggio presso la club house.\nParcheggio riservato.");
        $this->select($notification, 'CLAUSOLA_CLUB_RESPONSABILITA', 'Assicurazione a cura del circolo.');
        $this->select($notification, 'CLAUSOLA_ARBITRO_ALTRO', 'Questa non va nel fac-simile.');

        $doc = app(DocumentGenerationService::class)->generateClubDocument($tournament, $notification);
        $text = $this->docxText($doc['path']);
        @unlink($doc['path']);

        $this->assertStringContainsString('Spese a carico del circolo & rimborso "forfait".', $text);
        $this->assertStringNotContainsString('Linee guida', $text, 'La clausola Spese prende il posto del paragrafo fisso');
        $this->assertStringContainsString("Alloggio presso la club house.\nParcheggio riservato.", $text);
        $this->assertStringContainsString('Assicurazione a cura del circolo.', $text);
        $this->assertStringNotContainsString('Questa non va nel fac-simile.', $text);
        // Ordine: spese, logistica, responsabilita', poi la richiesta di conferma
        $this->assertLessThan(strpos($text, 'Alloggio'), strpos($text, 'Spese a carico'));
        $this->assertLessThan(strpos($text, 'Assicurazione'), strpos($text, 'Parcheggio'));
        $this->assertLessThan(strpos($text, 'Si prega di confermare'), strpos($text, 'Assicurazione'));
    }

    public function test_club_letter_without_clauses_keeps_the_fixed_text(): void
    {
        $tournament = $this->zonalTournament();
        $notification = TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);

        $doc = app(DocumentGenerationService::class)->generateClubDocument($tournament, $notification);
        $text = $this->docxText($doc['path']);
        @unlink($doc['path']);

        $this->assertStringContainsString('Linee guida', $text);
    }

    public function test_every_letterhead_has_a_place_for_every_clause(): void
    {
        $codes = [
            'CLAUSOLA_CLUB_SPESE', 'CLAUSOLA_CLUB_LOGISTICA', 'CLAUSOLA_CLUB_RESPONSABILITA',
            'CLAUSOLA_ARBITRO_RESPONSABILITA', 'CLAUSOLA_ARBITRO_COMUNICAZIONI', 'CLAUSOLA_ARBITRO_ALTRO',
        ];
        $files = glob(storage_path('lettere_intestate/*.docx')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $text = $this->docxText($file);
            foreach ($codes as $code) {
                $this->assertStringContainsString('${BLOCCO_'.$code.'}', $text, basename($file)." senza {$code}");
                $this->assertStringContainsString('${'.$code.'}', $text, basename($file)." senza {$code}");
                $this->assertStringContainsString('${/BLOCCO_'.$code.'}', $text, basename($file)." senza {$code}");
            }
        }
    }

    /**
     * Su ogni carta intestata di zona: le clausole scelte compaiono, quelle
     * non scelte spariscono, nessun segnaposto resta nel documento.
     */
    public function test_new_clause_places_are_filled_or_removed_in_the_szr_letter(): void
    {
        $zonal = TournamentType::where('is_national', false)->firstOrFail()->id;
        foreach (\App\Models\Zone::orderBy('id')->get() as $zone) {
            $zoneId = (int) $zone->id;
            $club = $this->createClub(['zone_id' => $zoneId, 'email' => "c{$zoneId}@example.test"]);
            $tournament = $this->createTournament(['club_id' => $club->id, 'tournament_type_id' => $zonal]);
            $tournament->forceFill(['zone_id' => $zoneId])->save();
            $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $this->createReferee(['zone_id' => $zoneId])->id]);
            $notification = TournamentNotification::create(['tournament_id' => $tournament->id, 'status' => 'pending']);
            $this->select($notification, 'CLAUSOLA_CLUB_RESPONSABILITA', 'Responsabilita circolo di prova.');
            $this->select($notification, 'CLAUSOLA_ARBITRO_ALTRO', 'Altro per gli arbitri di prova.');

            $doc = app(DocumentGenerationService::class)->generateConvocationForTournament($tournament->fresh() ?? $tournament, $notification);
            $text = $this->docxText($doc['path']);
            @unlink($doc['path']);

            $this->assertStringContainsString('Responsabilita circolo di prova.', $text, "zona {$zoneId}");
            $this->assertStringContainsString('Altro per gli arbitri di prova.', $text, "zona {$zoneId}");
            $this->assertStringNotContainsString('CLAUSOLA', $text, "zona {$zoneId}: segnaposto rimasto nella lettera");
        }
    }

    /** @return array<string, mixed> */
    private function updateData(Tournament $tournament, int $typeId): array
    {
        return [
            'name' => $tournament->name,
            'club_id' => $tournament->club_id,
            'tournament_type_id' => $typeId,
            'start_date' => $tournament->start_date->format('Y-m-d'),
            'end_date' => $tournament->end_date?->format('Y-m-d'),
            'availability_deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ];
    }

    public function test_tournament_type_cannot_change_after_creation(): void
    {
        $zonalTypes = TournamentType::where('is_national', false)->where('is_active', true)->orderBy('id')->take(2)->get();
        $this->assertCount(2, $zonalTypes, 'Servono due tipi zonali attivi');
        $typeA = $zonalTypes->firstOrFail();
        $typeB = $zonalTypes->skip(1)->firstOrFail();

        $tournament = $this->createTournament([
            'club_id' => $this->createClub(['zone_id' => 1])->id,
            'tournament_type_id' => $typeA->id,
            'start_date' => now()->addDays(30), 'end_date' => now()->addDays(31),
        ]);
        $tournament->forceFill(['zone_id' => 1])->save();
        $szr = $this->createZoneAdmin(1);

        // SZR: la pagina di modifica non offre la scelta del tipo
        $this->actingAs($szr)->get(route('admin.tournaments.edit', $tournament))
            ->assertOk()
            ->assertDontSee('<select name="tournament_type_id"', false)
            ->assertSee('Il tipo non si cambia dopo la creazione');

        // SZR: un cambio forzato viene rifiutato
        $this->actingAs($szr)->put(route('admin.tournaments.update', $tournament), $this->updateData($tournament, $typeB->id))
            ->assertSessionHasErrors('tournament_type_id');
        $this->assertSame($typeA->id, (int) Tournament::findOrFail($tournament->id)->tournament_type_id);

        // SZR: il resto si modifica ancora (tipo invariato)
        $this->actingAs($szr)->put(route('admin.tournaments.update', $tournament), ['name' => 'Nome nuovo'] + $this->updateData($tournament, $typeA->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nome nuovo', Tournament::findOrFail($tournament->id)->name);

        // Super admin: puo' correggere
        $this->actingAs($this->createSuperAdmin())
            ->put(route('admin.tournaments.update', $tournament), $this->updateData($tournament->fresh() ?? $tournament, $typeB->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($typeB->id, (int) Tournament::findOrFail($tournament->id)->tournament_type_id);
    }
}
