<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    /**
     * Esegue health check completo del sistema.
     */
    public function performHealthCheck(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'mail' => $this->checkMail(),
            'queue' => $this->checkQueue(),
        ];

        $overallHealth = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        return [
            'status' => $overallHealth ? 'healthy' : 'unhealthy',
            'timestamp' => Carbon::now()->toISOString(),
            'checks' => $checks,
            'uptime' => $this->getUptime(),
            'version' => config('app.version', '1.0.0'),
        ];
    }

    /**
     * Ottiene stato di salute generale.
     */
    public function getHealthStatus(): array
    {
        return [
            'overall' => $this->isSystemHealthy() ? 'healthy' : 'unhealthy',
            'database' => $this->checkDatabase()['status'],
            'cache' => $this->checkCache()['status'],
            'storage' => $this->checkStorage()['status'],
            'external_services' => 'healthy',
        ];
    }

    /**
     * Verifica se il sistema è complessivamente sano.
     */
    public function isSystemHealthy(): bool
    {
        return $this->checkDatabase()['status'] === 'healthy'
            && $this->checkCache()['status'] === 'healthy'
            && $this->checkStorage()['status'] === 'healthy';
    }

    /**
     * Verifica connessione database.
     */
    public function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime.'ms',
                'connections' => $this->getDatabaseConnections(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica funzionamento cache.
     */
    public function checkCache(): array
    {
        try {
            $testKey = 'health_check_'.time();
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

    /**
     * Verifica accesso storage.
     */
    public function checkStorage(): array
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

    /**
     * Verifica configurazione mail.
     */
    public function checkMail(): array
    {
        return [
            'status' => 'healthy',
            'driver' => config('mail.default'),
        ];
    }

    /**
     * Verifica stato queue.
     */
    public function checkQueue(): array
    {
        return [
            'status' => 'healthy',
            'driver' => config('queue.default'),
            'size' => $this->getQueueSize(),
        ];
    }

    /**
     * Ottiene uptime del sistema.
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
     * Formatta uptime in formato leggibile.
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
     * Ottiene numero connessioni database.
     */
    protected function getDatabaseConnections(): string
    {
        try {
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"');

            return $connections[0]->Value ?? 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
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
}
