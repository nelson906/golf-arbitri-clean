<?php

// Prevenzione corruzione cache

echo date('Y-m-d H:i:s').' - Manutenzione preventiva<br>';

// Pulisci cache vecchia (non Laravel)
$dirs = [
    '../storage/framework/cache/data',
    '../storage/framework/sessions',
    '../storage/framework/views',
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir.'/*');
        $cleaned = 0;
        $weekAgo = time() - (7 * 24 * 3600);

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $weekAgo && basename($file) !== '.gitignore') {
                unlink($file);
                $cleaned++;
            }
        }
        echo "🧹 Puliti {$cleaned} file vecchi da ".basename($dir).'<br>';
    }
}

echo '✅ Manutenzione completata<br>';
