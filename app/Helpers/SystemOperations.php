<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class SystemOperations
{
    /**
     * Esegui comando shell in modo sicuro
     *
     * @return array{success: bool, output: list<string>, exit_code: int}
     */
    private static function execCommand(string $command): array
    {
        $output = [];
        $returnVar = 0;

        exec($command.' 2>&1', $output, $returnVar);

        return [
            'success' => $returnVar === 0,
            'output' => $output,
            'exit_code' => $returnVar,
        ];
    }

    // ================================
    // COMPOSER OPERATIONS
    // ================================

    /**
     * Verifica versione Composer (con percorsi multipli)
     */
    public static function getComposerVersion(): ?string
    {
        // Lista percorsi comuni dove Composer potrebbe essere installato
        $possiblePaths = [
            'composer',                           // PATH globale
            '/usr/local/bin/composer',            // Installazione standard
            '/usr/bin/composer',                  // Debian/Ubuntu
            base_path('composer.phar'),           // Locale al progetto
            '/opt/alt/php81/usr/bin/composer',    // Aruba CloudLinux PHP 8.1
            '/opt/alt/php82/usr/bin/composer',    // Aruba CloudLinux PHP 8.2
            '/opt/alt/php83/usr/bin/composer',    // Aruba CloudLinux PHP 8.3
            getenv('HOME').'/composer',         // Home directory
            getenv('HOME').'/bin/composer',     // Home bin
        ];

        foreach ($possiblePaths as $path) {
            // Prova ad eseguire composer --version
            $result = self::execCommand("{$path} --version 2>&1");

            if ($result['success'] && ! empty($result['output'])) {
                $output = $result['output'][0] ?? '';

                // Verifica che contenga "Composer"
                if (stripos($output, 'composer') !== false) {
                    return $output;
                }
            }
        }

        return null;
    }

    /**
     * Trova il percorso di Composer
     */
    private static function findComposerPath(): ?string
    {
        static $composerPath = null;

        if ($composerPath !== null) {
            return $composerPath;
        }

        $possiblePaths = [
            'composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            base_path('composer.phar'),
            '/opt/alt/php81/usr/bin/composer',
            '/opt/alt/php82/usr/bin/composer',
            '/opt/alt/php83/usr/bin/composer',
            getenv('HOME').'/composer',
            getenv('HOME').'/bin/composer',
        ];

        foreach ($possiblePaths as $path) {
            $result = self::execCommand("{$path} --version 2>&1");

            if ($result['success'] && ! empty($result['output'])) {
                $output = $result['output'][0] ?? '';

                if (stripos($output, 'composer') !== false) {
                    $composerPath = $path;

                    return $composerPath;
                }
            }
        }

        return null;
    }

    /**
     * Verifica se un comando esiste (migliorato)
     */
    public static function commandExists(string $command): bool
    {
        // Per Composer, usa il metodo specifico
        if ($command === 'composer') {
            return self::findComposerPath() !== null;
        }

        // Per altri comandi
        $result = self::execCommand("which {$command} 2>&1");

        return $result['success'] && ! empty($result['output']);
    }

    /**
     * Composer dump-autoload (aggiornato)
     *
     * @return array{success: bool, output: string}
     */
    public static function composerDumpAutoload(): array
    {
        $composerPath = self::findComposerPath();

        if (! $composerPath) {
            return [
                'success' => false,
                'output' => 'Composer non trovato. Verifica l\'installazione.',
            ];
        }

        $basePath = base_path();
        $result = self::execCommand("cd {$basePath} && {$composerPath} dump-autoload --optimize 2>&1");

        return [
            'success' => $result['success'],
            'output' => implode("\n", $result['output']),
        ];
    }

    /**
     * Lista pacchetti Composer outdated (aggiornato)
     *
     * @return array{success: bool, packages: list<string>}
     */
    public static function composerOutdated(): array
    {
        $composerPath = self::findComposerPath();

        if (! $composerPath) {
            return [
                'success' => false,
                'packages' => ['Composer non trovato'],
            ];
        }

        $basePath = base_path();
        $result = self::execCommand("cd {$basePath} && {$composerPath} outdated --direct 2>&1");

        return [
            'success' => $result['success'],
            'packages' => $result['output'],
        ];
    }

    // ================================
    // GIT OPERATIONS
    // ================================

    /**
     * Verifica se Git è disponibile
     */
    public static function isGitAvailable(): bool
    {
        return self::commandExists('git');
    }

    /**
     * Ottieni branch corrente
     */
    public static function getCurrentBranch(): ?string
    {
        if (! self::isGitAvailable()) {
            return null;
        }

        $basePath = base_path();
        $result = self::execCommand("cd {$basePath} && git rev-parse --abbrev-ref HEAD");

        return $result['success'] ? trim($result['output'][0] ?? '') : null;
    }

    /**
     * Git status
     *
     * @return array<string, mixed>
     */
    public static function gitStatus(): array
    {
        if (! self::isGitAvailable()) {
            return ['success' => false, 'output' => 'Git non disponibile'];
        }

        $basePath = base_path();
        $result = self::execCommand("cd {$basePath} && git status --short");

        return [
            'success' => $result['success'],
            'files' => $result['output'],
            'has_changes' => count($result['output']) > 0,
        ];
    }

    // ================================
    // DATABASE OPERATIONS
    // ================================

    /**
     * Backup Database MySQL
     *
     * @return array{success: bool, output: string, filename: string, filepath: string, size: int}
     */
    public static function backupDatabase(): array
    {
        $connection = Config::string('database.default');
        $driver = Config::string("database.connections.{$connection}.driver");

        if ($driver !== 'mysql') {
            return self::backupFailure('Solo MySQL supportato');
        }

        $database = Config::string("database.connections.{$connection}.database");
        $username = Config::string("database.connections.{$connection}.username");
        $password = Config::string("database.connections.{$connection}.password");
        $host = Config::string("database.connections.{$connection}.host");

        $backupPath = storage_path('backups/database');

        if (! File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0775, true);
        }

        $filename = $database.'_'.date('Y-m-d_H-i-s').'.sql';
        $filepath = $backupPath.'/'.$filename;

        // Verifica se mysqldump esiste
        if (! self::commandExists('mysqldump')) {
            return self::backupFailure('mysqldump non disponibile');
        }

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        $result = self::execCommand($command);

        return [
            'success' => $result['success'],
            // FIX: prima 'output' mancava in questo ramo, ma ArubaToolsController
            // la legge quando success e' false — un mysqldump fallito produceva
            // un "Undefined array key" invece del messaggio d'errore.
            'output' => implode("\n", $result['output']),
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => File::exists($filepath) ? File::size($filepath) : 0,
        ];
    }

    /**
     * Fallimento di backupDatabase() nella stessa forma del successo, cosi'
     * il chiamante puo' leggere 'output' senza controllare quale ramo e'.
     *
     * @return array{success: bool, output: string, filename: string, filepath: string, size: int}
     */
    private static function backupFailure(string $motivo): array
    {
        return [
            'success' => false,
            'output' => $motivo,
            'filename' => '',
            'filepath' => '',
            'size' => 0,
        ];
    }

    /**
     * Lista backup database
     *
     * @return list<array{filename: string, size: int, date: string, path: string}>
     */
    public static function listDatabaseBackups(): array
    {
        $backupPath = storage_path('backups/database');

        if (! File::exists($backupPath)) {
            return [];
        }

        $files = File::files($backupPath);
        $backups = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'sql') {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    'path' => $file->getPathname(),
                ];
            }
        }

        // Ordina per data decrescente
        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $backups;
    }

    /**
     * Ripristina Database da backup
     *
     * @return array{success: bool, output: string}
     */
    public static function restoreDatabase(string $filename): array
    {
        // Anti path-traversal: solo nome file semplice con estensione .sql,
        // confinato in storage/backups/database.
        $filename = basename($filename);

        if (! preg_match('/^[\w\-. ]+\.sql$/', $filename)) {
            return ['success' => false, 'output' => 'Nome file backup non valido'];
        }

        $filepath = storage_path('backups/database/'.$filename);

        if (! File::exists($filepath)) {
            return ['success' => false, 'output' => 'File backup non trovato'];
        }

        $connection = Config::string('database.default');
        $database = Config::string("database.connections.{$connection}.database");
        $username = Config::string("database.connections.{$connection}.username");
        $password = Config::string("database.connections.{$connection}.password");
        $host = Config::string("database.connections.{$connection}.host");

        if (! self::commandExists('mysql')) {
            return ['success' => false, 'output' => 'mysql command non disponibile'];
        }

        $command = sprintf(
            'mysql -h %s -u %s -p%s %s < %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        $result = self::execCommand($command);

        return [
            'success' => $result['success'],
            'output' => implode("\n", $result['output']),
        ];
    }

    // ================================
    // FILE SYSTEM OPERATIONS
    // ================================

    /**
     * Ottieni dimensione directory
     *
     * @return array<string, mixed>
     */
    public static function getDirectorySize(string $path): array
    {
        if (! File::exists($path)) {
            return ['success' => false, 'size' => 0];
        }

        $result = self::execCommand('du -sh '.escapeshellarg($path));

        if ($result['success'] && ! empty($result['output'])) {
            $parts = explode("\t", $result['output'][0]);

            return [
                'success' => true,
                'size' => $parts[0] ?? '0',
                'path' => $parts[1] ?? $path,
            ];
        }

        return ['success' => false, 'size' => 0];
    }

    // ================================
    // PROCESS MONITORING
    // ================================

    /**
     * Lista processi PHP attivi
     *
     * @return array<string, mixed>
     */
    public static function listPhpProcesses(): array
    {
        $result = self::execCommand('ps aux | grep php');

        $processes = [];
        foreach ($result['output'] as $line) {
            if (strpos($line, 'grep') === false) {
                $processes[] = $line;
            }
        }

        return [
            'success' => $result['success'],
            'processes' => $processes,
            'count' => count($processes),
        ];
    }

    /**
     * Ottieni utilizzo risorse server
     *
     * @return array<string, mixed>
     */
    public static function getServerLoad(): array
    {
        $load = sys_getloadavg();

        // Memoria
        $memResult = self::execCommand('free -m');
        $memInfo = [];

        if ($memResult['success'] && count($memResult['output']) > 1) {
            $memLine = preg_split('/\s+/', $memResult['output'][1]);
            $memInfo = [
                'total' => ($memLine[1] ?? 0).' MB',
                'used' => ($memLine[2] ?? 0).' MB',
                'free' => ($memLine[3] ?? 0).' MB',
            ];
        }

        // Disco
        $diskResult = self::execCommand('df -h '.base_path());
        $diskInfo = [];

        if ($diskResult['success'] && count($diskResult['output']) > 1) {
            $diskLine = preg_split('/\s+/', $diskResult['output'][1]);
            $diskInfo = [
                'filesystem' => $diskLine[0] ?? '',
                'size' => $diskLine[1] ?? '',
                'used' => $diskLine[2] ?? '',
                'available' => $diskLine[3] ?? '',
                'use_percent' => $diskLine[4] ?? '',
            ];
        }

        return [
            'load_average' => [
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0,
            ],
            'memory' => $memInfo,
            'disk' => $diskInfo,
        ];
    }

    // ================================
    // SECURITY CHECKS
    // ================================

    /**
     * Verifica permessi file sensibili
     *
     * @return array<string, mixed>
     */
    public static function checkSensitiveFiles(): array
    {
        $files = [
            '.env' => base_path('.env'),
            'composer.json' => base_path('composer.json'),
            'composer.lock' => base_path('composer.lock'),
            'storage/' => storage_path(),
        ];

        $results = [];

        foreach ($files as $name => $path) {
            if (File::exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $results[$name] = [
                    'exists' => true,
                    'permissions' => $perms,
                    'writable' => is_writable($path),
                    'readable' => is_readable($path),
                    'secure' => ! is_writable($path) || $name === 'storage/', // storage deve essere writable
                ];
            } else {
                $results[$name] = ['exists' => false];
            }
        }

        return $results;
    }

    /**
     * Cerca file potenzialmente pericolosi
     *
     * @return array<string, mixed>
     */
    public static function scanForSuspiciousFiles(): array
    {
        $basePath = base_path();

        // Cerca file con estensioni sospette nel public
        $command = sprintf(
            'find %s -type f \( -name "*.php.bak" -o -name "*.php~" -o -name ".DS_Store" \)',
            escapeshellarg($basePath.'/public')
        );

        $result = self::execCommand($command);

        return [
            'suspicious_files' => $result['output'],
            'count' => count($result['output']),
        ];
    }
}
