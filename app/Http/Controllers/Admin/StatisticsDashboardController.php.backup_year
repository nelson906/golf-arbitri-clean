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
        $year = $request->get('year', Carbon::now()->year);

        // Statistiche generali
        $generalStats = $this->getGeneralStats($user, $isNationalAdmin);

        // Statistiche periodo
        $periodStats = $this->getPeriodStats($user, $isNationalAdmin, $startDate);

        // Statistiche per zona
        $zoneStats = $this->getZoneStats($user, $isNationalAdmin);

        // Statistiche arbitri
        $refereeStats = $this->getRefereeStats($user, $isNationalAdmin, $year);

        // Statistiche tornei
        $tournamentStats = $this->getTournamentStats($user, $isNationalAdmin, $year);

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
            'period',
            'year'
        ));
    }

    /**
     * Statistiche disponibilità
     */
    public function disponibilita(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $year = $request->get('year', Carbon::now()->year);
        $month = $request->get('month');

        // Query base
        $query = Availability::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Filtri temporali
        $query->whereHas('tournament', function ($q) use ($year, $month) {
            $q->whereYear('start_date', $year);
            if ($month) {
                $q->whereMonth('start_date', $month);
            }
        });

        $availabilities = $query->paginate(50);

        // Statistiche riepilogo
        $stats = [
            'total' => $query->count(),
            // 'by_status' => $query->clone()->select('is_available', DB::raw('count(*) as count'))
            //     ->groupBy('is_available')->pluck('count', 'is_available'),
            'by_zone' => $isNationalAdmin ? $this->getAvailabilityByZone($year, $month) : [],
            'by_level' => $this->getAvailabilityByLevel($user, $isNationalAdmin, $year, $month),
            'conversion_rate' => $this->getAvailabilityConversionRate($user, $isNationalAdmin, $year, $month),
            'totale_disponibilita' => Availability::count(),
            'arbitri_con_disponibilita' => Availability::distinct('user_id')->count(),
            'tornei_con_disponibilita' => Availability::distinct('tournament_id')->count(),
            'disponibilita_per_mese' => Availability::selectRaw('MONTH(created_at) as mese, COUNT(*) as totale')
                ->whereYear('created_at', date('Y'))
                ->groupBy('mese')
                ->pluck('totale', 'mese'),
            'top_arbitri' => User::withCount('availabilities')
                ->orderBy('availabilities_count', 'desc')
                ->limit(10)
                ->get()

        ];

        return view('admin.statistics.disponibilita', compact(
            'availabilities',
            'stats',
            'isNationalAdmin',
            'year',
            'month'
        ));
    }

    /**
     * Statistiche assegnazioni
     */
    public function assegnazioni(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $year = $request->get('year', Carbon::now()->year);
        $status = $request->get('status');

        // Query base
        $query = Assignment::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Filtri
        $query->whereHas('tournament', function ($q) use ($year) {
            $q->whereYear('start_date', $year);
        });

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
            'by_role' => $query->clone()->select('role', DB::raw('count(*) as count'))
                ->orderByRaw("FIELD(role, \"Direttore di Torneo\", \"Arbitro\", \"Osservatore\")")
                ->groupBy('role')->pluck('count', 'role'),
            'by_zone' => $isNationalAdmin ? $this->getAssignmentsByZone($year) : [],
            'by_level' => $this->getAssignmentsByLevel($user, $isNationalAdmin, $year),
            'workload' => $this->getWorkloadStats($user, $isNationalAdmin, $year),
            'totale_assegnazioni' => Assignment::count(),
            'per_zona' => Assignment::join('zones', "assignments_{$year}.assigned_by_id", '=', 'zones.id')
                ->selectRaw('zones.name, COUNT(*) as totale')
                ->orderBy('zones.name')
                ->groupBy('zones.name')
                ->pluck('totale', 'name'),
            'per_ruolo' => Assignment::selectRaw('role, COUNT(*) as totale')
                ->orderByRaw("FIELD(role, \"Direttore di Torneo\", \"Arbitro\", \"Osservatore\")")
                ->groupBy('role')
                ->pluck('totale', 'role'),
            'tornei_assegnati' => Tournament::has('assignments')->count(),
            'media_arbitri_torneo' => round(
                Assignment::count() /
                    Tournament::has('assignments')->count(),
                2
            ),
            'ultimi_30_giorni' => Assignment::where('assigned_at', '>=', now()->subDays(30))->count()

        ];

        return view('admin.statistics.assegnazioni', compact(
            'assignments',
            'stats',
            'isNationalAdmin',
            'year',
            'status'
        ));
    }

    /**
     * Statistiche tornei
     */
    public function tornei(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $year = $request->get('year', Carbon::now()->year);
        $status = $request->get('status');
        $category = $request->get('category');

        // Query base
        $query = Tournament::with(['club', 'zone', 'tournamentType']);

        // Filtri zona per admin locali
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // Filtri
        $query->whereYear('start_date', $year);

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
            'by_status' => $query->clone()->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->pluck('count', 'status'),
            'by_category' => $query->clone()->select('tournament_type_id', DB::raw('count(*) as count'))
                ->orderBy('tournament_type_id')
                ->groupBy('tournament_type_id')->with('tournamentType')->get()
                ->pluck('count', 'tournamentType.name'),
            'by_zone' => $isNationalAdmin ? $this->getTournamentsByZone($year) : [],
            'by_month' => $this->getTournamentsByMonth($user, $isNationalAdmin, $year),
            'avg_referees' => $this->getAverageRefereesPerTournament($user, $isNationalAdmin, $year),
            'totale_tornei' => Tournament::count(),
            'per_stato' => Tournament::selectRaw('status, COUNT(*) as totale')
                ->groupBy('status')
                ->pluck('totale', 'status'),
            'per_zona' => Tournament::join('clubs', "tournaments_{$year}.club_id", '=', 'clubs.id')
                ->join('zones', 'clubs.zone_id', '=', 'zones.id')
                ->selectRaw('zones.name, COUNT(*) as totale')
                ->orderBy('zones.name')
                ->groupBy('zones.name')
                ->pluck('totale', 'name'),
            'prossimi_30_giorni' => Tournament::where('start_date', '>=', now())
                ->where('start_date', '<=', now()->addDays(30))
                ->count(),
            'con_notifiche' => Notification::distinct('tournament_id')->count()

        ];

        return view('admin.statistics.tornei', compact(
            'tournaments',
            'stats',
            'isNationalAdmin',
            'year',
            'status',
            'category'
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
        $year = $request->get('year', Carbon::now()->year);

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
            'activity' => $this->getRefereeActivityStats($user, $isNationalAdmin, $year),
            'availability_rate' => $this->getRefereeAvailabilityRate($user, $isNationalAdmin, $year),
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
            'zone',
            'year'
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

        $year = $request->get('year', Carbon::now()->year);

        $zones = Zone::with(['users', 'clubs', 'tournaments'])->get();

        $zoneStats = [];
        foreach ($zones as $zone) {
            $zoneStats[] = [
                'zone' => $zone,
                'referees' => $zone->users()->where('user_type', 'referee')->count(),
                'clubs' => $zone->clubs()->count(),
                'tournaments' => $zone->tournaments()->whereYear('start_date', $year)->count(),
                'assignments' => Assignment::whereHas('tournament', function ($q) use ($zone, $year) {
                    $q->where('zone_id', $zone->id)->whereYear('start_date', $year);
                })->count(),
                'availability_rate' => $this->getZoneAvailabilityRate($zone->id, $year),
                'activity_score' => $this->getZoneActivityScore($zone->id, $year)
            ];
        }

        return view('admin.statistics.zone', compact(
            'zoneStats',
            'year'
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
            'completed_tournaments' => $query->clone()->where('status', 'completed')->count(),
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

    private function getRefereeStats($user, $isNationalAdmin, $year)
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

    private function getTournamentStats($user, $isNationalAdmin, $year)
    {
        $query = $isNationalAdmin ?
            Tournament::whereYear('start_date', $year) :
            Tournament::where('zone_id', $user->zone_id)->whereYear('start_date', $year);

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
    private function getAvailabilityByZone($year, $month)
    {
        return [];
    }
    private function getAvailabilityByLevel($user, $isNationalAdmin, $year, $month)
    {
        return [];
    }
    private function getAvailabilityConversionRate($user, $isNationalAdmin, $year, $month)
    {
        return 0;
    }
    private function getAssignmentsByZone($year)
    {
        return [];
    }
    private function getAssignmentsByLevel($user, $isNationalAdmin, $year)
    {
        return [];
    }
    private function getWorkloadStats($user, $isNationalAdmin, $year)
    {
        return [];
    }
    private function getTournamentsByZone($year)
    {
        return [];
    }
    private function getTournamentsByMonth($user, $isNationalAdmin, $year)
    {
        return [];
    }
    private function getAverageRefereesPerTournament($user, $isNationalAdmin, $year)
    {
        return 0;
    }
    private function getRefereesByZone()
    {
        return [];
    }
    private function getRefereeActivityStats($user, $isNationalAdmin, $year)
    {
        return [];
    }
    private function getRefereeAvailabilityRate($user, $isNationalAdmin, $year)
    {
        return 0;
    }
    private function getZoneAvailabilityRate($zoneId, $year)
    {
        return 0;
    }
    private function getZoneActivityScore($zoneId, $year)
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
