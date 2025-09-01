<?php

/**
 * TASK 8: Sistema Monitoring e Alerting Automatico
 *
 * OBIETTIVO: Monitoring completo sistema con alerting intelligente
 * TEMPO STIMATO: 3-4 ore
 * COMPLESSITÀ: Media-Alta
 *
 * FEATURES:
 * - Health checks automatici
 * - Performance monitoring
 * - Business metrics tracking
 * - Alert management
 * - Dashboard real-time
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Notification;
use Carbon\Carbon;

class MonitoringService
{
    private $alerts = [];
    private $metrics = [];
    private $thresholds;

    public function __construct()
    {
        $this->thresholds = config('monitoring.thresholds', [
            'response_time_ms' => 1000,
            'error_rate_percent' => 5,
            'queue_size' => 100,
            'disk_usage_percent' => 80,
            'memory_usage_percent' => 85,
            'failed_jobs_count' => 10,
            'email_failure_rate' => 10,
        ]);
    }

    /**
     * Esegue check completo sistema
     */
    public function performHealthCheck(): array
    {
        $startTime = microtime(true);

        $results = [
            'timestamp' => now()->toISOString(),
            'overall_status' => 'healthy',
            'checks' => [],
            'metrics' => [],
            'alerts' => []
        ];

        // Esegui tutti i controlli
        $this->checkDatabase($results);
        $this->checkCache($results);
        $this->checkQueue($results);
        $this->checkFileSystem($results);
        $this->checkEmailSystem($results);
        $this->checkBusinessMetrics($results);
        $this->checkPerformance($results);
        $this->checkSecurity($results);

        // Calcola metriche generali
        $executionTime = (microtime(true) - $startTime) * 1000;
        $this->metrics['health_check_duration_ms'] = round($executionTime, 2);

        $results['metrics'] = $this->metrics;
        $results['alerts'] = $this->alerts;
        $results['execution_time_ms'] = $executionTime;

        // Determina stato generale
        $criticalAlerts = array_filter($this->alerts, fn($a) => $a['level'] === 'critical');
        $highAlerts = array_filter($this->alerts, fn($a) => $a['level'] === 'high');

        if (!empty($criticalAlerts)) {
            $results['overall_status'] = 'critical';
        } elseif (!empty($highAlerts)) {
            $results['overall_status'] = 'warning';
        }

        // Salva risultati
        $this->saveHealthCheckResults($results);

        // Invia alert se necessario
        if ($results['overall_status'] !== 'healthy') {
            $this->sendAlerts($results);
        }

        return $results;
    }

    /**
     * Check 1: Database Health
     */
    private function checkDatabase(&$results)
    {
        $startTime = microtime(true);

        try {
            // Test connessione
            $pdo = DB::connection()->getPdo();
            $this->addCheck($results, 'database_connection', 'ok', 'Database connection successful');

            // Test query performance
            $queryStart = microtime(true);
            $userCount = User::count();
            $queryTime = (microtime(true) - $queryStart) * 1000;

            $this->metrics['database_query_time_ms'] = round($queryTime, 2);
            $this->metrics['total_users'] = $userCount;

            if ($queryTime > 500) {
                $this->addAlert('Database query slow', 'high', [
                    'query_time_ms' => $queryTime,
                    'threshold_ms' => 500
                ]);
            }

            // Check connessioni attive
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0];
            $maxConnections = DB::select('SHOW VARIABLES LIKE "max_connections"')[0];

            $connectionUsage = ($connections->Value / $maxConnections->Value) * 100;
            $this->metrics['database_connection_usage_percent'] = round($connectionUsage, 2);

            if ($connectionUsage > 70) {
                $this->addAlert('High database connection usage', 'medium', [
                    'usage_percent' => $connectionUsage,
                    'active_connections' => $connections->Value,
                    'max_connections' => $maxConnections->Value
                ]);
            }

            // Check dimensione database
            $dbSize = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ")[0];

            $this->metrics['database_size_mb'] = $dbSize->size_mb;

        } catch (\Exception $e) {
            $this->addCheck($results, 'database_connection', 'error', 'Database connection failed: ' . $e->getMessage());
            $this->addAlert('Database connection failed', 'critical', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['database_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 2: Cache System
     */
    private function checkCache(&$results)
    {
        $startTime = microtime(true);

        try {
            // Test cache write/read
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);

            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);

            if ($retrieved === $testValue) {
                $this->addCheck($results, 'cache_operations', 'ok', 'Cache read/write successful');
                Cache::forget($testKey);
            } else {
                $this->addCheck($results, 'cache_operations', 'error', 'Cache read/write failed');
                $this->addAlert('Cache system malfunction', 'high', [
                    'expected' => $testValue,
                    'retrieved' => $retrieved
                ]);
            }

            // Redis specific checks se disponibile
            if (config('cache.default') === 'redis') {
                $redis = Cache::getStore()->getRedis();
                $info = $redis->info();

                $this->metrics['redis_memory_usage_mb'] = round($info['used_memory'] / 1024 / 1024, 2);
                $this->metrics['redis_connected_clients'] = $info['connected_clients'];
                $this->metrics['redis_keyspace_hits'] = $info['keyspace_hits'] ?? 0;
                $this->metrics['redis_keyspace_misses'] = $info['keyspace_misses'] ?? 0;

                // Cache hit rate
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;

                if ($total > 0) {
                    $hitRate = ($hits / $total) * 100;
                    $this->metrics['cache_hit_rate_percent'] = round($hitRate, 2);

                    if ($hitRate < 70) {
                        $this->addAlert('Low cache hit rate', 'medium', [
                            'hit_rate_percent' => $hitRate,
                            'hits' => $hits,
                            'misses' => $misses
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            $this->addCheck($results, 'cache_operations', 'error', 'Cache system error: ' . $e->getMessage());
            $this->addAlert('Cache system error', 'high', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['cache_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 3: Queue System
     */
    private function checkQueue(&$results)
    {
        $startTime = microtime(true);

        try {
            // Check failed jobs
            $failedJobs = DB::table('failed_jobs')->count();
            $this->metrics['failed_jobs_count'] = $failedJobs;

            if ($failedJobs > $this->thresholds['failed_jobs_count']) {
                $this->addAlert('High number of failed jobs', 'medium', [
                    'failed_jobs' => $failedJobs,
                    'threshold' => $this->thresholds['failed_jobs_count']
                ]);
            }

            // Check queue size (Redis)
            if (config('queue.default') === 'redis') {
                $redis = app('redis')->connection('default');
                $queueSize = $redis->llen('queues:default');
                $this->metrics['queue_size'] = $queueSize;

                if ($queueSize > $this->thresholds['queue_size']) {
                    $this->addAlert('Queue size too large', 'medium', [
                        'queue_size' => $queueSize,
                        'threshold' => $this->thresholds['queue_size']
                    ]);
                }
            }

            // Test job dispatch
            try {
                // Dispatch test job
                \Illuminate\Support\Facades\Queue::push(function() {
                    // Test job che non fa nulla
                });
                $this->addCheck($results, 'queue_dispatch', 'ok', 'Queue job dispatch successful');
            } catch (\Exception $e) {
                $this->addCheck($results, 'queue_dispatch', 'error', 'Queue dispatch failed');
                $this->addAlert('Queue dispatch failed', 'high', [
                    'error' => $e->getMessage()
                ]);
            }

        } catch (\Exception $e) {
            $this->addCheck($results, 'queue_system', 'error', 'Queue system error: ' . $e->getMessage());
            $this->addAlert('Queue system error', 'high', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['queue_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 4: File System
     */
    private function checkFileSystem(&$results)
    {
        $startTime = microtime(true);

        try {
            // Check disk space
            $diskFree = disk_free_space(storage_path());
            $diskTotal = disk_total_space(storage_path());
            $diskUsage = (($diskTotal - $diskFree) / $diskTotal) * 100;

            $this->metrics['disk_usage_percent'] = round($diskUsage, 2);
            $this->metrics['disk_free_gb'] = round($diskFree / 1024 / 1024 / 1024, 2);

            if ($diskUsage > $this->thresholds['disk_usage_percent']) {
                $this->addAlert('High disk usage', 'high', [
                    'usage_percent' => $diskUsage,
                    'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
                    'threshold_percent' => $this->thresholds['disk_usage_percent']
                ]);
            }

            // Check storage writable
            $testFile = storage_path('app/health_check_test.txt');
            if (file_put_contents($testFile, 'test') !== false) {
                $this->addCheck($results, 'storage_writable', 'ok', 'Storage directory writable');
                unlink($testFile);
            } else {
                $this->addCheck($results, 'storage_writable', 'error', 'Storage directory not writable');
                $this->addAlert('Storage not writable', 'critical', [
                    'path' => storage_path('app')
                ]);
            }

            // Check log files size
            $logPath = storage_path('logs');
            $logSize = 0;
            if (is_dir($logPath)) {
                $files = glob($logPath . '/*.log');
                foreach ($files as $file) {
                    $logSize += filesize($file);
                }
            }

            $logSizeMB = round($logSize / 1024 / 1024, 2);
            $this->metrics['log_files_size_mb'] = $logSizeMB;

            if ($logSizeMB > 500) {
                $this->addAlert('Log files too large', 'low', [
                    'size_mb' => $logSizeMB,
                    'suggestion' => 'Run log rotation'
                ]);
            }

        } catch (\Exception $e) {
            $this->addCheck($results, 'file_system', 'error', 'File system error: ' . $e->getMessage());
            $this->addAlert('File system error', 'high', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['filesystem_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 5: Email System
     */
    private function checkEmailSystem(&$results)
    {
        $startTime = microtime(true);

        try {
            // Check email configuration
            $mailConfig = config('mail');
            if (empty($mailConfig['from']['address'])) {
                $this->addCheck($results, 'email_config', 'error', 'Email FROM address not configured');
                $this->addAlert('Email configuration incomplete', 'medium', [
                    'missing' => 'from.address'
                ]);
            } else {
                $this->addCheck($results, 'email_config', 'ok', 'Email configuration valid');
            }

            // Check recent notification failures
            $recentFailures = Notification::where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            $totalRecent = Notification::where('created_at', '>=', now()->subHours(24))->count();

            $this->metrics['email_failures_24h'] = $recentFailures;
            $this->metrics['email_total_24h'] = $totalRecent;

            if ($totalRecent > 0) {
                $failureRate = ($recentFailures / $totalRecent) * 100;
                $this->metrics['email_failure_rate_percent'] = round($failureRate, 2);

                if ($failureRate > $this->thresholds['email_failure_rate']) {
                    $this->addAlert('High email failure rate', 'medium', [
                        'failure_rate_percent' => $failureRate,
                        'failures' => $recentFailures,
                        'total' => $totalRecent,
                        'threshold_percent' => $this->thresholds['email_failure_rate']
                    ]);
                }
            }

            // TODO: Test SMTP connection (optional, può essere lento)
            // $this->testSmtpConnection();

        } catch (\Exception $e) {
            $this->addCheck($results, 'email_system', 'error', 'Email system error: ' . $e->getMessage());
            $this->addAlert('Email system error', 'medium', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['email_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 6: Business Metrics
     */
    private function checkBusinessMetrics(&$results)
    {
        $startTime = microtime(true);

        try {
            // Statistiche utenti attivi
            $activeUsers24h = User::where('last_login_at', '>=', now()->subDay())->count();
            $activeUsers7d = User::where('last_login_at', '>=', now()->subWeek())->count();

            $this->metrics['active_users_24h'] = $activeUsers24h;
            $this->metrics['active_users_7d'] = $activeUsers7d;

            // Tornei stats
            $upcomingTournaments = Tournament::where('start_date', '>=', now())
                ->where('start_date', '<=', now()->addMonth())
                ->count();

            $openTournaments = Tournament::where('status', 'open')->count();
            $confirmedTournaments = Tournament::where('status', 'confirmed')
                ->where('start_date', '>=', now())
                ->count();

            $this->metrics['upcoming_tournaments'] = $upcomingTournaments;
            $this->metrics['open_tournaments'] = $openTournaments;
            $this->metrics['confirmed_tournaments'] = $confirmedTournaments;

            // Assegnazioni stats
            $pendingAssignments = Assignment::where('status', 'pending')->count();
            $recentAssignments = Assignment::where('created_at', '>=', now()->subWeek())->count();

            $this->metrics['pending_assignments'] = $pendingAssignments;
            $this->metrics['recent_assignments'] = $recentAssignments;

            // Business alerts
            if ($openTournaments > 50) {
                $this->addAlert('Many tournaments awaiting closure', 'low', [
                    'open_tournaments' => $openTournaments
                ]);
            }

            if ($pendingAssignments > 20) {
                $this->addAlert('Many pending assignments', 'medium', [
                    'pending_assignments' => $pendingAssignments
                ]);
            }

            $this->addCheck($results, 'business_metrics', 'ok', 'Business metrics collected');

        } catch (\Exception $e) {
            $this->addCheck($results, 'business_metrics', 'error', 'Business metrics error: ' . $e->getMessage());
            $this->addAlert('Business metrics collection failed', 'low', [
                'error' => $e->getMessage()
            ]);
        }

        $this->metrics['business_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 7: Performance
     */
    private function checkPerformance(&$results)
    {
        $startTime = microtime(true);

        try {
            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

            if ($memoryLimit > 0) {
                $memoryUsagePercent = ($memoryUsage / $memoryLimit) * 100;
                $this->metrics['memory_usage_percent'] = round($memoryUsagePercent, 2);

                if ($memoryUsagePercent > $this->thresholds['memory_usage_percent']) {
                    $this->addAlert('High memory usage', 'medium', [
                        'usage_percent' => $memoryUsagePercent,
                        'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                        'limit_mb' => round($memoryLimit / 1024 / 1024, 2)
                    ]);
                }
            }

            $this->metrics['memory_usage_mb'] = round($memoryUsage / 1024 / 1024, 2);
            $this->metrics['memory_peak_mb'] = round($memoryPeak / 1024 / 1024, 2);

            // CPU load (Linux only)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $this->metrics['cpu_load_1m'] = round($load[0], 2);
                $this->metrics['cpu_load_5m'] = round($load[1], 2);
                $this->metrics['cpu_load_15m'] = round($load[2], 2);

                if ($load[0] > 4.0) {
                    $this->addAlert('High CPU load', 'medium', [
                        'load_1m' => $load[0]
                    ]);
                }
            }

            $this->addCheck($results, 'performance_metrics', 'ok', 'Performance metrics collected');

        } catch (\Exception $e) {
            $this->addCheck($results, 'performance_metrics', 'error', 'Performance check error: ' . $e->getMessage());
        }

        $this->metrics['performance_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Check 8: Security
     */
    private function checkSecurity(&$results)
    {
        $startTime = microtime(true);

        try {
            // Check environment
            if (config('app.debug') === true) {
                $this->addAlert('Debug mode enabled in production', 'critical', [
                    'app_debug' => true,
                    'recommendation' => 'Set APP_DEBUG=false'
                ]);
            }

            if (config('app.env') !== 'production') {
                $this->addAlert('Non-production environment detected', 'high', [
                    'app_env' => config('app.env')
                ]);
            }

            // Check failed login attempts (se implementato)
            // $failedLogins = $this->getFailedLogins();

            $this->addCheck($results, 'security_check', 'ok', 'Security check completed');

        } catch (\Exception $e) {
            $this->addCheck($results, 'security_check', 'error', 'Security check error: ' . $e->getMessage());
        }

        $this->metrics['security_check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Utility methods
     */
    private function addCheck(&$results, $name, $status, $message)
    {
        $results['checks'][] = [
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];
    }

    private function addAlert($message, $level, $context = [])
    {
        $this->alerts[] = [
            'message' => $message,
            'level' => $level,
            'context' => $context,
            'timestamp' => now()->toISOString()
        ];

        // Log alert
        Log::channel('monitoring')->log($level, $message, $context);
    }

    private function parseMemoryLimit($limit)
    {
        if ($limit === '-1') return 0;

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }

    private function saveHealthCheckResults($results)
    {
        // Salva in cache per API veloce
        Cache::put('system_health_check', $results, 300); // 5 minuti

        // Salva su file per storico
        $filename = 'health_checks/health_' . date('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put($filename, json_encode($results, JSON_PRETTY_PRINT));

        // Mantieni solo ultimi 24 files
        $this->cleanupOldHealthChecks();
    }

    private function cleanupOldHealthChecks()
    {
        $files = Storage::disk('local')->files('health_checks');
        if (count($files) > 24) {
            $toDelete = array_slice($files, 0, count($files) - 24);
            foreach ($toDelete as $file) {
                Storage::disk('local')->delete($file);
            }
        }
    }

    private function sendAlerts($results)
    {
        $criticalAlerts = array_filter($this->alerts, fn($a) => $a['level'] === 'critical');
        $highAlerts = array_filter($this->alerts, fn($a) => $a['level'] === 'high');

        if (!empty($criticalAlerts) || !empty($highAlerts)) {
            // Rate limiting: non inviare più di 1 alert ogni 15 minuti
            $lastAlert = Cache::get('last_monitoring_alert');
            if ($lastAlert && $lastAlert > now()->subMinutes(15)) {
                return;
            }

            Cache::put('last_monitoring_alert', now(), 900); // 15 minuti

            // Invia email alert
            $this->sendEmailAlert($results, $criticalAlerts, $highAlerts);
        }
    }

    private function sendEmailAlert($results, $criticalAlerts, $highAlerts)
    {
        $recipients = config('monitoring.alert_recipients', ['sysadmin@federgolf.it']);

        $subject = 'ALERT: Golf Arbitri System - ' . $results['overall_status'];

        $message = "Sistema Golf Arbitri - Alert Automatico\n\n";
        $message .= "Timestamp: " . $results['timestamp'] . "\n";
        $message .= "Stato Generale: " . strtoupper($results['overall_status']) . "\n";
        $message .= "Tempo Controllo: " . $results['execution_time_ms'] . "ms\n\n";

        if (!empty($criticalAlerts)) {
            $message .= "🚨 ALERT CRITICI:\n";
            foreach ($criticalAlerts as $alert) {
                $message .= "- " . $alert['message'] . "\n";
                if (!empty($alert['context'])) {
                    $message .= "  Dettagli: " . json_encode($alert['context']) . "\n";
                }
            }
            $message .= "\n";
        }

        if (!empty($highAlerts)) {
            $message .= "⚠️ ALERT ALTA PRIORITÀ:\n";
            foreach ($highAlerts as $alert) {
                $message .= "- " . $alert['message'] . "\n";
            }
            $message .= "\n";
        }

        $message .= "Dashboard: https://arbitri.federgolf.it/admin/monitoring\n";
        $message .= "Health Check API: https://arbitri.federgolf.it/health\n";

        foreach ($recipients as $email) {
            try {
                Mail::raw($message, function($mail) use ($email, $subject) {
                    $mail->to($email)->subject($subject);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send monitoring alert email', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * API per dashboard real-time
     */
    public function getRealtimeMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'system_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : null,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'active_users' => Cache::remember('active_users_count', 60, function() {
                return User::where('last_login_at', '>=', now()->subMinutes(30))->count();
            }),
            'pending_notifications' => Cache::remember('pending_notifications_count', 30, function() {
                return Notification::where('status', 'pending')->count();
            }),
            'recent_errors' => Cache::remember('recent_errors_count', 60, function() {
                return DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            })
        ];
    }
}
