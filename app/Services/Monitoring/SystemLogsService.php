<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * @phpstan-type LogEntry array{
 *     level: string,
 *     message: string,
 *     time: \Illuminate\Support\Carbon,
 * }
 */
class SystemLogsService
{
    /**
     * Ottiene log di sistema.
     * @return \Illuminate\Support\Collection<int, LogEntry>
     */
    public function getLogs(
        string $level = 'all',
        ?string $date = null,
        ?string $search = null
    ): Collection {
        $date = $date ?? Carbon::today()->format('Y-m-d');

        // Per ora dati mock - implementare lettura log reali se necessario
        /** @var Collection<int, LogEntry> $logs */
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
     *
     * @return array<string, mixed>
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

}
