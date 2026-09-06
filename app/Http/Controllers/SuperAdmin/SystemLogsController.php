<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemLogsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemLogsController extends Controller
{
    public function __construct(
        protected SystemLogsService $logsService
    ) {}

    /**
     * Log di sistema
     */
    public function index(Request $request): View
    {
        $level = $request->string('level', 'all')->toString();
        $date = $request->string('date', Carbon::today()->format('Y-m-d'))->toString();
        $search = $request->string('search')->toString() ?: null;

        $logs = $this->logsService->getLogs($level, $date, $search);
        $logStats = $this->logsService->getLogStats($date);

        return view('super-admin.monitoring.logs', compact(
            'logs',
            'logStats',
            'level',
            'date',
            'search'
        ));
    }

    /**
     * Conta errori recenti
     */
    public function errorCount(Request $request): JsonResponse
    {
        $hours = $request->integer('hours', 24);
        $count = $this->logsService->countRecentErrors($hours);

        return response()->json([
            'error_count' => $count,
            'period_hours' => $hours,
        ]);
    }
}
