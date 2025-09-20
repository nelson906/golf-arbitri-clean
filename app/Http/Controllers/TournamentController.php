<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Zone;
use App\Models\TournamentType;
use App\Models\Club;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 🎯 TournamentController Unificato con Codifica Colori Recuperata
 */
class TournamentController extends Controller
{
    /**
     * ✅ Lista tornei unificata
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isAdmin = $this->isAdmin($user);
        $isNationalReferee = $this->isNationalReferee($user);

        $query = Tournament::with(['tournamentType', 'zone', 'club']);

        // 🔐 FILTRO ACCESSO
        if ($isAdmin) {
            if (!$this->isNationalAdmin($user)) {
                $query->where('zone_id', $user->zone_id);
            }
        } else {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);

            if (!$isNationalReferee && $user->zone_id) {
                $query->where('zone_id', $user->zone_id);
            }
        }

        // 🔍 FILTRI
        $this->applyFilters($query, $request);

        $tournaments = $query->orderBy('start_date', 'desc')->paginate(20);

        // 📊 STATISTICHE per admin
        $stats = [];
        if ($isAdmin) {
            $stats = $this->calculateStats($tournaments);
        }

        return view('tournaments.index', [
            'tournaments' => $tournaments,
            'isAdmin' => $isAdmin,
            'stats' => $stats,
            'isNationalAdmin' => auth()->user()->user_type === 'national_admin' // ← AGGIUNGI QUESTA
        ]);
    }

    /**
     * ✅ Calendario unificato con CODIFICA COLORI RECUPERATA
     */
    public function calendar(Request $request): View
    {
        $user = auth()->user();
        // Allow forcing user mode with ?view_as=user parameter
        $forceUserMode = $request->get('view_as') === 'user';
        $isAdmin = $forceUserMode ? false : $this->isAdmin($user);
        $isNationalReferee = $this->isNationalReferee($user);

        // Query base con relazioni necessarie ottimizzate
        $query = Tournament::with([
            'tournamentType:id,name,short_name,calendar_color',
            'club:id,name,zone_id',
            'club.zone:id,name'
        ]);

        // Load additional relations based on user type
        if ($isAdmin) {
            $query->withCount(['assignments', 'availabilities']);
        }

        // 🔐 STESSO FILTRO ACCESSO di index()
        if ($isAdmin) {
            if (!$this->isNationalAdmin($user)) {
                $query->where('zone_id', $user->zone_id);
            }
        } else {
            $query->whereIn('status', ['open', 'closed', 'assigned', 'completed']);
            if (!$isNationalReferee && $user->zone_id) {
                $query->where('zone_id', $user->zone_id);
            }
        }

        // Filtri opzionali per ottimizzare il caricamento
        $currentYear = $request->get('year', now()->year);
        $startDate = Carbon::create($currentYear, 1, 1)->startOfYear();
        $endDate = Carbon::create($currentYear, 12, 31)->endOfYear();

        $query->whereBetween('start_date', [$startDate, $endDate]);

        // Filtro per zona
        if ($request->filled('zone_id')) {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        // Filtro per tipo torneo
        if ($request->filled('type_id')) {
            $query->where('tournament_type_id', $request->type_id);
        }

        $query->orderBy('start_date');

        $tournaments = $query->get();

        // 👤 USER-SPECIFIC DATA
        $userAvailabilities = [];
        $userAssignments = [];

        if ($user->user_type === 'referee') {
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();
        }

        // 📅 FORMAT FOR CALENDAR WITH COLORS
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments, $isAdmin, $user) {
                return $this->formatTournamentForCalendar($tournament, $userAvailabilities, $userAssignments, $isAdmin, $user);
            }),

            // 🎯 CONTEXT DATA
            'userType' => $isAdmin ? 'admin' : 'referee',
            'userRoles' => [$user->user_type],
            'canModify' => true,
            'isAdmin' => $isAdmin,

            // 🔧 FILTER DATA
            'zones' => Zone::orderBy('name')->get(),
            'types' => TournamentType::active()->ordered()->get(),
            'clubs' => Club::active()->ordered()->get(),

            // 👤 USER DATA
            'availabilities' => $userAvailabilities,
            'assignments' => $userAssignments,

