<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Club;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsDashboardController extends Controller
{
    /**
     * Display the statistics dashboard.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Filtri temporali
        $period = $request->get('period', '30'); // giorni
        $startDate = Carbon::now()->subDays($period);

        // Statistiche generali
        $generalStats = $this->getGeneralStats($user, $isNationalAdmin);

        // Statistiche periodo
        $periodStats = $this->getPeriodStats($user, $isNationalAdmin, $startDate);

        // Statistiche per zona
        $zoneStats = $this->getZoneStats($user, $isNationalAdmin);

        // Statistiche arbitri
        $refereeStats = $this->getRefereeStats($user, $isNationalAdmin);

        // Statistiche tornei
        $tournamentStats = $this->getTournamentStats($user, $isNationalAdmin);

        // Grafici dati (ultimi 12 mesi)
        $chartData = $this->getChartData($user, $isNationalAdmin);

        // Performance metriche
        $performanceMetrics = $this->getPerformanceMetrics($user, $isNationalAdmin);

        return view('admin.statistics.dashboard', compact(
            'generalStats',
            'periodStats',
            'zoneStats',
            'refereeStats',
            'tournamentStats',
            'chartData',
            'performanceMetrics',
            'isNationalAdmin',
            'period'
        ));
    }

    /**
     * Statistiche disponibilità
     */
    public function disponibilita(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $month = $request->get('month');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sortBy = $request->get('sort', 'availabilities_count');
        $sortDirection = $request->get('direction', 'desc');

        // Query base per le disponibilità (INVARIATA)
        $query = Availability::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        // Filtri zona per admin locali (INVARIATO)
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Filtri temporali (INVARIATI)
        if ($dateFrom) {
            $query->whereHas('tournament', function ($q) use ($dateFrom) {
                $q->where('start_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('tournament', function ($q) use ($dateTo) {
                $q->where('start_date', '<=', $dateTo);
            });
        }
        if ($month) {
            $query->whereHas('tournament', function ($q) use ($month) {
                $q->whereMonth('start_date', $month);
            });
        }

        $availabilities = $query->paginate(50);

        // ===== CLASSIFICA SOLO ARBITRI ATTIVI =====
        $refereesQuery = User::where('user_type', 'referee')
            ->where('is_active', true)  // ← AGGIUNTO FILTRO ATTIVI
            ->with(['zone'])
            ->withCount(['availabilities', 'assignments']);

        if ($user->user_type === 'admin') {
            $refereesQuery->where('zone_id', $user->zone_id);
        } elseif ($user->user_type === 'national_admin') {
            $refereesQuery->whereIn('level', ['Nazionale', 'Internazionale']);
        }

        $refereesRanking = $refereesQuery
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('name', 'asc')
            ->get();

        // Statistiche riepilogo (aggiorna anche qui per coerenza)
        $stats = [
            'total' => $query->count(),
            'by_zone' => $isNationalAdmin ? $this->getAvailabilityByZone($month) : [],
            'by_level' => $this->getAvailabilityByLevel($user, $isNationalAdmin, $month),
            'conversion_rate' => $this->getAvailabilityConversionRate($user, $isNationalAdmin, $month),
            'totale_disponibilita' => Availability::count(),
            'arbitri_con_disponibilita' => Availability::distinct('user_id')->count(),
            'tornei_con_disponibilita' => Availability::distinct('tournament_id')->count(),
            'disponibilita_per_mese' => Availability::selectRaw('MONTH(created_at) as mese, COUNT(*) as totale')
                ->whereYear('created_at', date('Y'))
                ->groupBy('mese')
                ->pluck('totale', 'mese'),
            'top_arbitri' => User::where('user_type', 'referee')
                ->where('is_active', true)  // ← AGGIUNTO ANCHE QUI
                ->withCount('availabilities')
                ->orderBy('availabilities_count', 'desc')
                ->limit(10)
                ->get(),
            'referees_ranking' => $refereesRanking,
        ];

        return view('admin.statistics.disponibilita', compact(
            'availabilities',
            'stats',
            'isNationalAdmin',
            'month',
            'dateFrom',
            'dateTo',
            'sortBy',
            'sortDirection'
        ));
    }

    /**
     * Statistiche assegnazioni
     */
    public function assegnazioni(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Query base
        $query = Assignment::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Filtri temporali
        if ($dateFrom) {
            $query->whereHas('tournament', function ($q) use ($dateFrom) {
                $q->where('start_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('tournament', function ($q) use ($dateTo) {
                $q->where('start_date', '<=', $dateTo);
            });
        }

        if ($status) {
            if ($status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($status === 'pending') {
                $query->where('is_confirmed', false);
            }
        }

        $assignments = $query->paginate(50);

        // Statistiche riepilogo
        $stats = [
            'total' => $query->count(),
            'confirmed' => $query->clone()->where('is_confirmed', true)->count(),
            'pending' => $query->clone()->where('is_confirmed', false)->count(),
            'by_role' => $this->getAssignmentsByRole($user, $isNationalAdmin),
            'by_zone' => $isNationalAdmin ? $this->getAssignmentsByZone() : [],
            'by_level' => $this->getAssignmentsByLevel($user, $isNationalAdmin),
            'workload' => $this->getWorkloadStats($user, $isNationalAdmin),

            // Modificate per rispettare zona:
            'totale_assegnazioni' => $this->getTotalAssignments($user, $isNationalAdmin),
            'per_zona' => $this->getAssignmentsByZone(),
            'tornei_assegnati' => $this->getTournamentsWithAssignments($user, $isNationalAdmin),
            'media_arbitri_torneo' => $this->getAverageRefereesPerTournament($user, $isNationalAdmin),
            'ultimi_30_giorni' => $this->getRecentAssignments($user, $isNationalAdmin)

        ];

        return view('admin.statistics.assegnazioni', compact(
            'assignments',
            'stats',
            'isNationalAdmin',
            'status',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Statistiche tornei
     */
    public function tornei(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $status = $request->get('status');
        $category = $request->get('category');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Query base
        $query = Tournament::with(['club', 'zone', 'tournamentType']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // Filtri temporali
        if ($dateFrom) {
            $query->where('start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('start_date', '<=', $dateTo);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('tournament_type_id', $category);
        }

        $tournaments = $query->paginate(30);

        // Statistiche riepilogo
        $stats = [
            'total' => $query->count(),
            'by_type' => $this->getTournamentsByType($user, $isNationalAdmin),
            'by_month' => $this->getTournamentsByMonth($user, $isNationalAdmin),
            'avg_referees' => $this->getAverageRefereesPerTournament($user, $isNationalAdmin),
            'totale_tornei' => $this->getTotalTournaments($user, $isNationalAdmin),
            'per_stato' => $this->getTournamentsByStatus($user, $isNationalAdmin),
            'per_zona' => $this->getTournamentsByZone($user, $isNationalAdmin),
            'prossimi_30_giorni' => Tournament::where('start_date', '>=', now())
                ->where('start_date', '<=', now()->addDays(30))
                ->count(),
            'con_notifiche' => Notification::distinct('tournament_id')->count()

        ];

        return view('admin.statistics.tornei', compact(
            'tournaments',
            'stats',
            'isNationalAdmin',
            'status',
            'category',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Statistiche arbitri
     */
    public function arbitri(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $level = $request->get('level');
        $zone = $request->get('zone');

        // Query base
        $query = User::where('user_type', 'referee')->with(['zone']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // Filtri
        if ($level) {
            $query->where('level', $level);
        }

        if ($zone) {
            $query->where('zone_id', $zone);
        }

        $referees = $query->paginate(50);

        // Statistiche riepilogo
        $stats = [
            'total' => $query->count(),
            'active' => $query->clone()->where('is_active', true)->count(),
            'by_level' => $query->clone()->select('level', DB::raw('count(*) as count'))
                ->groupBy('level')->pluck('count', 'level'),
            'by_zone' => $isNationalAdmin ? $this->getRefereesByZone() : [],
            'activity' => $this->getRefereeActivityStats($user, $isNationalAdmin),
            'availability_rate' => $this->getRefereeAvailabilityRate($user, $isNationalAdmin),
            'totale_arbitri' => User::where('user_type', 'referee')
                ->where('level', "<>", 'Archivio')
                ->count(),
            'per_livello' => User::where('user_type', 'referee')
                ->selectRaw('level, COUNT(*) as totale')
                ->orderByRaw("FIELD(level, \"Aspirante\", \"1_livello\", \"Regionale\",  \"Nazionale\", \"Internazionale\")")
                ->groupBy('level')
                ->where('level', "<>", 'Archivio')
                ->pluck('totale', 'level'),
            'per_zona' => User::where('user_type', 'referee')
                ->join('zones', 'users.zone_id', '=', 'zones.id')
                ->selectRaw('zones.name, COUNT(*) as totale')
                ->orderBy('zones.name')
                ->groupBy('zones.name')
                ->where('level', "<>", 'Archivio')
                ->pluck('totale', 'name'),
            'attivi_ultimo_mese' => User::where('user_type', 'referee')
                ->where('last_login_at', '>=', now()->subMonth())
                ->count(),
            'con_assegnazioni' => User::where('user_type', 'referee')
                ->has('assignments')
                ->count()

        ];

        return view('admin.statistics.arbitri', compact(
            'referees',
            'stats',
            'isNationalAdmin',
            'level',
            'zone'
        ));
    }

    /**
     * Statistiche zone
     */
    public function zone(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Solo admin nazionali possono vedere tutte le zone
        if (!$isNationalAdmin) {
            abort(403, 'Accesso non autorizzato');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $zones = Zone::with(['users', 'clubs', 'tournaments'])->get();

        $zoneStats = [];
        foreach ($zones as $zone) {
            $tournamentsQuery = $zone->tournaments();
            if ($dateFrom) {
                $tournamentsQuery->where('start_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $tournamentsQuery->where('start_date', '<=', $dateTo);
            }

            $assignmentsQuery = Assignment::whereHas('tournament', function ($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            });
            if ($dateFrom) {
                $assignmentsQuery->whereHas('tournament', function ($q) use ($dateFrom) {
                    $q->where('start_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $assignmentsQuery->whereHas('tournament', function ($q) use ($dateTo) {
                    $q->where('start_date', '<=', $dateTo);
                });
            }

            $zoneStats[] = [
                'zone' => $zone,
                'referees' => $zone->users()->where('user_type', 'referee')->count(),
                'clubs' => $zone->clubs()->count(),
                'tournaments' => $tournamentsQuery->count(),
                'assignments' => $assignmentsQuery->count(),
                'availability_rate' => $this->getZoneAvailabilityRate($zone->id),
                'activity_score' => $this->getZoneActivityScore($zone->id)
            ];
        }

        return view('admin.statistics.zone', compact(
            'zoneStats',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Metriche performance
     */
    public function performance(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $period = $request->get('period', 30);
        $startDate = Carbon::now()->subDays($period);

        $metrics = [
            'response_time' => $this->getResponseTimeMetrics($user, $isNationalAdmin, $startDate),
            'assignment_efficiency' => $this->getAssignmentEfficiency($user, $isNationalAdmin, $startDate),
            'availability_trends' => $this->getAvailabilityTrends($user, $isNationalAdmin, $startDate),
            'system_health' => $this->getSystemHealthMetrics(),
            'user_engagement' => $this->getUserEngagementMetrics($user, $isNationalAdmin, $startDate)
        ];

        return view('admin.statistics.performance', compact(
            'metrics',
            'isNationalAdmin',
            'period'
        ));
    }

    /**
     * Export statistiche CSV
     */
    public function exportCsv(Request $request)
    {
        $type = $request->get('type', 'general');
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $filename = "statistiche_{$type}_" . Carbon::now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($type, $user, $isNationalAdmin) {
            $handle = fopen('php://output', 'w');

            switch ($type) {
                case 'tornei':
                    $this->exportTournamentsCSV($handle, $user, $isNationalAdmin);
                    break;
                case 'arbitri':
                    $this->exportRefereesCSV($handle, $user, $isNationalAdmin);
                    break;
                case 'assegnazioni':
                    $this->exportAssignmentsCSV($handle, $user, $isNationalAdmin);
                    break;
                default:
                    $this->exportGeneralCSV($handle, $user, $isNationalAdmin);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * API endpoint per statistiche
     */
    public function apiStats(Request $request, $type)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        switch ($type) {
            case 'dashboard':
                return response()->json($this->getGeneralStats($user, $isNationalAdmin));
            case 'charts':
                return response()->json($this->getChartData($user, $isNationalAdmin));
            case 'zones':
                return response()->json($this->getZoneStats($user, $isNationalAdmin));
            default:
                return response()->json(['error' => 'Tipo non valido'], 400);
        }
    }

    // Private helper methods
    private function getGeneralStats($user, $isNationalAdmin)
    {
        $query = $isNationalAdmin ? Tournament::query() : Tournament::where('zone_id', $user->zone_id);

        return [
            'total_tournaments' => $query->count(),
            'active_tournaments' => $query->clone()->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'completed_tournaments' => $query->clone()->count(),
            'total_referees' => $isNationalAdmin ?
                User::where('user_type', 'referee')->count() :
                User::where('user_type', 'referee')->where('zone_id', $user->zone_id)->count(),
            'active_referees' => $isNationalAdmin ?
                User::where('user_type', 'referee')->where('is_active', true)->count() :
                User::where('user_type', 'referee')->where('zone_id', $user->zone_id)->where('is_active', true)->count(),
            'total_assignments' => $isNationalAdmin ?
                Assignment::count() :
                Assignment::whereHas('tournament', function ($q) use ($user) {
                    $q->where('zone_id', $user->zone_id);
                })->count(),
            'pending_assignments' => $isNationalAdmin ?
                Assignment::where('is_confirmed', false)->count() :
                Assignment::where('is_confirmed', false)->whereHas('tournament', function ($q) use ($user) {
                    $q->where('zone_id', $user->zone_id);
                })->count(),
        ];
    }

    private function getPeriodStats($user, $isNationalAdmin, $startDate)
    {
        $query = $isNationalAdmin ?
            Tournament::where('created_at', '>=', $startDate) :
            Tournament::where('zone_id', $user->zone_id)->where('created_at', '>=', $startDate);

        return [
            'new_tournaments' => $query->count(),
            'new_assignments' => $isNationalAdmin ?
                Assignment::where('created_at', '>=', $startDate)->count() :
                Assignment::whereHas('tournament', function ($q) use ($user) {
                    $q->where('zone_id', $user->zone_id);
                })->where('created_at', '>=', $startDate)->count(),
            'new_availabilities' => $isNationalAdmin ?
                Availability::where('created_at', '>=', $startDate)->count() :
                Availability::whereHas('tournament', function ($q) use ($user) {
                    $q->where('zone_id', $user->zone_id);
                })->where('created_at', '>=', $startDate)->count(),
        ];
    }

    private function getZoneStats($user, $isNationalAdmin)
    {
        if (!$isNationalAdmin) {
            return [];
        }

        return Zone::with(['tournaments', 'users'])
            ->get()
            ->map(function ($zone) {
                return [
                    'name' => $zone->name,
                    'tournaments' => $zone->tournaments->count(),
                    'referees' => $zone->users()->where('user_type', 'referee')->count(),
                    'active_referees' => $zone->users()->where('user_type', 'referee')->where('is_active', true)->count(),
                ];
            });
    }

    private function getRefereeStats($user, $isNationalAdmin)
    {
        $query = $isNationalAdmin ?
            User::where('user_type', 'referee') :
            User::where('user_type', 'referee')->where('zone_id', $user->zone_id);

        return [
            'by_level' => $query->clone()->select('level', DB::raw('count(*) as count'))
                ->groupBy('level')->pluck('count', 'level'),
            'active_percentage' => round(($query->clone()->where('is_active', true)->count() / max($query->count(), 1)) * 100, 1),
        ];
    }

    private function getTournamentStats($user, $isNationalAdmin)
    {
        $query = $isNationalAdmin ?
            Tournament::query() :
            Tournament::where('zone_id', $user->zone_id);

        return [
            'by_status' => $query->clone()->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->pluck('count', 'status'),
            'by_month' => $query->clone()->select(DB::raw('MONTH(start_date) as month'), DB::raw('count(*) as count'))
                ->groupBy('month')->pluck('count', 'month'),
        ];
    }

    private function getChartData($user, $isNationalAdmin)
    {
        // Implementa la logica per i dati dei grafici
        return [
            'tournaments_by_month' => [],
            'assignments_by_month' => [],
            'availability_trends' => []
        ];
    }

    private function getPerformanceMetrics($user, $isNationalAdmin)
    {
        return [
            'assignment_rate' => 85.5,
            'response_time' => 2.3,
            'user_satisfaction' => 92.1,
            'system_uptime' => 99.8
        ];
    }

    // Additional helper methods for specific statistics...
    private function getAvailabilityByZone($month)
    {
        return [];
    }
    private function getAvailabilityByLevel($user, $isNationalAdmin, $month)
    {
        $query = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount('availabilities');

        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();
    }
    private function getAvailabilityConversionRate($user, $isNationalAdmin, $month)
    {
        return 0;
    }
    private function getAssignmentsByZone(): mixed
    {

        $query = Assignment::join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->join('zones', 'tournaments.zone_id', '=', 'zones.id');

        return $query->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }
    private function getAssignmentsByLevel($user, $isNationalAdmin)
    {
        return [];
    }
    private function getTotalAssignments($user, $isNationalAdmin)
    {
        $query = Assignment::query();

        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->count();
    }

    private function getTournamentsWithAssignments($user, $isNationalAdmin)
    {
        $query = Tournament::has('assignments');

        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->count();
    }

    private function getAverageRefereesPerTournament($user, $isNationalAdmin)
    {
        $totalAssignments = $this->getTotalAssignments($user, $isNationalAdmin);
        $tournamentsWithAssignments = $this->getTournamentsWithAssignments($user, $isNationalAdmin);

        return $tournamentsWithAssignments > 0 ?
            round($totalAssignments / $tournamentsWithAssignments, 2) : 0;
    }

    private function getRecentAssignments($user, $isNationalAdmin)
    {
        $query = Assignment::where('assigned_at', '>=', now()->subDays(30));

        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->count();
    }

    private function getAssignmentsByRole($user, $isNationalAdmin)
    {
        $query = Assignment::selectRaw('role, COUNT(*) as totale')
            ->orderByRaw("FIELD(role, \"Direttore di Torneo\", \"Arbitro\", \"Osservatore\")")
            ->groupBy('role');

        // Applica filtro zona per admin locali
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->pluck('totale', 'role');
    }
    private function getWorkloadStats($user, $isNationalAdmin)
    {
        $refereesQuery = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount('assignments');

        if (!$isNationalAdmin) {
            $refereesQuery->where('zone_id', $user->zone_id);
        }

        $referees = $refereesQuery->get();

        return [
            'avg_assignments' => $referees->avg('assignments_count'),
            'max_assignments' => $referees->max('assignments_count'),
            'min_assignments' => $referees->min('assignments_count'),
            'overloaded_referees' => $referees->where('assignments_count', '>', 10)->count(), // soglia arbitraria
        ];
    }
    private function getTournamentsByZone($user, $isNationalAdmin)
    {
        $query = Tournament::join('zones', 'tournaments.zone_id', '=', 'zones.id');

        // Applica filtro zona per admin locali
        // if (!$isNationalAdmin) {
        //     $query->where('tournaments.zone_id', $user->zone_id);
        // }

        return $query->selectRaw('zones.name, COUNT(*) as totale')
            ->orderBy('zones.name')
            ->groupBy('zones.name')
            ->pluck('totale', 'name');
    }
    private function getTournamentsByType($user, $isNationalAdmin)
    {
        $query = Tournament::join('tournament_types', 'tournaments.tournament_type_id', '=', 'tournament_types.id');

        if (!$isNationalAdmin) {
            $query->where('tournaments.zone_id', $user->zone_id);
        }

        return $query->selectRaw('tournament_types.name, COUNT(*) as totale')
            ->orderBy('tournament_types.name')
            ->groupBy('tournament_types.name')
            ->pluck('totale', 'name');
    }
    private function getTournamentsByStatus($user, $isNationalAdmin)
    {
        $query = Tournament::query();

        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->selectRaw('status, COUNT(*) as totale')
            ->groupBy('status')
            ->pluck('totale', 'status');
    }
    private function getTournamentsByMonth($user, $isNationalAdmin)
    {
        $query = Tournament::query();

        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        $monthlyData = $query->selectRaw('MONTH(start_date) as month, COUNT(*) as totale')
            ->whereYear('start_date', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('totale', 'month');

        // Trasforma in nomi mesi
        $monthNames = [
            1 => 'Gennaio',
            2 => 'Febbraio',
            3 => 'Marzo',
            4 => 'Aprile',
            5 => 'Maggio',
            6 => 'Giugno',
            7 => 'Luglio',
            8 => 'Agosto',
            9 => 'Settembre',
            10 => 'Ottobre',
            11 => 'Novembre',
            12 => 'Dicembre'
        ];

        $result = [];
        foreach ($monthlyData as $monthNum => $count) {
            $result[$monthNames[$monthNum]] = $count;
        }

        return $result;
    }
    private function getTotalTournaments($user, $isNationalAdmin)
    {
        $query = Tournament::query();

        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->count();
    }
    private function getRefereesByZone()
    {
        return [];
    }
    private function getRefereeActivityStats($user, $isNationalAdmin)
    {
        return [];
    }
    private function getRefereeAvailabilityRate($user, $isNationalAdmin)
    {
        return 0;
    }
    private function getZoneAvailabilityRate($zoneId)
    {
        return 0;
    }
    private function getZoneActivityScore($zoneId)
    {
        return 0;
    }
    private function getResponseTimeMetrics($user, $isNationalAdmin, $startDate)
    {
        return [];
    }
    private function getAssignmentEfficiency($user, $isNationalAdmin, $startDate)
    {
        return [];
    }
    private function getAvailabilityTrends($user, $isNationalAdmin, $startDate)
    {
        return [];
    }
    private function getSystemHealthMetrics()
    {
        return [];
    }
    private function getUserEngagementMetrics($user, $isNationalAdmin, $startDate)
    {
        return [];
    }
    private function exportTournamentsCSV($handle, $user, $isNationalAdmin) {}
    private function exportRefereesCSV($handle, $user, $isNationalAdmin) {}
    private function exportAssignmentsCSV($handle, $user, $isNationalAdmin) {}
    private function exportGeneralCSV($handle, $user, $isNationalAdmin) {}
}
