<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 📁 DocumentController - Gestione documenti e file
 */
class DocumentController extends Controller
{
    /**
     * Display a listing of documents
     */
    public function index(Request $request): View
    {
        $user = $this->authUser();

        $query = Document::with(['uploader', 'tournament', 'zone'])
            ->orderBy('created_at', 'desc');

        // Filtro accesso per zona — national_admin e super_admin vedono tutto
        if (! $user->isNationalAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereNull('zone_id')
                    ->orWhere('uploader_id', $user->id) // I propri documenti
                    ->orWhere('is_public', true); // Documenti pubblici
            });
        }

        // Filtri opzionali
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search')->toString().'%')
                    ->orWhere('description', 'like', '%'.$request->string('search')->toString().'%')
                    ->orWhere('original_name', 'like', '%'.$request->string('search')->toString().'%');
            });
        }

        $documents = $query->paginate(20);

        $stats = [
            'total' => Document::count(),
            'size_total' => Document::sum('file_size'),
            'this_month' => Document::whereMonth('created_at', now()->month)->count(),
            'by_type' => Document::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return view('user.documents.index', compact('documents', 'stats'));
    }

    /**
     * Download a document
     */
    public function download(Document $document): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($document);

        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File non trovato.');
        }

        // Incrementa download counter
        $document->increment('download_count');

        $filePath = Storage::disk('public')->path($document->file_path);

        // Determina il MIME type corretto basandosi sull'estensione
        $mimeType = $this->getCorrectMimeType($document->original_name, $document->mime_type);

        return response()->download(
            $filePath,
            $document->original_name,
            [
                'Content-Type' => $mimeType,
            ]
        );
    }

    /**
     * Ottiene il MIME type corretto per il download.
     * Risolve problemi con DOCX/XLSX che vengono rilevati come application/zip
     */
    private function getCorrectMimeType(string $filename, ?string $storedMimeType): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Mappa estensioni -> MIME types corretti per Office
        $officeMimeTypes = [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pdf' => 'application/pdf',
        ];

        // Se è un file Office, usa il MIME type corretto
        if (isset($officeMimeTypes[$extension])) {
            return $officeMimeTypes[$extension];
        }

        // Altrimenti usa quello salvato nel database
        return $storedMimeType ?? 'application/octet-stream';
    }

    /**
     * Check if user can access document
     */
    private function authorizeDocumentAccess(Document $document, bool $requireOwnership = false): void
    {
        $user = $this->authUser();

        // Super admin e national admin possono accedere a tutto
        if ($user->isNationalAdmin()) {
            return;
        }

        // Se richiede ownership, verifica che sia il proprietario
        if ($requireOwnership && $document->uploader_id !== $user->id) {
            abort(403, 'Puoi eliminare solo i tuoi documenti.');
        }

        // Verifica accesso per zona
        if ($document->zone_id && $document->zone_id !== $user->zone_id) {
            // Verifica se è un documento pubblico o dell'utente
            if (! $document->is_public && $document->uploader_id !== $user->id) {
                abort(403, 'Accesso negato a questo documento.');
            }
        }
    }
}
