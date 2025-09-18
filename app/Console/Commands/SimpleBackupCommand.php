<?php
// app/Console/Commands/SimpleBackupCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SimpleBackupCommand extends Command
{
    protected $signature = 'backup:simple {--db-only : Solo database} {--code-only : Solo codice}';
    protected $description = 'Backup semplice senza dipendenze esterne';

    public function handle()
    {
        $this->info('🔒 BACKUP SEMPLICE');
        $this->info('==================');

        $timestamp = now()->format('Y_m_d_H_i_s');
        $backupDir = storage_path('app/backups');

        // Crea directory backup
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        if (!$this->option('code-only')) {
            $this->backupDatabase($backupDir, $timestamp);
        }

        if (!$this->option('db-only')) {
            $this->backupCode($timestamp);
        }

        $this->info("✅ Backup completato in: {$backupDir}");
        return 0;
    }

    private function backupDatabase($backupDir, $timestamp)
    {
        $this->info('📊 Backup database...');

        $config = config('database.connections.mysql');
        $backupFile = "{$backupDir}/db_backup_{$timestamp}.sql";

        // Costruisci comando mysqldump
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['username'],
            $config['password'],
            $config['database'],
            $backupFile
        );

        // Nascondi password nell'output
        $displayCommand = str_replace("-p{$config['password']}", '-p***', $command);
        $this->line("Executing: {$displayCommand}");

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            $size = $this->formatBytes(filesize($backupFile));
            $this->info("✅ Database backup: {$backupFile} ({$size})");
        } else {
            $this->error("❌ Errore backup database");
            if (!empty($output)) {
                $this->error("Output: " . implode("\n", $output));
            }
        }
    }

    private function backupCode($timestamp)
    {
        $this->info('📁 Backup Git...');

        // Verifica se è un repository git
        if (!is_dir(base_path('.git'))) {
            $this->warn('⚠️ Non è un repository Git. Creo backup fisico...');
            $this->createPhysicalBackup($timestamp);
            return;
        }

        // Status Git
        exec('git status --porcelain', $gitStatus);

        if (!empty($gitStatus)) {
            $this->info('📝 Commit cambiamenti pendenti...');
            exec('git add -A', $addOutput);
            exec("git commit -m '🔒 Backup automatico pre-cleanup {$timestamp}'", $commitOutput, $commitReturn);

            if ($commitReturn === 0) {
                $this->info('✅ Cambiamenti committati');
            }
        }

        // Crea tag
        $tagName = "backup-{$timestamp}";
        exec("git tag {$tagName}", $tagOutput, $tagReturn);

        if ($tagReturn === 0) {
            $this->info("✅ Tag creato: {$tagName}");
            $this->info("💡 Per rollback: git checkout {$tagName}");
        } else {
            $this->warn('⚠️ Impossibile creare tag Git');
        }
    }

    private function createPhysicalBackup($timestamp)
    {
        $backupDir = storage_path("app/backups/code_backup_{$timestamp}");
        $this->info("📦 Backup fisico in: {$backupDir}");

        // File/directory importanti da backuppare
        $important = [
            'app/',
            'database/',
            'routes/',
            'resources/',
            'config/',
            'composer.json',
            'composer.lock',
            '.env'
        ];

        File::makeDirectory($backupDir, 0755, true);

        foreach ($important as $item) {
            $sourcePath = base_path($item);
            $destPath = $backupDir . '/' . $item;

            if (File::exists($sourcePath)) {
                if (File::isDirectory($sourcePath)) {
                    File::copyDirectory($sourcePath, $destPath);
                } else {
                    File::copy($sourcePath, $destPath);
                }
                $this->line("  ✅ Copiato: {$item}");
            }
        }

        $this->info("✅ Backup fisico completato");
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