            // 📊 METADATA
            'totalTournaments' => $tournaments->count(),
            'lastUpdated' => now()->toISOString(),
        ];

        return view('tournaments.calendar', compact('calendarData'));
    }

    /**
     * ✅ Dettagli torneo
     */
    public function show(Tournament $tournament): View
    {
        $user = auth()->user();
        $isAdmin = $this->isAdmin($user);

        // 🔐 CHECK ACCESS
        $this->checkTournamentAccess($tournament, $user, $isAdmin);

        // 📚 LOAD RELATIONS
        $tournament->load(['tournamentType', 'zone', 'club']);

        if ($isAdmin) {
            $tournament->load(['assignments.user', 'availabilities.user']);
        }

        // 👤 REFEREE-SPECIFIC DATA
        $userAvailability = null;
        $userAssignment = null;

        if ($user->user_type === 'referee') {
            $userAvailability = $tournament->availabilities()->where('user_id', $user->id)->first();
            $userAssignment = $tournament->assignments()->where('user_id', $user->id)->first();
        }

        // 📊 ADMIN-SPECIFIC STATS
        $stats = [];
        $assignedReferees = collect();
        $availableReferees = collect();

        if ($isAdmin) {
            $stats = [
                'total_assignments' => $tournament->assignments()->count(),
                'total_availabilities' => $tournament->availabilities()->count(),
                'required_referees' => $tournament->required_referees ?? 1,
                'max_referees' => $tournament->max_referees ?? 4,
                'days_until_deadline' => $tournament->availability_deadline
                    ? Carbon::parse($tournament->availability_deadline)->diffInDays(now(), false)
                    : null,
                'is_editable' => method_exists($tournament, 'isEditable') ? $tournament->isEditable() : true,
            ];

            $assignedReferees = $tournament->assignedReferees;
            $availableReferees = $tournament->availabilities()->with('user')->get();
        }

        // Get required referees from tournament type
        $required_referees = $tournament->tournamentType?->min_referees ?? 1;

        return view('tournaments.show', compact(
            'tournament',
            'userAvailability',
            'userAssignment',
            'stats',
            'assignedReferees',
            'availableReferees',
            'required_referees',
            'isAdmin'
        ));
    }

    // ===============================================
    // 🛠️ HELPER METHODS
    // ===============================================

    private function isAdmin($user): bool
    {
        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    private function isNationalAdmin($user): bool
    {
        return in_array($user->user_type, ['national_admin', 'super_admin']);
    }

    private function isNationalReferee($user): bool
    {
        return in_array($user->level ?? '', ['nazionale', 'internazionale']);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query;
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->filled('month')) {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });
        }
    }

    /**
     * 🎨 Format tournament for calendar display WITH COLOR LOGIC
     */
    private function formatTournamentForCalendar($tournament, $userAvailabilities, $userAssignments, $isAdmin, $user): array
    {
        $isAvailable = in_array($tournament->id, $userAvailabilities);
        $isAssigned = in_array($tournament->id, $userAssignments);

        return [
            'id' => $tournament->id,
            'title' => $tournament->name,
            'start' => $tournament->start_date->format('Y-m-d'),
            'end' => $tournament->end_date->addDay()->format('Y-m-d'),
            // 🎨 RECUPERATA LOGICA COLORI ORIGINALE
            'color' => $this->getEventColor($tournament, $isAssigned, $isAvailable, $isAdmin),
            'borderColor' => $this->getBorderColor($tournament, $isAssigned, $isAvailable, $isAdmin),
            'extendedProps' => [
                // Basic info
                'club' => $tournament->club->name ?? 'N/A',
                'zone' => $tournament->zone->name ?? 'N/A',
                'zone_id' => $tournament->zone_id,
                'category' => $tournament->tournamentType->name ?? 'N/A',
                'status' => $tournament->status,

                // 🎯 DIFFERENT URL BASED ON USER TYPE
                'tournament_url' => $isAdmin
                    ? route('admin.tournaments.edit', $tournament)
                    : route('tournaments.show', $tournament),

                'deadline' => Carbon::parse($tournament->availability_deadline)?->format('d/m/Y') ?? 'N/A',
                'type_id' => $tournament->tournament_type_id,

                // Referee-specific
                'is_available' => $isAvailable,
                'is_assigned' => $isAssigned,
                'can_apply' => $this->canApply($tournament, $user),
                'personal_status' => $isAssigned ? 'assigned' : ($isAvailable ? 'available' : 'can_apply'),

                // Admin-specific - usa i count già caricati se disponibili
                'availabilities_count' => $isAdmin ? ($tournament->availabilities_count ?? $tournament->availabilities()->count()) : 0,
                'assignments_count' => $isAdmin ? ($tournament->assignments_count ?? $tournament->assignments()->count()) : 0,
                'required_referees' => $tournament->required_referees ?? 1,
                'max_referees' => $tournament->max_referees ?? 4,
                'management_priority' => $isAdmin ? $this->getManagementPriority($tournament) : 'none',

                // 🎯 UI BEHAVIOR FLAGS
                'show_edit_button' => $isAdmin,
                'show_delete_button' => $isAdmin && $tournament->status === 'draft',
                'click_action' => $isAdmin ? 'edit' : 'show',
            ],
        ];
    }

    private function checkTournamentAccess($tournament, $user, $isAdmin): void
    {
        if (!$isAdmin) {
            if ($tournament->status === 'draft') {
                abort(404);
            }

            if (!$this->isNationalReferee($user) && $user->zone_id && $tournament->zone_id !== $user->zone_id) {
                abort(403, 'Non hai accesso a questo torneo.');
            }
        } else {
            if (!$this->isNationalAdmin($user) && $tournament->zone_id !== $user->zone_id) {
                abort(403, 'Non hai accesso a questo torneo.');
            }
        }
    }

    private function calculateStats($tournaments): array
    {
        if (method_exists($tournaments, 'getCollection')) {
            $collection = $tournaments->getCollection();
            $total = $tournaments->count();
        } else {
            $collection = $tournaments;
            $total = $tournaments->count();
        }

        $byStatus = $collection->groupBy('status');

        return [
            'total' => $total,
            'draft' => $byStatus->get('draft', collect())->count(),
            'open' => $byStatus->get('open', collect())->count(),
            'closed' => $byStatus->get('closed', collect())->count(),
            'assigned' => $byStatus->get('assigned', collect())->count(),
            'completed' => $byStatus->get('completed', collect())->count(),
        ];
    }

    // ===============================================
    // 🎨 COLOR LOGIC RECUPERATA DA Admin\CalendarController
    // ===============================================

    /**
     * 🎨 Get event color - RECUPERATA LOGICA ORIGINALE
     */
    private function getEventColor($tournament, $isAssigned = false, $isAvailable = false, $isAdmin = false): string
    {
        if ($isAdmin) {
            // Admin: usa il colore dal database
            return $tournament->tournamentType->calendar_color ?? '#3B82F6';
        } else {
            // Referee: colore basato su personal status
            if ($isAssigned) return '#10B981';  // Green - Assigned
            if ($isAvailable) return '#F59E0B'; // Yellow - Available
            return '#3B82F6';                   // Blue - Can apply
        }
    }

    /**
     * 🎨 Get border color - RECUPERATA LOGICA ORIGINALE
     */
    private function getBorderColor($tournament, $isAssigned = false, $isAvailable = false, $isAdmin = false): string
    {
        if ($isAdmin) {
            // Admin border: basato su STATUS TORNEO (logica originale)
            return match ($tournament->status) {
                'draft' => '#F59E0B',       // Amber - Draft
                'open' => '#10B981',        // Green - Published/Open
                'closed' => '#6B7280',      // Gray - Closed
                'assigned' => '#059669',    // Dark Green - Assigned
                'completed' => '#374151',   // Dark Gray - Completed
                'cancelled' => '#EF4444',  // Red - Cancelled
                default => '#10B981'        // Green default
            };
        } else {
            // Referee border: basato su personal status
            if ($isAssigned) return '#059669';  // Dark green
            if ($isAvailable) return '#D97706'; // Dark yellow
            return '#1E40AF';                   // Dark blue
        }
    }

    /**
     * 🎨 Calculate management priority - RECUPERATA LOGICA ORIGINALE
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
                $daysUntilDeadline = Carbon::parse($tournament->availability_deadline)->diffInDays(now(), false);
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

    private function canApply($tournament, $user): bool
    {
        if ($user->user_type !== 'referee') return false;
        if ($tournament->status !== 'open') return false;
        if ($tournament->start_date <= now()) return false;
        if ($tournament->availability_deadline && $tournament->availability_deadline < now()) return false;

        return true;
    }
}

/*
=================================================================
🎨 CODIFICA COLORI RECUPERATA:
=================================================================

ADMIN VIEW:
- Colore principale: Categoria Torneo
  * Categoria A: #FF6B6B (Rosso)
  * Categoria B: #4ECDC4 (Teal)
  * Categoria C: #45B7D1 (Blu)
  * Categoria D: #96CEB4 (Verde)

- Bordo: Status Torneo
  * Draft: #F59E0B (Amber)
  * Open: #10B981 (Green)
  * Closed: #6B7280 (Gray)
  * Assigned: #059669 (Dark Green)
  * Completed: #374151 (Dark Gray)
  * Cancelled: #EF4444 (Red)

REFEREE VIEW:
- Colore: Personal Status
  * Assigned: #10B981 (Green)
  * Available: #F59E0B (Yellow)
  * Can Apply: #3B82F6 (Blue)

- Bordo: Personal Status
  * Assigned: #059669 (Dark Green)
  * Available: #D97706 (Dark Yellow)
  * Can Apply: #1E40AF (Dark Blue)

MANAGEMENT PRIORITY:
- urgent: deadline passata o arbitri mancanti
- complete: completamente staffato
- in_progress: parzialmente staffato
- open: pronto per disponibilità
*/
