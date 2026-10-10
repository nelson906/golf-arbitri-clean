<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use App\Services\CalendarDataService;
use App\Support\TournamentVisibility;
use App\Traits\HasZoneVisibility;
use App\Traits\TournamentControllerTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    use HasZoneVisibility;
    use TournamentControllerTrait;

    public function __construct(CalendarDataService $calendarService)
    {
        $this->initTournamentServices($calendarService);
    }

    /**
     * Display a listing of tournaments.
     */
    public function index(Request $request): View
    {
        $user = $this->authUser();

        $query = Tournament::with(['club.zone', 'tournamentType', 'notification'])->withCount(['assignments', 'availabilities']);
        $this->applyTournamentVisibility($query, $user);

        // Filtro status specifico admin

        // Filtro club specifico admin
        if ($request->filled('club_id')) {
            $query->where('club_id', $request->club_id);
        }

        // Usa metodo condiviso dal trait
        $this->applyCommonFilters($query, $request);

        // Riepilogo per data (lo stato del torneo non esiste piu': decisione 2026-10-03)
        $today = now()->startOfDay();
        $summary = [
            'total' => (clone $query)->count(),
            'upcoming' => (clone $query)->where('start_date', '>=', $today)->count(),
            'past' => (clone $query)->where('end_date', '<', $today)->count(),
            'without_referees' => (clone $query)->where('start_date', '>=', $today)->doesntHave('assignments')->count(),
        ];

        $tournaments = $query->orderBy('start_date', 'asc')->paginate(20);

        // Usa metodo condiviso dal trait
        $this->addDeadlineInfo($tournaments);

        $zones = $this->isNationalAdmin($user) ? Zone::orderBy('name', 'asc')->get() : collect();
        $tournamentTypes = TournamentType::active()->ordered()->get();

        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'tournamentTypes',
            'summary'
        ))->with('isNationalAdmin', $this->isNationalAdmin($user));
    }

    /**
     * Show tournaments calendar view
     */
    public function calendar(Request $request): View
    {
        $user = $this->authUser();

        // Conteggi in una sola query (prima: disponibilita' caricate torneo
        // per torneo per contarle)
        $tournaments = Tournament::visible($user)
            ->with(['tournamentType', 'zone', 'club', 'assignments.user'])
            ->withCount(['availabilities', 'assignments'])
            ->get();

        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Usa metodo condiviso dal trait
        $calendarData = $this->prepareCalendarData(
            $tournaments,
            $user,
            'admin',
            [
                'zones' => $zones,
                'clubs' => Club::visible($user)->active()->orderBy('name')->get(),
            ]
        );

        // Override per admin roles
        $calendarData['userRoles'] = $this->getAdminRoles($user);

        return view('admin.tournaments.calendar', compact('calendarData'));
    }

    /**
     * Get admin roles for permissions
     *
     * @return list<string>
     */
    private function getAdminRoles(User $user): array
    {
        $roles = ['Admin'];
        if ($user->isSuperAdmin()) {
            $roles[] = 'SuperAdmin';
        } elseif ($user->user_type === UserType::NationalAdmin) {
            $roles[] = 'NationalAdmin';
        }

        return $roles;
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create(): View
    {
        $user = $this->authUser();
        $isNationalAdmin = $user->isNationalAdmin();

        // Tutti gli admin vedono tutti i tipi di torneo attivi
        $tournamentTypes = $this->tournamentTypesForForm();

        // Get zones con visibilità
        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Get clubs con visibilità
        $clubsQuery = Club::active();
        $this->applyClubVisibility($clubsQuery, $user);
        $clubs = $clubsQuery->ordered()->get();

        return view('admin.tournaments.create', compact('tournamentTypes', 'zones', 'clubs'));
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Tournament $tournament): View
    {
        // Check access usando il trait
        $this->checkTournamentEditable($tournament);

        $user = $this->authUser();

        // Tutti gli admin vedono tutti i tipi di torneo attivi
        $tournamentTypes = $this->tournamentTypesForForm();

        // Get zones con visibilità
        $zones = $this->isNationalAdmin($user)
            ? Zone::orderBy('name', 'asc')->get()
            : Zone::where('id', '=', $user->zone_id)->get();

        // Get clubs della zona del torneo
        $clubs = Club::active()
            ->where('zone_id', $tournament->zone_id)
            ->ordered()
            ->get();

        return view('admin.tournaments.edit', compact('tournament', 'tournamentTypes', 'zones', 'clubs'));
    }

    /**
     * Store a newly created tournament in storage.
     */
    public function store(TournamentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Set zone_id from club se admin zonale.
        // Su un torneo T.B.A. (nessun circolo scelto) la zona e' quella
        // dell'admin che lo sta creando: e' l'unica informazione disponibile,
        // ed e' cio' che lo tiene visibile nel suo calendario.
        if ($this->isZoneAdmin()) {
            $clubId = $request->integer('club_id');

            $data['zone_id'] = $clubId > 0
                ? Club::findOrFail($clubId)->zone_id
                : $this->authUser()->zone_id;
        } elseif ($request->integer('club_id') > 0) {
            // CRC e super admin: con un circolo scelto vale la sua zona
            $data['zone_id'] = Club::findOrFail($request->integer('club_id'))->zone_id;
        }
        $data['created_by'] = auth()->id();

        // Create tournament
        $tournament = Tournament::create($data);

        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo creato con successo!');
    }

    /**
     * Display the specified tournament for admin view
     */
    public function show(Tournament $tournament): View
    {
        $user = $this->authUser();

        // Check permissions using trait method (consistent with edit/update)
        if (! $this->canAccessTournament($tournament, $user)) {
            abort(403, 'Non hai i permessi per visualizzare questo torneo.');
        }

        // Ora le relazioni funzioneranno con le tabelle corrette
        $tournament->load([
            'tournamentType',
            'zone',
            'club',
            'assignments.user',
            'availabilities.user',
        ]);

        // Ottieni gli arbitri assegnati
        $assignedReferees = $tournament->assignments()
            ->with(relations: 'user')
            ->get();

        $availableReferees = $tournament->availabilities()
            ->with(relations: 'user')
            ->get();

        // Statistics
        $stats = [
            'total_assignments' => $assignedReferees ? $assignedReferees->count() : 0,
            'total_availabilities' => $availableReferees ? $availableReferees->count() : 0,
            'assigned_referees' => $assignedReferees ? $assignedReferees->count() : 0,
            'required_referees' => $tournament->tournamentType->min_referees ?? 2,
            'days_until_deadline' => $tournament->availability_deadline
                ? (int) now()->startOfDay()->diffInDays($tournament->availability_deadline->copy()->startOfDay(), false)
                : null,
        ];

        $canEdit = TournamentVisibility::canEdit($tournament, $user);

        return view('admin.tournaments.show', compact(
            'canEdit',
            'tournament',
            'assignedReferees',
            'availableReferees',
            'stats'
        ));
    }

    /**
     * Update the specified tournament in storage.
     */
    public function update(TournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        // Check access
        $this->checkTournamentEditable($tournament);

        $data = $request->validated();

        // Con un circolo la zona e' sempre la sua (anche se il form ne manda
        // un'altra): un torneo ha una sola zona
        if ($request->integer('club_id') > 0) {
            $data['zone_id'] = Club::findOrFail($request->integer('club_id'))->zone_id;
        }

        $tournament->update($data);

        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo aggiornato con successo!');
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Request $request, Tournament $tournament): RedirectResponse
    {
        // Check access
        $this->checkTournamentEditable($tournament);

        // Check if has assignments and needs confirmation
        if ($tournament->assignments()->exists() && ! $request->has('confirm')) {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('warning', 'Questo torneo ha delle assegnazioni. Per eliminarlo, conferma nuovamente l\'eliminazione.')
                ->with('tournament_id', $tournament->id)
                ->with('tournament_name', $tournament->name);
        }

        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Torneo eliminato con successo!');
    }

    /**
     * Show availabilities for a tournament.
     */
    public function availabilities(Tournament $tournament): View
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Get available referees with their level and zone
        $availabilities = $tournament->availabilities()
            ->with([
                'user' => function ($query) {
                    $query->with('zone');
                },
            ])
            ->get()
            ->sortBy('user.name');

        // Get all eligible referees who haven't declared availability
        $eligibleReferees = \App\Models\User::with('zone')->where('user_type', '=', 'referee')
            ->where('is_active', '=', true)

            // Nazionale: il CRC (e il super admin) vede gli arbitri Nazionali e
            // Internazionali; la SZR gli arbitri della sua zona, di ogni livello,
            // che le servono per gli osservatori (decisione 2026-10-07).
            ->when(($tournament->tournamentType->is_national ?? false) && ! $this->isZoneAdmin(), function ($q) {
                // Usa i valori dell'enum RefereeLevel per evitare inconsistenze di case
                $q->whereIn('level', [\App\Enums\RefereeLevel::Nazionale->value, \App\Enums\RefereeLevel::Internazionale->value]);
            }, function ($q) use ($tournament) {
                $q->where('zone_id', '=', $tournament->zone_id);
            })
            ->whereNotIn('id', $tournament->availabilities()->pluck('user_id'))
            ->whereNotIn('id', $tournament->assignments()->pluck('user_id'))
            ->orderBy('name', 'asc')->get();

        return view('admin.tournaments.availabilities', compact(
            'tournament',
            'availabilities',
            'eligibleReferees'
        ));
    }

    /**
     * Check if user can access tournament (usa il trait HasZoneVisibility).
     */
    private function checkTournamentAccess(Tournament $tournament): void
    {
        if (! $this->canAccessTournament($tournament)) {
            abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
        }
    }

    /**
     * Modifica ed eliminazione: l'admin di zona gestisce i tornei zonali;
     * sui nazionali della sua zona designa solo gli osservatori.
     */
    /**
     * Tipi di torneo che chi opera puo' scegliere nel form: l'admin di zona i
     * zonali, il CRC i nazionali, il super admin tutti.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TournamentType>
     */
    private function tournamentTypesForForm(): \Illuminate\Database\Eloquent\Collection
    {
        $user = $this->authUser();

        return TournamentType::active()->ordered()
            ->when($user->isZoneAdmin(), fn ($q) => $q->where('is_national', false))
            ->when($user->user_type === \App\Enums\UserType::NationalAdmin, fn ($q) => $q->where('is_national', true))
            ->get();
    }

    private function checkTournamentEditable(Tournament $tournament): void
    {
        $this->checkTournamentAccess($tournament);

        if (! TournamentVisibility::canEdit($tournament, $this->authUser())) {
            abort(403, 'I tornei nazionali li gestisce il CRC: la zona designa solo gli osservatori.');
        }
    }
}
