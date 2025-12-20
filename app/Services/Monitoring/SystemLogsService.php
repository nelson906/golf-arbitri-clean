<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class SystemLogsService
{
    /**
     * Ottiene log di sistema.
     */
    public function getLogs(
        string $level = 'all',
        ?string $date = null,
        ?string $search = null
    ): Collection {
        $date = $date ?? Carbon::today()->format('Y-m-d');

        // Per ora dati mock - implementare lettura log reali se necessario
        $logs = collect([
            ['level' => 'info', 'message' => 'Sistema avviato correttamente', 'time' => now()],
            ['level' => 'info', 'message' => 'Database connesso - 3 connessioni attive', 'time' => now()],
            ['level' => 'warning', 'message' => 'Memoria utilizzo al 75%', 'time' => now()],
            ['level' => 'info', 'message' => 'Health check completato', 'time' => now()],
        ]);

        // Filtra per livello
        if ($level !== 'all') {
            $logs = $logs->where('level', $level);
        }

        // Filtra per ricerca
        if ($search) {
            $logs = $logs->filter(function ($log) use ($search) {
                return str_contains(strtolower($log['message']), strtolower($search));
            });
        }

        return $logs;
    }

    /**
     * Ottiene statistiche log.
     */
    public function getLogStats(?string $date = null): array
    {
        $logs = $this->getLogs('all', $date);

        return [
            'total' => $logs->count(),
            'errors' => $logs->where('level', 'error')->count(),
            'warnings' => $logs->where('level', 'warning')->count(),
            'info' => $logs->where('level', 'info')->count(),
            'debug' => $logs->where('level', 'debug')->count(),
        ];
    }

    /**
     * Legge file di log Laravel.
     */
    public function readLaravelLog(?string $date = null, int $lines = 100): array
    {
        $date = $date ?? Carbon::today()->format('Y-m-d');
        $logFile = storage_path("logs/laravel-{$date}.log");

        if (! file_exists($logFile)) {
            $logFile = storage_path('logs/laravel.log');
        }

        if (! file_exists($logFile)) {
            return [];
        }

        $content = file_get_contents($logFile);
        if ($content === false) {
            return [];
        }
        $logLines = array_slice(explode("\n", $content), -$lines);

        return array_filter($logLines);
    }

    /**
     * Parsa una riga di log.
     */
    protected function parseLogLine(string $line): ?array
    {
        // Pattern per log Laravel: [2025-01-01 12:00:00] production.ERROR: Message
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/';

        if (preg_match($pattern, $line, $matches)) {
            return [
                'time' => $matches[1],
                'level' => strtolower($matches[2]),
                'message' => $matches[3],
            ];
        }

        return null;
    }

    /**
     * Ottiene log per livello.
     */
    public function getLogsByLevel(string $level, ?string $date = null, int $limit = 50): Collection
    {
        return $this->getLogs($level, $date)->take($limit);
    }

    /**
     * Conta errori recenti.
     */
    public function countRecentErrors(int $hours = 24): int
    {
        $logs = $this->getLogs('error');

        return $logs->filter(function ($log) use ($hours) {
            return $log['time']->isAfter(now()->subHours($hours));
        })->count();
    }
}
