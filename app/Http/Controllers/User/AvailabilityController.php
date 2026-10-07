<?php

namespace App\Http\Controllers\User;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Mail\BatchAvailabilityAdminNotification;
use App\Mail\BatchAvailabilityNotification;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use App\Support\Untrusted;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    use HasZoneVisibility;

    protected TournamentColorService $colorService;

    protected CalendarDataService $calendarService;

    public function __construct(
        TournamentColorService $colorService,
        CalendarDataService $calendarService,
    ) {
        $this->colorService = $colorService;
        $this->calendarService = $calendarService;
    }

    /**
     * Show user's availabilities
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $user = $this->authUser();

        // Disponibilità dell'utente con i tornei associati
        $availabilities = $user->availabilities()
            ->with(['tournament.club', 'tournament.tournamentType'])
            ->join('tournaments', 'availabilities.tournament_id', '=', 'tournaments.id')
            ->orderBy('tournaments.start_date', 'desc')
            ->select('availabilities.*')
            ->get();

        return view('referee.availabilities.index', compact('availabilities'));
    }

    /**
     * Show tournaments for declaring availability
     */
    public function tournaments(Request $request): \Illuminate\Contracts\View\View
    {
        $user = $this->authUser();

        // Query base per i tornei futuri
        $query = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('start_date', '>=', now());

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($query, $user);

        // Filtri opzionali
        if ($request->filled('zone_id')) {
            // Zona dal circolo o, per i tornei T.B.A., dalla colonna (D10)
            $zoneFilter = $request->integer('zone_id');
            $query->where(fn ($z) => $z
                ->whereHas('club', fn ($q) => $q->where('zone_id', $zoneFilter))
                ->orWhere('zone_id', $zoneFilter));
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->integer('tournament_type_id'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('start_date', $request->integer('month'));
        }

        $tournaments = $query->orderBy('start_date')->paginate(20);

        // Recupera le disponibilità già dichiarate
        $userAvailabilities = $user->availabilities()
            ->pluck('tournament_id')
            ->toArray();

        // Zone accessibili per i filtri
        $zones = $this->isNationalReferee($user)
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Tipi di torneo
        $tournamentTypes = TournamentType::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('referee.availabilities.tournaments', compact(
            'tournaments',
            'userAvailabilities',
            'zones',
            'tournamentTypes'
        ));
    }

    /**
     * Store/update availability for tournament
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'available' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $this->authUser();

        if (! $user->isReferee()) {
            abort(403);
        }
        /** @var Tournament $tournament */
        $tournament = Tournament::findOrFail($request->tournament_id);

        // Verifica che l'utente possa dichiarare disponibilità per questo torneo
        if (! $this->canDeclareAvailability($user, $tournament)) {
            $errorMessage = 'Non sei autorizzato a dichiarare disponibilità per questo torneo.';

            if ($tournament->start_date < now()) {
                $errorMessage = 'Non puoi dichiarare disponibilità per tornei con date antecedenti a oggi.';
            } elseif (! $tournament->acceptsAvailability()) {
                $errorMessage = 'Il termine per dichiarare disponibilità per questo torneo è scaduto.';
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                ], 403);
            }

            return back()->withErrors(['availability' => $errorMessage]);
        }

        $available = $request->boolean('available', true);

        if ($available) {
            // Aggiungi o aggiorna disponibilità
            $availability = Availability::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                ],
                [
                    'notes' => $request->notes,
                    'submitted_at' => now(),
                ]
            );
            $message = 'Disponibilità dichiarata con successo.';

            // Email solo se la disponibilita' e' davvero nuova: una seconda
            // dichiarazione (doppio clic, richiesta ripetuta) non manda niente
            $mailWarning = $availability->wasRecentlyCreated
                ? $this->handleSingleNotification($user, $tournament, 'added')
                : null;
        } else {
            // Rimuovi disponibilità
            $removed = Availability::where('user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->delete();
            $message = 'Disponibilità rimossa con successo.';

            // Email solo se c'era davvero una disponibilita' da togliere
            $mailWarning = $removed > 0
                ? $this->handleSingleNotification($user, $tournament, 'removed')
                : null;
        }

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            // La pagina si ricarica dopo la risposta: messaggio e avviso giallo
            // (riepilogo non partito) restano in sessione per comparire li'
            session()->flash('success', $message);
            if ($mailWarning !== null) {
                session()->flash('warning', $mailWarning);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'email_sent' => $mailWarning === null,
                'warning' => $mailWarning,
            ]);
        }

        return back()->with('success', $message)->with('warning', $mailWarning);
    }

    /**
     * Remove a single availability
     */
    public function destroy(Availability $availability): RedirectResponse
    {
        $user = $this->authUser();

        if ((int) $availability->user_id !== (int) $user->id) {
            abort(403);
        }

        $tournament = $availability->tournament;

        // Decisione 2026-10-03 (P2): dopo la scadenza la disponibilita' resta
        if ($tournament && ! $tournament->acceptsAvailability()) {
            return back()->withErrors([
                'availability' => 'La scadenza è passata: la disponibilità non si può più ritirare. Contatta la zona o il CRC.',
            ]);
        }

        $availability->delete();

        // FIX A5: notifica arbitro + SZR/CRC anche su questo percorso di rimozione
        // (prima solo store(available=false) notificava — workflow asimmetrico)
        $mailWarning = $tournament
            ? $this->handleSingleNotification($user, $tournament, 'removed')
            : null;

        return back()->with('success', 'Disponibilità rimossa con successo.')
            ->with('warning', $mailWarning);
    }

    /**
     * Salva le disponibilita' della pagina "Dichiara Disponibilita'".
     *
     * Decisione 2026-10-03 (P1, P2): le disponibilita' devono rimanere SEMPRE.
     * Il salvataggio modifica solo i tornei che il form dichiara di aver
     * mostrato (`page_tournaments[]`) e che accettano ancora disponibilita'
     * (torneo non iniziato, scadenza non passata, visibile all'arbitro).
     * Disponibilita' su tornei di altre pagine, esclusi dai filtri o con
     * scadenza passata non vengono mai toccate.
     */
    public function saveBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'availabilities' => 'array',
            'availabilities.*' => 'exists:tournaments,id',
            'page_tournaments' => 'array',
            'page_tournaments.*' => 'integer',
        ]);

        $user = $this->authUser();

        $selected = Untrusted::intList($request->array('availabilities'));
        $shown = Untrusted::intList($request->array('page_tournaments'));

        // Tornei mostrati E ancora modificabili da questo arbitro
        $editableIds = Untrusted::intList(
            Tournament::whereIn('id', $shown)
                ->get()
                ->filter(fn (Tournament $t) => $this->canDeclareAvailability($user, $t))
                ->pluck('id')
                ->all()
        );

        $existing = Untrusted::intList(
            Availability::where('user_id', $user->id)
                ->whereIn('tournament_id', $editableIds)
                ->pluck('tournament_id')
                ->toArray()
        );

        $wanted = array_values(array_intersect($selected, $editableIds));
        $toAdd = array_values(array_diff($wanted, $existing));
        $toRemove = array_values(array_diff($existing, $wanted));

        DB::beginTransaction();

        try {
            if ($toRemove !== []) {
                Availability::where('user_id', $user->id)
                    ->whereIn('tournament_id', $toRemove)
                    ->delete();
            }

            foreach ($toAdd as $tournamentId) {
                Availability::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournamentId,
                    'submitted_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Errore salvataggio disponibilità batch', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Errore durante il salvataggio. Riprova.']);
        }

        // Notifiche solo sulle differenze reali
        $mailWarning = $this->handleNotifications($user, $wanted, $existing);

        return redirect()->route('user.availability.index')
            ->with('success', 'Disponibilità aggiornate con successo!')
            ->with('warning', $mailWarning);
    }

    /**
     * Show calendar view for user
     */
    public function calendar(Request $request): View
    {
        $user = $this->authUser();

        try {
            // Tornei rilevanti per l'utente con filtro visibilità centralizzato
            $query = Tournament::with(['tournamentType', 'club.zone', 'assignments']);
            $this->applyTournamentVisibility($query, $user);
            $tournaments = $query->get();

            // Disponibilità e assegnazioni dell'utente
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();

            // Usa CalendarDataService per preparare i dati
            $calendarData = $this->calendarService->prepareFullCalendarData(
                $tournaments,
                $user,
                'referee',
                [
                    'availableTournamentIds' => $userAvailabilities,
                    'assignedTournamentIds' => $userAssignments,
                    'tournamentTypes' => TournamentType::active()->ordered()->get(),
                ]
            );
            // Aggiungi can_declare per ogni torneo (logica specifica di questo controller)
            $calendarData['tournaments'] = $calendarData['tournaments']->map(function (array $event) use ($user, $tournaments) {
                $tournament = $tournaments->firstWhere('id', $event['id'] ?? null);
                if ($tournament && is_array($event['extendedProps'] ?? null)) {
                    $event['extendedProps']['can_declare'] = $this->canDeclareAvailability($user, $tournament);
                }

                return $event;
            });

            return view('referee.availabilities.calendar', compact('calendarData'));
        } catch (\Exception $e) {
            Log::error('Errore caricamento calendario disponibilità', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $calendarData = [
                'tournaments' => collect(),
                'userType' => 'user',
                'error' => 'Si è verificato un errore nel caricamento del calendario.',
            ];

            return view('referee.availabilities.calendar', compact('calendarData'));
        }
    }

    /**
     * Private methods
     * Nota: getAccessibleZones e getAccessibleTournaments sono ora nel trait HasZoneVisibility
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Tournament  $tournament
     */
    private function canDeclareAvailability($user, $tournament): bool
    {
        // Torneo non iniziato e scadenza non passata (regola unica nel model)
        if (! $tournament->acceptsAvailability()) {
            return false;
        }

        // Verifica accesso per zona usando il trait
        return $this->canAccessTournament($tournament, $user);
    }

    /**
     * Gestisce l'invio delle notifiche per aggiornamenti batch di disponibilità.
     *
     * - Arbitro: memo con TUTTE le disponibilita' modificate
     * - SZR: tutti i tornei della propria zona, zonali e nazionali (P5, 2026-10-03)
     * - CRC: i tornei nazionali
     * (dettaglio in sendSeparatedAdminNotifications)
     *
     * @param  User  $user  L'arbitro che ha modificato le disponibilità
     * @param  list<int>  $newAvailabilities  ID tornei con nuova disponibilità
     * @param  list<int>  $oldAvailabilities  ID tornei con disponibilità precedente
     */
    private function handleNotifications(User $user, array $newAvailabilities, array $oldAvailabilities): ?string
    {
        $added = array_diff($newAvailabilities, $oldAvailabilities);
        $removed = array_diff($oldAvailabilities, $newAvailabilities);

        if (count($added) === 0 && count($removed) === 0) {
            return null; // Nessuna modifica, nessuna notifica
        }

        return $this->notifyAvailabilityChanges(
            $user,
            Tournament::with(['club', 'tournamentType'])->whereIn('id', $added)->get(),
            Tournament::with(['club', 'tournamentType'])->whereIn('id', $removed)->get()
        );
    }

    /**
     * Gestisce l'invio delle notifiche per una singola dichiarazione di disponibilità.
     *
     * Invia due tipi di notifiche:
     * 1. MEMO all'arbitro (conferma della dichiarazione/rimozione)
     * 2. Notifica agli admin appropriati (determinati da getAdminEmailsForNotification)
     *
     * @param  User  $user  L'arbitro che ha dichiarato/rimosso la disponibilità
     * @param  Tournament  $tournament  Il torneo per cui è stata modificata la disponibilità
     * @param  string  $action  'added' o 'removed'
     */
    private function handleSingleNotification(User $user, Tournament $tournament, string $action): ?string
    {
        $tournament->load(['club', 'tournamentType']);

        return $this->notifyAvailabilityChanges(
            $user,
            $action === 'added' ? collect([$tournament]) : collect(),
            $action === 'removed' ? collect([$tournament]) : collect()
        );
    }

    /**
     * Riepilogo all'arbitro + avvisi agli admin.
     *
     * Decisione 2026-10-04: l'arbitro deve avere un ritorno sicuro. Le due parti
     * sono indipendenti (un errore sugli avvisi admin non blocca il riepilogo
     * all'arbitro, e viceversa) e il metodo restituisce l'avviso da mostrare
     * all'arbitro quando il SUO riepilogo non e' partito; null se e' partito.
     * Gli errori sugli avvisi admin restano solo nel log.
     *
     * @param  Collection<int, Tournament>  $addedTournaments
     * @param  Collection<int, Tournament>  $removedTournaments
     */
    private function notifyAvailabilityChanges(User $user, Collection $addedTournaments, Collection $removedTournaments): ?string
    {
        $warning = null;

        if (empty($user->email)) {
            $warning = 'Il tuo profilo non ha un indirizzo email: non riceverai il riepilogo. '
                .'Le disponibilità sono comunque salvate.';
        } else {
            try {
                Mail::to($user->email)->send(new BatchAvailabilityNotification(
                    $user,
                    $addedTournaments,
                    $removedTournaments
                ));
            } catch (\Throwable $e) {
                Log::error('Riepilogo disponibilità all\'arbitro non inviato', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $warning = 'L\'email di riepilogo non è partita. Le disponibilità sono comunque salvate: '
                    .'puoi controllarle in «Le Mie Disponibilità».';
            }
        }

        try {
            $this->sendSeparatedAdminNotifications($user, $addedTournaments, $removedTournaments);
        } catch (\Throwable $e) {
            Log::error('Avviso disponibilità agli admin non inviato', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $warning;
    }

    /**
     * Avvisi agli amministratori per le disponibilita' aggiunte/tolte.
     *
     * Decisione 2026-10-03 (P5):
     * - ogni zona riceve l'avviso per TUTTI i tornei che si giocano nella sua
     *   zona, zonali e nazionali (la SZR designa gli osservatori dei nazionali);
     * - il CRC riceve l'avviso per i tornei nazionali.
     * Una mail per zona: gli admin di una zona non vedono i tornei (ne' gli
     * indirizzi) delle altre.
     *
     * @param  \Illuminate\Support\Collection<int, Tournament>  $addedTournaments
     * @param  \Illuminate\Support\Collection<int, Tournament>  $removedTournaments
     */
    private function sendSeparatedAdminNotifications(User $user, Collection $addedTournaments, Collection $removedTournaments): void
    {
        $allTournaments = $addedTournaments->merge($removedTournaments);

        // ── SZR: una mail per zona, tornei zonali e nazionali della zona ──
        $byZone = $allTournaments->groupBy(
            fn (Tournament $t): int => (int) ($t->club->zone_id ?? $t->zone_id ?? 0)
        );

        foreach ($byZone as $zoneId => $zoneTournaments) {
            if ($zoneId === 0) {
                continue;
            }

            $zoneEmails = $this->collectZoneAdminEmails($zoneTournaments);
            if (empty($zoneEmails)) {
                continue;
            }

            $ids = $zoneTournaments->pluck('id');

            Mail::to($zoneEmails)->send(new BatchAvailabilityAdminNotification(
                $user,
                $addedTournaments->whereIn('id', $ids),
                $removedTournaments->whereIn('id', $ids)
            ));

            Log::info('Notifica SZR disponibilità inviata', [
                'user_id' => $user->id,
                'zone_id' => $zoneId,
                'tournaments_count' => $ids->count(),
            ]);
        }

        // ── CRC: solo tornei nazionali ──
        $nationalIds = $allTournaments
            ->filter(fn ($t) => $t->tournamentType->is_national ?? false)
            ->pluck('id');

        if ($nationalIds->isNotEmpty()) {
            $crcEmails = $this->collectNationalAdminEmails();

            if (! empty($crcEmails)) {
                Mail::to($crcEmails)->send(new BatchAvailabilityAdminNotification(
                    $user,
                    $addedTournaments->whereIn('id', $nationalIds),
                    $removedTournaments->whereIn('id', $nationalIds)
                ));

                Log::info('Notifica CRC disponibilità inviata (solo tornei nazionali)', [
                    'user_id' => $user->id,
                    'crc_emails' => count($crcEmails),
                    'tournaments_count' => $nationalIds->count(),
                ]);
            }
        }
    }

    /**
     * Raccoglie email zone admin per i tornei specificati
     *
     * @param  iterable<int, \App\Models\Tournament>  $tournaments
     * @return array<string, mixed>
     */
    private function collectZoneAdminEmails($tournaments): array
    {
        // FIX M3: raccoglie prima tutti gli zone_id, poi una sola query whereIn
        // (prima: una query per torneo)
        $zoneIds = collect($tournaments)
            ->map(fn ($tournament) => $tournament->club->zone_id ?? $tournament->zone_id)
            ->filter()
            ->unique()
            ->values();

        if ($zoneIds->isEmpty()) {
            return [];
        }

        // Uso UserType::ZoneAdmin->value invece della stringa 'admin' per robustezza
        $emails = User::whereIn('zone_id', $zoneIds)
            ->where('user_type', UserType::ZoneAdmin->value)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        return array_unique(array_filter($emails));
    }

    /**
     * Raccoglie email national admin (CRC)
     *
     * @return array<string, mixed>
     */
    private function collectNationalAdminEmails(): array
    {
        return User::where('user_type', UserType::NationalAdmin->value)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();
    }
}
