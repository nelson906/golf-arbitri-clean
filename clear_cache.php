<?php
require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

try {
    // Pulisci cache
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    
    echo "Cache pulita con successo";
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
?>