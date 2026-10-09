<?php

namespace App\Console\Commands;

use App\Services\DataConsistencyService;
use Illuminate\Console\Command;

/**
 * Controlli di coerenza sui dati (2026-10-09): stesso contenuto della pagina
 * Sistema -> Controllo dati. Non modifica niente.
 */
class DataCheckCommand extends Command
{
    protected $signature = 'golf:controlla-dati {--dettagli : Elenca anche le righe di ogni controllo}';

    protected $description = 'Elenca le anomalie nei dati (tipi, zone, date, designazioni, notifiche)';

    public function handle(DataConsistencyService $service): int
    {
        $problems = 0;
        foreach ($service->run() as $check) {
            $n = count($check['rows']);
            $problems += $n;
            $this->line(sprintf('%s %3d  %s', $n === 0 ? '✓' : ($check['level'] === 'errore' ? '✗' : '?'), $n, $check['title']));
            if ($n > 0 && $this->option('dettagli')) {
                foreach ($check['rows'] as $row) {
                    $this->line('       - '.$row['label']);
                }
            }
        }
        $this->newLine();
        $this->info("Righe da guardare: {$problems}");

        return self::SUCCESS;
    }
}
