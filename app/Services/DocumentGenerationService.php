<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use App\Models\Letterhead;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\RefereeRoleHelper;

class DocumentGenerationService
{
    protected $fileStorage;

    public function __construct(FileStorageService $fileStorage)
    {
        $this->fileStorage = $fileStorage;
    }

    /**
     * Generate convocation letter for a single assignment
     */
    public function generateConvocationLetter(Assignment $assignment): string
    {
        try {
            $assignment->load(['user', 'tournament.club', 'tournament.zone', 'assignedBy']);

            $template = $this->getTemplate('convocation', $assignment->tournament);
            $variables = $this->getConvocationVariables($assignment);

            $phpWord = new PhpWord();
            $this->configureDocument($phpWord);

            $section = $phpWord->addSection();
            $this->addLetterhead($section, $assignment->tournament->zone_id);
            $this->addDocumentContent($section, $template, $variables);
            $this->addFooter($section, $assignment->tournament->zone);

            $filename = $this->generateFilename('convocation', $assignment);
            $path = $this->saveDocumentToZone($phpWord, $filename, $assignment->tournament);

            $assignment->update([
                'convocation_file_path' => $path,
                'convocation_generated_at' => Carbon::now(),
            ]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Error generating convocation letter', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

/**
 * Generate convocation for entire tournament
 * RITORNA SEMPRE ARRAY CON path, filename, type
 */
public function generateConvocationForTournament(Tournament $tournament): array
{
    try {
        $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);

        $phpWord = new PhpWord();
        $this->configureDocument($phpWord);

        $section = $phpWord->addSection();
        $this->addLetterhead($section, $tournament->zone_id);

        // Contenuto
        $section->addText(
            'CONVOCAZIONE ARBITRI',
            ['bold' => true, 'size' => 14],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addTextBreak(2);

        $section->addText("Torneo: {$tournament->name}", ['bold' => true]);
        $section->addText("Date: {$tournament->date_range}");
        $section->addText("Circolo: {$tournament->club->name}");
        $section->addTextBreak();

                // ORDINA GLI ARBITRI PER GERARCHIA
        $sortedAssignments = RefereeRoleHelper::sortByRole($tournament->assignments);

        $section->addText("Arbitri convocati:", ['bold' => true]);
        // Raggruppa per ruolo per una visualizzazione più chiara
        $groupedByRole = $sortedAssignments->groupBy('role');

        foreach (['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role) {
            if (isset($groupedByRole[$role])) {
                $section->addTextBreak();
                $section->addText($role . ':', ['bold' => true, 'italic' => true]);
                foreach ($groupedByRole[$role] as $assignment) {
                    $section->addText("  • {$assignment->user->name}", ['indent' => 0.5]);
                }
            }
        }

        $this->addFooter($section, $tournament->zone);

        $filename = 'convocazione-' . Str::slug($tournament->name) . '-' . date('Ymd') . '.docx';
        $tempPath = storage_path('app/temp/' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0777, true);
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempPath);

        return [
            'path' => $tempPath,
            'filename' => $filename,
            'type' => 'convocation'
        ];

    } catch (\Exception $e) {
        Log::error('Error generating tournament convocation: ' . $e->getMessage());
        throw $e;
    }
}


    /**
     * Generate club letter for a tournament
     */
    public function generateClubLetter(Tournament $tournament): string
    {
        try {
            $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);

            $template = $this->getTemplate('club', $tournament);
            $variables = $this->getClubLetterVariables($tournament);

            $phpWord = new PhpWord();
            $this->configureDocument($phpWord);

            $section = $phpWord->addSection();
            $this->addLetterhead($section, $tournament->zone_id);
            $this->addDocumentContent($section, $template, $variables);
            $this->addRefereeList($section, $tournament);
            $this->addFooter($section, $tournament->zone);

            $filename = $this->generateFilename('club_letter', $tournament);
            $path = $this->saveDocumentToZone($phpWord, $filename, $tournament);

            $tournament->update([
                'club_letter_file_path' => $path,
                'club_letter_generated_at' => Carbon::now(),
            ]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Error generating club letter', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate facsimile for club
     */
public function generateClubDocument(Tournament $tournament): array
    {
        try {
            // Crea documento PHPWord
            $phpWord = new PhpWord();

            // Impostazioni lingua e font di default
            $language = new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::IT_IT);
            $phpWord->getSettings()->setThemeFontLang($language);
            $phpWord->setDefaultFontName('Times New Roman');

            // Stili identici alla generateClubLetterDocument
            $fontStyleName = 'rStyle';
            $phpWord->addFontStyle($fontStyleName, [
                'bold' => true,
                'italic' => true,
                'size' => 16,
                'allCaps' => true,
                'doubleStrikethrough' => true
            ]);

            $paragraphStyleName = 'pStyle';
            $phpWord->addParagraphStyle($paragraphStyleName, [
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'spaceAfter' => 100
            ]);

            $phpWord->addTitleStyle(1, [
                'underline' => 'single',
                'allCaps' => true,
                'color' => 'red',
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
            ], ['spaceAfter' => 240]);

            $phpWord->addParagraphStyle('ConoscenzaStyle', [
                'indentation' => ['left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(12)],
                'spaceAfter' => 100
            ]);

            $phpWord->addParagraphStyle('ComitatoStyle', [
                'tabs' => [new \PhpOffice\PhpWord\Style\Tab('left', \PhpOffice\PhpWord\Shared\Converter::cmToTwip(6))],
                'lineHeight' => 1,
                'spacing' => 60,
            ]);

            // Sezione
            $section = $phpWord->addSection();

            // Margini
            $sectionStyle = $section->getStyle();
            $sectionStyle->setMarginLeft(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5));
            $sectionStyle->setMarginRight(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5));

            // Titolo identico
            $section->addTitle(
                'FAC SIMILE DA INVIARE SU CARTA INTESTATA DEL CIRCOLO ORGANIZZATORE' . "\n\r",
                1
            );
            $section->addTextBreak();

            // Destinatari - Arbitri assegnati
            $section->addText('Ai Signori:', null, ['lineHeight' => 1, 'spacing' => 60]);

            // ORDINA GLI ARBITRI PER GERARCHIA (mantengo il tuo sistema)
            $sortedAssignments = RefereeRoleHelper::sortByRole($tournament->assignments);

            foreach ($sortedAssignments as $assignment) {
                // Controllo null safety
                if ($assignment && $assignment->user && $assignment->user->name) {
                    $section->addText($assignment->user->name);
                }
            }

            // Conoscenza
            $section->addText('e p.c.:', null, 'ConoscenzaStyle');

            // Aggiungi Ufficio Campionati
            $section->addText("Ufficio Campionati", null, 'ConoscenzaStyle');

            // Aggiungi SZR - Controllo null safety per zone
            $zoneId = $tournament->zone ? ($tournament->zone->id ?? 'X') : 'X';
            $section->addText("Sezione Zonale Regole {$zoneId}", null, 'ConoscenzaStyle');

            // Oggetto
            $dateRange = $tournament->start_date->format('d/m/Y');
            if ($tournament->end_date && !$tournament->start_date->isSameDay($tournament->end_date)) {
                $dateRange .= " al " . $tournament->end_date->format('d/m/Y');
            }

            $tournamentName = $tournament->name ?? 'Torneo senza nome';
            $oggetto = "OGGETTO: GARA {$tournamentName} {$dateRange}";
            $oggetto = htmlspecialchars($oggetto);

            $section->addText($oggetto, ['bold' => true], ['spaceBefore' => 240, 'spacing' => 240]);
            $section->addTextBreak();

            // Preambolo identico
            $preambolo = "In qualità di Circolo Organizzatore Vi comunichiamo che siete convocati per la manifestazione indicata in " .
                "oggetto con i compiti/ruoli sottoindicati:";

            $section->addText($preambolo, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120
            ]);

            // Comitato di Gara
            $section->addText('Comitato di Gara', [
                'italic' => true,
                'underline' => 'single'
            ], ['spaceBefore' => 240, 'spacing' => 240]);

            // Lista arbitri con ruoli (usando il tuo sistema di ordinamento)
            foreach ($sortedAssignments as $assignment) {
                // Controllo null safety
                if ($assignment && $assignment->user && $assignment->user->name) {
                    $ruolo = $assignment->role === 'Direttore di Torneo' ? 'Direttore di Torneo' : 'Arbitro';
                    $section->addText(
                        $assignment->user->name . "\t" . $ruolo,
                        ['bold' => true],
                        'ComitatoStyle'
                    );
                }
            }

            $section->addTextBreak();

            // Istruzioni finali identiche
            $preparazione = "Il Comitato e gli Osservatori sono tenuti a presenziare dalle ore 9.00 del giorno precedente l'inizio della " .
                "manifestazione sino al termine della stessa o secondo le decisioni che verranno direttamente comunicate dal " .
                "Direttore di Torneo.";

            $section->addText($preparazione, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120
            ]);

            // Spese - Testo identico
            $spese = "Si ricorda che questo Circolo Organizzatore, rimborserà le eventuali spese di viaggio, vitto e alloggio, così come " .
                "previsto dalla Normativa Tecnica in vigore. Il rimborso sarà effettuato sulla base della nota spese emessa dal " .
                "singolo soggetto. Tutte le spese sono rimborsate nei limiti previsti dalla FIG e indicati nelle \"Linee guida " .
                "trasferte e rimborsi spese\" annualmente pubblicate.";

            $section->addText($spese, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120
            ]);

            // Conferma - Controllo null safety per zone e club
            $zoneId = $tournament->zone ? ($tournament->zone->id ?? 'X') : 'X';

            // Gestione sicura del contact_info del club
            $clubEmail = 'email-non-disponibile@esempio.com';
            if ($tournament->club && $tournament->club->email) {
                    $clubEmail = $tournament->club->email;
            }

            $conferma = "Si prega di confermare la propria presenza sia alla Sezione Zonale Regole di competenza (szr{$zoneId}@federgolf.it) sia " .
                "a questo Circolo Organizzatore ({$clubEmail})";

            $section->addText($conferma, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120
            ]);

            // Saluti identici
            $saluti = "Cordiali saluti.";

            $section->addText($saluti, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120
            ]);

