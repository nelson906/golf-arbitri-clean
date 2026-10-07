<?php

namespace App\Services;

use App\Helpers\ZoneHelper;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service per la gestione dei documenti delle notifiche
 */
class NotificationDocumentService
{
    public function __construct(
        private DocumentGenerationService $documentService
    ) {}

    /**
     * Genera o rigenera un singolo documento
     */
    public function generateDocument(
        TournamentNotification $notification,
        string $type
    ): string {
        $tournament = $notification->tournament;
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        Log::info('Generating document', [
            'type' => $type,
            'notification_id' => $notification->id,
            'tournament_id' => $tournament->id,
        ]);

        if ($type === 'convocation') {
            $data = $this->documentService->generateConvocationForTournament($tournament, $notification);
            $fileName = basename($data['path']);
            $destPath = $this->docsRoot()."/{$zone}/generated/{$fileName}";

            $this->ensureDirectoryExists($destPath);
            $this->copyDocument($data['path'], $destPath);

            return $fileName;
        }

        if ($type === 'club_letter') {
            $data = $this->documentService->generateClubDocument($tournament, $notification);
            $fileName = basename($data['path']);
            $destPath = $this->docsRoot()."/{$zone}/generated/{$fileName}";

            $this->ensureDirectoryExists($destPath);
            $this->copyDocument($data['path'], $destPath);

            return $fileName;
        }

        throw new \InvalidArgumentException("Invalid document type: {$type}");
    }

    /**
     * Elimina un documento
     */
    public function deleteDocument(
        TournamentNotification $notification,
        string $type
    ): void {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);

        if (empty($documents[$type])) {
            throw new \Exception('Documento non trovato');
        }

        $zone = ZoneHelper::getFolderCodeForTournament($tournament);
        $path = $this->docsRoot()."/{$zone}/generated/{$documents[$type]}";

        if ($this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }

