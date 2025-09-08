<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

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
        $user = Auth::user();

        $query = Document::with(['uploader', 'tournament', 'zone'])
            ->orderBy('created_at', 'desc');

        // Filtro accesso per zona
        if ($user->user_type !== 'national_admin' && $user->user_type !== 'super_admin') {
            $query->where(function($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                  ->orWhereNull('zone_id')
                  ->orWhere('uploader_id', $user->id); // I propri documenti
            });
        }

        // Filtri opzionali
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('original_name', 'like', '%' . $request->search . '%');
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

        return view('documents.index', compact('documents', 'stats'));
    }

    /**
     * Upload a new document
     */
    public function upload(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'category' => 'required|string|in:general,tournament,regulation,form,template',
            'description' => 'nullable|string|max:500',
            'tournament_id' => 'nullable|exists:tournaments,id',
            'is_public' => 'boolean',
        ]);

        try {
            $file = $request->file('file');
            $user = Auth::user();

            // Genera nome file unico
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' .
                       time() . '.' . $extension;

            // Determina il path di storage
            $category = $request->category;
            $year = now()->year;
            $month = now()->format('m');
            $storagePath = "documents/{$category}/{$year}/{$month}";

            // Salva il file
            $filePath = $file->storeAs($storagePath, $fileName, 'public');

            // Crea record nel database
            $document = Document::create([
                'name' => pathinfo($originalName, PATHINFO_FILENAME),
                'original_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category' => $category,
                'type' => $this->determineDocumentType($file->getMimeType()),
                'description' => $request->description,
                'tournament_id' => $request->tournament_id,
                'zone_id' => $user->zone_id,
                'uploader_id' => $user->id,
                'is_public' => $request->boolean('is_public', false),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento caricato con successo!',
                    'document' => $document,
                ]);
            }

            return back()->with('success', 'Documento caricato con successo!');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore durante il caricamento: ' . $e->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Errore durante il caricamento: ' . $e->getMessage());
        }
    }

    /**
     * Download a document
     */
    public function download(Document $document)
    {
        $this->authorizeDocumentAccess($document);

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File non trovato.');
        }

        // Incrementa download counter
        $document->increment('download_count');

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name
        );
    }

    /**
     * Remove a document
     */
    public function destroy(Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($document, true);

        try {
            // Elimina il file fisico
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Elimina il record dal database
            $document->delete();

            return back()->with('success', 'Documento eliminato con successo!');

        } catch (\Exception $e) {
            return back()->with('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * Determine document type from MIME type
     */
    private function determineDocumentType(string $mimeType): string
    {
        return match(true) {
            str_contains($mimeType, 'pdf') => 'pdf',
            str_contains($mimeType, 'word') || str_contains($mimeType, 'document') => 'document',
            str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel') => 'spreadsheet',
            str_contains($mimeType, 'image') => 'image',
            str_contains($mimeType, 'text') => 'text',
            default => 'other',
        };
    }

    /**
     * Check if user can access document
     */
    private function authorizeDocumentAccess(Document $document, bool $requireOwnership = false): void
    {
        $user = Auth::user();

        // Super admin e national admin possono accedere a tutto
        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        // Se richiede ownership, verifica che sia il proprietario
        if ($requireOwnership && $document->uploader_id !== $user->id) {
            abort(403, 'Puoi eliminare solo i tuoi documenti.');
        }

        // Verifica accesso per zona
        if ($document->zone_id && $document->zone_id !== $user->zone_id) {
            // Verifica se è un documento pubblico o dell'utente
            if (!$document->is_public && $document->uploader_id !== $user->id) {
                abort(403, 'Accesso negato a questo documento.');
            }
        }
    }
}
