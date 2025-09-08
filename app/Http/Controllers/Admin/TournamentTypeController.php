<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TournamentTypeController extends Controller
{
    /**
     * Display a listing of the tournament types.
     */
    public function index()
    {
        $types = TournamentType::orderBy('sort_order')->paginate(20);
        
        return view('admin.tournament-types.index', compact('types'));
    }

    /**
     * Show the form for creating a new tournament type.
     */
    public function create()
    {
        return view('admin.tournament-types.create');
    }

    /**
     * Store a newly created tournament type in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tournament_types',
            'code' => 'nullable|string|max:50|unique:tournament_types',
            'description' => 'nullable|string',
            'calendar_color' => 'nullable|string|max:7',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = Str::upper(Str::slug($validated['name'], '_'));
        }

        // Set default values
        $validated['sort_order'] = $validated['sort_order'] ?? (TournamentType::max('sort_order') ?? 0) + 1;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['calendar_color'] = $validated['calendar_color'] ?? '#' . substr(md5($validated['name']), 0, 6);

        TournamentType::create($validated);

        return redirect()->route('super-admin.tournament-types.index')
            ->with('success', 'Tipo di torneo creato con successo.');
    }

    /**
     * Display the specified tournament type.
     */
    public function show(TournamentType $tournamentType)
    {
        $tournamentType->load(['tournaments' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return view('admin.tournament-types.show', compact('tournamentType'));
    }

    /**
     * Show the form for editing the specified tournament type.
     */
    public function edit(TournamentType $tournamentType)
    {
        return view('admin.tournament-types.edit', compact('tournamentType'));
    }

    /**
     * Update the specified tournament type in storage.
     */
    public function update(Request $request, TournamentType $tournamentType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tournament_types,name,' . $tournamentType->id,
            'code' => 'nullable|string|max:50|unique:tournament_types,code,' . $tournamentType->id,
            'description' => 'nullable|string',
            'calendar_color' => 'nullable|string|max:7',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        $tournamentType->update($validated);

        return redirect()->route('super-admin.tournament-types.index')
            ->with('success', 'Tipo di torneo aggiornato con successo.');
    }

    /**
     * Remove the specified tournament type from storage.
     */
    public function destroy(TournamentType $tournamentType)
    {
        // Check if there are tournaments using this type
        if ($tournamentType->tournaments()->exists()) {
            return back()->with('error', 'Non puoi eliminare questo tipo di torneo perché è utilizzato da uno o più tornei.');
        }

        $tournamentType->delete();

        return redirect()->route('super-admin.tournament-types.index')
            ->with('success', 'Tipo di torneo eliminato con successo.');
    }

    /**
     * Toggle the active state of a tournament type.
     */
    public function toggleActive(TournamentType $tournamentType)
    {
        $tournamentType->update([
            'is_active' => !$tournamentType->is_active
        ]);

        return back()->with('success', 
            $tournamentType->is_active ? 
                'Tipo di torneo attivato.' : 
                'Tipo di torneo disattivato.'
        );
    }

    /**
     * Update the order of tournament types.
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:tournament_types,id'
        ]);

        foreach ($validated['order'] as $index => $id) {
            TournamentType::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
