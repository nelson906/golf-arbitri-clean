<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemHealthService;
use App\Services\Monitoring\SystemMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
    public function dashboard(Request $request): View
    {
        $metrics = $this->metricsService->getAllMetrics();
        $healthStatus = $this->healthService->getHealthStatus();
        $realtimeStats = $this->metricsService->getRealtimeStats();
        $alerts = $this->metricsService->getSystemAlerts();
        $performance = $this->metricsService->getPerformanceOverview();

        $period = $request->string('period', '24h')->toString();
        $autoRefresh = $request->boolean('auto_refresh', true);

        return view('super-admin.monitoring.dashboard', compact(
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
    public function healthCheck(Request $request): JsonResponse|View
    {
        $response = $this->healthService->performHealthCheck();
        $overallHealth = $response['status'] === 'healthy';
        $checks = $response['checks'];

        if ($request->wantsJson()) {
            return response()->json($response, $overallHealth ? 200 : 503);
        }

        return view('super-admin.monitoring.health', compact('response', 'overallHealth', 'checks'));
    }

    /**
     * Metriche real-time
     */
    public function realtimeMetrics(Request $request): JsonResponse|View
    {
        $metrics = $this->metricsService->getRealtimeMetrics();

        if ($request->wantsJson()) {
            return response()->json($metrics);
        }

        return view('super-admin.monitoring.metrics', compact('metrics'));
    }

    /**
     * Metriche performance dettagliate
     */
    public function performanceMetrics(Request $request): View
    {
        $timeframe = $request->string('timeframe', '1h')->toString();

        $metrics = $this->metricsService->getDetailedPerformanceMetrics($timeframe);

        return view('super-admin.monitoring.performance', compact('metrics', 'timeframe'));
    }

    /**
     * API endpoint per metriche
     */
    public function apiMetrics(Request $request, string $type): JsonResponse
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
