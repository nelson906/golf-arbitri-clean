<?php

namespace App\Services;

use App\Helpers\ZoneHelper;
use App\Http\Helpers\RefereeRoleHelper;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentGenerationService
{
    /**
     * Gets the correct zone folder for tournament file storage
     */
    public function getZoneFolder(Tournament $tournament): string
    {
        return ZoneHelper::getFolderCodeForTournament($tournament);
    }

    /**
     * Generate convocation for entire tournament
     */
    public function generateConvocationForTournament(Tournament $tournament, ?TournamentNotification $notification = null): array
    {
        try {
            $tournament->load([
                'club',
                'zone',
                'tournamentType',
                'assignments.user',
            ]);

            // If a specific notification is not provided, try to find the latest one
            if (! $notification) {
                $notification = TournamentNotification::where('tournament_id', $tournament->id)
                    ->latest()
                    ->first();
            }

            if ($notification) {
                $notification->loadMissing('clauseSelections.clause');
            }

            // Carica template dalla zona
            $templatePath = $this->getZoneTemplatePath($tournament->zone_id);

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
                                'category' => $selection->clause->category,
                            ],
                        ];
                    })
                    ->toArray();
            }

            $variables = [
                'tournament_name' => ucwords(strtolower($tournament->name)), // ✅ Prima lettera maiuscola
                'tournament_dates' => $this->formatTournamentDates($tournament), // ✅ Date formattate
                'club_name' => $tournament->club->name,
                'zone_name' => $tournament->zone->name ?? 'Zona Non Specificata',
                'current_date' => Carbon::now()->format('d/m/Y'),
                'clauses' => $selectedClauses,
            ];

            // Genera nome file
            $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
            $tournamentName = substr($tournamentName, 0, 50);
            $filename = "convocazione_{$tournament->id}_{$tournamentName}.docx";
            $outputPath = storage_path('app/temp/'.$filename);

            // Carica template, sostituisci variabili e aggiungi arbitri (docType: referee)
            $this->processTemplateWithReferees($templatePath, $variables, $tournament, $outputPath, 'referee');

            return [
                'path' => $outputPath,
                'filename' => $filename,
                'type' => 'convocation',
            ];
        } catch (\Exception $e) {
            Log::error('Error generating tournament convocation', [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'zone_id' => $tournament->zone_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Errore nella generazione della convocazione: '.$e->getMessage());
        }
    }

    /**
     * Generate facsimile for club
     */
    public function generateClubDocument(Tournament $tournament, ?TournamentNotification $notification = null): array
    {
        try {
            // Crea documento PHPWord
            $phpWord = new PhpWord;

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
                'doubleStrikethrough' => true,
            ]);

            $paragraphStyleName = 'pStyle';
            $phpWord->addParagraphStyle($paragraphStyleName, [
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'spaceAfter' => 100,
            ]);

            $phpWord->addTitleStyle(1, [
                'underline' => 'single',
                'allCaps' => true,
                'color' => 'red',
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            ], ['spaceAfter' => 240]);

            $phpWord->addParagraphStyle('ConoscenzaStyle', [
                'indentation' => ['left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(12)],
                'spaceAfter' => 100,
            ]);

            $phpWord->addParagraphStyle('ComitatoStyle', [
                'tabs' => [new \PhpOffice\PhpWord\Style\Tab('left', (int) \PhpOffice\PhpWord\Shared\Converter::cmToTwip(6))],
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
                'FAC SIMILE DA INVIARE SU CARTA INTESTATA DEL CIRCOLO ORGANIZZATORE'."\n\r",
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
            $section->addText('Ufficio Campionati', null, 'ConoscenzaStyle');

            // Aggiungi SZR - Controllo null safety per zone
            $zoneId = $tournament->zone ? ($tournament->zone->id ?? 'X') : 'X';
            $section->addText("Sezione Zonale Regole {$zoneId}", null, 'ConoscenzaStyle');

            // Oggetto
            $dateRange = $tournament->start_date->format('d/m/Y');
            if ($tournament->end_date && ! $tournament->start_date->isSameDay($tournament->end_date)) {
                $dateRange .= ' al '.$tournament->end_date->format('d/m/Y');
            }

            $tournamentName = $tournament->name ?? 'Torneo senza nome';
            $oggetto = "OGGETTO: GARA {$tournamentName} {$dateRange}";
            $oggetto = htmlspecialchars($oggetto);

            $section->addText($oggetto, ['bold' => true], ['spaceBefore' => 240, 'spacing' => 240]);
            $section->addTextBreak();

            // Preambolo identico
            $preambolo = 'In qualità di Circolo Organizzatore Vi comunichiamo che siete convocati per la manifestazione indicata in '.
                'oggetto con i compiti/ruoli sottoindicati:';

            $section->addText($preambolo, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120,
            ]);

            // Comitato di Gara
            $section->addText('Comitato di Gara', [
                'italic' => true,
                'underline' => 'single',
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
                        $assignment->user->name."\t".$ruolo,
                        ['bold' => true],
                        'ComitatoStyle'
                    );
                }
            }

            $section->addTextBreak();

            // Istruzioni finali identiche
            $preparazione = "Il Comitato e gli Osservatori sono tenuti a presenziare dalle ore 9.00 del giorno precedente l'inizio della ".
                'manifestazione sino al termine della stessa o secondo le decisioni che verranno direttamente comunicate dal '.
                'Direttore di Torneo.';

            $section->addText($preparazione, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120,
            ]);

            // Spese - Testo identico
            $spese = 'Si ricorda che questo Circolo Organizzatore, rimborserà le eventuali spese di viaggio, vitto e alloggio, così come '.
                'previsto dalla Normativa Tecnica in vigore. Il rimborso sarà effettuato sulla base della nota spese emessa dal '.
                'singolo soggetto. Tutte le spese sono rimborsate nei limiti previsti dalla FIG e indicati nelle "Linee guida '.
                'trasferte e rimborsi spese" annualmente pubblicate.';

            $section->addText($spese, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120,
            ]);

            // Conferma - Controllo null safety per zone e club
            $zoneId = $tournament->zone ? ($tournament->zone->id ?? 'X') : 'X';

            // Gestione sicura del contact_info del club
            $clubEmail = 'email-non-disponibile@esempio.com';
            if ($tournament->club && $tournament->club->email) {
                $clubEmail = $tournament->club->email;
            }

            $conferma = "Si prega di confermare la propria presenza sia alla Sezione Zonale Regole di competenza (szr{$zoneId}@federgolf.it) sia ".
                "a questo Circolo Organizzatore ({$clubEmail})";

            $section->addText($conferma, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120,
            ]);

            // Saluti identici
            $saluti = 'Cordiali saluti.';

            $section->addText($saluti, null, [
                'align' => 'both',
                'spaceBefore' => 120,
                'lineHeight' => 1.5,
                'spacing' => 120,
            ]);

            // Genera nome file con pattern standard come in gestione_arbitri
            $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
            $tournamentName = substr($tournamentName, 0, 50);
            $filename = "lettera_circolo_{$tournament->id}_{$tournamentName}.docx";
            $tempPath = storage_path('app/temp/'.$filename);

            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0777, true);
            }

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            return [
                'path' => $tempPath,
                'filename' => $filename,
                'type' => 'club_letter',
            ];
        } catch (\Exception $e) {
            Log::error('Errore generazione documento circolo', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    // ========================================
    // METODI CON TEMPLATEPROCESSOR
    // ========================================

    /**
     * Get zone template path
     */
    protected function getZoneTemplatePath($zoneId): string
    {
        $zoneCode = $this->getZoneCode($zoneId);
        // Check if storage directory exists
        $storageDir = storage_path('lettere_intestate');
        if (! is_dir($storageDir)) {
            Log::error('Template directory does not exist', ['directory' => $storageDir]);
            throw new \Exception('Directory dei template non trovata. Contattare l\'amministratore.');
        }

        $templatePath = "{$storageDir}/lettera_intestata_{$zoneCode}.docx";

        if (! file_exists($templatePath)) {
            Log::warning('Zone template not found, using default', [
                'zone_id' => $zoneId,
                'attempted_path' => $templatePath,
            ]);
            $templatePath = storage_path('lettere_intestate/lettera_intestata_default.docx');
        }

        if (! file_exists($templatePath)) {
            Log::error('Template not found', [
                'zone_id' => $zoneId,
                'template_path' => $templatePath,
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
     * Process template with referees list
     */
    protected function processTemplateWithReferees($templatePath, array $variables, Tournament $tournament, $outputPath, string $docType): void
    {
        if (! class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
            throw new \Exception('TemplateProcessor class not found. Install PhpWord with composer require phpoffice/phpword');
        }

        $templateProcessor = new TemplateProcessor($templatePath);

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
                'referee_level' => ucwords($assignment->user->level ?? ''),
            ];
        })->toArray();

        // Se il template ha placeholder per arbitri, clona le righe o blocchi
        try {
            if (count($refereesList) > 0) {
                // Prova a clonare le righe degli arbitri (se esistono nel template)
                $templateProcessor->cloneRow('referee_name', count($refereesList));

                foreach ($refereesList as $index => $referee) {
                    $templateProcessor->setValue('referee_name#'.($index + 1), $referee['referee_name']);
                    $templateProcessor->setValue('referee_role#'.($index + 1), $referee['referee_role']);
                    $templateProcessor->setValue('referee_code#'.($index + 1), $referee['referee_code']);
                    $templateProcessor->setValue('referee_level#'.($index + 1), $referee['referee_level']);
                }
            }
        } catch (\Exception $e) {
            // Se tutto fallisce, procedi solo con le variabili base
        }

        // Crea directory se non esiste
        if (! is_dir(dirname($outputPath))) {
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
            return $startDate->format('d').'-'.$endDate->format('d/m/Y');
        }

        // Mesi diversi
        return $startDate->format('d/m/Y').' - '.$endDate->format('d/m/Y');
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
                'CLAUSOLA_CLUB_RESPONSABILITA',
            ],
            'referee' => [
                'CLAUSOLA_ARBITRO_RESPONSABILITA',
                'CLAUSOLA_ARBITRO_COMUNICAZIONI',
                'CLAUSOLA_ARBITRO_ALTRO',
            ],
            'institutional' => [
                'CLAUSOLA_ISTITUZIONALE_RESPONSABILITA',
            ],
        ];

        return $placeholders[$type] ?? [];
    }

    /**
     * Get block names for clause placeholders
     * Block format in Word template: ${BLOCCO_CLAUSOLA_X}content${/BLOCCO_CLAUSOLA_X}
     */
    private function getClauseBlockName(string $placeholderCode): string
    {
        return 'BLOCCO_'.$placeholderCode;
    }

    /**
     * Process clause blocks in document
     * Processes ALL clause types, filling selected ones and removing non-selected
     */
    private function processClauseBlocks($templateProcessor, array $clauses, string $docType): void
    {
        // Processa TUTTI i tipi di placeholder (club, referee, institutional)
        // perché il template potrebbe contenere placeholder di più tipi
        $allPlaceholders = array_merge(
            $this->getPlaceholdersForDocumentType('club'),
            $this->getPlaceholdersForDocumentType('referee'),
            $this->getPlaceholdersForDocumentType('institutional')
        );

        foreach ($allPlaceholders as $placeholderCode) {
            $blockName = $this->getClauseBlockName($placeholderCode);

            try {
                if (isset($clauses[$placeholderCode]) && ! empty($clauses[$placeholderCode]['content'])) {
                    // Clausola presente: sostituisci il placeholder interno con il contenuto
                    // Prima sostituisci il placeholder, poi rimuovi i marcatori del blocco
                    $templateProcessor->setValue($placeholderCode, $clauses[$placeholderCode]['content']);

                    // Rimuovi i marcatori di apertura e chiusura del blocco
                    $templateProcessor->setValue($blockName, '');
                    $templateProcessor->setValue('/'.$blockName, '');
                } else {
                    // Clausola non selezionata: rimuovi completamente il blocco (0 cloni)
                    $templateProcessor->cloneBlock($blockName, 0, true, true);
                }
            } catch (\Exception $e) {
                // Se il blocco non esiste nel template, prova con setValue semplice
                if (isset($clauses[$placeholderCode]) && ! empty($clauses[$placeholderCode]['content'])) {
                    try {
                        $templateProcessor->setValue($placeholderCode, $clauses[$placeholderCode]['content']);
                    } catch (\Exception $e2) {
                        // Placeholder non esiste nel template - ignora silenziosamente
                    }
                } else {
                    // Rimuovi placeholder vuoto se esiste
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
