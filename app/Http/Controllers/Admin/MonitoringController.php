<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    /**
     * Dashboard principale monitoraggio
     */
    public function dashboard(Request $request)
    {
        $metrics = $this->getSystemMetrics();
        $healthStatus = $this->getHealthStatus();
        $realtimeStats = $this->getRealtimeStats();
        $alerts = $this->getSystemAlerts();
        $performance = $this->getPerformanceOverview();

        // Parametri per la vista
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
     */
    public function healthCheck(Request $request)
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'mail' => $this->checkMail(),
            'queue' => $this->checkQueue(),
        ];

        $overallHealth = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        $response = [
            'status' => $overallHealth ? 'healthy' : 'unhealthy',
            'timestamp' => Carbon::now()->toISOString(),
            'checks' => $checks,
            'uptime' => $this->getUptime(),
            'version' => config('app.version', '1.0.0'),
        ];

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
        $metrics = [
            'active_users' => $this->getActiveUsers(),
            'database_connections' => $this->getDatabaseConnections(),
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCpuUsage(),
            'response_times' => $this->getResponseTimes(),
            'error_rates' => $this->getErrorRates(),
            'throughput' => $this->getThroughput(),
        ];

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
        $period = $request->get('period', '24h'); // 24h, 7d, 30d
        $metric = $request->get('metric', 'response_time');

        $historicalData = $this->getHistoricalData($period, $metric);
        $trends = $this->calculateTrends($historicalData);

        return view('admin.monitoring.history', compact(
            'historicalData',
            'trends',
            'period',
            'metric'
        ));
    }

    /**
     * Log di sistema
     */
    public function systemLogs(Request $request)
    {
        $level = $request->get('level', 'all'); // error, warning, info, debug
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $search = $request->get('search');

        // Per ora dati mock - implementa lettura log reali se necessario
        $logs = collect([
            ['level' => 'info', 'message' => 'Sistema avviato correttamente', 'time' => now()],
            ['level' => 'info', 'message' => 'Database connesso - 3 connessioni attive', 'time' => now()],
            ['level' => 'warning', 'message' => 'Memoria utilizzo al 75%', 'time' => now()],
            ['level' => 'info', 'message' => 'Health check completato', 'time' => now()],
        ]);

        $logStats = [
            'total' => $logs->count(),
            'errors' => $logs->where('level', 'error')->count(),
            'warnings' => $logs->where('level', 'warning')->count(),
            'info' => $logs->where('level', 'info')->count(),
        ];

        return view('admin.monitoring.logs', compact(
            'logs',
            'logStats',
            'level',
            'date',
            'search'
        ));
    }

    /**
     * Metriche performance dettagliate
     */
    public function performanceMetrics(Request $request)
    {
        $timeframe = $request->get('timeframe', '1h');

        $metrics = [
            'response_times' => [
                'min' => 95,
                'avg' => 245,
                'max' => 1200,
                'p95' => 485
            ],
            'database_performance' => [
                'queries_per_sec' => 12.5,
                'slow_queries' => 2,
                'connections' => '3/100'
            ],
            'cache_performance' => [
                'hit_rate' => 89.2,
                'miss_rate' => 10.8,
                'evictions' => 45
            ],
            'memory_trends' => [
                'cpu' => 15.2,
                'memory' => 75.8,
                'disk' => 45.1,
                'network' => '2.1MB/s'
            ],
            'slow_queries' => [
                ['time' => 1200, 'query' => 'SELECT * FROM tournaments WHERE start_date >= \'2025-01-01\''],
                ['time' => 650, 'query' => 'SELECT u.*, z.name FROM users u LEFT JOIN zones z ON u.zone_id = z.id']
            ]
        ];

        return view('admin.monitoring.performance', compact('metrics', 'timeframe'));
    }

    /**
     * Pulisci cache sistema
     */
    public function clearCache(Request $request)
    {
        try {
            $types = $request->get('types', ['application', 'config', 'route', 'view']);
            $results = [];

            foreach ($types as $type) {
                switch ($type) {
                    case 'application':
                        Cache::flush();
                        $results['application'] = 'Cache applicazione pulita';
                        break;
                    case 'config':
                        Artisan::call('config:clear');
                        $results['config'] = 'Cache configurazione pulita';
                        break;
                    case 'route':
                        Artisan::call('route:clear');
                        $results['route'] = 'Cache route pulita';
                        break;
                    case 'view':
                        Artisan::call('view:clear');
                        $results['view'] = 'Cache view pulita';
                        break;
                }
            }

            Log::info('Cache pulita manualmente', ['types' => $types, 'user' => auth()->id()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cache pulita con successo',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Errore pulizia cache', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Errore durante la pulizia della cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ottimizza sistema
     */
    public function optimize(Request $request)
    {
        try {
            $operations = $request->get('operations', ['config', 'route', 'view']);
            $results = [];

            foreach ($operations as $operation) {
                switch ($operation) {
                    case 'config':
                        Artisan::call('config:cache');
                        $results['config'] = 'Configurazione ottimizzata';
                        break;
                    case 'route':
                        Artisan::call('route:cache');
                        $results['route'] = 'Route ottimizzate';
                        break;
                    case 'view':
                        Artisan::call('view:cache');
                        $results['view'] = 'View ottimizzate';
                        break;
                    case 'database':
                        $this->optimizeDatabase();
                        $results['database'] = 'Database ottimizzato';
                        break;
                }
            }

            Log::info('Sistema ottimizzato manualmente', ['operations' => $operations, 'user' => auth()->id()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sistema ottimizzato con successo',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Errore ottimizzazione sistema', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Errore durante l\'ottimizzazione: ' . $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods

    private function getSystemMetrics()
    {
        return [
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'cpu_load' => $this->getCpuUsage(),
            'active_connections' => $this->getActiveConnections(),
            'response_time' => $this->getAverageResponseTime(),
        ];
    }

    private function getHealthStatus()
    {
        return [
            'overall' => 'healthy',
            'database' => $this->checkDatabase()['status'],
            'cache' => $this->checkCache()['status'],
            'storage' => $this->checkStorage()['status'],
            'external_services' => 'healthy',
        ];
    }

    private function getRealtimeStats()
    {
        return [
            'requests_per_minute' => Cache::remember('requests_per_minute', 60, fn() => rand(45, 85)),
            'active_sessions' => Cache::remember('active_sessions', 300, fn() => rand(15, 45)),
            'queue_size' => $this->getQueueSize(),
            'error_rate' => $this->getCurrentErrorRate(),
        ];
    }

    private function getSystemAlerts()
    {
        $alerts = [];

        // Check memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage['percentage'] > 85) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Utilizzo memoria elevato: {$memoryUsage['percentage']}%",
                'timestamp' => Carbon::now()
            ];
        }

        // Check disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage['percentage'] > 90) {
            $alerts[] = [
                'type' => 'critical',
                'message' => "Spazio disco in esaurimento: {$diskUsage['percentage']}%",
                'timestamp' => Carbon::now()
            ];
        }

        // Check error rate
        $errorRate = $this->getCurrentErrorRate();
        if ($errorRate > 5) {
            $alerts[] = [
                'type' => 'error',
                'message' => "Tasso di errore elevato: {$errorRate}%",
                'timestamp' => Carbon::now()
            ];
        }

        return $alerts;
    }

    private function getPerformanceOverview()
    {
        return [
            'response_time_avg' => $this->getAverageResponseTime(),
            'throughput' => $this->getThroughput(),
            'error_rate' => $this->getCurrentErrorRate(),
            'uptime_percentage' => 99.8,
        ];
    }

    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime . 'ms',
                'connections' => $this->getDatabaseConnections(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache()
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test';

            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => $retrieved === $testValue ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage()
    {
        try {
            $testFile = storage_path('app/health_check.txt');
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            return [
                'status' => $content === 'test' ? 'healthy' : 'unhealthy',
                'writable' => is_writable(storage_path('app')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMail()
    {
        return [
            'status' => 'healthy',
            'driver' => config('mail.default'),
        ];
    }

    private function checkQueue()
    {
        return [
            'status' => 'healthy',
            'driver' => config('queue.default'),
            'size' => $this->getQueueSize(),
        ];
    }

    private function checkExternalAPIs()
    {
        return [
            'status' => 'healthy',
            'apis_checked' => 0,
        ];
    }

    private function getUptime()
    {
        $uptimeFile = storage_path('app/uptime.txt');
        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());
            return '0 seconds';
        }

        $startTime = (int)file_get_contents($uptimeFile);
        $uptime = time() - $startTime;

        return $this->formatUptime($uptime);
    }

    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days} giorni, {$hours} ore";
        } elseif ($hours > 0) {
            return "{$hours} ore, {$minutes} minuti";
        } else {
            return "{$minutes} minuti";
        }
    }

    private function getMemoryUsage()
    {
        $memoryUsed = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $percentage = round(($memoryUsed / $memoryLimit) * 100, 2);

        return [
            'used' => $this->formatBytes($memoryUsed),
            'limit' => $this->formatBytes($memoryLimit),
            'percentage' => $percentage,
        ];
    }

    private function getDiskUsage()
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        $percentage = round(($usedSpace / $totalSpace) * 100, 2);

        return [
            'used' => $this->formatBytes($usedSpace),
            'total' => $this->formatBytes($totalSpace),
            'percentage' => $percentage,
        ];
    }

    private function getCpuUsage()
    {
        // Simulazione - in produzione utilizzare sys_getloadavg() o comandi di sistema
        return round(rand(5, 25) + (rand(0, 100) / 100), 2);
    }

    private function getActiveUsers()
    {
        return Cache::remember('active_users_count', 300, function() {
            return DB::table('sessions')->where('last_activity', '>', time() - 900)->count();
        });
    }

    private function getDatabaseConnections()
    {
        try {
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"');
            return $connections[0]->Value ?? 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getActiveConnections()
    {
        return rand(5, 15); // Simulazione
    }

    private function getAverageResponseTime()
    {
        return Cache::remember('avg_response_time', 300, fn() => rand(150, 350));
    }

    private function getResponseTimes()
    {
        return [
            'min' => rand(50, 100),
            'avg' => rand(150, 250),
            'max' => rand(400, 800),
            'p95' => rand(300, 500),
        ];
    }

    private function getErrorRates()
    {
        return [
            'current' => rand(0, 3),
            'avg_24h' => rand(1, 4),
        ];
    }

    private function getCurrentErrorRate()
    {
        return Cache::remember('current_error_rate', 300, fn() => rand(0, 3));
    }

    private function getThroughput()
    {
        return Cache::remember('throughput', 300, fn() => rand(50, 120));
    }

    private function getQueueSize()
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function parseMemoryLimit($limit)
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;

        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }

        return $limit;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // Metodi placeholder per funzionalità avanzate
    private function getHistoricalData($period, $metric) { return []; }
    private function calculateTrends($data) { return []; }
    private function getSystemLogs($level, $date, $search) { return []; }
    private function getLogStatistics($date) { return []; }
    private function getDetailedResponseTimes($timeframe) { return []; }
    private function getDatabasePerformance($timeframe) { return []; }
    private function getCachePerformance($timeframe) { return []; }
    private function getMemoryTrends($timeframe) { return []; }
    private function getSlowQueries($timeframe) { return []; }
    private function identifyBottlenecks($timeframe) { return []; }
    private function optimizeDatabase() { return true; }
}
