<?php

namespace App\Services;

use App\Helpers\ZoneHelper;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Carbon\Carbon;
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
     * Genera i documenti iniziali per una notifica
     */
    public function generateInitialDocuments(
        Tournament $tournament,
        TournamentNotification $notification
    ): array {
        try {
            $documents = [];
            $zone = ZoneHelper::getFolderCodeForTournament($tournament);

            // Genera convocazione DOCX
            $convocationData = $this->documentService->generateConvocationForTournament($tournament);
            $convFileName = basename($convocationData['path']);
            $convDestPath = "convocazioni/{$zone}/generated/{$convFileName}";

            $this->ensureDirectoryExists($convDestPath);
            $this->copyDocument($convocationData['path'], $convDestPath);
            $documents['convocation'] = $convFileName;

            // Genera lettera circolo DOCX
            $clubDocData = $this->documentService->generateClubDocument($tournament);
            $clubFileName = basename($clubDocData['path']);
            $clubDestPath = "convocazioni/{$zone}/generated/{$clubFileName}";

            $this->copyDocument($clubDocData['path'], $clubDestPath);
            $documents['club_letter'] = $clubFileName;

            return $documents;
        } catch (\Exception $e) {
            Log::error('Error generating initial documents', [
                'tournament_id' => $tournament->id,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

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
            $destPath = "convocazioni/{$zone}/generated/{$fileName}";

            $this->ensureDirectoryExists($destPath);
            $this->copyDocument($data['path'], $destPath);

            return $fileName;
        }

        if ($type === 'club_letter') {
            $data = $this->documentService->generateClubDocument($tournament, $notification);
            $fileName = basename($data['path']);
            $destPath = "convocazioni/{$zone}/generated/{$fileName}";

            $this->ensureDirectoryExists($destPath);
            $this->copyDocument($data['path'], $destPath);

            return $fileName;
        }

        throw new \InvalidArgumentException("Invalid document type: {$type}");
    }

    /**
     * Rigenera tutti i documenti con le clausole aggiornate
     */
    public function regenerateAllDocuments(TournamentNotification $notification): array
    {
        $tournament = $notification->tournament;
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);
        $documents = [];

        try {
            // Convocazione
            $convocationData = $this->documentService->generateConvocationForTournament($tournament, $notification);
            $convFileName = basename($convocationData['path']);
            $convDest = "convocazioni/{$zone}/generated/{$convFileName}";

            $this->ensureDirectoryExists($convDest);
            $this->copyDocument($convocationData['path'], $convDest);
            $documents['convocation'] = $convFileName;

            // Lettera circolo
            $clubDocData = $this->documentService->generateClubDocument($tournament, $notification);
            $clubFileName = basename($clubDocData['path']);
            $clubDest = "convocazioni/{$zone}/generated/{$clubFileName}";

            $this->copyDocument($clubDocData['path'], $clubDest);
            $documents['club_letter'] = $clubFileName;

            return $documents;
        } catch (\Exception $e) {
            Log::warning('Could not regenerate documents', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        $path = "convocazioni/{$zone}/generated/{$documents[$type]}";

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
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
        $basePath = "convocazioni/{$zone}/generated/";

        Log::info('Attempting to delete all documents', [
            'zone' => $zone,
            'basePath' => $basePath,
            'documents' => $documents,
        ]);

        foreach (['convocation', 'club_letter'] as $type) {
            if (! empty($documents[$type])) {
                $path = $basePath.$documents[$type];
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    Log::info("Deleted document: {$type}", ['path' => $path]);
                } else {
                    Log::warning("Document not found: {$type}", ['path' => $path]);
                }
            }
        }
    }

    /**
     * Carica un documento manualmente
     */
    public function uploadDocument(
        TournamentNotification $notification,
        string $type,
        $file
    ): string {
        $tournament = $notification->tournament;
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        $filename = str_replace(' ', '_', $file->getClientOriginalName());
        $file->storeAs("convocazioni/{$zone}/generated", $filename, 'public');

        Log::info('Document uploaded', [
            'notification_id' => $notification->id,
            'type' => $type,
            'filename' => $filename,
        ]);

        return $filename;
    }

    /**
     * Ottiene lo stato dei documenti
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
            $path = "convocazioni/{$zone}/generated/{$documents['convocation']}";
            if (Storage::disk('public')->exists($path)) {
                $response['convocation'] = [
                    'filename' => $documents['convocation'],
                    'generated_at' => Carbon::createFromTimestamp(
                        Storage::disk('public')->lastModified($path)
                    )->format('d/m/Y H:i'),
                    'size' => $this->formatBytes(Storage::disk('public')->size($path)),
                ];
            }
        }

        // Check lettera circolo
        if (! empty($documents['club_letter'])) {
            $path = "convocazioni/{$zone}/generated/{$documents['club_letter']}";
            if (Storage::disk('public')->exists($path)) {
                $response['club_letter'] = [
                    'filename' => $documents['club_letter'],
                    'generated_at' => Carbon::createFromTimestamp(
                        Storage::disk('public')->lastModified($path)
                    )->format('d/m/Y H:i'),
                    'size' => $this->formatBytes(Storage::disk('public')->size($path)),
                ];
            }
        }

        return $response;
    }

    /**
     * Verifica se i documenti esistono
     */
    public function checkDocumentsExist(TournamentNotification $notification): array
    {
        $tournament = $notification->tournament;
        $documents = $this->parseDocuments($notification->documents);
        $zone = ZoneHelper::getFolderCodeForTournament($tournament);

        return [
            'hasConvocation' => isset($documents['convocation']) &&
                Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$documents['convocation']}"),
            'hasClubLetter' => isset($documents['club_letter']) &&
                Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$documents['club_letter']}"),
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
        $path = "convocazioni/{$zone}/generated/{$documents[$type]}";
        $fullPath = storage_path('app/public/'.$path);

        if (! file_exists($fullPath)) {
            throw new \Exception('File non trovato sul server');
        }

        return $fullPath;
    }

    /**
     * Parse documents field (può essere string JSON o array)
     */
    private function parseDocuments($documents): array
    {
        if (is_string($documents)) {
            return json_decode($documents, true) ?? [];
        }

        return $documents ?? [];
    }

    /**
     * Assicura che la directory esista
     */
    private function ensureDirectoryExists(string $path): void
    {
        $fullDestDir = Storage::disk('public')->path(dirname($path));
        if (! is_dir($fullDestDir)) {
            mkdir($fullDestDir, 0755, true);
        }
    }

    /**
     * Copia un documento e rimuove il temporaneo
     */
    private function copyDocument(string $sourcePath, string $destPath): void
    {
        $fullDestPath = Storage::disk('public')->path($destPath);
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
}
