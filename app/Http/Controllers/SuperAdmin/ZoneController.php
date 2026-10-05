<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestione Zone (decisione 2026-10-03, P18): elenco e modifica dei dati di
 * contatto, solo super admin, nessuna cancellazione.
 *
 * Codice e "nazionale" restano in sola lettura: il codice individua le
 * cartelle dei documenti e la carta intestata della zona.
 */
class ZoneController extends Controller
{
    public function index(): View
    {
        $zones = Zone::withCount(['clubs', 'referees'])
            ->orderBy('is_national')
            ->orderBy('code')
            ->get();

        return view('super-admin.zones.index', compact('zones'));
    }

    public function edit(Zone $zone): View
    {
        return view('super-admin.zones.edit', compact('zone'));
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
        ]);

        $zone->update([
            'name' => $request->string('name')->toString(),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        return redirect()->route('super-admin.zones.index')
            ->with('success', "Zona {$zone->name} aggiornata.");
    }
}
