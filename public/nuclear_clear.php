<?php

/**
 * PULIZIA NUCLEARE - Elimina TUTTA la cache
 * Usa solo se gli altri script non hanno funzionato
 */
echo '<pre>';
echo "=== ☢️  PULIZIA NUCLEARE CACHE ☢️  ===\n\n";
echo "⚠️  ATTENZIONE: Questo script eliminerà TUTTA la cache!\n\n";

$basePath = realpath(__DIR__.'/..');
$deleted = 0;
$errors = 0;

// 1. ELIMINA TUTTO bootstrap/cache
echo "=== BOOTSTRAP CACHE ===\n";
$bootstrapCache = $basePath.'/bootstrap/cache';

if (is_dir($bootstrapCache)) {
    $files = scandir($bootstrapCache);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitignore') {
            continue;
        }

        $fullPath = $bootstrapCache.'/'.$file;
        if (is_file($fullPath)) {
            if (@unlink($fullPath)) {
                echo "✓ Deleted: $file\n";
                $deleted++;
            } else {
                echo "✗ Failed: $file\n";
                $errors++;
            }
        }
    }
} else {
    echo "⚠️  Directory non trovata\n";
}

// 2. ELIMINA TUTTO storage/framework/cache
echo "\n=== FRAMEWORK CACHE ===\n";
$frameworkCache = $basePath.'/storage/framework/cache';

function deleteAllFiles($dir, &$deleted, &$errors)
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitignore') {
            continue;
        }

        $path = $dir.'/'.$item;

        if (is_file($path)) {
            if (@unlink($path)) {
                $deleted++;
            } else {
                $errors++;
            }
        } elseif (is_dir($path)) {
            deleteAllFiles($path, $deleted, $errors);
            @rmdir($path);
        }
    }
}

deleteAllFiles($frameworkCache, $deleted, $errors);
echo "✓ Cleaned framework cache\n";

// 3. ELIMINA TUTTO storage/framework/views
echo "\n=== COMPILED VIEWS ===\n";
$views = $basePath.'/storage/framework/views';
deleteAllFiles($views, $deleted, $errors);
echo "✓ Cleaned compiled views\n";

// 4. ELIMINA TUTTO storage/framework/sessions
echo "\n=== SESSIONS ===\n";
$sessions = $basePath.'/storage/framework/sessions';
deleteAllFiles($sessions, $deleted, $errors);
echo "✓ Cleaned sessions\n";

// 5. ELIMINA composer cache se esiste
echo "\n=== COMPOSER CACHE ===\n";
$composerCache = $basePath.'/vendor/composer';
if (is_dir($composerCache)) {
    $composerFiles = ['autoload_classmap.php', 'autoload_files.php', 'autoload_psr4.php', 'autoload_static.php'];
    // NON eliminare questi, ma segnala
    echo "⚠️  Composer cache presente (non eliminato)\n";
}

// 6. ELIMINA config cache specifici che potrebbero esistere
echo "\n=== CONFIG CACHE SPECIFICI ===\n";
$specificCaches = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/routes.php',
    'bootstrap/cache/routes-v7.php',
    'bootstrap/cache/events.php',
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/compiled.php',
];

foreach ($specificCaches as $cache) {
    $path = $basePath.'/'.$cache;
    if (file_exists($path)) {
        if (@unlink($path)) {
            echo '✓ Deleted: '.basename($cache)."\n";
            $deleted++;
        } else {
            echo '✗ Failed: '.basename($cache)."\n";
            $errors++;
        }
    }
}

// RIEPILOGO
echo "\n=== RIEPILOGO ===\n";
echo "File eliminati: $deleted\n";
echo "Errori: $errors\n";

if ($deleted > 0) {
    echo "\n✓✓✓ PULIZIA NUCLEARE COMPLETATA! ✓✓✓\n";
} else {
    echo "\n⚠️  Nessun file eliminato\n";
}

// VERIFICA FINALE
echo "\n=== VERIFICA FINALE ===\n";

$checkDirs = [
    'bootstrap/cache' => 'Bootstrap Cache',
    'storage/framework/cache/data' => 'Framework Cache',
    'storage/framework/views' => 'Compiled Views',
];

foreach ($checkDirs as $dir => $name) {
    $fullPath = $basePath.'/'.$dir;
    if (is_dir($fullPath)) {
        $files = array_diff(scandir($fullPath), ['.', '..', '.gitignore']);
        $count = count($files);

        if ($count === 0) {
            echo "✓ $name: PULITO\n";
        } else {
            echo "⚠️  $name: $count file rimasti\n";
        }
    }
}

echo "\n=== PROSSIMI PASSI ===\n";
echo "1. Ricarica il sito\n";
echo "2. Laravel rigenererà la cache automaticamente\n";
echo "3. Se ANCORA non funziona:\n";
echo "   - Esegui find_files_reference.php per trovare dove è 'files'\n";
echo "   - Controlla il file trovato e correggilo\n";
echo "4. ELIMINA TUTTI gli script di debug dalla /public/\n";

echo "\n⚠️  ELIMINA QUESTO FILE DOPO L'USO!\n";
echo '</pre>';
