<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use Illuminate\Http\Request;

class InstitutionalEmailController extends Controller
{
    /**
     * Display a listing of institutional emails.
     */
    public function index(Request $request)
    {
        $query = InstitutionalEmail::with('zone');
        
        // Filtri
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->filled('zone_id')) {
            if ($request->zone_id === 'null') {
                $query->whereNull('zone_id');
            } else {
                $query->where('zone_id', $request->zone_id);
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
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;
        
        return view('super-admin.institutional-emails.create', compact('zones', 'categories'));
    }

    /**
     * Store a newly created institutional email.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email',
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:' . implode(',', array_keys(InstitutionalEmail::CATEGORIES)),
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'in:' . implode(',', array_keys(InstitutionalEmail::NOTIFICATION_TYPES)),
        ]);

        // Se receive_all_notifications è true, non serve specificare i tipi
        if ($validated['receive_all_notifications'] ?? false) {
            $validated['notification_types'] = [];
        }
        
        // Valori di default
        $validated['is_active'] = $validated['is_active'] ?? true;

        InstitutionalEmail::create($validated);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale creata con successo.');
    }

    /**
     * Display the specified institutional email.
     */
    public function show(InstitutionalEmail $institutionalEmail)
    {
        $institutionalEmail->load('zone');
        return view('super-admin.institutional-emails.show', compact('institutionalEmail'));
    }

    /**
     * Show the form for editing the institutional email.
     */
    public function edit(InstitutionalEmail $institutionalEmail)
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;
        
        return view('super-admin.institutional-emails.edit', compact('institutionalEmail', 'zones', 'categories'));
    }

    /**
     * Update the specified institutional email.
     */
    public function update(Request $request, InstitutionalEmail $institutionalEmail)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email,' . $institutionalEmail->id,
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:' . implode(',', array_keys(InstitutionalEmail::CATEGORIES)),
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'in:' . implode(',', array_keys(InstitutionalEmail::NOTIFICATION_TYPES)),
        ]);

        // Se receive_all_notifications è true, non serve specificare i tipi
        if ($validated['receive_all_notifications'] ?? false) {
            $validated['notification_types'] = [];
        }
        
        $validated['is_active'] = $validated['is_active'] ?? false;

        $institutionalEmail->update($validated);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale aggiornata con successo.');
    }

    /**
     * Remove the specified institutional email.
     */
    public function destroy(InstitutionalEmail $institutionalEmail)
    {
        $institutionalEmail->delete();

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale eliminata con successo.');
    }

    /**
     * Toggle email active status.
     */
    public function toggleActive(InstitutionalEmail $institutionalEmail)
    {
        $institutionalEmail->update(['is_active' => !$institutionalEmail->is_active]);

        return back()->with('success', 
            $institutionalEmail->is_active ? 
                'Email istituzionale attivata.' : 
                'Email istituzionale disattivata.'
        );
    }
    
    /**
     * Export institutional emails.
     */
    public function export()
    {
        $emails = InstitutionalEmail::with('zone')->get();

        $filename = 'institutional_emails_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($emails) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, [
                'Nome',
                'Email',
                'Categoria',
                'Zona',
                'Descrizione',
                'Stato',
                'Riceve Tutte',
                'Tipi Notifica',
                'Creato'
            ]);

            // Dati
            foreach ($emails as $email) {
                fputcsv($file, [
                    $email->name,
                    $email->email,
                    $email->category_label ?? $email->category,
                    $email->zone?->name ?? 'Tutte',
                    $email->description,
                    $email->is_active ? 'Attivo' : 'Inattivo',
                    $email->receive_all_notifications ? 'Sì' : 'No',
                    implode(', ', $email->notification_types ?? []),
                    $email->created_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
