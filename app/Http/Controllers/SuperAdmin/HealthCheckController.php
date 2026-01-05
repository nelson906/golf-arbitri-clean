<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemHealthService;
use Illuminate\Http\Request;

class HealthCheckController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Health check completo sistema
     */
    public function index(Request $request)
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
     * Check singolo componente
     */
    public function check(Request $request, string $component)
    {
        $result = match ($component) {
            'database' => $this->healthService->checkDatabase(),
            'cache' => $this->healthService->checkCache(),
            'storage' => $this->healthService->checkStorage(),
            'mail' => $this->healthService->checkMail(),
            'queue' => $this->healthService->checkQueue(),
            default => ['status' => 'unknown', 'error' => 'Componente non riconosciuto'],
        };

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('check_result', $result);
    }

    /**
     * Ottieni stato di salute generale
     */
    public function status(Request $request)
    {
        $status = $this->healthService->getHealthStatus();

        if ($request->wantsJson()) {
            return response()->json($status);
        }

        return view('super-admin.monitoring.status', compact('status'));
    }

    /**
     * Ottieni uptime sistema
     */
    public function uptime(Request $request)
    {
        $uptime = $this->healthService->getUptime();

        if ($request->wantsJson()) {
            return response()->json(['uptime' => $uptime]);
        }

        return back()->with('uptime', $uptime);
    }
}
