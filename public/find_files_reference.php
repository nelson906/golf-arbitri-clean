<?php

/**
 * Script per trovare dove viene referenziato il disco 'files'
 */
echo '<pre>';
echo "=== RICERCA RIFERIMENTI A DISCO 'files' ===\n\n";

$basePath = realpath(__DIR__.'/..');
$filesToCheck = [];

// Directory da controllare
$directories = [
    'app/Providers',
    'config',
    'routes',
    'bootstrap',
];

echo "Cercando in:\n";

foreach ($directories as $dir) {
    $fullPath = $basePath.'/'.$dir;

    if (! is_dir($fullPath)) {
        echo "⚠️  Directory $dir non trovata\n";

        continue;
    }

    echo "  - $dir/\n";

    // Scansiona ricorsivamente
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filesToCheck[] = $file->getPathname();
        }
    }
}

echo "\nTotale file da controllare: ".count($filesToCheck)."\n\n";
echo "=== RISULTATI RICERCA ===\n\n";

$found = [];

foreach ($filesToCheck as $file) {
    $content = file_get_contents($file);

    // Cerca pattern che potrebbero referenziare il disco 'files'
    $patterns = [
        "/'files'/" => "Stringa 'files'",
        '/"files"/' => 'Stringa "files"',
        "/Storage::disk\('files'\)/" => "Storage::disk('files')",
        '/disk\(.*files.*\)/' => "disk() con 'files'",
        '/FILESYSTEM.*=.*files/i' => 'FILESYSTEM = files',
        "/'default'\s*=>\s*'files'/" => "default => 'files'",
    ];

    foreach ($patterns as $pattern => $description) {
        if (preg_match($pattern, $content, $matches)) {
            $relativePath = str_replace($basePath.'/', '', $file);

            // Trova la linea esatta
            $lines = file($file);
            foreach ($lines as $lineNum => $line) {
                if (preg_match($pattern, $line)) {
                    $found[] = [
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                        'pattern' => $description,
                        'content' => trim($line),
                    ];
                }
            }
        }
    }
}

if (empty($found)) {
    echo "✓ Nessun riferimento a disco 'files' trovato nei file PHP\n";
    echo "\n⚠️  Questo significa che il problema potrebbe essere:\n";
    echo "  1. Cache non completamente pulita\n";
    echo "  2. Riferimento in un file .env o .env.example\n";
    echo "  3. Riferimento in un pacchetto vendor/\n";
    echo "  4. File di configurazione cached\n";
} else {
    echo '⚠️  TROVATI '.count($found)." RIFERIMENTI:\n\n";

    foreach ($found as $item) {
        echo "📍 {$item['file']}:{$item['line']}\n";
        echo "   Pattern: {$item['pattern']}\n";
        echo "   Codice: {$item['content']}\n\n";
    }
}

// Controlla .env files
echo "\n=== CONTROLLO FILE .ENV ===\n\n";

$envFiles = [
    '.env',
    '.env.example',
    '.env.backup',
];

foreach ($envFiles as $envFile) {
    $envPath = $basePath.'/'.$envFile;

    if (file_exists($envPath)) {
        echo "Controllo $envFile:\n";
        $content = file_get_contents($envPath);

        if (preg_match('/FILESYSTEM.*=.*files/i', $content, $matches)) {
            echo '  ⚠️  TROVATO: '.trim($matches[0])."\n";
            echo "  CORREGGERE IN: FILESYSTEM_DISK=local\n";
        } else {
            echo "  ✓ OK\n";
        }
    }
}

// Controlla cache bootstrap
echo "\n=== CONTROLLO CACHE BOOTSTRAP ===\n\n";

$cacheFiles = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/packages.php',
];

foreach ($cacheFiles as $cacheFile) {
    $cachePath = $basePath.'/'.$cacheFile;

    if (file_exists($cachePath)) {
        echo "⚠️  $cacheFile ESISTE (dovrebbe essere pulito)\n";

        $content = file_get_contents($cachePath);
        if (strpos($content, "'files'") !== false || strpos($content, '"files"') !== false) {
            echo "   ⚠️  Contiene riferimento a 'files'!\n";
            echo "   SOLUZIONE: Eliminare questo file\n";
        }
    } else {
        echo "✓ $cacheFile pulito\n";
    }
}

// Suggerimenti finali
echo "\n=== SOLUZIONI PROPOSTE ===\n\n";

echo "1. PULISCI CACHE AGGRESSIVAMENTE:\n";
echo "   - Elimina TUTTA la directory bootstrap/cache/*\n";
echo "   - Elimina TUTTA la directory storage/framework/cache/*\n";
echo "   - Elimina TUTTA la directory storage/framework/views/*\n\n";

echo "2. CONTROLLA SERVICE PROVIDERS:\n";
echo "   - app/Providers/AppServiceProvider.php\n";
echo "   - app/Providers/EventServiceProvider.php\n";
echo "   Cerca righe con Storage::disk('files') o disk('files')\n\n";

echo "3. VERIFICA .ENV:\n";
echo "   - Deve contenere: FILESYSTEM_DISK=local\n";
echo "   - NON deve contenere: FILESYSTEM_DISK=files\n\n";

echo "4. CERCA IN COMPOSER.JSON:\n";
$composerPath = $basePath.'/composer.json';
if (file_exists($composerPath)) {
    $composer = json_decode(file_get_contents($composerPath), true);
    if (isset($composer['extra']['laravel']['providers'])) {
        echo "   Service providers registrati:\n";
        foreach ($composer['extra']['laravel']['providers'] as $provider) {
            echo "   - $provider\n";
        }
    }
}

echo "\n⚠️  ELIMINA QUESTO FILE DOPO L'USO!\n";
echo '</pre>';
