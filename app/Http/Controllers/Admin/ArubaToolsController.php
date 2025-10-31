<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use App\Helpers\SystemInfo;

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
            return back()->with('error', 'Errore: ' . $e->getMessage());
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
            return back()->with('error', 'Errore: ' . $e->getMessage());
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
            return back()->with('error', 'Errore: ' . $e->getMessage());
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
                $results[] = "❌ Errore su {$dir}: " . $e->getMessage();
            }
        }

        return back()->with('info', implode('<br>', $results));
    }
}
