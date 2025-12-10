<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Helpers\RefereeRoleHelper;

class DocumentGenerationService
{
    /**
     * Gets the correct zone folder for tournament file storage
     */
    public function getZoneFolder(Tournament $tournament): string
    {
        // Se è nazionale, va in CRC
        if (
            $tournament->is_national ||
            ($tournament->tournamentType && $tournament->tournamentType->is_national)
        ) {
            return 'CRC';
        }

        // Altrimenti usa la zona del circolo
        $zoneId = $tournament->club->zone_id ?? $tournament->zone_id;

        return match ($zoneId) {
            1 => 'SZR1',
            2 => 'SZR2',
            3 => 'SZR3',
            4 => 'SZR4',
            5 => 'SZR5',
            6 => 'SZR6',
            7 => 'SZR7',
            default => 'SZR' . $zoneId
        };
    }

    /**
     * Deletes a convocation document
     */
    public function deleteConvocation(Tournament $tournament): bool
    {
        if ($tournament->convocation_file_path) {
            return Storage::disk('public')->delete($tournament->convocation_file_path);
        }
        return false;
    }

    /**
     * Deletes a club letter document
     */
    public function deleteClubLetter(Tournament $tournament): bool
    {
        if ($tournament->club_letter_file_path) {
            return Storage::disk('public')->delete($tournament->club_letter_file_path);
        }
        return false;
    }

