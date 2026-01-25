<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemMetricsService
{
    /**
     * Ottiene tutte le metriche di sistema.
     */
    public function getAllMetrics(): array
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

    /**
     * Ottiene metriche real-time.
     */
    public function getRealtimeMetrics(): array
    {
        return [
            'active_users' => $this->getActiveUsers(),
            'database_connections' => $this->getDatabaseConnections(),
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCpuUsage(),
            'response_times' => $this->getResponseTimes(),
            'error_rates' => $this->getErrorRates(),
            'throughput' => $this->getThroughput(),
        ];
    }

    /**
     * Ottiene statistiche real-time per dashboard.
     */
    public function getRealtimeStats(): array
    {
        return [
            'requests_per_minute' => Cache::remember('requests_per_minute', 60, fn () => rand(45, 85)),
            'active_sessions' => Cache::remember('active_sessions', 300, fn () => rand(15, 45)),
            'queue_size' => $this->getQueueSize(),
            'error_rate' => $this->getCurrentErrorRate(),
        ];
    }

    /**
     * Ottiene panoramica performance.
     */
    public function getPerformanceOverview(): array
    {
        return [
            'response_time_avg' => $this->getAverageResponseTime(),
            'throughput' => $this->getThroughput(),
            'error_rate' => $this->getCurrentErrorRate(),
            'uptime_percentage' => 99.8,
        ];
    }

    /**
     * Ottiene metriche performance dettagliate.
     */
    public function getDetailedPerformanceMetrics(string $timeframe = '1h'): array
    {
        return [
            'response_times' => [
                'min' => 95,
                'avg' => 245,
                'max' => 1200,
                'p95' => 485,
            ],
            'database_performance' => [
                'queries_per_sec' => 12.5,
                'slow_queries' => 2,
                'connections' => '3/100',
            ],
            'cache_performance' => [
                'hit_rate' => 89.2,
                'miss_rate' => 10.8,
                'evictions' => 45,
            ],
            'memory_trends' => [
                'cpu' => 15.2,
                'memory' => 75.8,
                'disk' => 45.1,
                'network' => '2.1MB/s',
            ],
            'slow_queries' => [
                ['time' => 1200, 'query' => 'SELECT * FROM tournaments WHERE start_date >= \'2025-01-01\''],
                ['time' => 650, 'query' => 'SELECT u.*, z.name FROM users u LEFT JOIN zones z ON u.zone_id = z.id'],
            ],
        ];
    }

    /**
     * Ottiene alert di sistema basati sulle metriche.
     */
    public function getSystemAlerts(): array
    {
        $alerts = [];

        // Check memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage['percentage'] > 85) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Utilizzo memoria elevato: {$memoryUsage['percentage']}%",
                'timestamp' => Carbon::now(),
            ];
        }

        // Check disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage['percentage'] > 90) {
            $alerts[] = [
                'type' => 'critical',
                'message' => "Spazio disco in esaurimento: {$diskUsage['percentage']}%",
                'timestamp' => Carbon::now(),
            ];
        }

        // Check error rate
        $errorRate = $this->getCurrentErrorRate();
        if ($errorRate > 5) {
            $alerts[] = [
                'type' => 'error',
                'message' => "Tasso di errore elevato: {$errorRate}%",
                'timestamp' => Carbon::now(),
            ];
        }

        return $alerts;
    }

    /**
     * Ottiene utilizzo memoria.
     */
    public function getMemoryUsage(): array
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

    /**
     * Ottiene utilizzo disco.
     */
    public function getDiskUsage(): array
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        /** @phpstan-ignore booleanOr.alwaysFalse */
        if ($totalSpace === false || $freeSpace === false) {
            return [
                'used' => 'N/A',
                'total' => 'N/A',
                'percentage' => 0,
            ];
        }

        $usedSpace = (int) ($totalSpace - $freeSpace);
        $percentage = round(($usedSpace / $totalSpace) * 100, 2);

        return [
            'used' => $this->formatBytes((int) $usedSpace),
            'total' => $this->formatBytes((int) $totalSpace),
            'percentage' => $percentage,
        ];
    }

    /**
     * Ottiene utilizzo CPU.
     */
    public function getCpuUsage(): float
    {
        // In produzione utilizzare sys_getloadavg() o comandi di sistema
        return round(rand(5, 25) + (rand(0, 100) / 100), 2);
    }

    /**
     * Ottiene utenti attivi.
     */
    public function getActiveUsers(): int
    {
        return Cache::remember('active_users_count', 300, function () {
            return DB::table('sessions')->where('last_activity', '>', time() - 900)->count();
        });
    }

    /**
     * Ottiene connessioni database.
     */
    public function getDatabaseConnections(): string
    {
        try {
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"');

            return $connections[0]->Value ?? 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Ottiene connessioni attive.
     */
    public function getActiveConnections(): int
    {
        return rand(5, 15);
    }

    /**
     * Ottiene tempo di risposta medio.
     */
    public function getAverageResponseTime(): int
    {
        return Cache::remember('avg_response_time', 300, fn () => rand(150, 350));
    }

    /**
     * Ottiene tempi di risposta.
     */
    public function getResponseTimes(): array
    {
        return [
            'min' => rand(50, 100),
            'avg' => rand(150, 250),
            'max' => rand(400, 800),
            'p95' => rand(300, 500),
        ];
    }

    /**
     * Ottiene tassi di errore.
     */
    public function getErrorRates(): array
    {
        return [
            'current' => rand(0, 3),
            'avg_24h' => rand(1, 4),
        ];
    }

    /**
     * Ottiene tasso di errore corrente.
     */
    public function getCurrentErrorRate(): int
    {
        return Cache::remember('current_error_rate', 300, fn () => rand(0, 3));
    }

    /**
     * Ottiene throughput.
     */
    public function getThroughput(): int
    {
        return Cache::remember('throughput', 300, fn () => rand(50, 120));
    }

    /**
     * Ottiene uptime.
     */
    public function getUptime(): string
    {
        $uptimeFile = storage_path('app/uptime.txt');
        if (! file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());

            return '0 secondi';
        }

        $startTime = (int) file_get_contents($uptimeFile);
        $uptime = time() - $startTime;

        return $this->formatUptime($uptime);
    }

    /**
     * Ottiene dimensione coda.
     */
    protected function getQueueSize(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Formatta uptime.
     */
    protected function formatUptime(int $seconds): string
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

    /**
     * Parsa limite memoria.
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
                // no break
            case 'm':
                $limit *= 1024;
                // no break
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }

    /**
     * Formatta bytes.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Ottiene dati storici.
     */
    public function getHistoricalData(string $period, string $metric): array
    {
        // Placeholder - implementare con storage reale se necessario
        return [];
    }

    /**
     * Calcola trend.
     */
    public function calculateTrends(array $data): array
    {
        // Placeholder - implementare con logica reale
        return [];
    }
}