        Log::info("Deleted document: {$type}", [
            'notification_id' => $notification->id,
            'path' => $path,
        ]);
    }

    /**
     * Elimina tutti i documenti di una notifica
     */
    public function deleteAllDocuments(TournamentNotification $notification): void
    {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);

        if (empty($documents)) {
            return;
        }

        $zone = ZoneHelper::getFolderCodeForTournament($tournament);
        $basePath = $this->docsRoot()."/{$zone}/generated/";

        Log::info('Attempting to delete all documents', [
            'zone' => $zone,
            'basePath' => $basePath,
            'documents' => $documents,
        ]);

        foreach (['convocation', 'club_letter'] as $type) {
            if (! empty($documents[$type])) {
                $path = $basePath.$documents[$type];
                if ($this->disk()->exists($path)) {
                    $this->disk()->delete($path);
                    Log::info("Deleted document: {$type}", ['path' => $path]);
                } else {
                    Log::warning("Document not found: {$type}", ['path' => $path]);
                }
            }
        }
    }

    /**
     * Carica un documento manualmente
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    public function uploadDocument(
        TournamentNotification $notification,
        string $type,
        $file
    ): string {
        $tournament = $notification->tournament;
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        // Nome legato al torneo (decisione 2026-10-07): prima si teneva il nome
        // originale, e due tornei della stessa zona che ricaricavano
        // "Convocazione.docx" si sovrascrivevano a vicenda
        $prefix = $type === 'club_letter' ? 'lettera_circolo' : 'convocazione';
        $extension = strtolower($file->getClientOriginalExtension()) === 'doc' ? 'doc' : 'docx';
        $filename = "{$prefix}_{$tournament->id}_corretta.{$extension}";
        // FIX M2: disk privato (era hardcoded 'public')
        $file->storeAs($this->docsRoot()."/{$zone}/generated", $filename, Config::string('golf.documents.disk', 'docs'));

        // La versione precedente (creata o caricata prima) non serve piu'
        $previous = $this->parseDocuments($notification->documents)[$type] ?? null;
        if (is_string($previous) && $previous !== '' && $previous !== $filename) {
            $this->disk()->delete($this->docsRoot()."/{$zone}/generated/{$previous}");
        }

        Log::info('Document uploaded', [
            'notification_id' => $notification->id,
            'type' => $type,
            'filename' => $filename,
        ]);

        return $filename;
    }

    /**
     * Ottiene lo stato dei documenti
     *
     * @return array<string, mixed>
     */
    public function getDocumentsStatus(TournamentNotification $notification): array
    {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        $response = [
            'notification_id' => $notification->id,
            'tournament_id' => $tournament->id,
            'convocation' => null,
            'club_letter' => null,
        ];

        // Check convocazione
        if (! empty($documents['convocation'])) {
            $path = $this->docsRoot()."/{$zone}/generated/{$documents['convocation']}";
            if ($this->disk()->exists($path)) {
                $response['convocation'] = [
                    'filename' => $documents['convocation'],
                    'generated_at' => Carbon::createFromTimestamp(
                        $this->disk()->lastModified($path)
                    )->format('d/m/Y H:i'),
                    'size' => $this->formatBytes($this->disk()->size($path)),
                ];
            }
        }

        // Check lettera circolo
        if (! empty($documents['club_letter'])) {
            $path = $this->docsRoot()."/{$zone}/generated/{$documents['club_letter']}";
            if ($this->disk()->exists($path)) {
                $response['club_letter'] = [
                    'filename' => $documents['club_letter'],
                    'generated_at' => Carbon::createFromTimestamp(
                        $this->disk()->lastModified($path)
                    )->format('d/m/Y H:i'),
                    'size' => $this->formatBytes($this->disk()->size($path)),
                ];
            }
        }

        return $response;
    }

    /**
     * Verifica se i documenti esistono
     *
     * @return array<string, mixed>
     */
    public function checkDocumentsExist(TournamentNotification $notification): array
    {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        return [
            'hasConvocation' => isset($documents['convocation']) &&
                $this->disk()->exists($this->docsRoot()."/{$zone}/generated/{$documents['convocation']}"),
            'hasClubLetter' => isset($documents['club_letter']) &&
                $this->disk()->exists($this->docsRoot()."/{$zone}/generated/{$documents['club_letter']}"),
        ];
    }

    /**
     * Ottiene il path completo di un documento
     */
    public function getDocumentPath(
        TournamentNotification $notification,
        string $type
    ): string {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);

        if (empty($documents[$type])) {
            throw new \Exception('Documento non trovato');
        }

        $zone = ZoneHelper::getFolderCodeForTournament($tournament);
        $path = $this->docsRoot()."/{$zone}/generated/{$documents[$type]}";
        $fullPath = $this->disk()->path($path);

        if (! file_exists($fullPath)) {
            throw new \Exception('File non trovato sul server');
        }

        return $fullPath;
    }

    /**
     * Parse documents field (può essere string JSON o array)
     *
     * `documents` e' una colonna JSON: { convocation: filename, club_letter: filename }.
     * json_decode puo' restituire qualsiasi cosa (o null su JSON malformato) e le
     * righe storiche la contengono come stringa: si filtra ai soli valori stringa
     * invece di dichiarare una forma che il type system crederebbe sulla parola.
     *
     * @param  string|array<array-key, mixed>|null  $documents
     * @return array<string, string>
     */
    private function parseDocuments($documents): array
    {
        if (is_string($documents)) {
            $documents = json_decode($documents, true);
        }

        if (! is_array($documents)) {
            return [];
        }

        $parsed = [];

        foreach ($documents as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Assicura che la directory esista
     */
    private function ensureDirectoryExists(string $path): void
    {
        $fullDestDir = $this->disk()->path(dirname($path));
        if (! is_dir($fullDestDir)) {
            mkdir($fullDestDir, 0755, true);
        }
    }

    /**
     * Copia un documento e rimuove il temporaneo
     */
    private function copyDocument(string $sourcePath, string $destPath): void
    {
        $fullDestPath = $this->disk()->path($destPath);
        copy($sourcePath, $fullDestPath);

        if (file_exists($sourcePath)) {
            unlink($sourcePath);
        }
    }

    /**
     * Formatta dimensione file
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Radice dei documenti generati sul disk documenti.
     * Centralizzata in config (override in testing per evitare
     * proliferazione di docx nei percorsi reali).
     */
    private function docsRoot(): string
    {
        return Config::string('golf.documents.storage_path', 'convocazioni');
    }

    /**
     * Disk dei documenti generati.
     *
     * FIX M2 (audit 2026-07): disk 'docs' PRIVATO (storage/app/docs) — prima
     * 'public': i DOCX erano scaricabili senza login via /storage/... con
     * nomi prevedibili. L'accesso ora passa solo da downloadDocument (auth
     * + check zona) e dagli allegati email.
     */
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(Config::string('golf.documents.disk', 'docs'));
    }
}
