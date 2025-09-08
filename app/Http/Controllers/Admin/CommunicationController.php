<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * 📢 CommunicationController - Gestione comunicazioni di sistema
 */
class CommunicationController extends Controller
{
    /**
     * Display a listing of communications
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Communication::with(['author', 'zone'])
            ->orderBy('created_at', 'desc');

        // Filtro per zona se non è national admin
        if ($user->user_type !== 'national_admin' && $user->user_type !== 'super_admin') {
            $query->where(function($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
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
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
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

        // Se non è admin nazionale, forza la zona dell'utente
        $user = Auth::user();
        if ($user->user_type !== 'national_admin' && $user->user_type !== 'super_admin') {
            $validated['zone_id'] = $user->zone_id;
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
     * Get available zones for user
     */
    private function getAvailableZones($user)
    {
        if ($user->user_type === 'national_admin' || $user->user_type === 'super_admin') {
            return \App\Models\Zone::orderBy('name')->get();
        }

        return \App\Models\Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Check if user can access communication
     */
    private function authorizeAccess(Communication $communication): void
    {
        $user = Auth::user();

        // Super admin e national admin possono accedere a tutto
        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        // Zone admin può accedere solo a comunicazioni della sua zona o globali
        if ($communication->zone_id && $communication->zone_id !== $user->zone_id) {
            abort(403, 'Accesso negato a questa comunicazione.');
        }
    }
}
