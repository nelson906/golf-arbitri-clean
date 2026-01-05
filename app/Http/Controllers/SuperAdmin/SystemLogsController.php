<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\SystemLogsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SystemLogsController extends Controller
{
    public function __construct(
        protected SystemLogsService $logsService
    ) {}

    /**
     * Log di sistema
     */
    public function index(Request $request)
    {
        $level = $request->get('level', 'all');
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $search = $request->get('search');

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
     * Ottieni log per livello specifico
     */
    public function byLevel(Request $request, string $level)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $limit = $request->get('limit', 50);

        $logs = $this->logsService->getLogsByLevel($level, $date, $limit);

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('super-admin.monitoring.logs-level', compact('logs', 'level', 'date'));
    }

    /**
     * Conta errori recenti
     */
    public function errorCount(Request $request)
    {
        $hours = $request->get('hours', 24);
        $count = $this->logsService->countRecentErrors($hours);

        return response()->json([
            'error_count' => $count,
            'period_hours' => $hours,
        ]);
    }

    /**
     * Statistiche log
     */
    public function stats(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $stats = $this->logsService->getLogStats($date);

        if ($request->wantsJson()) {
            return response()->json($stats);
        }

        return view('super-admin.monitoring.logs-stats', compact('stats', 'date'));
    }
}
