<?php
// File: app/Http/Controllers/Admin/ClubController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ClubController extends Controller
{
    /**
     * Display lista circoli
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        $query = Club::with(['zone']);

        // Conta tornei per ogni club (senza filtro active)
        $query->withCount('tournaments');

        // Filtro ricerca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");

                // Se esiste la colonna code
                if (Schema::hasColumn('clubs', 'code')) {
                    $q->orWhere('code', 'like', "%{$search}%");
                }
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

        // Se non è admin nazionale, mostra solo i circoli della sua zona
        if (!$isNationalAdmin && $user->zone_id) {
            $query->where('zone_id', $user->zone_id);
        }

        // Ordinamento
        $clubs = $query
            ->withCount('tournaments')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();


        // Zone per filtro
        $zones = Zone::orderBy('name')->get();

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

        $isNationalAdmin = in_array(auth()->user()->user_type, ['national_admin', 'super_admin']);

        return view('admin.clubs.show', compact('club', 'tournaments', 'stats', 'isNationalAdmin'));
    }

    /**
     * Show form creazione
     */
    public function create()
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Zone disponibili
        if ($isNationalAdmin) {
            $zones = Zone::orderBy('name')->get();
        } else {
            $zones = Zone::where('id', $user->zone_id)->get();
        }

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
        ];

        // Aggiungi validazione per campi opzionali se esistono
        if (Schema::hasColumn('clubs', 'code')) {
            $rules['code'] = 'nullable|string|max:20|unique:clubs';
        }

        if (Schema::hasColumn('clubs', 'active')) {
            $rules['active'] = 'boolean';
        }

        $validated = $request->validate($rules);

        // Imposta defaults se necessario
        if (Schema::hasColumn('clubs', 'active') && !isset($validated['active'])) {
            $validated['active'] = true;
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
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin && $club->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato a modificare questo circolo');
        }

        // Zone disponibili
        if ($isNationalAdmin) {
            $zones = Zone::orderBy('name')->get();
        } else {
            $zones = Zone::where('id', $user->zone_id)->get();
        }

        return view('admin.clubs.edit', compact('club', 'zones', 'isNationalAdmin'));
    }

    /**
     * Update circolo
     */
    public function update(Request $request, Club $club)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin && $club->zone_id != $user->zone_id) {
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
        ];

        // Validazione campi opzionali
        if (Schema::hasColumn('clubs', 'code')) {
            $rules['code'] = 'nullable|string|max:20|unique:clubs,code,' . $club->id;
        }

        if (Schema::hasColumn('clubs', 'active')) {
            $rules['active'] = 'boolean';
        }

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
        $isNationalAdmin = in_array(auth()->user()->user_type, ['national_admin', 'super_admin']);

        if (!$isNationalAdmin) {
            abort(403, 'Solo gli admin nazionali possono eliminare circoli');
        }

        // Verifica se ha tornei associati
        if ($club->tournaments()->exists()) {
            return back()->with('error', 'Impossibile eliminare: il circolo ha tornei associati');
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
        if (!Schema::hasColumn('clubs', 'active')) {
            return back()->with('error', 'Funzionalità non disponibile');
        }

        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin && $club->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato');
        }

        $club->active = !$club->active;
        $club->save();

        $status = $club->active ? 'attivato' : 'disattivato';
        return back()->with('success', "Circolo {$status} con successo");
    }

    /**
     * Esporta lista circoli
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        $query = Club::with(['zone']);

        // Applica gli stessi filtri della index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if (!$isNationalAdmin && $user->zone_id) {
            $query->where('zone_id', $user->zone_id);
        }

        $clubs = $query->orderBy('name')->get();

        // Genera CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="circoli_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($clubs) {
            $file = fopen('php://output', 'w');

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
                    $club->tournaments()->count()
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
