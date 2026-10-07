<?php

// File: app/Http/Controllers/Admin/UserController.php

namespace App\Http\Controllers\Admin;

use App\Enums\RefereeLevel;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FigImportAccess;
use App\Support\UserManagement;
use App\Models\Zone;
use App\Traits\HasZoneVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    use HasZoneVisibility;

    /**
     * Display lista utenti (arbitri + admin)
     */
    public function index(Request $request): View
    {
        $user = $this->authUser();

        // Usa metodi del trait per determinare i ruoli
        $isNationalAdmin = $this->isNationalAdmin($user);
        $isSuperAdmin = $this->isSuperAdmin($user);
        $isZoneAdmin = $this->isZoneAdmin($user);

        // Query base
        $query = User::with(['zone']);

        // Filtro per tipo utente
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->string('user_type')->toString());
        }

        // Filtro per livello
        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
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
            $query->where('zone_id', $request->integer('zone_id'));
        }

        // Filtro ricerca
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referee_code', 'like', "%{$search}%");
            });
        }

        // Applica filtro visibilità utenti tramite trait
        $this->applyUserVisibility($query, $user);

        // Account riservato (caricamento comitati FIG): invisibile agli altri
        $riservato = FigImportAccess::email();
        if ($riservato !== null && ! FigImportAccess::isAccount($user)) {
            $query->where('email', '!=', $riservato);
        }

        // Filtro per stato attivo (di default mostra solo attivi se non specificato)
        if ($request->has('status')) {
            if ($request->string('status')->toString() === 'active') {
                $query->where('is_active', true);
            } elseif ($request->string('status')->toString() === 'inactive') {
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
            UserType::Referee->value       => 'Arbitro',
            UserType::ZoneAdmin->value     => 'Admin Zona',
            UserType::NationalAdmin->value => 'Admin Nazionale',
            UserType::SuperAdmin->value    => 'Super Admin',
        ];

        // Array dei livelli (chiavi = valori DB enum)
        $levels = RefereeLevel::selectOptions(true);

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
    public function show(User $user): View
    {
        $currentUser = $this->authUser();
        if (FigImportAccess::hiddenFrom($user, $currentUser)) {
            abort(404);
        }
        $isNationalAdmin = $this->isNationalAdmin($currentUser);
        $isSuperAdmin = $this->isSuperAdmin($currentUser);

        // Verifica permessi visualizzazione tramite trait
        if (! $isNationalAdmin && $this->getUserZoneId($currentUser) != $user->zone_id) {
            abort(403, 'Non autorizzato a visualizzare questo utente');
        }

        $user->load(['zone', 'assignments.tournament', 'availabilities']);

        $stats = [
            'total_assignments' => $user->assignments->count(),
            'current_year_assignments' => $user->assignments
                ->filter(fn ($a) => $a->tournament->start_date->year === now()->year)
                ->count(),
            'total_availabilities' => $user->availabilities->count(),
        ];

        return view('admin.users.show', compact('user', 'isNationalAdmin', 'isSuperAdmin', 'stats'));
    }

    /**
     * Form creazione utente
     */
    public function create(): View
    {
        $currentUser = $this->authUser();
        $isNationalAdmin = $this->isNationalAdmin($currentUser);
        $isSuperAdmin = $this->isSuperAdmin($currentUser);

        // L'admin di zona crea account solo nella sua zona
        $zones = $this->zonesForNewUser($currentUser);

        // Circoli disponibili (tutti, anche fuori zona)
        $clubs = \App\Models\Club::orderBy('name')->get();

        $userTypes = UserManagement::assignableTypes($currentUser);

        return view('admin.users.create', compact('zones', 'clubs', 'userTypes', 'isNationalAdmin', 'isSuperAdmin'));
    }

    /**
     * Salva nuovo utente
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $this->authUser();

        // Validazione base
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'zone_id' => 'required|in:'.$this->zonesForNewUser($currentUser)->pluck('id')->implode(','),
            'referee_code' => 'nullable|string|max:20|unique:users',
            'level' => 'required|in:Aspirante,1_livello,Regionale,Nazionale,Internazionale,Archivio',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'club_member' => 'nullable|string|max:255',
            // Stesse regole della modifica (UserManagement)
            'user_type' => 'required|in:'.implode(',', array_keys(UserManagement::assignableTypes($currentUser))),
        ];

        $validated = $request->validate($rules);

        // Imposta password predefinita (come indicato nel form)
        $validated['password'] = Hash::make('password123');

        // Genera automaticamente il campo 'name' concatenando first_name e last_name
        $validated['name'] = trim($validated['first_name'].' '.$validated['last_name']);

        // Gestisci il campo is_active (checkbox)
        $validated['is_active'] = $request->has('is_active');

        // Genera codice arbitro se non è fornito
        if (empty($validated['referee_code'])) {
            $lastUser = User::orderBy('id', 'desc')->first();
            $nextId = $lastUser ? $lastUser->id + 1 : 1;
            $validated['referee_code'] = 'REF'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
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
    public function edit(User $user): View
    {
        $currentUser = $this->authUser();
        if (FigImportAccess::hiddenFrom($user, $currentUser)) {
            abort(404);
        }

        $this->ensureCanManage($currentUser, $user);
        $isNationalAdmin = $this->isNationalAdmin($currentUser);
        $isSuperAdmin = $this->isSuperAdmin($currentUser);

        // Solo il super admin sposta un utente in un'altra zona
        $canChangeZone = UserManagement::canChangeZone($currentUser);
        $zones = $canChangeZone
            ? Zone::orderBy('name')->get()
            : Zone::whereKey($user->zone_id)->get();

        // Circoli disponibili (tutti, anche fuori zona)
        $clubs = \App\Models\Club::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Tipi utente modificabili (stessa regola di update())
        $userTypes = UserManagement::assignableTypes($currentUser);
        $canDeactivate = UserManagement::canToggleActive($currentUser, $user);

        return view('admin.users.edit', compact('user', 'zones', 'clubs', 'userTypes', 'isNationalAdmin', 'isSuperAdmin', 'canChangeZone', 'canDeactivate'));
    }

    /**
     * Aggiorna utente
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $this->authUser();
        if (FigImportAccess::hiddenFrom($user, $currentUser)) {
            abort(404);
        }

        $this->ensureCanManage($currentUser, $user);
        $canChangeZone = UserManagement::canChangeZone($currentUser);

        // Validazione
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            // P14: solo il super admin assegna il tipo super_admin
            'user_type' => 'required|in:'.implode(',', array_keys(UserManagement::assignableTypes($currentUser))),
            // Solo il super admin sposta un utente in un'altra zona
            'zone_id' => $canChangeZone ? 'required|exists:zones,id' : 'nullable',
            'referee_code' => 'nullable|string|max:20|unique:users,referee_code,'.$user->id,
            'level' => 'nullable|in:Aspirante,1_livello,Regionale,Nazionale,Internazionale,Archivio',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,mixed',
            'notes' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'club_member' => 'nullable|string|max:255',
        ];
        // Password opzionale in update
        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }
        $validated = $request->validate($rules);

        // Hash password se fornita
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Genera automaticamente il campo 'name' concatenando first_name e last_name
        $validated['name'] = trim($validated['first_name'].' '.$validated['last_name']);

        if (! $canChangeZone) {
            $validated['zone_id'] = $user->zone_id;
        }

        // Gestisci il campo is_active (checkbox); un super admin resta attivo
        $validated['is_active'] = UserManagement::canToggleActive($currentUser, $user)
            ? $request->has('is_active')
            : $user->is_active || $user->isSuperAdmin();

        // Aggiorna utente
        $user->update($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Utente aggiornato con successo');
    }

    /**
     * Elimina utente
     */
    public function destroy(User $user): RedirectResponse
    {
        $currentUser = $this->authUser();
        if (FigImportAccess::hiddenFrom($user, $currentUser)) {
            abort(404);
        }

        $this->ensureCanManage($currentUser, $user);

        // Non permettere auto-eliminazione
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Non puoi eliminare il tuo stesso account');
        }

        // Verifica se ha assegnazioni
        if (Schema::hasTable('assignments') && $user->assignments()->exists()) {
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
    public function toggleActive(User $user): RedirectResponse
    {
        $currentUser = $this->authUser();
        if (FigImportAccess::hiddenFrom($user, $currentUser)) {
            abort(404);
        }
        $this->ensureCanManage($currentUser, $user);
        if (! UserManagement::canToggleActive($currentUser, $user)) {
            return back()->with('error', $user->isSuperAdmin()
                ? 'Un super admin non può essere disattivato'
                : 'Non puoi disattivare il tuo account');
        }

        // Toggle is_active
        $user->is_active = ! $user->is_active;
        $user->save();

        $status = $user->is_active ? 'attivato' : 'disattivato';

        return back()->with('success', "Utente {$status} con successo");
    }

    /**
     * Blocca chi non puo' gestire l'account (vedi UserManagement).
     */
    private function ensureCanManage(User $currentUser, User $user): void
    {
        if (! UserManagement::canManage($currentUser, $user)) {
            abort(403, 'Non autorizzato a gestire questo utente');
        }
    }

    /**
     * Zone in cui chi opera puo' creare un account: tutte per super admin
     * e CRC, solo la propria per l'admin di zona.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Zone>
     */
    private function zonesForNewUser(User $currentUser): \Illuminate\Database\Eloquent\Collection
    {
        return $currentUser->isZoneAdmin()
            ? Zone::whereKey($currentUser->zone_id)->get()
            : Zone::orderBy('name')->get();
    }
}
