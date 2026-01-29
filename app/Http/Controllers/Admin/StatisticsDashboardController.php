<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Statistics\AssignmentStatsService;
use App\Services\Statistics\AvailabilityStatsService;
use App\Services\Statistics\RefereeStatsService;
use App\Services\Statistics\TournamentStatsService;
use App\Services\Statistics\ZoneStatsService;
use App\Traits\HasZoneVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticsDashboardController extends Controller
{
    use HasZoneVisibility;

    public function __construct(
        protected TournamentStatsService $tournamentStats,
        protected RefereeStatsService $refereeStats,
        protected AssignmentStatsService $assignmentStats,
        protected AvailabilityStatsService $availabilityStats,
        protected ZoneStatsService $zoneStats
    ) {}

    /**
     * Display the statistics dashboard.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $this->isNationalAdmin($user);

        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        $generalStats = $this->getGeneralStats($user);
        $periodStats = $this->getPeriodStats($user, $startDate);
        $zoneStats = $this->zoneStats->getZonesSummary($user);
        $refereeStats = [
            'by_level' => $this->refereeStats->getByLevel($user),
            'active_percentage' => $this->refereeStats->getActivePercentage($user),
        ];
        $tournamentStats = $this->tournamentStats->getGeneralStats($user);
        $chartData = $this->getChartData();
        $performanceMetrics = $this->getPerformanceMetrics();

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
        $isNationalAdmin = $this->isNationalAdmin($user);

        $month = $request->get('month');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sortBy = $request->get('sort', 'availabilities_count');
        $sortDirection = $request->get('direction', 'desc');

        // Query base con filtri
        $query = Availability::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        $this->applyTournamentRelationVisibility($query, $user);
        $this->applyDateFilters($query, $dateFrom, $dateTo, $month);

        $availabilities = $query->paginate(50);

        // Statistiche via Service
        $stats = $this->availabilityStats->getFullStats($month, $sortBy, $sortDirection, $user);

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
        $isNationalAdmin = $this->isNationalAdmin($user);

        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Query assegnazioni con filtri
        $query = Assignment::with(['referee', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

        $this->applyTournamentRelationVisibility($query, $user);
        $this->applyDateFilters($query, $dateFrom, $dateTo);
        $this->applyStatusFilter($query, $status);

        $assignments = $query->paginate(50);

        // Statistiche via Service
        $stats = $this->assignmentStats->getFullStats($user);

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
        $isNationalAdmin = $this->isNationalAdmin($user);

        $status = $request->get('status');
        $category = $request->get('category');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Query tornei con filtri
        $query = Tournament::with(['club', 'zone', 'tournamentType']);
        $this->applyTournamentVisibility($query, $user);

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

        // Statistiche via Service
        $stats = $this->tournamentStats->getFullStats($user);
        $stats['total'] = $query->count();
        $stats['avg_referees'] = $this->assignmentStats->getAverageRefereesPerTournament($user);

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
        $isNationalAdmin = $this->isNationalAdmin($user);

        $level = $request->get('level');
        $zone = $request->get('zone');

        // Query arbitri con filtri
        $query = User::where('user_type', '=', 'referee')->with(['zone']);
        $this->applyUserVisibility($query, $user);

        if ($level) {
            $query->where('level', '=', $level);
        }
        if ($zone) {
            $query->where('zone_id', '=', $zone);
        }

        $referees = $query->paginate(50);

        // Statistiche via Service
        $stats = $this->refereeStats->getFullStats($user);
        $stats['total'] = $query->count();
        $stats['active'] = $query->clone()->where('is_active', '=', true)->count();
        $stats['by_level'] = $this->refereeStats->getByLevel($user);
        $stats['by_zone'] = $this->refereeStats->getByZone($user);

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

        // Solo admin nazionali possono vedere tutte le zone
        if (! $this->isNationalAdmin($user)) {
            abort(403, 'Accesso non autorizzato');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $zoneStats = $this->zoneStats->getAllZonesStats($dateFrom, $dateTo, $user);

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
        $isNationalAdmin = $this->isNationalAdmin($user);

        $period = $request->get('period', 30);

        $metrics = [
            'response_time' => [],
            'assignment_efficiency' => [],
            'availability_trends' => [],
            'system_health' => [],
            'user_engagement' => [],
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

        $filename = "statistiche_{$type}_".Carbon::now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($type, $user) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            match ($type) {
                'tornei' => $this->exportTournamentsCSV($handle, $user),
                'arbitri' => $this->exportRefereesCSV($handle, $user),
                'assegnazioni' => $this->exportAssignmentsCSV($handle, $user),
                default => $this->exportGeneralCSV($handle, $user),
            };

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * API endpoint per statistiche
     */
    public function apiStats($type)
    {
        $user = auth()->user();

        return match ($type) {
            'dashboard' => response()->json($this->getGeneralStats($user)),
            'charts' => response()->json($this->getChartData()),
            'zones' => response()->json($this->zoneStats->getZonesSummary($user)),
            default => response()->json(['error' => 'Tipo non valido'], 400),
        };
    }

    // Private helper methods
    private function getGeneralStats($user): array
    {
        $tournamentStats = $this->tournamentStats->getGeneralStats($user);
        $refereeStats = $this->refereeStats->getGeneralStats($user);
        $assignmentStats = $this->assignmentStats->getGeneralStats($user);

        return [
            'total_tournaments' => $tournamentStats['total'],
            'active_tournaments' => $tournamentStats['active'],
            'completed_tournaments' => $tournamentStats['completed'],
            'total_referees' => $refereeStats['total'],
            'active_referees' => $refereeStats['active'],
            'total_assignments' => $assignmentStats['total'],
            'pending_assignments' => $assignmentStats['pending'],
        ];
    }

    private function getPeriodStats($user, $startDate): array
    {
        $tournamentQuery = Tournament::query()->where('created_at', '>=', $startDate);
        $this->applyTournamentVisibility($tournamentQuery, $user);

        $assignmentQuery = Assignment::query()->where('created_at', '>=', $startDate);
        $this->applyTournamentRelationVisibility($assignmentQuery, $user);

        $availabilityQuery = Availability::query()->where('created_at', '>=', $startDate);
        $this->applyTournamentRelationVisibility($availabilityQuery, $user);

        return [
            'new_tournaments' => $tournamentQuery->count(),
            'new_assignments' => $assignmentQuery->count(),
            'new_availabilities' => $availabilityQuery->count(),
        ];
    }

    private function getChartData(): array
    {
        return [
            'tournaments_by_month' => [],
            'assignments_by_month' => [],
            'availability_trends' => [],
        ];
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'assignment_rate' => 85.5,
            'response_time' => 2.3,
            'user_satisfaction' => 92.1,
            'system_uptime' => 99.8,
        ];
    }

    private function applyDateFilters($query, ?string $dateFrom, ?string $dateTo, ?string $month = null): void
    {
        if ($dateFrom) {
            $query->whereHas('tournament', fn ($q) => $q->where('start_date', '>=', $dateFrom));
        }

        if ($dateTo) {
            $query->whereHas('tournament', fn ($q) => $q->where('start_date', '<=', $dateTo));
        }

        if ($month) {
            $query->whereHas('tournament', fn ($q) => $q->whereMonth('start_date', $month));
        }
    }

    private function applyStatusFilter($query, ?string $status): void
    {
        if ($status === 'confirmed') {
            $query->where('is_confirmed', true);
        } elseif ($status === 'pending') {
            $query->where('is_confirmed', false);
        }
    }

    // Export methods (placeholder implementations)
    private function exportTournamentsCSV($handle, $user): void {}

    private function exportRefereesCSV($handle, $user): void {}

    private function exportAssignmentsCSV($handle, $user): void {}

    private function exportGeneralCSV($handle, $user): void {}
}
