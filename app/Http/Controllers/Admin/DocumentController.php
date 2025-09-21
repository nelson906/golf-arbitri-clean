<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
 * 📁 Admin DocumentController - Gestione documenti e file (Admin)
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

        // Admin di zona vede solo documenti della sua zona o globali
        if ($user->user_type === 'admin') {
            $query->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereNull('zone_id');
            });
        }
        // National admin e super admin vedono tutto

        // Filtri opzionali
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('zone_id') && in_array($user->user_type, ['national_admin', 'super_admin'])) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
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
     * Show the form for creating a new document
     */
    public function create(): View
    {
        return view('documents.create');
    }

    /**
     * Store a newly created document
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->upload($request);
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
            'zone_id' => 'nullable|exists:zones,id',
            // 'is_public' => 'boolean', // checkbox invia 'on' non boolean
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

            // Per admin, usa la zone_id fornita o quella dell'utente
            $zoneId = $request->zone_id;
            if (!$zoneId && $user->user_type === 'admin') {
                $zoneId = $user->zone_id;
            }

            // Crea record nel database
            $document = Document::create([
                'name' => $request->name ?? pathinfo($originalName, PATHINFO_FILENAME),
                'original_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category' => $category,
                'type' => $this->determineDocumentType($file->getMimeType()),
                'description' => $request->description,
                'tournament_id' => $request->tournament_id,
                'zone_id' => $zoneId,
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

            return redirect()
                ->route('admin.documents.index')
                ->with('success', 'Documento caricato con successo!');
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
     * Display the specified document
     */
    public function show(Document $document)
    {
        $this->authorizeDocumentAccess($document);

        return view('documents.show', compact('document'));
    }

    /**
     * Show the form for editing the document
     */
    public function edit(Document $document): View
    {
        $this->authorizeDocumentAccess($document, true);

        return view('documents.edit', compact('document'));
    }

    /**
     * Update the specified document
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($document, true);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'category' => 'required|string|in:general,tournament,regulation,form,template',
            'is_public' => 'boolean',
            'zone_id' => 'nullable|exists:zones,id',
        ]);

        $document->update($request->only(['name', 'description', 'category', 'is_public', 'zone_id']));

        return redirect()
            ->route('admin.documents.show', $document)
            ->with('success', 'Documento aggiornato con successo!');
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

        $filePath = Storage::disk('public')->path($document->file_path);
        return response()->download(
            $filePath,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
            ]
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

            return redirect()
                ->route('admin.documents.index')
                ->with('success', 'Documento eliminato con successo!');
        } catch (\Exception $e) {
            return back()->with('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * Determine document type from MIME type
     */
    private function determineDocumentType(string $mimeType): string
    {
        return match (true) {
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

        // Se richiede ownership per modifica/cancellazione
        if ($requireOwnership) {
            // Admin può modificare/cancellare documenti della propria zona
            if ($user->user_type === 'admin') {
                if ($document->zone_id && $document->zone_id !== $user->zone_id) {
                    abort(403, 'Non puoi modificare documenti di altre zone.');
                }
                return;
            }
            // Altri utenti possono modificare solo i propri documenti
            if ($document->uploader_id !== $user->id) {
                abort(403, 'Puoi modificare solo i tuoi documenti.');
            }
        }

        // Verifica accesso per lettura
        if ($user->user_type === 'admin') {
            // Admin può vedere documenti della propria zona o globali
            if ($document->zone_id && $document->zone_id !== $user->zone_id) {
                abort(403, 'Accesso negato a questo documento.');
            }
        }
    }
}