            // Mantieni la tua logica di salvataggio
            $filename = 'facsimile-' . Str::slug($tournament->club->name) . '-' . Str::slug($tournament->name) . '.docx';
            $tempPath = storage_path('app/temp/' . $filename);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0777, true);
            }

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            return [
                'path' => $tempPath,
                'filename' => $filename,
                'type' => 'club_letter'
            ];

        } catch (\Exception $e) {
            Log::error('Errore generazione documento circolo', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Save document using FileStorageService
     */
    protected function saveDocumentToZone(PhpWord $phpWord, string $filename, Tournament $tournament): string
    {
        $tempPath = storage_path('app/temp/' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0777, true);
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempPath);

        $fileData = [
            'path' => $tempPath,
            'filename' => $filename,
            'type' => 'generated'
        ];

        $relativePath = $this->fileStorage->storeInZone($fileData, $tournament, 'docx');

        // Elimina file temporaneo
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $relativePath;
    }

    /**
     * Get template for document type
     */
    protected function getTemplate(string $type, Tournament $tournament): ?LetterTemplate
    {
        return LetterTemplate::active()
            ->ofType($type)
            ->forZone($tournament->zone_id)
            ->where('tournament_type_id', $tournament->tournament_type_id)
            ->orderBy('tournament_type_id', 'desc')
            ->orderBy('zone_id', 'desc')
            ->first();
    }

    /**
     * Get convocation variables
     */
    protected function getConvocationVariables(Assignment $assignment): array
    {
        $tournament = $assignment->tournament;
        $referee = $assignment->user;

        return [
            'referee_name' => $referee->name,
            'referee_code' => $referee->referee_code,
            'referee_level' => ucfirst($referee->level),
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'tournament_type' => $tournament->tournamentType->name ?? 'N/A',
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address ?? '',
            'club_phone' => $tournament->club->phone ?? '',
            'club_email' => $tournament->club->email ?? '',
            'zone_name' => $tournament->zone->name,
            'role' => $assignment->role,
            'assignment_notes' => $assignment->notes ?? '',
            'assigned_date' => $assignment->assigned_at->format('d/m/Y'),
            'assigned_by' => $assignment->assignedBy->name ?? 'Sistema',
            'current_date' => Carbon::now()->format('d/m/Y'),
            'current_year' => Carbon::now()->year,
        ];
    }

    /**
     * Get club letter variables
     */
    protected function getClubLetterVariables(Tournament $tournament): array
    {
        $refereeList = $tournament->assignments->map(function ($assignment) {
            return "- {$assignment->user->name} ({$assignment->role})";
        })->implode("\n");

        return [
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'tournament_type' => $tournament->tournamentType->name ?? 'N/A',
            'club_name' => $tournament->club->name,
            'contact_person' => $tournament->club->contact_person ?? 'Responsabile',
            'zone_name' => $tournament->zone->name,
            'total_referees' => $tournament->assignments->count(),
            'referee_list' => $refereeList,
            'current_date' => Carbon::now()->format('d/m/Y'),
            'current_year' => Carbon::now()->year,
        ];
    }

    /**
     * Configure document settings
     */
    protected function configureDocument(PhpWord $phpWord): void
    {
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $properties = $phpWord->getDocInfo();
        $properties->setCreator('Golf Referee System');
        $properties->setCompany('Federazione Italiana Golf');
        $properties->setTitle('Comunicazione Ufficiale');
        $properties->setSubject('Arbitraggio Tornei Golf');
    }

    /**
     * Add letterhead to section
     */
    protected function addLetterhead($section, $zoneId): void
    {
        $letterhead = Letterhead::where('zone_id', $zoneId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();

        if (!$letterhead) {
            $letterhead = Letterhead::whereNull('zone_id')
                ->where('is_default', true)
                ->first();
        }

        if ($letterhead && $letterhead->logo_path) {
            $logoPath = storage_path('app/public/' . $letterhead->logo_path);

            if (file_exists($logoPath)) {
                $section->addImage($logoPath, [
                    'width' => 550,
                    'height' => 80,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                    'marginTop' => 0,
                    'marginBottom' => 300,
                ]);
                $section->addTextBreak(1);
            }
        }
    }

    /**
     * Add document content
     */
    protected function addDocumentContent($section, ?LetterTemplate $template, array $variables): void
    {
        if (!$template) {
            $this->addDefaultContent($section, $variables);
            return;
        }

        $content = $template->body;
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }

        $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);
        Html::addHtml($section, nl2br($content));
    }

    /**
     * Add default content when no template exists
     */
    protected function addDefaultContent($section, array $variables): void
    {
        if (!isset($variables['referee_name'])) {
            // Contenuto per lettera circolo
            $section->addText(
                'COMUNICAZIONE ARBITRI ASSEGNATI',
                ['bold' => true, 'size' => 14],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );
            $section->addTextBreak(2);

            $section->addText("Gentile {$variables['club_name']},");
            $section->addTextBreak();

            $section->addText("Vi comunichiamo gli arbitri assegnati per il torneo:");
            $section->addText("{$variables['tournament_name']}", ['bold' => true]);
            $section->addText("Date: {$variables['tournament_dates']}");
            return;
        }

        // Contenuto per convocazione arbitro
        $section->addText(
            'CONVOCAZIONE ARBITRO',
            ['bold' => true, 'size' => 14],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addTextBreak(2);

        $section->addText("Gentile {$variables['referee_name']},", ['bold' => true]);
        $section->addTextBreak();

        $section->addText("Con la presente La informiamo che è stato/a designato/a per arbitrare:");
        $section->addTextBreak();

        $section->addText("Torneo: {$variables['tournament_name']}", ['bold' => true]);
        $section->addText("Date: {$variables['tournament_dates']}");
        $section->addText("Circolo: {$variables['club_name']}");
        $section->addText("Ruolo: {$variables['role']}");
    }

    /**
     * Add referee list to club letter
     */
protected function addRefereeList($section, Tournament $tournament): void
{
    if ($tournament->assignments->isEmpty()) {
        return;
    }

    // ORDINA GLI ARBITRI
    $sortedAssignments = RefereeRoleHelper::sortByRole($tournament->assignments);

    $section->addTextBreak();
    $section->addText('ARBITRI DESIGNATI:', ['bold' => true, 'size' => 12]);
    $section->addTextBreak();

    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 80
    ]);

    $table->addRow();
    $table->addCell(3000)->addText('Nome', ['bold' => true]);
    $table->addCell(2000)->addText('Codice', ['bold' => true]);
    $table->addCell(2000)->addText('Livello', ['bold' => true]);
    $table->addCell(2000)->addText('Ruolo', ['bold' => true]);

    foreach ($sortedAssignments as $assignment) {
        $table->addRow();

        // Evidenzia il Direttore di Torneo
        $nameStyle = $assignment->role === 'Direttore di Torneo' ? ['bold' => true] : [];

        $table->addCell(3000)->addText($assignment->user->name, $nameStyle);
        $table->addCell(2000)->addText($assignment->user->referee_code);
        $table->addCell(2000)->addText(ucfirst($assignment->user->level));
        $table->addCell(2000)->addText($assignment->role, $nameStyle);
    }
}

    /**
     * Add footer to section
     */
    protected function addFooter($section, $zone): void
    {
        $footer = $section->addFooter();

        $footer->addText(
            "Comitato Regionale Arbitri - {$zone->name}",
            ['size' => 9],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );

        $footer->addText(
            "Documento generato il " . Carbon::now()->format('d/m/Y \a\l\l\e H:i'),
            ['size' => 8, 'italic' => true],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
    }

    /**
     * Generate filename
     */
    public function generateFilename(string $type, $model): string
    {
        $date = Carbon::now()->format('Ymd');

        if ($model instanceof Tournament) {
            $tournament = preg_replace('/[^a-zA-Z0-9_-]/', '_', $model->name);
            $tournament = trim($tournament, '_');
            return "{$type}_{$tournament}.docx";
        }

        if ($model instanceof Assignment) {
            $referee = preg_replace('/[^a-zA-Z0-9_-]/', '_', $model->user->name);
            $tournament = preg_replace('/[^a-zA-Z0-9_-]/', '_', $model->tournament->name);
            return "{$type}_{$date}_{$referee}_{$tournament}.docx";
        }

        return "{$type}_{$date}.docx";
    }

    /**
     * Generate convocation PDF for tournament
     */
public function generateConvocationPDF(Tournament $tournament): string
{
    try {
        $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);

        // ORDINA GLI ARBITRI
        $sortedAssignments = RefereeRoleHelper::sortByRole($tournament->assignments);

        // Prepara dati per la view
        $data = [
            'tournament' => $tournament,
            'letterhead' => $this->getLetterhead($tournament->zone_id),
            'referees' => $sortedAssignments->map(function ($assignment) {
                return [
                    'name' => $assignment->user->name,
                    'role' => $assignment->role,
                    'code' => $assignment->user->referee_code,
                    'level' => $assignment->user->level,
                    'is_director' => $assignment->role === 'Direttore di Torneo'
                ];
            }),
            'generated_at' => Carbon::now()->format('d/m/Y H:i')
        ];

        // Genera PDF usando una view Blade
        $pdf = Pdf::loadView('documents.convocation-pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        // Nome file
        $filename = "convocazione_" . Str::slug($tournament->name) . ".pdf";

        // Salva nella zona corretta
        $zone = $this->fileStorage->getZoneFolder($tournament);
        $relativePath = "convocazioni/{$zone}/generated/{$filename}";

        Storage::disk('public')->put($relativePath, $pdf->output());

        return $relativePath;

    } catch (\Exception $e) {
        Log::error('Error generating convocation PDF: ' . $e->getMessage());
        throw $e;
    }
}

    /**
     * Get letterhead for zone
     */
    protected function getLetterhead($zoneId)
    {
        return Letterhead::where('zone_id', $zoneId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();
    }
}
