<?php
// regenerate_autoload.php

echo "Rigenerazione autoload...\n";

$vendorDir = __DIR__ . '/vendor';
$composerDir = $vendorDir . '/composer';

// Rimuovi file autoload corrotti
$files = [
    'autoload_real.php',
    'autoload_static.php',
    'ClassLoader.php',
    'autoload_classmap.php'
];

foreach ($files as $file) {
    $path = $composerDir . '/' . $file;
    if (file_exists($path)) {
        unlink($path);
        echo "Rimosso: $file\n";
    }
}

// Esegui composer dump-autoload via exec (se disponibile)
if (function_exists('exec')) {
    chdir(__DIR__);
    exec('composer dump-autoload --optimize 2>&1', $output, $return);
    echo implode("\n", $output);
} else {
    echo "Exec non disponibile. Devi caricare manualmente vendor/ completa.\n";
}
