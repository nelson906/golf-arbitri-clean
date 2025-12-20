<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemHealthService;
use App\Services\Monitoring\SystemMetricsService;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService,
        protected SystemMetricsService $metricsService
    ) {}

    /**
     * Dashboard principale monitoraggio
     */
    public function dashboard(Request $request)
    {
        $metrics = $this->metricsService->getAllMetrics();
        $healthStatus = $this->healthService->getHealthStatus();
        $realtimeStats = $this->metricsService->getRealtimeStats();
        $alerts = $this->metricsService->getSystemAlerts();
        $performance = $this->metricsService->getPerformanceOverview();

        $period = $request->get('period', '24h');
        $autoRefresh = $request->get('auto_refresh', true);

        return view('admin.monitoring.dashboard', compact(
            'metrics',
            'healthStatus',
            'realtimeStats',
            'alerts',
            'performance',
            'period',
            'autoRefresh'
        ));
    }

    /**
     * Health check completo sistema
     *
     * @deprecated Usa HealthCheckController@index
     */
    public function healthCheck(Request $request)
    {
        $response = $this->healthService->performHealthCheck();
        $overallHealth = $response['status'] === 'healthy';
        $checks = $response['checks'];

        if ($request->wantsJson()) {
            return response()->json($response, $overallHealth ? 200 : 503);
        }

        return view('admin.monitoring.health', compact('response', 'overallHealth', 'checks'));
    }

    /**
     * Metriche real-time
     */
    public function realtimeMetrics(Request $request)
    {
        $metrics = $this->metricsService->getRealtimeMetrics();

        if ($request->wantsJson()) {
            return response()->json($metrics);
        }

        return view('admin.monitoring.metrics', compact('metrics'));
    }

    /**
     * Storico performance
     */
    public function history(Request $request)
    {
        $period = $request->get('period', '24h');
        $metric = $request->get('metric', 'response_time');

        $historicalData = $this->metricsService->getHistoricalData($period, $metric);
        $trends = $this->metricsService->calculateTrends($historicalData);

        return view('admin.monitoring.history', compact(
            'historicalData',
            'trends',
            'period',
            'metric'
        ));
    }

    /**
     * Metriche performance dettagliate
     */
    public function performanceMetrics(Request $request)
    {
        $timeframe = $request->get('timeframe', '1h');

        $metrics = $this->metricsService->getDetailedPerformanceMetrics($timeframe);

        return view('admin.monitoring.performance', compact('metrics', 'timeframe'));
    }

    /**
     * API endpoint per metriche
     */
    public function apiMetrics(Request $request, string $type)
    {
        return match ($type) {
            'realtime' => response()->json($this->metricsService->getRealtimeMetrics()),
            'stats' => response()->json($this->metricsService->getRealtimeStats()),
            'performance' => response()->json($this->metricsService->getPerformanceOverview()),
            'alerts' => response()->json($this->metricsService->getSystemAlerts()),
            'health' => response()->json($this->healthService->getHealthStatus()),
            default => response()->json(['error' => 'Tipo non valido'], 400),
        };
    }
}
