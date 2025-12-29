<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\NotificationClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NotificationClauseController extends Controller
{
    /**
     * Display a listing of clauses
     */
    public function index(Request $request)
    {
        $query = NotificationClause::query();

        // Filtro ricerca testuale
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filtro categoria
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Filtro applies_to
        if ($request->filled('applies_to')) {
            $query->where('applies_to', $request->applies_to);
        }

        // Filtro stato attivo
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $clauses = $query->ordered()->paginate(20)->withQueryString();

        $categories = NotificationClause::CATEGORIES;
        $appliesTo = NotificationClause::APPLIES_TO;

        return view('super-admin.clauses.index', compact('clauses', 'categories', 'appliesTo'));
    }

    /**
     * Show the form for creating a new clause
     */
    public function create()
    {
        $categories = NotificationClause::CATEGORIES;
        $appliesTo = NotificationClause::APPLIES_TO;

        return view('super-admin.clauses.create', compact('categories', 'appliesTo'));
    }

    /**
     * Store a newly created clause
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                'unique:notification_clauses,code',
            ],
            'category' => [
                'required',
                Rule::in(array_keys(NotificationClause::CATEGORIES)),
            ],
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'applies_to' => [
                'required',
                Rule::in(array_keys(NotificationClause::APPLIES_TO)),
            ],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        try {
            NotificationClause::create($validated);

            return redirect()
                ->route('super-admin.clauses.index')
                ->with('success', 'Clausola creata con successo.');
        } catch (\Exception $e) {
            Log::error('Errore creazione clausola: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore nella creazione della clausola: '.$e->getMessage());
        }
    }

    /**
     * Display the specified clause
     */
    public function show(NotificationClause $clause)
    {
        $clause->loadCount('selections');

        return view('super-admin.clauses.show', compact('clause'));
    }

    /**
     * Show the form for editing the clause
     */
    public function edit(NotificationClause $clause)
    {
        $categories = NotificationClause::CATEGORIES;
        $appliesTo = NotificationClause::APPLIES_TO;
        $usageCount = $clause->selections()->count();

        return view('super-admin.clauses.edit', compact('clause', 'categories', 'appliesTo', 'usageCount'));
    }

    /**
     * Update the specified clause
     */
    public function update(Request $request, NotificationClause $clause)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('notification_clauses', 'code')->ignore($clause->id),
            ],
            'category' => [
                'required',
                Rule::in(array_keys(NotificationClause::CATEGORIES)),
            ],
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'applies_to' => [
                'required',
                Rule::in(array_keys(NotificationClause::APPLIES_TO)),
            ],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;

        try {
            $clause->update($validated);

            return redirect()
                ->route('super-admin.clauses.index')
                ->with('success', 'Clausola aggiornata con successo.');
        } catch (\Exception $e) {
            Log::error('Errore aggiornamento clausola: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore nell\'aggiornamento della clausola: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified clause
     */
    public function destroy(NotificationClause $clause)
    {
        try {
            // Verifica se la clausola è in uso
            $usageCount = $clause->selections()->count();

            if ($usageCount > 0) {
                return redirect()
                    ->back()
                    ->with('warning', "La clausola è utilizzata in {$usageCount} notifiche. Eliminazione non consentita.");
            }

            $clause->delete();

            return redirect()
                ->route('super-admin.clauses.index')
                ->with('success', 'Clausola eliminata con successo.');
        } catch (\Exception $e) {
            Log::error('Errore eliminazione clausola: '.$e->getMessage());

            return redirect()
                ->back()
                ->with('error', 'Errore nell\'eliminazione della clausola: '.$e->getMessage());
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive(NotificationClause $clause)
    {
        try {
            $clause->update(['is_active' => ! $clause->is_active]);

            $message = $clause->is_active
                ? 'Clausola attivata con successo.'
                : 'Clausola disattivata con successo.';

            return redirect()
                ->back()
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Errore toggle clausola: '.$e->getMessage());

            return redirect()
                ->back()
                ->with('error', 'Errore nel cambio stato: '.$e->getMessage());
        }
    }

    /**
     * Reorder clauses (AJAX)
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:notification_clauses,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['items'] as $item) {
                NotificationClause::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ordine aggiornato con successo.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore riordino clausole: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'ordine.',
            ], 500);
        }
    }

    /**
     * Preview clause formatted content (AJAX)
     */
    public function preview(NotificationClause $clause)
    {
        return response()->json([
            'success' => true,
            'content' => $clause->formatted_content,
            'title' => $clause->title,
            'category' => $clause->category_label,
        ]);
    }
}
