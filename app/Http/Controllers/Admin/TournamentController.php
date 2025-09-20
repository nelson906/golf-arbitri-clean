<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentType; // ✅ FIXED: Changed from TournamentCategory
use App\Models\Club;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\CrudActions;

class TournamentController extends Controller
{
    // use CrudActions;
    /**
     * Display a listing of tournaments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Base query with eager loading
        $query = Tournament::with(['club.zone', 'tournamentType']);

        // Filter by zone for zone admins
        if (!$isNationalAdmin) {
            $query->whereHas('club', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('zone_id') && $request->zone_id !== '') {
            $query->whereHas('club', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        // Tournament type filter
        if ($request->has('tournament_type_id') && $request->tournament_type_id !== '') {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->has('month') && $request->month !== '') {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            });
        }

        // Search in tournament name or club name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('club', function($clubQuery) use ($search) {
                      $clubQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Order by start date descending and paginate
        $tournaments = $query->orderBy('start_date', 'desc')->paginate(20);

        // Get data for filters
        $zones = $isNationalAdmin ? Zone::orderBy('name')->get() : collect();
        $tournamentTypes = TournamentType::active()->ordered()->get();
        $statuses = Tournament::STATUSES;

        // ✅ FIXED: compact() uses tournamentTypes instead of categories
        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'tournamentTypes', // ← FIXED: was 'categories'
            'statuses',
            'isNationalAdmin'
        ));
    }

    /**
     * Show tournaments calendar view
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin' || $user->user_type === 'admin';

        // ✅ FIXED: tournamentType relationship
        $tournaments = Tournament::with(['tournamentType', 'zone', 'club', 'assignments.user'])
            ->when(!$isNationalAdmin && !in_array($user->user_type, ['super_admin']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        // Get zones for filter
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs for filter
        $clubs = \App\Models\Club::active()
            ->when(!$isNationalAdmin && !in_array($user->user_type, ['super_admin']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->orderBy('name')
            ->get();

        // ✅ FIXED: Variable name from $types to $tournamentTypes
        $tournamentTypes = TournamentType::active()->ordered()->get();

        // User roles for permissions
        $userRoles = ['Admin'];
        if ($user->user_type === 'super_admin') {
            $userRoles[] = 'SuperAdmin';
        } elseif ($user->user_type === 'national_admin') {
            $userRoles[] = 'NationalAdmin';
        }

        // Format tournaments for calendar
        $calendarTournaments = $tournaments->map(function ($tournament) {
            return [
                'id' => $tournament->id,
                'title' => $tournament->name,
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $this->getAdminEventColor($tournament),
                'borderColor' => $this->getAdminBorderColor($tournament),
                'extendedProps' => [
                    'club' => $tournament->club->name ?? 'N/A',
                    'zone' => $tournament->zone->name ?? 'N/A',
                    'zone_id' => $tournament->zone_id,
                    // ✅ FIXED: tournamentType relationship
                    'tournament_type' => $tournament->tournamentType->name ?? 'N/A',
                    'status' => $tournament->status,
                    'tournament_url' => route('admin.tournaments.show', $tournament),
                    'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                    'type_id' => $tournament->tournament_type_id,
                    'availabilities_count' => $tournament->availabilities()->count(),
                    'assignments_count' => $tournament->assignments()->count(),
                    'required_referees' => $tournament->required_referees ?? 1,
                    'max_referees' => $tournament->max_referees ?? 4,
                    'management_priority' => 'open',
                ],
            ];
        });

        // Prepare data for React component
        $calendarData = [
            'tournaments' => $calendarTournaments,
            'zones' => $zones->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ];
            }),
            'clubs' => $clubs->map(function ($club) {
                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'zone_id' => $club->zone_id,
                ];
            }),
            // ✅ FIXED: tournamentTypes instead of types
            'tournamentTypes' => $tournamentTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'short_name' => $type->short_name,
                    'is_national' => $type->is_national,
                ];
            }),
            'userRoles' => $userRoles,
            'userType' => 'admin',
            'canModify' => true,
        ];

        return view('admin.tournaments.calendar', compact('calendarData'));
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create()
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Get tournament types based on user role
        if ($isNationalAdmin) {
            // National admin sees all active types
            $tournamentTypes = TournamentType::active()->ordered()->get();
        } else {
            // Zone admin sees both national types AND zone-specific types
            $tournamentTypes = TournamentType::active()
                ->where(function ($query) {
                    $query->where('is_national', true)  // National types
                          ->orWhere('is_national', false); // Zone types
                })
                ->ordered()
                ->get();
        }
        // Get zones
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs
        $clubs = Club::active()
            ->when(!$isNationalAdmin, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->ordered()
            ->get();

        return view('admin.tournaments.create', compact('tournamentTypes', 'zones', 'clubs'));
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if editable
        if (!$tournament->isEditable()) {
            return redirect()
                ->route('admin.tournaments.show', $tournament)
                ->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
        }

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Get tournament types based on user role - same logic as create
        if ($isNationalAdmin) {
            // National admin sees all active types
            $tournamentTypes = TournamentType::active()->ordered()->get();
        } else {
            // Zone admin sees both national types AND zone-specific types
            $tournamentTypes = TournamentType::active()
                ->where(function ($query) {
                    $query->where('is_national', true)  // National types
                          ->orWhere('is_national', false); // Zone types
                })
                ->ordered()
                ->get();
        }

        // Get zones
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs
        $clubs = Club::active()
            ->where('zone_id', $tournament->zone_id)
            ->ordered()
            ->get();

        return view('admin.tournaments.edit', compact('tournament', 'tournamentTypes', 'zones', 'clubs'));
    }


    /**
     * Store a newly created tournament in storage.
     */
    public function store(TournamentRequest $request)
    {
        $data = $request->validated();

        // Set zone_id from club if not national admin
        if (auth()->user()->user_type !== 'national_admin') {
            $club = club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
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
    public function show(Tournament $tournament)
    {
        $user = auth()->user();

        // Check permissions
        if ($user->user_type === 'admin' && $user->zone_id !== $tournament->zone_id) {
            abort(403, 'Non hai i permessi per visualizzare questo torneo.');
        }

        // Ora le relazioni funzioneranno con le tabelle corrette
        $tournament->load([
            'tournamentType',
            'zone',
            'club',
            'assignments.user',
            'availabilities.user'
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
                ? now()->diffInDays($tournament->availability_deadline, false)
                : null
        ];

        return view('admin.tournaments.show', compact(
            'tournament',
            'assignedReferees',
            'availableReferees',
            'stats'
        ));
    }


    /**
     * Update the specified tournament in storage.
     */
    public function update(TournamentRequest $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if editable
        if (!$tournament->isEditable()) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
        }

        $data = $request->validated();

        // Update zone_id from club if changed
        if (isset($data['club_id']) && $data['club_id'] != $tournament->club_id) {
            $club = club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
        }

        $tournament->update($data);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Torneo aggiornato con successo!');
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if can be deleted
        if ($tournament->assignments()->exists()) {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('error', 'Impossibile eliminare un torneo con assegnazioni.');
        }

        if ($tournament->status !== 'draft') {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('error', 'Solo i tornei in bozza possono essere eliminati.');
        }

        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Torneo eliminato con successo!');
    }

    /**
     * Update tournament status.
     */
    public function updateStatus(Request $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Tournament::STATUSES))],
        ]);

        $newStatus = $request->status;
        $currentStatus = $tournament->status;

        // Validate status transition
        $validTransitions = [
            'draft' => ['open'],
            'open' => ['closed'],
            'closed' => ['open', 'assigned'],
            'assigned' => ['completed'],
            'completed' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Transizione di stato non valida.'
            ], 400);
        }

        // Additional checks
        if ($newStatus === 'assigned' && $tournament->assignments()->count() < $tournament->required_referees) {
            return response()->json([
                'success' => false,
                'message' => 'Non ci sono abbastanza arbitri assegnati.'
            ], 400);
        }

        $tournament->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Stato aggiornato con successo.',
            'new_status' => $newStatus,
            'new_status_label' => Tournament::STATUSES[$newStatus],
            'new_status_color' => $tournament->status_color,
        ]);
    }

    /**
     * Show availabilities for a tournament.
     */
    public function availabilities(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Get available referees with their level and zone
        $availabilities = $tournament->availabilities()
            ->with([
                'user' => function ($query) {
                    $query->with('zone');
                }
            ])
            ->get()
            ->sortBy('user.name');

        // Get all eligible referees who haven't declared availability
        $eligibleReferees = \App\Models\User::where('user_type', 'referee')
            ->where('is_active', true)
            // ✅ FIXED: Use tournamentType relationship
            ->when($tournament->tournamentType->is_national, function ($q) {
                $q->whereIn('level', ['nazionale', 'internazionale']);
            }, function ($q) use ($tournament) {
                $q->where('zone_id', $tournament->zone_id);
            })
            ->whereNotIn('id', $tournament->availabilities()->pluck('user_id'))
            ->whereNotIn('id', $tournament->assignments()->pluck('user_id'))
            ->orderBy('name')
            ->get();

        return view('admin.tournaments.availabilities', compact(
            'tournament',
            'availabilities',
            'eligibleReferees'
        ));
    }

    /**
     * Check if user can access tournament.
     */
    private function checkTournamentAccess(Tournament $tournament)
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
        }
    }

    /**
     * Get clubs for a specific zone (AJAX).
     */
    public function getclubsByZone(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
        ]);

        $clubs = club::active()
            ->where('zone_id', $request->zone_id)
            ->ordered()
            ->get(['id', 'name', 'short_name']);

        return response()->json($clubs);
    }

    /**
     * Configurazione per il trait
     */
    protected function getEntityName($model): string
    {
        return 'Torneo';
    }

    protected function getIndexRoute(): string
    {
        return 'admin.tournaments.index';
    }

    protected function getDeleteErrorMessage($model): string
    {
        return 'Impossibile eliminare un torneo con assegnazioni.';
    }

    protected function canBeDeleted($tournament): bool
    {
        return !$tournament->assignments()->exists() && $tournament->status === 'draft';
    }

    protected function checkAccess($tournament): void
    {
        $this->checkTournamentAccess($tournament);
    }

    // ===============================================
    // 🎨 COLOR LOGIC PER ADMIN CALENDAR
    // ===============================================

    /**
     * 🎨 Get event color for admin calendar - NOMI REALI DAL DATABASE
     */
    private function getAdminEventColor($tournament): string
    {
        // Usa short_name per mappatura più efficiente
        $shortName = $tournament->tournamentType->short_name ?? 'default';
        return match ($shortName) {
            // 🟢 GARE GIOVANILI (Verde chiaro)
            'G12', 'G14', 'G16', 'G18' => '#96CEB4',  // Verde - Gare Giovanili
            'S14', 'T18' => '#96CEB4',                 // Verde - Circuiti Giovanili
            'USK' => '#96CEB4',                        // Verde - US Kids

            // 🔵 GARE NORMALI (Blu)
            'GN36', 'GN54', 'GN72' => '#45B7D1',      // Blu - Gare normali
            'MP' => '#45B7D1',                         // Blu - Match Play
            'EVEN' => '#45B7D1',                       // Blu - Eventi

            // 🟡 TROFEI (Teal)
            'TG', 'TGF' => '#4ECDC4',                  // Teal - Trofei Giovanili
            'TR', 'TNZ' => '#4ECDC4',                  // Teal - Trofei Regionali/Nazionali

            // 🔴 CAMPIONATI (Rosso)
            'CR', 'CNZ', 'CI' => '#FF6B6B',           // Rosso - Campionati

            // 🟠 PROFESSIONALI (Amber)
            'PRO', 'PATR' => '#F59E0B',               // Amber - Professionistiche/Patrocinate
            'GRS' => '#F59E0B',                        // Amber - Regolamento Speciale

            // 🔵 DEFAULT
            default => '#3B82F6'                       // Blu default
        };
    }

    /**
     * 🎨 Get border color for admin calendar
     */
    private function getAdminBorderColor($tournament): string
    {
        // Admin border: basato su STATUS TORNEO
        return match ($tournament->status) {
            'draft' => '#F59E0B',       // Amber - Draft
            'open' => '#10B981',        // Green - Published/Open
            'closed' => '#6B7280',      // Gray - Closed
            'assigned' => '#059669',    // Dark Green - Assigned
            'completed' => '#374151',   // Dark Gray - Completed
            'cancelled' => '#EF4444',   // Red - Cancelled
            default => '#10B981'        // Green default
        };
    }

    /**
     * 🎨 Calculate management priority
     */
    private function getManagementPriority($tournament): string
    {
        try {
            $availabilities = $tournament->availabilities()->count();
            $assignments = $tournament->assignments()->count();
            $required = $tournament->required_referees ?? $tournament->tournamentType->min_referees ?? 1;

            // Calcola giorni fino alla deadline
            $daysUntilDeadline = 999;
            if ($tournament->availability_deadline) {
                $daysUntilDeadline = $tournament->availability_deadline->diffInDays(now(), false);
            }

            // Urgent: Missing referees or overdue deadline
            if ($daysUntilDeadline < 0 || $assignments < $required) {
                return 'urgent';
            }

            // Complete: Fully staffed
            if ($assignments >= $required) {
                return 'complete';
            }

            // In progress: Has some availability/assignments but not complete
            if ($availabilities > 0 || $assignments > 0) {
                return 'in_progress';
            }

            // Open: Ready for availability submissions
            return 'open';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
