<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Zone;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubController extends Controller
{
    use HasZoneVisibility;

    /**
     * Display lista circoli
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin($user);

        $query = Club::with(['zone']);

        // Applica filtro visibilità zona
        $this->applyClubVisibility($query, $user);

        // Conta tornei per ogni club (senza filtro active)
        $query->withCount('tournaments');

        // Filtro ricerca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filtro zona
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }
        // Apply status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filtro zona già applicato da applyClubVisibility()

        // Ordinamento
        $clubs = $query
            ->withCount('tournaments')
            ->orderBy('is_active', 'desc')
            ->orderBy('name', 'asc')
            ->paginate(20)
            ->withQueryString();

        // Zone per filtro
        $zones = Zone::orderBy('name', 'asc')->get();

        return view('admin.clubs.index', compact('clubs', 'zones', 'isNationalAdmin'));
    }

    /**
     * Show dettaglio circolo
     */
    public function show(Club $club)
    {
        // Carica relazioni base
        $club->load(['zone']);

        // Carica tornei senza filtro active (campo non esiste più)
        $tournaments = $club->tournaments()
            ->with(['assignments.referee'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        // Statistiche
        $stats = [
            'total_tournaments' => $club->tournaments()->count(),
            'total_assignments' => DB::table('assignments')
                ->whereIn('tournament_id', $club->tournaments()->pluck('id'))
                ->count(),
            'upcoming_tournaments' => $club->tournaments()->upcoming()->count(),
            'completed_tournaments' => $club->tournaments()->where('status', 'completed')->count(),
            'active_tournaments' => $club->tournaments()->active()->count(),
        ];

        $isNationalAdmin = $this->isNationalAdmin();

        return view('admin.clubs.show', compact('club', 'tournaments', 'stats', 'isNationalAdmin'));
    }

    /**
     * Show form creazione
     */
    public function create()
    {
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin($user);

        // Zone disponibili (filtrate per ruolo)
        $zones = Zone::orderBy('name', 'asc');
        if (! $isNationalAdmin && $user && $user->zone_id) {
            $zones = $zones->where('id', $user->zone_id);
        }

        $zones = $zones->get();

        return view('admin.clubs.create', compact('zones', 'isNationalAdmin'));
    }

    /**
     * Store nuovo circolo
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'zone_id' => 'required|exists:zones,id',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:2',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'code' => 'nullable|string|max:20|unique:clubs',
            'is_active' => 'boolean',
        ];

        $validated = $request->validate($rules);

        // Imposta default per is_active
        if (! isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $club = Club::create($validated);

        return redirect()
            ->route('admin.clubs.show', $club)
            ->with('success', 'Circolo creato con successo');
    }

    /**
     * Show form modifica
     */
    public function edit(Club $club)
    {
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin($user);

        // Verifica permessi tramite trait
        if (! $isNationalAdmin && $club->zone_id != $this->getUserZoneId($user)) {
            abort(403, 'Non autorizzato a modificare questo circolo');
        }

        // Zone disponibili (filtrate per ruolo)
        $zones = Zone::orderBy('name', 'asc');
        if (! $isNationalAdmin && $user && $user->zone_id) {
            $zones = $zones->where('id', $user->zone_id);
        }
        $zones = $zones->get();

        return view('admin.clubs.edit', compact('club', 'zones', 'isNationalAdmin'));
    }

    /**
     * Update circolo
     */
    public function update(Request $request, Club $club)
    {
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin($user);

        // Verifica permessi tramite trait
        if (! $isNationalAdmin && $club->zone_id != $this->getUserZoneId($user)) {
            abort(403, 'Non autorizzato a modificare questo circolo');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'zone_id' => 'required|exists:zones,id',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'code' => 'nullable|string|max:20|unique:clubs,code,'.$club->id,
            'is_active' => 'boolean',
        ];

        $validated = $request->validate($rules);

        $club->update($validated);

        return redirect()
            ->route('admin.clubs.show', $club)
            ->with('success', 'Circolo aggiornato con successo');
    }

    /**
     * Delete circolo
     */
    public function destroy(Club $club)
    {
        if (! $this->isNationalAdmin()) {
            abort(403, 'Solo gli admin nazionali possono eliminare circoli');
        }

        // Verifica se ha tornei associati
        $tournaments = $club->tournaments()->get(['id', 'name', 'start_date', 'status']);
        if ($tournaments->isNotEmpty()) {
            $tournamentList = $tournaments->map(function ($t) {
                $date = $t->start_date ? $t->start_date->format('d/m/Y') : 'N/A';

                return "- {$t->name} ({$date}) [{$t->status}]";
            })->implode("\n");

            return back()->with('error', "Impossibile eliminare: il circolo ha {$tournaments->count()} tornei associati:\n{$tournamentList}");
        }

        $club->delete();

        return redirect()
            ->route('admin.clubs.index')
            ->with('success', 'Circolo eliminato con successo');
    }

    /**
     * Toggle stato attivo (se la colonna esiste)
     */
    public function toggleActive(Club $club)
    {

        $user = auth()->user();

        // Verifica permessi tramite trait
        if (! $this->isNationalAdmin($user) && $club->zone_id != $this->getUserZoneId($user)) {
            abort(403, 'Non autorizzato');
        }

        $club->is_active = ! $club->is_active;
        $club->save();

        $status = $club->is_active ? 'attivato' : 'disattivato';

        return back()->with('success', "Circolo {$status} con successo");
    }

    /**
     * Disattiva circolo
     */
    public function deactivate(Club $club)
    {
        $user = auth()->user();

        // Verifica permessi tramite trait
        if (! $this->isNationalAdmin($user) && $club->zone_id != $this->getUserZoneId($user)) {
            abort(403, 'Non autorizzato');
        }

        $club->is_active = false;
        $club->save();

        return back()->with('success', 'Circolo disattivato con successo');
    }

    /**
     *      * Esporta lista circoli
     */
    public function export(Request $request)
    {
        $user = auth()->user();

        $query = Club::with(['zone']);

        // Applica filtro visibilità zona tramite trait
        $this->applyClubVisibility($query, $user);

        // Applica gli stessi filtri della index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        $clubs = $query->orderBy('name', 'asc')->get();

        // Genera CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="circoli_'.date('Y-m-d').'.csv"',
        ];

        $callback = function () use ($clubs) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            // Headers CSV
            fputcsv($file, ['ID', 'Nome', 'Zona', 'Città', 'Email', 'Telefono', 'N° Tornei']);

            foreach ($clubs as $club) {
                fputcsv($file, [
                    $club->id,
                    $club->name,
                    $club->zone->name ?? 'N/A',
                    $club->city ?? '',
                    $club->email ?? '',
                    $club->phone ?? '',
                    $club->tournaments()->count(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
