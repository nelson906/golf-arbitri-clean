<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionalEmailController extends Controller
{
    /**
     * Display a listing of institutional emails.
     */
    public function index(Request $request): View
    {
        $query = InstitutionalEmail::with('zone');

        // Filtri
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('zone_id')) {
            if ($request->string('zone_id')->toString() === 'null') {
                $query->whereNull('zone_id');
            } else {
                $query->where('zone_id', $request->integer('zone_id'));
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $institutionalEmails = $query->orderBy('category')->orderBy('name')->paginate(20);

        $zones = Zone::where('is_active', true)->orderBy('name')->get();

        return view('super-admin.institutional-emails.index', compact('institutionalEmails', 'zones'));
    }

    /**
     * Show the form for creating a new institutional email.
     */
    public function create(): View
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;

        return view('super-admin.institutional-emails.create', compact('zones', 'categories'));
    }

    /**
     * Store a newly created institutional email.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email',
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:'.implode(',', array_keys(InstitutionalEmail::CATEGORIES)),
            'is_active' => 'boolean',
        ]);

        // Valori di default
        $validated['is_active'] = $validated['is_active'] ?? true;

        InstitutionalEmail::create($validated);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale creata con successo.');
    }

    /**
     * Show the form for editing the institutional email.
     */
    public function edit(InstitutionalEmail $institutionalEmail): View
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;

        return view('super-admin.institutional-emails.edit', compact('institutionalEmail', 'zones', 'categories'));
    }

    /**
     * Update the specified institutional email.
     */
    public function update(Request $request, InstitutionalEmail $institutionalEmail): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email,'.$institutionalEmail->id,
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:'.implode(',', array_keys(InstitutionalEmail::CATEGORIES)),
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;

        $institutionalEmail->update($validated);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale aggiornata con successo.');
    }

    /**
     * Remove the specified institutional email.
     */
    public function destroy(InstitutionalEmail $institutionalEmail): RedirectResponse
    {
        $institutionalEmail->delete();

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale eliminata con successo.');
    }

}
