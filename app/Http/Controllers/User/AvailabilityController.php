<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Zone;
use App\Models\TournamentType;
use App\Http\Helpers\RefereeLevelsHelper;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Mail\BatchAvailabilityNotification;
use App\Mail\BatchAvailabilityAdminNotification;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AvailabilityController extends Controller
{
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
        $isNationalUser = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        // Query base per i tornei
        $query = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('start_date', '>=', now());

        // Logica di filtraggio:
        // - Utenti zonali: solo tornei della propria zona
        // - Utenti nazionali/internazionali: tornei della propria zona + tornei nazionali
        if ($isNationalUser) {
            // Tornei della propria zona O tornei nazionali
            $query->where(function ($q) use ($user) {
                $q->whereHas('club', function ($clubQuery) use ($user) {
                    $clubQuery->where('zone_id', $user->zone_id);
                })->orWhereHas('tournamentType', function ($typeQuery) {
                    $typeQuery->where('is_national', true);
                });
            });
        } else {
            // Solo tornei della propria zona
            $query->whereHas('club', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

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
        $zones = $this->getAccessibleZones($user, $isNationalUser);

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
            'available' => 'required|boolean',
            'notes' => 'nullable|string|max:500'
        ]);

        $user = auth()->user();
        $tournament = Tournament::findOrFail($request->tournament_id);

        // Verifica che l'utente possa dichiarare disponibilità per questo torneo
        if (!$this->canDeclareAvailability($user, $tournament)) {
            $errorMessage = 'Non sei autorizzato a dichiarare disponibilità per questo torneo.';

            if ($tournament->start_date < now()) {
                $errorMessage = 'Non puoi dichiarare disponibilità per tornei con date antecedenti a oggi.';
            } elseif ($tournament->availability_deadline && $tournament->availability_deadline < now()) {
                $errorMessage = 'Il termine per dichiarare disponibilità per questo torneo è scaduto.';
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage
                ], 403);
            }
            return back()->with('error', $errorMessage);
        }

        if ($request->available) {
            // Aggiungi o aggiorna disponibilità
            Availability::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id
                ],
                [
                    'notes' => $request->notes,
                    'submitted_at' => now()
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
                'message' => $message
            ]);
        }

        return back()->with('success', $message);
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

        // Recupera i tornei nella pagina corrente (quelli mostrati nel form)
        $pageQuery = Tournament::with(['club', 'zone', 'tournamentType'])
            ->where('start_date', '>=', now());

        $isNationalUser = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        if ($isNationalUser) {
            $pageQuery->where(function ($q) use ($user) {
                $q->whereHas('club', function ($clubQuery) use ($user) {
                    $clubQuery->where('zone_id', $user->zone_id);
                })->orWhereHas('tournamentType', function ($typeQuery) {
                    $typeQuery->where('is_national', true);
                });
            });
        } else {
            $pageQuery->whereHas('club', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

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
                'error' => $e->getMessage()
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
            // Tornei rilevanti per l'utente
            $tournaments = $this->getAccessibleTournaments($user)
                ->with(['tournamentType', 'club.zone', 'assignments'])
                ->get();

            // Disponibilità e assegnazioni dell'utente
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();

            // Raccogli i tipi di torneo unici presenti
            $uniqueTournamentTypes = collect();

            // Formatta per il calendario
            $calendarData = [
                'tournaments' => $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments, $user, &$uniqueTournamentTypes) {
                    $isAvailable = in_array($tournament->id, $userAvailabilities);
                    $isAssigned = in_array($tournament->id, $userAssignments);

                    // Raccogli i tipi di torneo unici (solo se non è assegnato o disponibile)
                    if (!$isAssigned && !$isAvailable && $tournament->tournamentType) {
                        $uniqueTournamentTypes->put($tournament->tournamentType->id, [
                            'name' => $tournament->tournamentType->name,
                            'short_name' => $tournament->tournamentType->short_name,
                            'color' => $tournament->tournamentType->calendar_color ? $tournament->tournamentType->calendar_color . '80' : '#6B7280'
                        ]);
                    }

                    return [
                        'id' => $tournament->id,
                        'title' => $tournament->name ?? 'Torneo #' . $tournament->id,
                        'start' => $tournament->start_date ? $tournament->start_date->format('Y-m-d') : now()->format('Y-m-d'),
                        'end' => $tournament->end_date ? $tournament->end_date->addDay()->format('Y-m-d') : now()->addDay()->format('Y-m-d'),
                        'color' => $this->getEventColor($tournament, $isAvailable, $isAssigned),
                        'borderColor' => $this->getBorderColor($isAvailable, $isAssigned),
                        'extendedProps' => [
                            'club' => $tournament->club->name ?? 'N/A',
                            'zone' => $tournament->club->zone->name ?? 'N/A',
                            'category' => $tournament->tournamentType->name ?? 'N/A',
                            'status' => $tournament->status ?? 'active',
                            'is_available' => $isAvailable,
                            'is_assigned' => $isAssigned,
                            'personal_status' => $this->getPersonalStatus($isAvailable, $isAssigned),
                            'can_declare' => $this->canDeclareAvailability($user, $tournament),
                        ],
                    ];
                }),
                'userType' => 'user',
                'tournamentTypes' => $uniqueTournamentTypes->values(), // Passa i tipi di torneo unici
            ];

            return view('referee.availabilities.calendar', compact('calendarData'));
        } catch (\Exception $e) {
            Log::error('Errore caricamento calendario disponibilità', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            $calendarData = [
                'tournaments' => collect(),
                'userType' => 'user',
                'error' => 'Si è verificato un errore nel caricamento del calendario.'
            ];

            return view('referee.availabilities.calendar', compact('calendarData'));
        }
    }

    /**
     * Private methods
     */
    private function getAccessibleZones($user, $isNationalUser)
    {
        if ($isNationalUser) {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    private function getAccessibleTournaments($user)
    {
        $isNationalUser = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        $query = Tournament::query();

        if ($isNationalUser) {
            // Utente nazionale: tornei della sua zona + tornei nazionali
            $query->where(function ($q) use ($user) {
                $q->whereHas('club', function ($subQ) use ($user) {
                    $subQ->where('zone_id', $user->zone_id);
                })->orWhereHas('tournamentType', function ($subQ) {
                    $subQ->where('is_national', true);
                });
            });
        } else {
            // Utente zonale: solo tornei della sua zona
            $query->whereHas('club', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query;
    }

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

        // Verifica accesso per zona
        $isNationalUser = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        if (!$isNationalUser && $tournament->club && $tournament->club->zone_id != $user->zone_id) {
            return false;
        }

        return true;
    }

    private function handleNotifications($user, $newAvailabilities, $oldAvailabilities)
    {
        $added = array_diff($newAvailabilities, $oldAvailabilities);
        $removed = array_diff($oldAvailabilities, $newAvailabilities);

        if (count($added) > 0 || count($removed) > 0) {
            try {
                $addedTournaments = Tournament::whereIn('id', $added)->get();
                $removedTournaments = Tournament::whereIn('id', $removed)->get();

                // Notifica all'utente
                if (!empty($user->email)) {
                    Mail::to($user->email)->send(new BatchAvailabilityNotification(
                        $user,
                        $addedTournaments,
                        $removedTournaments
                    ));
                }

                // Notifica agli admin di zona
                $adminEmails = $this->getZoneAdminEmails($user->zone_id);
                if (!empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new BatchAvailabilityAdminNotification(
                        $user,
                        $addedTournaments,
                        $removedTournaments
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Errore invio notifiche disponibilità', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function getZoneAdminEmails($zoneId)
    {
        $emails = [];

        // Recupera gli admin della zona
        $zoneAdmins = User::where('zone_id', $zoneId)
            ->where('user_type', 'admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        // Aggiungi gli admin trovati
        $emails = array_merge($emails, $zoneAdmins);

        // Aggiungi anche l'email istituzionale della zona come backup
        $institutionalEmail = "szr{$zoneId}@federgolf.it";
        $emails[] = $institutionalEmail;

        // Rimuovi duplicati e valori vuoti
        return array_unique(array_filter($emails));
    }

    private function getEventColor($tournament, $isAvailable, $isAssigned): string
    {
        // Se assegnato o disponibile, usa colori specifici
        if ($isAssigned) return '#10B981'; // Verde per assegnato
        if ($isAvailable) return '#F59E0B'; // Arancione per disponibile

        // Altrimenti usa il colore del tournament type se disponibile
        if ($tournament->tournamentType && $tournament->tournamentType->calendar_color) {
            // Rendi il colore più tenue aggiungendo trasparenza
            $color = $tournament->tournamentType->calendar_color;
            // Se è un colore hex, aggiungi trasparenza
            if (str_starts_with($color, '#')) {
                return $color . '80'; // Aggiunge 50% di trasparenza
            }
            return $color;
        }

        // Colori di fallback per tipo torneo
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7'];
        return $colors[($tournament->tournament_type_id ?? 1) % count($colors)];
    }

    private function getBorderColor($isAvailable, $isAssigned): string
    {
        if ($isAssigned) return '#059669';
        if ($isAvailable) return '#D97706';
        return '#6B7280';
    }

    private function getPersonalStatus($isAvailable, $isAssigned): string
    {
        if ($isAssigned) return 'assigned';
        if ($isAvailable) return 'available';
        return 'can_apply';
    }

    /**
     * Handle notifications for single availability declaration
     */
    private function handleSingleNotification($user, $tournament, $action)
    {
        try {
            // Prepara i dati per le notifiche
            $addedTournaments = $action === 'added' ? collect([$tournament]) : collect();
            $removedTournaments = $action === 'removed' ? collect([$tournament]) : collect();

            // Notifica all'utente
            if (!empty($user->email)) {
                Mail::to($user->email)->send(new BatchAvailabilityNotification(
                    $user,
                    $addedTournaments,
                    $removedTournaments
                ));
            }

            // Notifica agli admin della sezione
            $zoneId = $tournament->club->zone_id ?? $user->zone_id;
            $adminEmails = $this->getZoneAdminEmails($zoneId);

            if (!empty($adminEmails)) {
                Mail::to($adminEmails)->send(new BatchAvailabilityAdminNotification(
                    $user,
                    $addedTournaments,
                    $removedTournaments
                ));
            }

            // Log per tracciamento
            Log::info('Notifiche disponibilità inviate', [
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'action' => $action,
                'admin_emails' => $adminEmails
            ]);
        } catch (\Exception $e) {
            // Non bloccare l'operazione se l'invio email fallisce
            Log::error('Errore invio notifica disponibilità singola', [
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
}
