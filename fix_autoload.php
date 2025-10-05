<?php
// fix_autoload.php
echo "=== FIX AUTOLOAD LARAVEL 11/12 ===\n";

try {
    require __DIR__.'/vendor/autoload.php';
    echo "✓ Autoload caricato correttamente\n";
} catch (Throwable $e) {
    echo "✗ ERRORE Autoload: " . $e->getMessage() . "\n";
    die();
}

try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    echo "✓ Bootstrap caricato\n";

    // Laravel 11+ usa Artisan facade direttamente
    Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "✓ Config cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "✓ Cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('route:clear');
    echo "✓ Route cache cleared\n";

    Illuminate\Support\Facades\Artisan::call('view:clear');
    echo "✓ View cache cleared\n";

    echo "\n=== TUTTO OK ===\n";

} catch (Throwable $e) {
    echo "✗ ERRORE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
