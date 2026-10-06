<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HealthCheckController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Health check completo sistema
     */
    public function index(Request $request): JsonResponse|View
    {
        $response = $this->healthService->performHealthCheck();
        $overallHealth = $response['status'] === 'healthy';
        $checks = $response['checks'];

        if ($request->wantsJson()) {
            return response()->json($response, $overallHealth ? 200 : 503);
        }

        return view('super-admin.monitoring.health', compact('response', 'overallHealth', 'checks'));
    }

}