    /**
     * Stores a document in the correct zone folder
     */
    protected function storeInZone(array $fileData, Tournament $tournament, string $extension): string
    {
        Log::info('Starting storeInZone', [
            'tournament_id' => $tournament->id,
            'extension' => $extension,
            'file_data' => array_keys($fileData)
        ]);

        $zone = $this->getZoneFolder($tournament);
        $filename = $fileData['filename'];
        $relativePath = "convocazioni/{$zone}/generated/{$filename}";

        // Verifica che il file sorgente esista
        if (!file_exists($fileData['path'])) {
            Log::error('Source file does not exist', [
                'path' => $fileData['path'],
                'tournament_id' => $tournament->id
            ]);
            throw new \Exception("File sorgente non trovato: {$fileData['path']}");
        }

        // Assicurati che la directory esista
        $fullPath = Storage::disk('public')->path(dirname($relativePath));
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Copia il file binario direttamente (preserva integrità DOCX)
        $destFullPath = Storage::disk('public')->path($relativePath);

        try {
            Log::info('Attempting to copy file', [
                'source' => $fileData['path'],
                'destination' => $destFullPath
            ]);

            $saved = copy($fileData['path'], $destFullPath);

            if (!$saved) {
                Log::error('Failed to copy file', [
                    'relative_path' => $relativePath,
                    'tournament_id' => $tournament->id
                ]);
                throw new \Exception("Impossibile salvare il file in: {$relativePath}");
            }

            Log::info('File stored successfully', [
                'relative_path' => $relativePath,
                'tournament_id' => $tournament->id
            ]);
        } catch (\Exception $e) {
            Log::error('Exception storing file', [
                'relative_path' => $relativePath,
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        // INFINE elimina il file temporaneo
        if (file_exists($fileData['path'])) {
            unlink($fileData['path']);
        }

        return $relativePath;
    }
    /**
     * Generate convocation letter for a single assignment
     */
    public function generateConvocationLetter(Assignment $assignment): string
    {
        try {
            $assignment->load(['user', 'tournament.club', 'tournament.zone', 'assignedBy']);

            // Usa TemplateProcessor per sostituire variabili
            $templatePath = $this->getZoneTemplatePath($assignment->tournament->zone_id);
            $variables = $this->getConvocationVariables($assignment);

            $filename = $this->generateFilename('convocation', $assignment);
            $outputPath = storage_path('app/temp/' . $filename);

            // Carica template e sostituisci variabili
            $this->processTemplate($templatePath, $variables, $outputPath, 'referee');

            $path = $this->saveGeneratedFile($outputPath, $assignment->tournament, $filename);

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
     */
    public function generateConvocationForTournament(Tournament $tournament, ?TournamentNotification $notification = null): array
    {
        Log::info('Starting convocation generation for tournament', [
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'zone_id' => $tournament->zone_id
        ]);
        try {
            $tournament->load([
                'club',
                'zone',
                'tournamentType',
                'assignments.user'
            ]);

            // If a specific notification is not provided, try to find the latest one
            if (!$notification) {
                $notification = TournamentNotification::where('tournament_id', $tournament->id)
                    ->latest()
                    ->first();
            }

            if ($notification) {
                $notification->loadMissing('clauseSelections.clause');
            }

            // Carica template dalla zona
            $templatePath = $this->getZoneTemplatePath($tournament->zone_id);

            Log::info('Selected template path', [
                'template_path' => $templatePath
            ]);

            // Prepara variabili per sostituzione
            // Prepara le clausole dal database (usando la notifica specifica se presente)
            $selectedClauses = [];

            if ($notification) {
                $selectedClauses = $notification->clauseSelections
                    ->mapWithKeys(function ($selection) {
                        return [
                            $selection->placeholder_code => [
                                'content' => $selection->clause->content,
                                'title' => $selection->clause->title,
                                'category' => $selection->clause->category
                            ]
                        ];
                    })
                    ->toArray();
            }

            Log::info('Selected clauses', ['clauses' => $selectedClauses]);

            $variables = [
                'tournament_name' => ucwords(strtolower($tournament->name)), // ✅ Prima lettera maiuscola
                'tournament_dates' => $this->formatTournamentDates($tournament), // ✅ Date formattate
                'club_name' => $tournament->club->name,
                'zone_name' => $tournament->zone->name ?? 'Zona Non Specificata',
                'current_date' => Carbon::now()->format('d/m/Y'),
                'clauses' => $selectedClauses
            ];

            // Genera nome file
            $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
            $tournamentName = substr($tournamentName, 0, 50);
            $filename = "convocazione_{$tournament->id}_{$tournamentName}.docx";
            $outputPath = storage_path('app/temp/' . $filename);

            // Carica template, sostituisci variabili e aggiungi arbitri (docType: referee)
            $this->processTemplateWithReferees($templatePath, $variables, $tournament, $outputPath, 'referee');

            return [
                'path' => $outputPath,
                'filename' => $filename,
                'type' => 'convocation'
            ];
        } catch (\Exception $e) {
            Log::error('Error generating tournament convocation', [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'zone_id' => $tournament->zone_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Errore nella generazione della convocazione: ' . $e->getMessage());
        }
    }

    /**
     * Generate club letter for a tournament
     */
    public function generateClubLetter(Tournament $tournament, ?TournamentNotification $notification = null): string
    {
        try {
            $tournament->load(['club', 'zone', 'tournamentType', 'assignments.user']);

            // Carica template dalla zona
            $templatePath = $this->getZoneTemplatePath($tournament->zone_id);
            $variables = $this->getClubLetterVariables($tournament);

            // Carica clausole dalla notifica (se presente)
            if (!$notification) {
                $notification = TournamentNotification::where('tournament_id', $tournament->id)
                    ->latest()
                    ->first();
            }
            if ($notification) {
                $notification->loadMissing('clauseSelections.clause');
                $variables['clauses'] = $notification->clauseSelections
                    ->mapWithKeys(function ($selection) {
                        return [
                            $selection->placeholder_code => [
                                'content' => $selection->clause->content,
                                'title' => $selection->clause->title,
                                'category' => $selection->clause->category
                            ]
                        ];
                    })->toArray();
            }

            $filename = $this->generateFilename('club_letter', $tournament);
            $outputPath = storage_path('app/temp/' . $filename);

            // Carica template e sostituisci variabili (docType: club)
            $this->processTemplateWithReferees($templatePath, $variables, $tournament, $outputPath, 'club');

            $path = $this->saveGeneratedFile($outputPath, $tournament, $filename);

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
    public function generateClubDocument(Tournament $tournament, ?TournamentNotification $notification = null): array
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
                    // Gestione corretta di tutti i ruoli incluso Osservatore
                    $ruolo = match ($assignment->role) {
                        'Direttore di Torneo' => 'Direttore di Torneo',
                        'Osservatore' => 'Osservatore',
                        default => 'Arbitro'
                    };
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

            // Genera nome file con pattern standard come in gestione_arbitri
            $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
            $tournamentName = substr($tournamentName, 0, 50);
            $filename = "lettera_circolo_{$tournament->id}_{$tournamentName}.docx";
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

        $relativePath = $this->storeInZone($fileData, $tournament, 'docx');

        // Elimina file temporaneo
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $relativePath;
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
            'referee_level' => ucwords($referee->level),
            'tournament_name' => ucwords(strtolower($tournament->name)), // ✅ Formattato
            'tournament_dates' => $this->formatTournamentDates($tournament), // ✅ Date formattate
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
            $table->addCell(2000)->addText(ucwords($assignment->user->level));
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


    // ========================================
    // NUOVI METODI CON TEMPLATEPROCESSOR
    // ========================================

    /**
     * Get zone template path
     */
    protected function getZoneTemplatePath($zoneId): string
    {
        Log::info('Getting zone template path', [
            'zone_id' => $zoneId
        ]);
        $zoneCode = $this->getZoneCode($zoneId);
        // Check if storage directory exists
        $storageDir = storage_path('lettere_intestate');
        if (!is_dir($storageDir)) {
            Log::error('Template directory does not exist', ['directory' => $storageDir]);
            throw new \Exception('Directory dei template non trovata. Contattare l\'amministratore.');
        }

        $templatePath = "{$storageDir}/lettera_intestata_{$zoneCode}.docx";

        if (!file_exists($templatePath)) {
            Log::warning('Zone template not found, using default', [
                'zone_id' => $zoneId,
                'attempted_path' => $templatePath
            ]);
            $templatePath = storage_path("lettere_intestate/lettera_intestata_default.docx");
        }

        if (!file_exists($templatePath)) {
            Log::error('Template not found', [
                'zone_id' => $zoneId,
                'template_path' => $templatePath
            ]);
            throw new \Exception("Template non trovato: {$templatePath}. Controlla che i file template esistano in storage/lettere_intestate/");
        }

        return $templatePath;
    }

    /**
     * Get zone code from zone ID
     */
    protected function getZoneCode($zoneId): string
    {
        return match ($zoneId) {
            1 => 'szr1',
            2 => 'szr2',
            3 => 'szr3',
            4 => 'szr4',
            5 => 'szr5',
            6 => 'szr6',
            7 => 'szr7',
            default => 'default'
        };
    }
    /**
     * Get zone logo as base64
     */
    protected function getZoneLogo($zoneId): ?string
    {
        $zoneCode = $this->getZoneCode($zoneId);

        // Check if logo directory exists
        $logoDir = storage_path('logo');
        if (!is_dir($logoDir)) {
            Log::error('Logo directory does not exist', ['directory' => $logoDir]);
            return null;
        }

        // Prova logo specifico zona
        $logoPath = "{$logoDir}/fig-{$zoneCode}.png";
        if (file_exists($logoPath)) {
            return base64_encode(file_get_contents($logoPath));
        }

        // Fallback logo generico FIG
        $defaultLogo = storage_path("logo/logo_fig.png");
        if (file_exists($defaultLogo)) {
            return base64_encode(file_get_contents($defaultLogo));
        }

        return null;
    }

    /**
     * Process template with TemplateProcessor
     */
    protected function processTemplate($templatePath, array $variables, $outputPath, string $docType): void
    {
        // Verify template exists
        if (!file_exists($templatePath)) {
            Log::error('Template file not found', ['path' => $templatePath]);
            throw new \Exception('Template non trovato: ' . basename($templatePath));
        }

        if (!class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
            throw new \Exception("TemplateProcessor class not found. Install PhpWord with composer require phpoffice/phpword");
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // Log variabili disponibili nel template
        try {
            $templateVars = $templateProcessor->getVariables();
            Log::info('Template variables found:', ['variables' => $templateVars]);
        } catch (\Throwable $e) {
            Log::warning('Could not read template variables: ' . $e->getMessage());
        }

        // Sostituisci tutte le variabili base (escluse le clausole)
        foreach ($variables as $key => $value) {
            if ($key !== 'clauses') {
                $templateProcessor->setValue($key, $value ?? '');
            }
        }

        // Gestione clausole con blocchi rimovibili
        $clauses = $variables['clauses'] ?? [];
        Log::info('Processing clauses for document', [
            'template' => basename($templatePath),
            'docType' => $docType,
            'clauseCount' => count($clauses)
        ]);
        $this->processClauseBlocks($templateProcessor, $clauses, $docType);

        $templateProcessor->saveAs($outputPath);
    }

    /**
     * Process template with referees list
     */
    protected function processTemplateWithReferees($templatePath, array $variables, Tournament $tournament, $outputPath, string $docType): void
    {
        if (!class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
            throw new \Exception("TemplateProcessor class not found. Install PhpWord with composer require phpoffice/phpword");
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // Log variabili disponibili nel template
        try {
            $templateVars = $templateProcessor->getVariables();
            Log::info('Template variables found (with referees):', ['variables' => $templateVars]);
        } catch (\Throwable $e) {
            Log::warning('Could not read template variables (with referees): ' . $e->getMessage());
        }

        // Sostituisci variabili base
        // TemplateProcessor usa ${variable}, NON {{variable}}
        foreach ($variables as $key => $value) {
            // Skip clauses array as it's handled separately
            if ($key !== 'clauses') {
                $templateProcessor->setValue($key, $value ?? '');
            }
        }

        // Gestione speciale per le clausole
        if (isset($variables['clauses']) && is_array($variables['clauses'])) {
            // Ottieni i placeholder disponibili per questo tipo
            $availablePlaceholders = $this->getPlaceholdersForDocumentType($docType);


            // Usa cloneBlock per rimuovere interi paragrafi quando la clausola non è selezionata
            $clauses = $variables['clauses'] ?? [];
            $this->processClauseBlocks($templateProcessor, $clauses, $docType);
        }

        // Aggiungi lista arbitri se il template ha placeholder
        $sortedAssignments = RefereeRoleHelper::sortByRole($tournament->assignments);

        // Prepara lista arbitri
        $refereesList = $sortedAssignments->map(function ($assignment) {
            return [
                'referee_name' => $assignment->user->name,
                'referee_role' => $this->translateRole($assignment->role),
                'referee_code' => $assignment->user->referee_code ?? '',
                'referee_level' => ucwords($assignment->user->level ?? '')
            ];
        })->toArray();

        // Se il template ha placeholder per arbitri, clona le righe o blocchi
        try {
            if (count($refereesList) > 0) {
                // Prova a clonare le righe degli arbitri (se esistono nel template)
                $templateProcessor->cloneRow('referee_name', count($refereesList));

                foreach ($refereesList as $index => $referee) {
                    $templateProcessor->setValue("referee_name#" . ($index + 1), $referee['referee_name']);
                    $templateProcessor->setValue("referee_role#" . ($index + 1), $referee['referee_role']);
                    $templateProcessor->setValue("referee_code#" . ($index + 1), $referee['referee_code']);
                    $templateProcessor->setValue("referee_level#" . ($index + 1), $referee['referee_level']);
                }
            }
        } catch (\Exception $e) {
            // Se tutto fallisce, procedi solo con le variabili base
            Log::info('Template without referee loops, proceeding with basic variables', ['error' => $e->getMessage()]);
        }

        // Crea directory se non esiste
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0777, true);
        }

        $templateProcessor->saveAs($outputPath);
    }

    /**
     * Translate role to Italian
     */
    protected function translateRole($role): string
    {
        return match ($role) {
            'Tournament Director' => 'Direttore di Torneo',
            'Direttore di Torneo' => 'Direttore di Torneo',
            'Observer' => 'Osservatore',
            'Osservatore' => 'Osservatore',
            'Referee' => 'Arbitro',
            'Arbitro' => 'Arbitro',
            default => $role
        };
    }

    /**
     * Save generated file using FileStorageService
     */
    protected function saveGeneratedFile($tempPath, Tournament $tournament, $filename): string
    {
        $fileData = [
            'path' => $tempPath,
            'filename' => $filename,
            'type' => 'generated'
        ];

        $relativePath = $this->storeInZone($fileData, $tournament, 'docx');

        // Elimina file temporaneo
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $relativePath;
    }

    /**
     * Generate PDF version of convocation
     */
    public function generateConvocationPDF(Tournament $tournament): string
    {
        try {
            // First generate the DOCX
            $convocationData = $this->generateConvocationForTournament($tournament);

            // Convert DOCX to HTML using PhpWord
            $phpWord = IOFactory::load($convocationData['path']);

            // Create temp directory if it doesn't exist
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            // Create temp HTML file
            $htmlPath = "{$tempDir}/convocation.html";
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            $htmlWriter->save($htmlPath);

            // Read HTML content
            $htmlContent = file_get_contents($htmlPath);

            // Generate PDF using Barryvdh\DomPDF
            $pdf = PDF::loadHTML($htmlContent);

            // Generate filename
            $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
            $tournamentName = substr($tournamentName, 0, 50);
            $filename = "convocazione_{$tournament->id}_{$tournamentName}.pdf";

            // Save PDF
            $tempPath = storage_path('app/temp/' . $filename);
            $pdf->save($tempPath);

            // Clean up HTML file
            if (file_exists($htmlPath)) {
                unlink($htmlPath);
            }

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Error generating PDF convocation', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Format tournament dates for templates
     */
    protected function formatTournamentDates(Tournament $tournament): string
    {
        $startDate = Carbon::parse($tournament->start_date);
        $endDate = Carbon::parse($tournament->end_date);

        // Se stesso giorno
        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('d/m/Y');
        }

        // Se stesso mese
        if ($startDate->format('m/Y') === $endDate->format('m/Y')) {
            return $startDate->format('d') . '-' . $endDate->format('d/m/Y');
        }

        // Mesi diversi
        return $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
    }

    /**
     * Replace clause placeholders in Word document
     */
    private function replacePlaceholdersWithClauses($templateProcessor, array $clauses, string $documentType): void
    {
        // Sostituisci i placeholder con il contenuto delle clausole
        foreach ($clauses as $placeholderCode => $clauseContent) {
            try {
                $templateProcessor->setValue($placeholderCode, $clauseContent);

                Log::debug("Replaced placeholder in {$documentType}", [
                    'placeholder' => $placeholderCode,
                    'content_length' => strlen($clauseContent)
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not replace placeholder {$placeholderCode}: " . $e->getMessage());
            }
        }
    }

    /**
     * Clear unused clause placeholders from document
     */
    private function clearUnusedPlaceholders($templateProcessor): void
    {
        try {
            $variables = $templateProcessor->getVariables();

            foreach ($variables as $variable) {
                // Rimuovi solo i placeholder delle clausole non compilati
                if (str_starts_with($variable, 'CLAUSOLA_')) {
                    $templateProcessor->setValue($variable, '');

                    Log::debug('Cleared unused placeholder', ['placeholder' => $variable]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not clear unused placeholders: ' . $e->getMessage());
        }
    }

    /**
     * Get available placeholders for document type
     * Placeholder names are now document-specific to avoid conflicts
     */
    private function getPlaceholdersForDocumentType(string $type): array
    {
        $placeholders = [
            'club' => [
                'CLAUSOLA_CLUB_SPESE',
                'CLAUSOLA_CLUB_LOGISTICA',
                'CLAUSOLA_CLUB_RESPONSABILITA'
            ],
            'referee' => [
                'CLAUSOLA_ARBITRO_RESPONSABILITA',
                'CLAUSOLA_ARBITRO_COMUNICAZIONI',
                'CLAUSOLA_ARBITRO_ALTRO'
            ],
            'institutional' => [
                'CLAUSOLA_ISTITUZIONALE_RESPONSABILITA'
            ]
        ];

        return $placeholders[$type] ?? [];
    }


    /**
     * Get block names for clause placeholders
     * Block format in Word template: ${BLOCCO_CLAUSOLA_X}content${/BLOCCO_CLAUSOLA_X}
     */
    private function getClauseBlockName(string $placeholderCode): string
    {
        return 'BLOCCO_' . $placeholderCode;
    }

    /**
     * Process clause blocks in document
     * Uses cloneBlock to remove entire block when clause is not selected
     */
    private function processClauseBlocks($templateProcessor, array $clauses, string $docType): void
    {
        $availablePlaceholders = $this->getPlaceholdersForDocumentType($docType);

        foreach ($availablePlaceholders as $placeholderCode) {
            $blockName = $this->getClauseBlockName($placeholderCode);

            try {
                if (isset($clauses[$placeholderCode]) && !empty($clauses[$placeholderCode]['content'])) {
                    // Clausola presente: clona il blocco 1 volta e sostituisci il contenuto
                    $templateProcessor->cloneBlock($blockName, 1, true, true);
                    $templateProcessor->setValue($placeholderCode, $clauses[$placeholderCode]['content']);

                    Log::debug("Clause block filled", [
                        'block' => $blockName,
                        'placeholder' => $placeholderCode,
                        'content_length' => strlen($clauses[$placeholderCode]['content'])
                    ]);
                } else {
                    // Clausola non selezionata: rimuovi completamente il blocco (0 cloni)
                    $templateProcessor->cloneBlock($blockName, 0, true, true);

                    Log::debug("Clause block removed (no selection)", [
                        'block' => $blockName,
                        'placeholder' => $placeholderCode
                    ]);
                }
            } catch (\Exception $e) {
                // Se il blocco non esiste nel template, prova con setValue semplice
                Log::warning("Block {$blockName} not found, trying simple setValue", [
                    'error' => $e->getMessage()
                ]);

                if (isset($clauses[$placeholderCode]) && !empty($clauses[$placeholderCode]['content'])) {
                    try {
                        $templateProcessor->setValue($placeholderCode, $clauses[$placeholderCode]['content']);
                    } catch (\Exception $e2) {
                        Log::warning("Could not set value for {$placeholderCode}: " . $e2->getMessage());
                    }
                } else {
                    // Rimuovi placeholder vuoto
                    try {
                        $templateProcessor->setValue($placeholderCode, '');
                    } catch (\Exception $e2) {
                        // Ignora se placeholder non esiste
                    }
                }
            }
        }
    }
}
