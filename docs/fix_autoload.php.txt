<?php

/**
 * Script per pulire cache Laravel 12 senza SSH
 * Da caricare nella cartella /public/ e chiamare via browser
 */
echo "=== FIX AUTOLOAD LARAVEL 12 ===\n\n";

try {
    // 1. Carica autoload
    require __DIR__.'/../vendor/autoload.php';
    echo "✓ Autoload caricato correttamente\n";
} catch (Throwable $e) {
    echo '✗ ERRORE Autoload: '.$e->getMessage()."\n";
    exit();
}

try {
    // 2. Carica l'applicazione Laravel 12
    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "✓ Bootstrap caricato\n";

    // 3. Laravel 12 usa direttamente l'app instance
    $app->boot();
    echo "✓ Application booted\n";

    // 4. Usa Artisan direttamente
    Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "✓ Config cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "✓ Application cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('route:clear');
    echo "✓ Route cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('view:clear');
    echo "✓ View cache cleared\n";

    try {
        Illuminate\Support\Facades\Artisan::call('event:clear');
        echo "✓ Event cache cleared\n";
    } catch (Throwable $e) {
        // Ignora se non esiste
    }

    try {
        Illuminate\Support\Facades\Artisan::call('optimize:clear');
        echo "✓ Optimize cleared\n";
    } catch (Throwable $e) {
        echo '⚠ Optimize clear: '.$e->getMessage()."\n";
    }

    echo "\n=== TUTTO OK ===\n";
    echo "\n⚠️  IMPORTANTE: Elimina questo file dopo l'uso per sicurezza!\n";

} catch (Throwable $e) {
    echo "\n✗ ERRORE: ".$e->getMessage()."\n";
    echo "\nStack trace:\n".$e->getTraceAsString()."\n";

    // Fallback: pulizia manuale dei file
    echo "\n=== TENTATIVO PULIZIA MANUALE ===\n";
    manualCacheClear();
}

// Mostra info aggiuntive
echo "\n=== INFO DEBUG ===\n";
echo 'PHP Version: '.PHP_VERSION."\n";
echo 'Laravel Path: '.realpath(__DIR__.'/../')."\n";
echo 'Storage writable: '.(is_writable(__DIR__.'/../storage') ? 'YES ✓' : 'NO ✗')."\n";
echo 'Bootstrap/cache writable: '.(is_writable(__DIR__.'/../bootstrap/cache') ? 'YES ✓' : 'NO ✗')."\n";

/**
 * Pulizia manuale dei file di cache
 */
function manualCacheClear()
{
    $basePath = realpath(__DIR__.'/../');
    $deleted = 0;

    // Cache directories da pulire
    $cacheDirs = [
        $basePath.'/storage/framework/cache/data',
        $basePath.'/storage/framework/views',
        $basePath.'/bootstrap/cache',
    ];

    // File specifici da eliminare
    $cacheFiles = [
        $basePath.'/bootstrap/cache/config.php',
        $basePath.'/bootstrap/cache/routes-v7.php',
        $basePath.'/bootstrap/cache/events.php',
        $basePath.'/bootstrap/cache/packages.php',
        $basePath.'/bootstrap/cache/services.php',
    ];

    // Elimina file specifici
    foreach ($cacheFiles as $file) {
        if (file_exists($file) && is_writable($file)) {
            if (@unlink($file)) {
                echo '  ✓ Deleted: '.basename($file)."\n";
                $deleted++;
            }
        }
    }

    // Pulisci directory cache
    foreach ($cacheDirs as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            $files = glob($dir.'/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
            echo '  ✓ Cleaned: '.basename($dir)."\n";
        }
    }

    echo "\nTotale file eliminati: $deleted\n";

    if ($deleted > 0) {
        echo "✓ Pulizia manuale completata!\n";
    } else {
        echo "⚠ Nessun file eliminato (controlla i permessi)\n";
    }
}
