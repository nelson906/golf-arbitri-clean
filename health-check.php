<?php
// Controllo salute applicazione
$checks = [
    'Laravel Bootstrap' => false,
    'Database' => false,
    'Cache Writable' => false,
    'Sessions Writable' => false
];

// Test 1: Laravel
try {
    require_once __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $checks['Laravel Bootstrap'] = true;
} catch (Exception $e) {
    // Laravel non si carica
}

// Test 2: Database
if ($checks['Laravel Bootstrap']) {
    try {
        DB::connection()->getPdo();
        $checks['Database'] = true;
    } catch (Exception $e) {
        // Database non raggiungibile
    }
}

// Test 3-4: Cartelle scrivibili
$checks['Cache Writable'] = is_writable(__DIR__.'/storage/framework/cache');
$checks['Sessions Writable'] = is_writable(__DIR__.'/storage/framework/sessions');

// Output
foreach ($checks as $test => $status) {
    echo ($status ? '✅' : '❌') . " {$test}<br>";
}

// Se tutto OK, elimina eventuali cache corrotte
if (all($checks)) {
    // Pulizia preventiva ogni tanto
    if (rand(1, 100) <= 5) { // 5% delle volte
        // Pulisci cache automaticamente
    }
}
?>