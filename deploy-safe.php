<?php
require_once __DIR__.'/vendor/autoload.php';

// Solo se Laravel si carica
try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    // Sequenza sicura
    $kernel->call('down');           // Manutenzione
    $kernel->call('cache:clear');    // Pulisci tutto
    $kernel->call('config:clear');
    $kernel->call('route:clear');
    $kernel->call('view:clear');

    // Ricompila solo se necessario
    $kernel->call('config:cache');   // Solo config
    $kernel->call('route:cache');    // Solo route

    $kernel->call('up');             // Fine manutenzione

    echo "✅ Deploy completato in sicurezza";
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage();
}
?>