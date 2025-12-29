<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CommunicationController - Gestione comunicazioni di sistema
 */
class CommunicationController extends Controller
{
    use HasZoneVisibility;

    /**
     * Display a listing of communications
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Communication::with(['author', 'zone'])
            ->orderBy('created_at', 'desc');

        // Filtro per zona se non è national admin (usa trait)
        if (! $this->isNationalAdmin($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('zone_id', $this->getUserZoneId($user))
                    ->orWhereNull('zone_id'); // Comunicazioni globali
            });
        }

        // Filtri opzionali
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('content', 'like', '%'.$request->search.'%');
            });
        }

        $communications = $query->paginate(15);

        $stats = [
            'total' => Communication::count(),
            'published' => Communication::where('status', 'published')->count(),
            'draft' => Communication::where('status', 'draft')->count(),
            'this_month' => Communication::whereMonth('created_at', now()->month)->count(),
        ];

        return view('admin.communications.index', compact('communications', 'stats'));
    }

    /**
     * Show the form for creating a new communication
     */
    public function create(): View
    {
        $user = Auth::user();

        // Determina zone disponibili
        $zones = $this->getAvailableZones($user);

        return view('admin.communications.create', compact('zones'));
    }

    /**
     * Store a newly created communication
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:announcement,alert,maintenance,info',
            'status' => 'required|in:draft,published',
            'zone_id' => 'nullable|exists:zones,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'scheduled_at' => 'nullable|date|after:now',
            'expires_at' => 'nullable|date|after:scheduled_at',
        ]);

        $validated['author_id'] = Auth::id();

        // Se non è admin nazionale, forza la zona dell'utente (usa trait)

        $user = Auth::user();
        if (! $this->isNationalAdmin($user)) {
            $validated['zone_id'] = $this->getUserZoneId($user);
        }

        $communication = Communication::create($validated);

        return redirect()
            ->route('admin.communications.index')
            ->with('success', 'Comunicazione creata con successo!');
    }

    /**
     * Display the specified communication
     */
    public function show(Communication $communication): View
    {
        $this->authorizeAccess($communication);

        $communication->load(['author', 'zone']);

        return view('admin.communications.show', compact('communication'));
    }

    /**
     * Publish a draft communication
     */
    public function publish(Communication $communication): RedirectResponse
    {
        $this->authorizeAccess($communication);

        $communication->update(['status' => 'published']);

        return redirect()
            ->route('admin.communications.index')
            ->with('success', 'Comunicazione pubblicata con successo!');
    }

    /**
     * Remove the specified communication
     */
    public function destroy(Communication $communication): RedirectResponse
    {
        $this->authorizeAccess($communication);

        $communication->delete();

        return redirect()
            ->route('admin.communications.index')
            ->with('success', 'Comunicazione eliminata con successo!');
    }

    /**
     * Get available zones for user (usa trait)
     */
    private function getAvailableZones($user)
    {
        if ($this->isNationalAdmin($user)) {
            return \App\Models\Zone::orderBy('name')->get();
        }

        return \App\Models\Zone::where('id', $this->getUserZoneId($user))->get();

    }

    /**
     * Check if user can access communication (usa trait)
     */
    private function authorizeAccess(Communication $communication): void
    {
        $user = Auth::user();

        // Super admin e national admin possono accedere a tutto
        if ($this->isNationalAdmin($user)) {
            return;
        }

        // Zone admin può accedere solo a comunicazioni della sua zona o globali
        if ($communication->zone_id && $communication->zone_id !== $this->getUserZoneId($user)) {
            abort(403, 'Accesso negato a questa comunicazione.');
        }
    }
}
