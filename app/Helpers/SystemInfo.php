<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemInfo
{
    /**
     * Ottieni informazioni sul sistema
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'disk_free_space' => self::formatBytes(disk_free_space(base_path()) ?: 0),
            'disk_total_space' => self::formatBytes(disk_total_space(base_path()) ?: 0),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'timezone' => Config::string('app.timezone'),
            'environment' => app()->environment(),
            'debug_mode' => Config::boolean('app.debug') ? 'ON' : 'OFF',
        ];
    }

    /**
     * Verifica permessi cartelle critiche
     *
     * @return array<string, mixed>
     */
    public static function checkPermissions(): array
    {
        $directories = [
            'storage' => storage_path(),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $results = [];

        foreach ($directories as $name => $path) {
            $results[$name] = [
                'path' => $path,
                'exists' => File::exists($path),
                'writable' => File::exists($path) && is_writable($path),
                'permissions' => File::exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A',
            ];
        }

        return $results;
    }

    /**
     * Ottieni statistiche database (compatibile con vari setup)
     *
     * @return array<string, mixed>
     */
    public static function getDatabaseStats(): array
    {
        try {
            $connection = Config::string('database.default');
            $driver = Config::string("database.connections.{$connection}.driver");

            // Adatta query in base al driver
            if ($driver === 'mysql') {
                $tables = DB::select('SHOW TABLES');
            } elseif ($driver === 'pgsql') {
                $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
            } elseif ($driver === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            } else {
                $tables = [];
            }

            $dbName = Config::string("database.connections.{$connection}.database");

            return [
                'connected' => true,
                'driver' => $driver,
                'database' => $dbName,
                'tables_count' => count($tables),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'driver' => Config::string('database.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formatta bytes in formato leggibile
     *
     * @param  float|int  $bytes
     */
    private static function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Ottieni ultimi log
     *
     * @return array<string, mixed>
     */
    public static function getLatestLogs(int $lines = 50): array
    {
        $logFile = storage_path('logs/laravel.log');

        if (! File::exists($logFile)) {
            return ['error' => 'File log non trovato'];
        }

        $content = File::get($logFile);
        $logLines = explode("\n", $content);
        $latestLines = array_slice($logLines, -$lines);

        return [
            'lines' => array_reverse($latestLines),
            'file_size' => self::formatBytes(File::size($logFile)),
            'last_modified' => date('Y-m-d H:i:s', File::lastModified($logFile)),
        ];
    }
}
