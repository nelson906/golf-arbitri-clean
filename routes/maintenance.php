<?php

use App\Http\Controllers\Admin\ArubaToolsController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// ========================================
// ROUTE TEMPORANEE PER SETUP INIZIALE
// (Rimuovere dopo aver impostato super admin)
// ========================================
Route::middleware(['auth'])->group(function () {

    // Verifica funzioni disabilitate (TEMPORANEO - per debugging)
    Route::get('/admin/check-disabled-functions', function () {
        if (auth()->id() !== 14) {
            abort(403);
        }

        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        $criticalFunctions = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open'];

        $output = '<h2>Funzioni PHP Disabilitate</h2><ul>';
        foreach ($criticalFunctions as $func) {
            $isDisabled = in_array($func, $disabledFunctions);
            $status = $isDisabled ? '❌ DISABILITATA' : '✅ DISPONIBILE';
            $output .= "<li><strong>{$func}()</strong>: {$status}</li>";
        }
        $output .= '</ul><pre>'.implode("\n", $disabledFunctions).'</pre>';

        return $output;
    });

    // Migration (solo utente ID 1)
    Route::get('/admin/run-migration', function () {
        if (auth()->id() !== 14) {
            abort(403, 'Solo il primo utente registrato');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            return '<pre>✅ Migration eseguita:\n'.Artisan::output().'</pre>';
        } catch (\Exception $e) {
            return '<pre>❌ Errore: '.$e->getMessage().'</pre>';
        }
    });

    // Imposta Super Admin (solo utente ID 1)
    Route::get('/admin/set-super-admin', function () {
        $user = auth()->user();

        if ($user->id !== 14) {
            abort(403, 'Solo il primo utente registrato');
        }

        // Imposta role='super_admin'
        $user->role = 'super_admin';
        $user->save();

        return "✅ Super Admin impostato per: {$user->email}<br><br>
                <a href='/aruba-admin' style='color: blue; text-decoration: underline;'>
                    Vai alla Dashboard Admin →
                </a>";
    });
});

// ========================================
// ARUBA ADMIN TOOLS - Solo Super Admin
// ========================================
Route::prefix('aruba-admin')
    ->name('aruba.admin.')
    ->middleware(['auth', 'super_admin'])
    ->group(function () {

        Route::get('/', [ArubaToolsController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/cache', [ArubaToolsController::class, 'cacheIndex'])
            ->name('cache.index');
        Route::post('/cache/clear', [ArubaToolsController::class, 'cacheClear'])
            ->name('cache.clear');
        Route::post('/optimize', [ArubaToolsController::class, 'optimize'])
            ->name('optimize');

        Route::get('/phpinfo', [ArubaToolsController::class, 'phpinfo'])
            ->name('phpinfo');

        Route::get('/logs', [ArubaToolsController::class, 'logs'])
            ->name('logs');
        Route::post('/logs/clear', [ArubaToolsController::class, 'clearLogs'])
            ->name('logs.clear');

        Route::get('/permissions', [ArubaToolsController::class, 'permissions'])
            ->name('permissions');
        Route::post('/permissions/fix', [ArubaToolsController::class, 'fixPermissions'])
            ->name('permissions.fix');

        // Composer
        Route::get('/composer', [ArubaToolsController::class, 'composerIndex'])
            ->name('composer.index');
        Route::post('/composer/dump-autoload', [ArubaToolsController::class, 'composerDumpAutoload'])
            ->name('composer.dump');

        // Database Backup
        Route::get('/database', [ArubaToolsController::class, 'databaseIndex'])
            ->name('database.index');
        Route::post('/database/backup', [ArubaToolsController::class, 'databaseBackup'])
            ->name('database.backup');
        Route::post('/database/restore', [ArubaToolsController::class, 'databaseRestore'])
            ->name('database.restore');

        // Server Monitoring
        Route::get('/monitoring', [ArubaToolsController::class, 'serverMonitoring'])
            ->name('monitoring');

        // Security
        Route::get('/security', [ArubaToolsController::class, 'securityIndex'])
            ->name('security');

        // Composer Diagnostic
        Route::post('/composer/diagnostic', [ArubaToolsController::class, 'composerDiagnostic'])
            ->name('composer.diagnostic');
    });
