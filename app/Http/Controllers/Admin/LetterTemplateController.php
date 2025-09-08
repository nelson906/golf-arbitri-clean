<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use App\Models\Zone;
use App\Models\TournamentType;
use Illuminate\Http\Request;

class LetterTemplateController extends Controller
{
/**
 * Display a listing of letter templates.
 */
public function index()
{
    $templates = LetterTemplate::with(['zone', 'tournamentType'])
        ->orderBy('type')
        ->orderBy('name')
        ->paginate(15);

    // ✅ AGGIUNGI QUESTA VARIABILE MANCANTE:
    $types = [
        'assignment' => 'Assegnazione',
        'convocation' => 'Convocazione',
        'club' => 'Circolo',
        'institutional' => 'Istituzionale'
    ];

    // ✅ AGGIUNGI ANCHE QUESTA VARIABILE MANCANTE:
    $zones = Zone::where('is_active', true)->orderBy('name')->get();

    return view('admin.letter-templates.index', compact('templates', 'types', 'zones'));
}
    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $tournamentTypes = TournamentType::where('is_active', true)->orderBy('name')->get();

        return view('admin.letter-templates.create', compact('zones', 'tournamentTypes'));
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:assignment,convocation,club,institutional',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'zone_id' => 'nullable|exists:zones,id',
            'tournament_type_id' => 'nullable|exists:tournament_types,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Se è impostato come default, rimuovi default da altri template dello stesso tipo
        if ($validated['is_default'] ?? false) {
            LetterTemplate::where('type', $validated['type'])
                ->where('zone_id', $validated['zone_id'])
                ->update(['is_default' => false]);
        }

        $template = LetterTemplate::create($validated);

        return redirect()->route('letter-templates.index')
            ->with('success', 'Template creato con successo.');
    }

    /**
     * Display the specified template.
     */
    public function show(LetterTemplate $template)
    {
        $template->load(['zone', 'tournamentType']);
        return view('admin.letter-templates.show', compact('template'));
    }

    /**
     * Show the form for editing the template.
     */
    public function edit(LetterTemplate $template)
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $tournamentTypes = TournamentType::where('is_active', true)->orderBy('name')->get();

        return view('admin.letter-templates.edit', compact('template', 'zones', 'tournamentTypes'));
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, LetterTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:assignment,convocation,club,institutional',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'zone_id' => 'nullable|exists:zones,id',
            'tournament_type_id' => 'nullable|exists:tournament_types,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Se è impostato come default, rimuovi default da altri template dello stesso tipo
        if ($validated['is_default'] ?? false) {
            LetterTemplate::where('type', $validated['type'])
                ->where('zone_id', $validated['zone_id'])
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return redirect()->route('letter-templates.index')
            ->with('success', 'Template aggiornato con successo.');
    }

    /**
     * Remove the specified template.
     */
    public function destroy(LetterTemplate $template)
    {
        $template->delete();

        return redirect()->route('letter-templates.index')
            ->with('success', 'Template eliminato con successo.');
    }

    /**
     * Duplicate the specified template.
     */
    public function duplicate(LetterTemplate $template)
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copia)';
        $newTemplate->is_default = false; // La copia non può essere default
        $newTemplate->save();

        return redirect()->route('letter-templates.edit', $newTemplate)
            ->with('success', 'Template duplicato con successo. Modifica i dettagli necessari.');
    }

    /**
     * Preview the template with sample data.
     */
    public function preview(LetterTemplate $template)
    {
        // Dati di esempio per la preview
        $sampleData = [
            'tournament_name' => 'Campionato Zonale di Esempio',
            'tournament_dates' => '15-16 Settembre 2025',
            'club_name' => 'Golf Club Esempio',
            'club_address' => 'Via del Golf 123, Milano',
            'referee_name' => 'Mario Rossi',
            'assignment_role' => 'Arbitro Principale',
            'zone_name' => 'SZR1',
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => 'Zonale',
        ];

        // Sostituisci le variabili nel template
        $previewSubject = $this->replaceVariables($template->subject, $sampleData);
        $previewBody = $this->replaceVariables($template->body, $sampleData);

        return view('admin.letter-templates.preview', compact('template', 'previewSubject', 'previewBody', 'sampleData'));
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(LetterTemplate $template)
    {
        $template->update(['is_active' => !$template->is_active]);

        return response()->json([
            'success' => true,
            'message' => $template->is_active ? 'Template attivato.' : 'Template disattivato.',
            'is_active' => $template->is_active
        ]);
    }

    /**
     * Set template as default.
     */
    public function setDefault(LetterTemplate $template)
    {
        // Rimuovi default da altri template dello stesso tipo e zona
        LetterTemplate::where('type', $template->type)
            ->where('zone_id', $template->zone_id)
            ->update(['is_default' => false]);

        // Imposta questo come default
        $template->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Template impostato come predefinito.'
        ]);
    }

    /**
     * Replace variables in text with actual values.
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }



    /**
     * Valida configurazione template sistema
     */
    public function validateTemplateSystem(): array
    {
        $issues = [];

        // Verifica template default esistenti
        $requiredTypes = ['assignment', 'convocation', 'club', 'institutional'];

        foreach ($requiredTypes as $type) {
            $hasDefault = LetterTemplate::where('type', $type)
                ->where('is_default', true)
                ->where('is_active', true)
                ->exists();

            if (!$hasDefault) {
                $issues[] = "Manca template default per tipo: {$type}";
            }
        }

        // Verifica letterhead default
        $hasDefaultLetterhead = Letterhead::whereNull('zone_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->exists();

        if (!$hasDefaultLetterhead) {
            $issues[] = "Manca letterhead default nazionale";
        }

        // Verifica template duplicati
        $duplicates = LetterTemplate::select('type', 'zone_id', 'tournament_type_id')
            ->selectRaw('COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('type', 'zone_id', 'tournament_type_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $issues[] = "Trovati {$duplicates->count()} template duplicati";
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'recommendations' => $this->getRecommendations($issues)
        ];
    }
}
