<?php
// File: app/Http/Controllers/Admin/UserController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Http\Helpers\RefereeLevelsHelper;

class UserController extends Controller
{
    /**
     * Display lista utenti (arbitri + admin)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // IMPORTANTE: Definisci tutte le variabili necessarie per la vista
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);
        $isSuperAdmin = $user->user_type === 'super_admin';
        $isZoneAdmin = $user->user_type === 'admin';

        // Query base
        $query = User::with(['zone']);

        // Filtro per tipo utente
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filtro per livello (se esiste la colonna)
        if ($request->filled('level') && Schema::hasColumn('users', 'level')) {
            $query->where('level', $request->level);
        }
        if (request('sort')) {
            switch (request('sort')) {
                case 'surname_asc':
                    $query->orderBy('last_name');
                    break;
                case 'surname_desc':
                    $query->orderByDesc('last_name');
                    break;
                case 'name_asc':
                    $query->orderBy('name');
                    break;
            }
        }
        // Filtro per zona
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Filtro ricerca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                // Aggiungi referee_code solo se la colonna esiste
                if (Schema::hasColumn('users', 'referee_code')) {
                    $q->orWhere('referee_code', 'like', "%{$search}%");
                }
            });
        }

        // Restrizioni per zona (solo per admin di zona)
        if ($isZoneAdmin && !$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // Filtro per stato attivo (di default mostra solo attivi se non specificato)
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
            // Se status = 'all', non applica filtri
        } else {
            // Di default mostra solo gli attivi
            $query->where('is_active', true);
        }

        // Ordinamento e paginazione
        $users = $query->orderBy('name')->paginate(20);

        // Recupera tutte le zone per il filtro
        $zones = Zone::orderBy('name')->get();

        // Array dei tipi utente disponibili
        $userTypes = [
            'referee' => 'Arbitro',
            'admin' => 'Admin Zona',
            'national_admin' => 'Admin Nazionale',
            'super_admin' => 'Super Admin'
        ];

        // Array dei livelli (se utilizzati)
        $levels = [
            'AR' => 'Archivio',
            'A' => 'Aspirante',
            '1' => 'Primo Livello',
            'R' => 'Regionale',
            'N' => 'Nazionale',
            'I' => 'Internazionale'
        ];

        return view('admin.users.index', compact(
            'users',
            'zones',
            'isNationalAdmin',
            'isSuperAdmin',
            'isZoneAdmin',
            'userTypes',
            'levels'
        ));
    }

    /**
     * Mostra dettagli utente
     */
    public function show(User $user)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);
        $isSuperAdmin = $currentUser->user_type === 'super_admin';

        // Verifica permessi visualizzazione
        if (!$isNationalAdmin && $currentUser->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato a visualizzare questo utente');
        }

        // Carica relazioni in modo sicuro
        $user->load(['zone']);

        // Carica assegnazioni se la tabella esiste
        if (Schema::hasTable('assignments')) {
            $user->load(['assignments.tournament']);
        }

        // Carica disponibilità se la tabella esiste
        if (Schema::hasTable('availabilities')) {
            $user->load(['availabilities']);
        }

        return view('admin.users.show', compact('user', 'isNationalAdmin', 'isSuperAdmin'));
    }

    /**
     * Form creazione utente
     */
    public function create()
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);
        $isSuperAdmin = $currentUser->user_type === 'super_admin';

        // Zone disponibili
        if ($isNationalAdmin) {
            $zones = Zone::orderBy('name')->get();
        } else {
            $zones = Zone::where('id', $currentUser->zone_id)->get();
        }

        // Tipi utente che può creare
        $userTypes = ['referee' => 'Arbitro'];
        if ($isNationalAdmin) {
            $userTypes['admin'] = 'Admin Zona';
        }
        if ($isSuperAdmin) {
            $userTypes['national_admin'] = 'Admin Nazionale';
            $userTypes['super_admin'] = 'Super Admin';
        }

        return view('admin.users.create', compact('zones', 'userTypes', 'isNationalAdmin', 'isSuperAdmin'));
    }

    /**
     * Salva nuovo utente
     */
    public function store(Request $request)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);

        // Validazione base
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'zone_id' => 'required|exists:zones,id',
        ];

        // Aggiungi validazione per campi opzionali se esistono
        if (Schema::hasColumn('users', 'referee_code')) {
            $rules['referee_code'] = 'nullable|string|max:20|unique:users';
        }

        if (Schema::hasColumn('users', 'level')) {
            $rules['level'] = 'required|in:Aspirante,1_livello,Regionale,Nazionale,Internazionale,Archivio';
        }

        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = 'nullable|string|max:20';
        }

        if (Schema::hasColumn('users', 'city')) {
            $rules['city'] = 'nullable|string|max:255';
        }

        if (Schema::hasColumn('users', 'club_member')) {
            $rules['club_member'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        // Imposta password predefinita (come indicato nel form)
        $validated['password'] = Hash::make('password123');

        // Imposta tipo utente predefinito (referee)
        $validated['user_type'] = 'referee';

        // Genera codice arbitro se la colonna esiste e non è fornito
        if (Schema::hasColumn('users', 'referee_code') && empty($validated['referee_code'])) {
            $lastUser = User::orderBy('id', 'desc')->first();
            $nextId = $lastUser ? $lastUser->id + 1 : 1;
            $validated['referee_code'] = 'REF' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        }

        // Crea utente
        $user = User::create($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Utente creato con successo. L\'arbitro dovrà accedere con email e password temporanea: password123');
    }

    /**
     * Form modifica utente
     */
    public function edit(User $user)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);
        $isSuperAdmin = $currentUser->user_type === 'super_admin';

        // Verifica permessi
        if (!$isNationalAdmin && $currentUser->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato a modificare questo utente');
        }

        // Zone disponibili
        if ($isNationalAdmin) {
            $zones = Zone::orderBy('name')->get();
        } else {
            $zones = Zone::where('id', $currentUser->zone_id)->get();
        }

        // Tipi utente modificabili
        $userTypes = ['referee' => 'Arbitro'];
        if ($isNationalAdmin) {
            $userTypes['admin'] = 'Admin Zona';
        }
        if ($isSuperAdmin) {
            $userTypes['national_admin'] = 'Admin Nazionale';
            $userTypes['super_admin'] = 'Super Admin';
        }

        return view('admin.users.edit', compact('user', 'zones', 'userTypes', 'isNationalAdmin', 'isSuperAdmin'));
    }

    /**
     * Aggiorna utente
     */
    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin && $currentUser->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato a modificare questo utente');
        }

        // Validazione
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'user_type' => 'required|in:referee,admin' . ($isNationalAdmin ? ',national_admin,super_admin' : ''),
            'zone_id' => 'required|exists:zones,id',
        ];

        // Password opzionale in update
        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        // Campi opzionali
        if (\Schema::hasColumn('users', 'referee_code')) {
            $rules['referee_code'] = 'nullable|string|max:20|unique:users,referee_code,' . $user->id;
        }

        if (\Schema::hasColumn('users', 'level')) {
            $rules['level'] = 'nullable|in:Aspirante,1_livello,Regionale,Nazionale,Internazionale,Archivio';
        }

        if (\Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = 'nullable|string|max:20';
        }

        if (\Schema::hasColumn('users', 'gender')) {
            $rules['gender'] = 'nullable|in:male,female,mixed';
        }

        if (\Schema::hasColumn('users', 'notes')) {
            $rules['notes'] = 'nullable|string';
        }

        if (\Schema::hasColumn('users', 'city')) {
            $rules['city'] = 'nullable|string|max:255';
        }

        if (\Schema::hasColumn('users', 'club_member')) {
            $rules['club_member'] = 'nullable|string|max:255';
        }
        $validated = $request->validate($rules);

        // Hash password se fornita
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Gestisci il campo is_active (checkbox)
        $validated['is_active'] = $request->has('is_active');

        // Aggiorna utente
        $user->update($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Utente aggiornato con successo');
    }

    /**
     * Elimina utente
     */
    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin) {
            abort(403, 'Solo gli admin nazionali possono eliminare utenti');
        }

        // Non permettere auto-eliminazione
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Non puoi eliminare il tuo stesso account');
        }

        // Verifica se ha assegnazioni
        if (\Schema::hasTable('assignments') && $user->assignments()->exists()) {
            return back()->with('error', 'Impossibile eliminare: l\'utente ha assegnazioni registrate');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Utente eliminato con successo');
    }

    /**
     * Toggle stato attivo/inattivo
     */
    public function toggleActive(User $user)
    {
        $currentUser = auth()->user();
        $isNationalAdmin = in_array($currentUser->user_type, ['national_admin', 'super_admin']);

        // Verifica permessi
        if (!$isNationalAdmin && $currentUser->zone_id != $user->zone_id) {
            abort(403, 'Non autorizzato');
        }

        // Toggle is_active
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'attivato' : 'disattivato';
        return back()->with('success', "Utente {$status} con successo");
    }
}
