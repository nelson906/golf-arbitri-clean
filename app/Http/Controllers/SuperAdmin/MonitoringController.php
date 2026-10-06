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

}
