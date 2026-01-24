<?php

namespace App\Http\Controllers\User;

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
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    use HasZoneVisibility;

    protected TournamentColorService $colorService;

    protected CalendarDataService $calendarService;

    public function __construct(TournamentColorService $colorService, CalendarDataService $calendarService)
    {
        $this->colorService = $colorService;
        $this->calendarService = $calendarService;
    }

    /**
     * Show user's availabilities
     */
    public function index()
    {
        $user = auth()->user();

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
    public function tournaments(Request $request)
    {
        $user = auth()->user();

        // Query base per i tornei futuri
        $query = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('start_date', '>=', now());

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($query, $user);

        // Filtri opzionali
        if ($request->filled('zone_id')) {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->filled('month')) {
            $query->whereMonth('start_date', $request->month);
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
    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'available' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        if (! $user || ! $user->isReferee()) {
            abort(403);
        }
        /** @var Tournament $tournament */
        $tournament = Tournament::findOrFail($request->tournament_id);

        // Verifica che l'utente possa dichiarare disponibilità per questo torneo
        if (! $this->canDeclareAvailability($user, $tournament)) {
            $errorMessage = 'Non sei autorizzato a dichiarare disponibilità per questo torneo.';

            if ($tournament->start_date < now()) {
                $errorMessage = 'Non puoi dichiarare disponibilità per tornei con date antecedenti a oggi.';
            } elseif ($tournament->availability_deadline && $tournament->availability_deadline < now()) {
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
            Availability::updateOrCreate(
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

            // Invia notifiche per disponibilità aggiunta
            $this->handleSingleNotification($user, $tournament, 'added');
        } else {
            // Rimuovi disponibilità
            Availability::where('user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->delete();
            $message = 'Disponibilità rimossa con successo.';

            // Invia notifiche per disponibilità rimossa
            $this->handleSingleNotification($user, $tournament, 'removed');
        }

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Remove a single availability
     */
    public function destroy(Availability $availability)
    {
        $user = auth()->user();

        if (! $user || (int) $availability->user_id !== (int) $user->id) {
            abort(403);
        }

        $availability->delete();

        return back()->with('success', 'Disponibilità rimossa con successo.');
    }

    /**
     * Save batch availabilities
     */
    public function saveBatch(Request $request)
    {
        $request->validate([
            'availabilities' => 'array',
            'availabilities.*' => 'exists:tournaments,id',
        ]);

        $user = auth()->user();
        $selectedTournaments = $request->input('availabilities', []);

        // Recupera i tornei nella pagina corrente con filtro visibilità centralizzato
        $pageQuery = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('start_date', '>=', now());

        // Usa il trait per il filtro visibilità
        $this->applyTournamentVisibility($pageQuery, $user);

        // Applica gli stessi filtri della vista
        if ($request->filled('zone_id')) {
            $pageQuery->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }
        if ($request->filled('tournament_type_id')) {
            $pageQuery->where('tournament_type_id', $request->tournament_type_id);
        }
        if ($request->filled('month')) {
            $pageQuery->whereMonth('start_date', $request->month);
        }

        // Ottieni solo i tornei della pagina corrente
        $pageTournamentIds = $pageQuery->pluck('id')->toArray();

        // Filtra solo i tornei selezionati che sono nella pagina corrente
        $selectedTournaments = array_intersect($selectedTournaments, $pageTournamentIds);

        // Ottieni le disponibilità esistenti solo per i tornei della pagina corrente
        $existingAvailabilities = Availability::where('user_id', $user->id)
            ->whereIn('tournament_id', $pageTournamentIds)
            ->pluck('tournament_id')
            ->toArray();

        DB::beginTransaction();

        try {
            // Rimuovi disponibilità solo per i tornei della pagina corrente
            Availability::where('user_id', $user->id)
                ->whereIn('tournament_id', $pageTournamentIds)
                ->delete();

            // Aggiungi le nuove disponibilità selezionate
            foreach ($selectedTournaments as $tournamentId) {
                Availability::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournamentId,
                    'submitted_at' => now(),
                ]);
            }

            DB::commit();

            // Gestione notifiche
            $this->handleNotifications($user, $selectedTournaments, $existingAvailabilities);

            return redirect()->route('user.availability.index')
                ->with('success', 'Disponibilità aggiornate con successo!');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Errore salvataggio disponibilità batch', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Errore durante il salvataggio. Riprova.']);
        }
    }

    /**
     * Show calendar view for user
     */
    public function calendar(Request $request): View
    {
        $user = auth()->user();

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
            $calendarData['tournaments'] = $calendarData['tournaments']->map(function ($event) use ($user, $tournaments) {
                $tournament = $tournaments->firstWhere('id', $event['id']);
                if ($tournament) {
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
     */
    private function canDeclareAvailability($user, $tournament): bool
    {
        // Verifica se il torneo è futuro
        if ($tournament->start_date < now()) {
            return false;
        }

        // Verifica deadline disponibilità se presente
        if ($tournament->availability_deadline && $tournament->availability_deadline < now()) {
            return false;
        }

        // Verifica accesso per zona usando il trait
        return $this->canAccessTournament($tournament, $user);
    }

    /**
     * Gestisce l'invio delle notifiche per aggiornamenti batch di disponibilità.
     *
     * Invia due tipi di notifiche:
     * 1. MEMO all'arbitro (conferma delle modifiche)
     * 2. Notifica agli admin (zone admin e/o national admin per tornei nazionali)
     *
     * @param  User  $user  L'arbitro che ha modificato le disponibilità
     * @param  array  $newAvailabilities  ID tornei con nuova disponibilità
     * @param  array  $oldAvailabilities  ID tornei con disponibilità precedente
     */
    private function handleNotifications($user, $newAvailabilities, $oldAvailabilities)
    {
        $added = array_diff($newAvailabilities, $oldAvailabilities);
        $removed = array_diff($oldAvailabilities, $newAvailabilities);

        if (count($added) > 0 || count($removed) > 0) {
            try {
                $addedTournaments = Tournament::with(['club', 'tournamentType'])->whereIn('id', $added)->get();
                $removedTournaments = Tournament::with(['club', 'tournamentType'])->whereIn('id', $removed)->get();

                // ═══════════════════════════════════════════════════════════════
                // 1. MEMO ALL'ARBITRO - Conferma delle modifiche
                // ═══════════════════════════════════════════════════════════════
                if (! empty($user->email)) {
                    Mail::to($user->email)->send(new BatchAvailabilityNotification(
                        $user,
                        $addedTournaments,
                        $removedTournaments
                    ));
                }

                // ═══════════════════════════════════════════════════════════════
                // 2. NOTIFICA ADMIN - Raccoglie email da tutti i tornei coinvolti
                // ═══════════════════════════════════════════════════════════════
                $allTournaments = $addedTournaments->merge($removedTournaments);
                $adminEmails = [];

                foreach ($allTournaments as $tournament) {
                    $tournamentAdminEmails = $this->getAdminEmailsForNotification($tournament);
                    $adminEmails = array_merge($adminEmails, $tournamentAdminEmails);
                }

                // Rimuovi duplicati
                $adminEmails = array_unique($adminEmails);

                if (! empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new BatchAvailabilityAdminNotification(
                        $user,
                        $addedTournaments,
                        $removedTournaments
                    ));
                }

                Log::info('Notifiche batch disponibilità inviate', [
                    'user_id' => $user->id,
                    'added_count' => count($added),
                    'removed_count' => count($removed),
                    'admin_emails_count' => count($adminEmails),
                ]);
            } catch (\Exception $e) {
                Log::error('Errore invio notifiche disponibilità batch', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Recupera gli indirizzi email degli admin da notificare per una disponibilità.
     *
     * LOGICA DI NOTIFICA DISPONIBILITÀ:
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ TIPO TORNEO              │ DESTINATARI                                  │
     * ├─────────────────────────────────────────────────────────────────────────┤
     * │ Torneo NAZIONALE         │ 1. National admin (CRC)                      │
     * │ (is_national = true)     │ 2. Zone admin della zona del torneo          │
     * ├─────────────────────────────────────────────────────────────────────────┤
     * │ Torneo ZONALE            │ 1. Zone admin della zona del torneo          │
     * │ (is_national = false)    │                                              │
     * └─────────────────────────────────────────────────────────────────────────┘
     *
     * NOTA: Le email istituzionali NON vengono incluse per le notifiche di
     * disponibilità. Vanno solo agli admin utenti (zone admin / national admin).
     *
     * @param  Tournament  $tournament  Il torneo per cui è stata dichiarata disponibilità
     * @return array Lista di email uniche degli admin da notificare
     */
    private function getAdminEmailsForNotification(Tournament $tournament): array
    {
        $emails = [];

        // Determina la zona del torneo (priorità: club.zone_id > tournament.zone_id)
        $tournamentZoneId = $tournament->club->zone_id ?? $tournament->zone_id;

        // Verifica se il torneo è nazionale
        $isNationalTournament = $tournament->tournamentType?->is_national ?? false;

        // ═══════════════════════════════════════════════════════════════════════
        // CASO 1: TORNEO NAZIONALE - Notifica national_admin (CRC)
        // ═══════════════════════════════════════════════════════════════════════
        if ($isNationalTournament) {
            $nationalAdmins = User::where('user_type', 'national_admin')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')
                ->toArray();
            $emails = array_merge($emails, $nationalAdmins);

            Log::debug('Notifica torneo nazionale', [
                'tournament_id' => $tournament->id,
                'national_admins' => count($nationalAdmins),
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // CASO 2: SEMPRE - Zone admin della zona del torneo
        // ═══════════════════════════════════════════════════════════════════════
        if ($tournamentZoneId) {
            $zoneAdmins = User::where('zone_id', $tournamentZoneId)
                ->where('user_type', 'admin')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')
                ->toArray();
            $emails = array_merge($emails, $zoneAdmins);

            Log::debug('Notifica zona torneo', [
                'tournament_id' => $tournament->id,
                'zone_id' => $tournamentZoneId,
                'zone_admins' => count($zoneAdmins),
            ]);
        }

        // Rimuovi duplicati e valori vuoti
        $uniqueEmails = array_unique(array_filter($emails));

        Log::info('Email admin per notifica disponibilità', [
            'tournament_id' => $tournament->id,
            'is_national' => $isNationalTournament,
            'total_emails' => count($uniqueEmails),
        ]);

        return $uniqueEmails;
    }

    // Metodi colore centralizzati in TournamentColorService

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
    private function handleSingleNotification($user, $tournament, $action)
    {
        try {
            // Assicurati che il torneo abbia le relazioni caricate
            $tournament->load(['club', 'tournamentType']);

            // Prepara i dati per le notifiche
            $addedTournaments = $action === 'added' ? collect([$tournament]) : collect();
            $removedTournaments = $action === 'removed' ? collect([$tournament]) : collect();

            // ═══════════════════════════════════════════════════════════════════
            // 1. MEMO ALL'ARBITRO - Conferma della dichiarazione
            // ═══════════════════════════════════════════════════════════════════
            if (! empty($user->email)) {
                Mail::to($user->email)->send(new BatchAvailabilityNotification(
                    $user,
                    $addedTournaments,
                    $removedTournaments
                ));
            }

            // ═══════════════════════════════════════════════════════════════════
            // 2. NOTIFICA ADMIN - Usa la nuova logica che considera tornei nazionali
            // ═══════════════════════════════════════════════════════════════════
            $adminEmails = $this->getAdminEmailsForNotification($tournament);

            if (! empty($adminEmails)) {
                Mail::to($adminEmails)->send(new BatchAvailabilityAdminNotification(
                    $user,
                    $addedTournaments,
                    $removedTournaments
                ));
            }

            // Log per tracciamento
            Log::info('Notifiche disponibilità singola inviate', [
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'is_national' => $tournament->tournamentType?->is_national ?? false,
                'action' => $action,
                'admin_emails_count' => count($adminEmails),
            ]);
        } catch (\Exception $e) {
            // Non bloccare l'operazione se l'invio email fallisce
            Log::error('Errore invio notifica disponibilità singola', [
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
