<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SystemInfo;
use App\Helpers\SystemOperations;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ArubaToolsController extends Controller
{
    /**
     * Dashboard principale
     */
    public function dashboard()
    {
        $data = [
            'system_info' => SystemInfo::get(),
            'database_stats' => SystemInfo::getDatabaseStats(),
            'permissions' => SystemInfo::checkPermissions(),
        ];

        return view('aruba-admin.dashboard', $data);
    }

    /**
     * Gestione cache
     */
    public function cacheIndex()
    {
        return view('aruba-admin.cache');
    }

    /**
     * Pulisci cache specifiche
     */
    public function cacheClear(Request $request)
    {
        $type = $request->input('type', 'all');
        $output = [];

        try {
            switch ($type) {
                case 'config':
                    Artisan::call('config:clear');
                    $output[] = '✅ Config cache pulita';
                    break;

                case 'route':
                    Artisan::call('route:clear');
                    $output[] = '✅ Route cache pulita';
                    break;

                case 'view':
                    Artisan::call('view:clear');
                    $output[] = '✅ View cache pulita';
                    break;

                case 'cache':
                    Artisan::call('cache:clear');
                    $output[] = '✅ Application cache pulita';
                    break;

                case 'all':
                    Artisan::call('config:clear');
                    Artisan::call('route:clear');
                    Artisan::call('view:clear');
                    Artisan::call('cache:clear');
                    Artisan::call('clear-compiled');
                    Artisan::call('optimize:clear');
                    $output[] = '✅ Tutte le cache pulite';
                    break;

                default:
                    $output[] = '❌ Tipo cache non valido';
            }

            return back()->with('success', implode('<br>', $output));
        } catch (\Exception $e) {
            return back()->with('error', 'Errore: '.$e->getMessage());
        }
    }

    /**
     * Ottimizza applicazione
     */
    public function optimize()
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            return back()->with('success', '✅ Applicazione ottimizzata');
        } catch (\Exception $e) {
            return back()->with('error', 'Errore: '.$e->getMessage());
        }
    }

    /**
     * PHPInfo
     */
    public function phpinfo()
    {
        return view('aruba-admin.phpinfo');
    }

    /**
     * Visualizza logs
     */
    public function logs()
    {
        $logs = SystemInfo::getLatestLogs(100);

        return view('aruba-admin.logs', compact('logs'));
    }

    /**
     * Pulisci log
     */
    public function clearLogs()
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (File::exists($logFile)) {
                File::put($logFile, '');

                return back()->with('success', '✅ Log puliti');
            }

            return back()->with('error', 'File log non trovato');
        } catch (\Exception $e) {
            return back()->with('error', 'Errore: '.$e->getMessage());
        }
    }

    /**
     * Verifica permessi
     */
    public function permissions()
    {
        $permissions = SystemInfo::checkPermissions();

        return view('aruba-admin.permissions', compact('permissions'));
    }

    /**
     * Correggi permessi (tentativo)
     */
    public function fixPermissions()
    {
        $directories = [
            storage_path(),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $results = [];

        foreach ($directories as $dir) {
            try {
                if (File::exists($dir)) {
                    chmod($dir, 0775);
                    $results[] = "✅ Permessi aggiornati: {$dir}";
                } else {
                    File::makeDirectory($dir, 0775, true);
                    $results[] = "✅ Cartella creata: {$dir}";
                }
            } catch (\Exception $e) {
                $results[] = "❌ Errore su {$dir}: ".$e->getMessage();
            }
        }

        return back()->with('info', implode('<br>', $results));
    }

    // ================================
    // COMPOSER OPERATIONS
    // ================================

    public function composerIndex()
    {
        $composerVersion = SystemOperations::getComposerVersion();
        $outdated = SystemOperations::composerOutdated();

        return view('aruba-admin.composer', compact('composerVersion', 'outdated'));
    }

    public function composerDumpAutoload()
    {
        $result = SystemOperations::composerDumpAutoload();

        $message = $result['success']
            ? '✅ Autoload rigenerato'
            : '❌ Errore: '.$result['output'];

        return back()->with($result['success'] ? 'success' : 'error', $message);
    }

    /**
     * Diagnostica Composer
     */
    public function composerDiagnostic()
    {
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

        $output = "🔍 RICERCA COMPOSER SUL SERVER\n";
        $output .= str_repeat('=', 50)."\n\n";

        $found = false;
        $foundPath = null;

        foreach ($possiblePaths as $path) {
            $output .= "Tentativo: {$path}\n";

            try {
                exec("{$path} --version 2>&1", $result, $returnCode);

                if ($returnCode === 0 && ! empty($result)) {
                    $output .= "  ✅ TROVATO!\n";
                    $output .= '  Versione: '.implode("\n", $result)."\n";
                    $found = true;
                    $foundPath = $path;
                    break;
                } else {
                    $output .= "  ❌ Non trovato (exit code: {$returnCode})\n";
                }
            } catch (\Exception $e) {
                $output .= '  ❌ Errore: '.$e->getMessage()."\n";
            }

            $output .= "\n";
        }

        $output .= str_repeat('=', 50)."\n";

        if ($found) {
            $output .= "\n✅ COMPOSER TROVATO IN: {$foundPath}\n";
            $output .= "\nPer usarlo, aggiorna il percorso nel codice.\n";
        } else {
            $output .= "\n❌ COMPOSER NON TROVATO\n";
            $output .= "\nPossibili soluzioni:\n";
            $output .= "1. Installa Composer locale: wget https://getcomposer.org/composer.phar\n";
            $output .= "2. Contatta supporto Aruba per verificare disponibilità\n";
            $output .= "3. Usa Composer in locale e carica vendor/ via FTP\n";
        }

        return response()->json([
            'found' => $found,
            'path' => $foundPath,
            'output' => $output,
        ]);
    }

    // ================================
    // DATABASE BACKUP
    // ================================

    public function databaseIndex()
    {
        $backups = SystemOperations::listDatabaseBackups();

        return view('aruba-admin.database', compact('backups'));
    }

    public function databaseBackup()
    {
        $result = SystemOperations::backupDatabase();

        if ($result['success']) {
            return back()->with('success', "✅ Backup creato: {$result['filename']} (".number_format($result['size'] / 1024 / 1024, 2).' MB)');
        }

        return back()->with('error', '❌ '.$result['output']);
    }

    public function databaseRestore(Request $request)
    {
        $filename = $request->input('filename');
        $result = SystemOperations::restoreDatabase($filename);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? "✅ Database ripristinato da: {$filename}" : '❌ '.$result['output']
        );
    }

    // ================================
    // SERVER MONITORING
    // ================================

    public function serverMonitoring()
    {
        $serverLoad = SystemOperations::getServerLoad();
        $phpProcesses = SystemOperations::listPhpProcesses();
        $storageSize = SystemOperations::getDirectorySize(storage_path());

        return view('aruba-admin.monitoring', compact('serverLoad', 'phpProcesses', 'storageSize'));
    }

    // ================================
    // SECURITY
    // ================================

    public function securityIndex()
    {
        $sensitiveFiles = SystemOperations::checkSensitiveFiles();
        $suspiciousFiles = SystemOperations::scanForSuspiciousFiles();

        return view('aruba-admin.security', compact('sensitiveFiles', 'suspiciousFiles'));
    }
}
