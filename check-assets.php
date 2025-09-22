<?php
echo "<h3>Check Assets Build</h3>";

// 1. Verifica struttura build
$buildDir = __DIR__.'/public/build';
echo "📁 Struttura public/build:<br>";

if (is_dir($buildDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        $relativePath = str_replace($buildDir.'/', '', $file->getPathname());
        $size = $file->isFile() ? filesize($file) : 0;
        echo "• {$relativePath}" . ($size > 0 ? " ({$size} bytes)" : " (directory)") . "<br>";
    }
} else {
    echo "❌ public/build NON esiste<br>";
}

// 2. Test accesso asset principali
$testAssets = [];

// Leggi manifest per ottenere i path reali
$manifestPath = $buildDir.'/manifest.json';
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);

    foreach ($manifest as $source => $built) {
        if (strpos($source, 'app.js') !== false || strpos($source, 'app.css') !== false) {
            $testAssets[] = [
                'source' => $source,
                'built' => $built['file'],
                'path' => $buildDir . '/' . $built['file']
            ];
        }
    }
} else {
    echo "❌ Manifest non trovato per test asset<br>";
}

echo "<br>🧪 Test accesso asset:<br>";
foreach ($testAssets as $asset) {
    if (file_exists($asset['path'])) {
        $size = filesize($asset['path']);
        echo "✅ {$asset['source']}: {$size} bytes<br>";

        // Test accesso via HTTP
        $httpPath = 'https://' . $_SERVER['HTTP_HOST'] . '/build/' . $asset['built'];
        echo "🌐 <a href='{$httpPath}' target='_blank'>Test HTTP</a><br>";
    } else {
        echo "❌ {$asset['source']}: file non trovato<br>";
    }
}

// 3. Verifica vite.config.js o webpack.mix.js
$viteConfig = __DIR__.'/vite.config.js';
$mixConfig = __DIR__.'/webpack.mix.js';

echo "<br>⚙️ Configurazione build:<br>";
if (file_exists($viteConfig)) {
    echo "✅ vite.config.js esiste<br>";
} elseif (file_exists($mixConfig)) {
    echo "✅ webpack.mix.js esiste<br>";
} else {
    echo "⚠️ Nessuna config build trovata<br>";
}

echo "<hr>Check assets completato";
?>
