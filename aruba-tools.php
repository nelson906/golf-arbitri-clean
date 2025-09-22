<?php
/**
 * 🛠️ ARUBA TOOLS FIXED - Versione corretta per struttura Aruba reale
 * Rileva automaticamente la struttura directory
 *
 *
 */

// Security key
define('TOOLS_SECRET', 'aruba_tools_2024_secure');

session_start();

if (!isset($_SESSION['tools_auth']) && $_GET['key'] !== TOOLS_SECRET) {
    if (isset($_POST['secret']) && $_POST['secret'] === TOOLS_SECRET) {
        $_SESSION['tools_auth'] = true;
    } else {
        showToolsLogin();
        exit;
    }
}

$_SESSION['tools_auth'] = true;

/**
 * 🔍 AUTO-DETECT ARUBA DIRECTORY STRUCTURE
 */
function detectArubaStructure() {
    $currentDir = __DIR__;
    $structure = [];

    // Rileva directory web corrente
    $structure['web_root'] = $currentDir;

    // CORREZIONE: Laravel è DENTRO home/private/ !!!
    $possiblePaths = [
        // WITHIN home directory - QUESTO È QUELLO GIUSTO!
        $currentDir . '/private',                             // /web/htdocs/www.grippa.it/home/private
        // Backup paths
        dirname($currentDir) . '/private',                    // /web/htdocs/www.grippa.it/private
        dirname($currentDir, 2) . '/private',                 // /web/htdocs/private
    ];

    // DEBUG: Aggiungiamo le informazioni per debug
    $structure['searched_paths'] = [];

    foreach ($possiblePaths as $path) {
        $structure['searched_paths'][] = [
            'path' => $path,
            'exists' => is_dir($path),
            'has_artisan' => file_exists($path . '/artisan'),
            'has_vendor' => is_dir($path . '/vendor'),
            'has_env' => file_exists($path . '/.env')
        ];

        if (is_dir($path) && file_exists($path . '/artisan')) {
            $structure['laravel_root'] = $path;
            break;
        }
    }

    return $structure;
}

$structure = detectArubaStructure();
define('LARAVEL_ROOT', $structure['laravel_root'] ?? '');
define('WEB_ROOT', $structure['web_root']);

function showToolsLogin() {
    echo '<!DOCTYPE html><html><head><title>Aruba Tools</title>';
    echo '<style>body{font-family:Arial;text-align:center;padding:50px;background:#f5f5f5;}</style></head><body>';
    echo '<h1>🛠️ Aruba Tools - Accesso</h1>';
    echo '<p>Strumenti per gestire Laravel senza SSH</p>';
    echo '<form method="post"><input type="password" name="secret" placeholder="Access Key" required>';
    echo '<button type="submit">Accedi</button></form>';
    echo '<p><small>Key: aruba_tools_2024_secure</small></p>';
    echo '</body></html>';
}

/**
 * 🔍 SYSTEM DETECTION
 */
function getSystemDetection() {
    global $structure;

    $detection = [
        'current_dir' => __DIR__,
        'web_root' => WEB_ROOT,
        'laravel_root' => LARAVEL_ROOT,
        'laravel_found' => LARAVEL_ROOT && is_dir(LARAVEL_ROOT),
        'artisan_found' => LARAVEL_ROOT && file_exists(LARAVEL_ROOT . '/artisan'),
        'env_found' => LARAVEL_ROOT && file_exists(LARAVEL_ROOT . '/.env'),
        'vendor_found' => LARAVEL_ROOT && is_dir(LARAVEL_ROOT . '/vendor'),
        'structure_type' => 'Unknown'
    ];

    // Determina il tipo di struttura
    if (strpos(__DIR__, '/htdocs/') !== false) {
        $detection['structure_type'] = 'Aruba htdocs';
    } elseif (strpos(__DIR__, '/public_html/') !== false) {
        $detection['structure_type'] = 'Standard public_html';
    } elseif (strpos(__DIR__, '/home/') !== false) {
        $detection['structure_type'] = 'Aruba home';
    }

    // INFORMAZIONI CRUCIALI PER BUILD
    $detection['build_should_go'] = WEB_ROOT . '/build/';
    $detection['build_exists'] = is_dir(WEB_ROOT . '/build/');
    $detection['manifest_should_go'] = WEB_ROOT . '/build/manifest.json';
    $detection['manifest_exists'] = file_exists(WEB_ROOT . '/build/manifest.json');

    return $detection;
}

/**
 * 🔑 GENERATORE APP_KEY
 */
function generateAppKey() {
    $key = base64_encode(random_bytes(32));
    return 'base64:' . $key;
}

/**
 * 📝 AGGIORNA FILE .ENV
 */
function updateEnvFile($key, $value) {
    if (!LARAVEL_ROOT) {
        return false;
    }

    $envPath = LARAVEL_ROOT . '/.env';

    if (!file_exists($envPath)) {
        return false;
    }

    $envContent = file_get_contents($envPath);

    // Se la chiave esiste, aggiornala
    if (preg_match("/^{$key}=.*$/m", $envContent)) {
        $envContent = preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $envContent);
    } else {
        // Se non esiste, aggiungila
        $envContent .= "\n{$key}={$value}";
    }

    return file_put_contents($envPath, $envContent);
}

/**
 * 🗂️ CACHE OPERATIONS
 */
function clearConfigCache() {
    if (!LARAVEL_ROOT) return "❌ Laravel root not found";

    $cacheFile = LARAVEL_ROOT . '/bootstrap/cache/config.php';
    if (file_exists($cacheFile)) {
        if (unlink($cacheFile)) {
            return "✅ Config cache cleared";
        } else {
            return "❌ Failed to delete config cache (permissions?)";
        }
    }
    return "ℹ️ Config cache already clear";
}

function clearRouteCache() {
    if (!LARAVEL_ROOT) return "❌ Laravel root not found";

    $cacheFiles = [
        LARAVEL_ROOT . '/bootstrap/cache/routes-v7.php',
        LARAVEL_ROOT . '/bootstrap/cache/routes.php'
    ];

    $cleared = 0;
    foreach ($cacheFiles as $cacheFile) {
        if (file_exists($cacheFile)) {
            if (unlink($cacheFile)) {
                $cleared++;
            }
        }
    }

    if ($cleared > 0) {
        return "✅ Route cache cleared ($cleared files)";
    }
    return "ℹ️ Route cache already clear";
}

function clearViewCache() {
    if (!LARAVEL_ROOT) return "❌ Laravel root not found";

    $viewCacheDir = LARAVEL_ROOT . '/storage/framework/views';
    if (is_dir($viewCacheDir)) {
        $files = glob($viewCacheDir . '/*');
        $cleared = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
        return "✅ View cache cleared ($cleared files)";
    }
    return "❌ View cache directory not found";
}

/**
 * 🔗 STORAGE LINK - Versione migliorata per Aruba
 */
function createStorageLink() {
    if (!LARAVEL_ROOT) {
        return "❌ Laravel root not found";
    }

    $linkPath = WEB_ROOT . '/storage';
    $targetPath = LARAVEL_ROOT . '/storage/app/public';

    // Verifica che la directory target esista
    if (!is_dir($targetPath)) {
        // Prova a crearla
        if (!mkdir($targetPath, 0755, true)) {
            return "❌ Target directory doesn't exist and cannot be created: $targetPath";
        }
    }

    // Rimuovi link esistente se presente
    if (is_link($linkPath)) {
        unlink($linkPath);
    }

    // Se esiste una directory, rimuovila
    if (is_dir($linkPath) && !is_link($linkPath)) {
        return "❌ Directory already exists at $linkPath (remove manually)";
    }

    // Prova diverse strategie per creare il link
    $strategies = [
        // Strategia 1: Link simbolico standard
        function($link, $target) {
            return symlink($target, $link);
        },
        // Strategia 2: Link relativo
        function($link, $target) {
            $relativePath = '../private/storage/app/public';
            return symlink($relativePath, $link);
        }
    ];

    foreach ($strategies as $i => $strategy) {
        try {
            if ($strategy($linkPath, $targetPath)) {
                return "✅ Storage link created (strategy " . ($i + 1) . "): $linkPath → $targetPath";
            }
        } catch (Exception $e) {
            // Continua con la strategia successiva
        }
    }

    // Se i link simbolici falliscono, suggerisci alternative
    return "❌ Symbolic link failed. Alternative: Create manually via file manager or ask hosting support";
}

/**
 * 🔧 CORREGGI MANIFEST VITE - Versione corretta
 */
function fixViteManifest() {
    // CORREZIONE: Cerca SOLO nella directory corretta
    $manifestPath = WEB_ROOT . '/build/manifest.json';

    if (!file_exists($manifestPath)) {
        return "❌ Manifest file not found: $manifestPath\n\n" .
               "📁 DOVE CARICARE BUILD:\n" .
               "1. Compila localmente: npm run build\n" .
               "2. Carica TUTTA la cartella 'build/' qui: " . WEB_ROOT . "/build/\n" .
               "3. La struttura deve essere:\n" .
               "   " . WEB_ROOT . "/build/manifest.json\n" .
               "   " . WEB_ROOT . "/build/assets/app-xxxxx.js\n" .
               "   " . WEB_ROOT . "/build/assets/app-xxxxx.css";
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);

    if (!$manifest) {
        return "❌ Invalid manifest file at: $manifestPath";
    }

    $fixed = 0;
    $changes = [];

    // Correggi i path nel manifest
    foreach ($manifest as $key => &$entry) {
        if (isset($entry['file'])) {
            $oldFile = $entry['file'];
            // Rimuovi 'build/' dal path se presente
            $entry['file'] = str_replace('build/', '', $entry['file']);
            if ($oldFile !== $entry['file']) {
                $changes[] = "$oldFile → {$entry['file']}";
                $fixed++;
            }
        }

        if (isset($entry['css'])) {
            foreach ($entry['css'] as &$cssFile) {
                $oldCss = $cssFile;
                $cssFile = str_replace('build/', '', $cssFile);
                if ($oldCss !== $cssFile) {
                    $changes[] = "CSS: $oldCss → $cssFile";
                }
            }
        }

        if (isset($entry['imports'])) {
            foreach ($entry['imports'] as &$importFile) {
                $oldImport = $importFile;
                $importFile = str_replace('build/', '', $importFile);
                if ($oldImport !== $importFile) {
                    $changes[] = "Import: $oldImport → $importFile";
                }
            }
        }
    }

    // Salva il manifest corretto
    if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        $result = "✅ Manifest fixed at: $manifestPath\n";
        $result .= "Fixed $fixed entries\n";
        if (!empty($changes)) {
            $result .= "Changes:\n- " . implode("\n- ", array_slice($changes, 0, 5));
            if (count($changes) > 5) {
                $result .= "\n- ... and " . (count($changes) - 5) . " more";
            }
        }
        return $result;
    } else {
        return "❌ Failed to save corrected manifest";
    }
}

/**
 * 📊 SYSTEM STATUS - Versione corretta
 */
function getSystemStatus() {
    $status = [];

    // Laravel directory
    $status['laravel_path'] = LARAVEL_ROOT && is_dir(LARAVEL_ROOT) ? '✅' : '❌';

    // .env file
    $status['env_file'] = LARAVEL_ROOT && file_exists(LARAVEL_ROOT . '/.env') ? '✅' : '❌';

    // Vendor directory
    $status['vendor'] = LARAVEL_ROOT && is_dir(LARAVEL_ROOT . '/vendor') ? '✅' : '❌';

    // Storage writable
    $status['storage_writable'] = LARAVEL_ROOT && is_writable(LARAVEL_ROOT . '/storage') ? '✅' : '❌';

    // Bootstrap cache writable
    $bootstrapCache = LARAVEL_ROOT . '/bootstrap/cache';
    $status['bootstrap_cache'] = LARAVEL_ROOT && is_writable($bootstrapCache) ? '✅' : '❌';

    // Build directory - CORREZIONE: cerca solo nella directory corretta
    $status['build_dir'] = is_dir(WEB_ROOT . '/build') ? '✅' : '❌';

    // Manifest file - CORREZIONE: cerca solo nella posizione corretta
    $status['manifest'] = file_exists(WEB_ROOT . '/build/manifest.json') ? '✅' : '❌';

    // Storage link
    $status['storage_link'] = is_link(WEB_ROOT . '/storage') ? '✅' : '❌';

    return $status;
}

$action = $_GET['action'] ?? 'dashboard';

?>
<!DOCTYPE html>
<html>
<head>
    <title>🛠️ Aruba Tools - Laravel Management (Fixed)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: bold; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }

        .result { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007bff; white-space: pre-line; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }

        .status-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .status-table th, .status-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .status-table th { background: #f8f9fa; }

        .tool-section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .tool-section h3 { margin-top: 0; color: #007bff; }

        .detection-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; font-size: 12px; }

        nav { background: #343a40; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        nav a { color: white; text-decoration: none; margin-right: 20px; padding: 8px 15px; border-radius: 3px; }
        nav a:hover, nav a.active { background: #007bff; }

        .key-display { background: #343a40; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; word-break: break-all; margin: 10px 0; }

        h1 { color: #343a40; text-align: center; }
        .subtitle { text-align: center; color: #6c757d; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Aruba Tools (Fixed)</h1>
        <p class="subtitle">Gestione Laravel senza SSH - Auto-detect Aruba Structure</p>

        <nav>
            <a href="?action=dashboard" <?= $action === 'dashboard' ? 'class="active"' : '' ?>>📊 Dashboard</a>
            <a href="?action=detection" <?= $action === 'detection' ? 'class="active"' : '' ?>>🔍 Detection</a>
            <a href="?action=generate_key" <?= $action === 'generate_key' ? 'class="active"' : '' ?>>🔑 APP_KEY</a>
            <a href="?action=cache" <?= $action === 'cache' ? 'class="active"' : '' ?>>🗂️ Cache</a>
            <a href="?action=storage" <?= $action === 'storage' ? 'class="active"' : '' ?>>🔗 Storage</a>
            <a href="?action=vite" <?= $action === 'vite' ? 'class="active"' : '' ?>>⚡ Vite Fix</a>
        </nav>

<?php

if ($action === 'detection') {
    echo '<h2>🔍 System Detection</h2>';

    $detection = getSystemDetection();

    echo '<div class="detection-box">';
    echo "Current Directory: " . $detection['current_dir'] . "\n";
    echo "Web Root: " . $detection['web_root'] . "\n";
    echo "Laravel Root: " . $detection['laravel_root'] . "\n";
    echo "Laravel Found: " . ($detection['laravel_found'] ? 'YES' : 'NO') . "\n";
    echo "Artisan Found: " . ($detection['artisan_found'] ? 'YES' : 'NO') . "\n";
    echo "Structure Type: " . $detection['structure_type'] . "\n";
    echo "\n--- BUILD INFORMATION ---\n";
    echo "Build Should Go: " . $detection['build_should_go'] . "\n";
    echo "Build Exists: " . ($detection['build_exists'] ? 'YES' : 'NO') . "\n";
    echo "Manifest Should Go: " . $detection['manifest_should_go'] . "\n";
    echo "Manifest Exists: " . ($detection['manifest_exists'] ? 'YES' : 'NO') . "\n";
    echo '</div>';

    // NUOVO: Debug Laravel search
    global $structure;
    if (isset($structure['searched_paths'])) {
        echo '<h3>🔍 Laravel Search Debug</h3>';
        echo '<table class="status-table">';
        echo '<tr><th>Path Cercato</th><th>Esiste?</th><th>artisan?</th><th>vendor?</th><th>.env?</th></tr>';
        foreach ($structure['searched_paths'] as $search) {
            $exists = $search['exists'] ? '✅' : '❌';
            $artisan = $search['has_artisan'] ? '✅' : '❌';
            $vendor = $search['has_vendor'] ? '✅' : '❌';
            $env = $search['has_env'] ? '✅' : '❌';
            echo "<tr><td>{$search['path']}</td><td>$exists</td><td>$artisan</td><td>$vendor</td><td>$env</td></tr>";
        }
        echo '</table>';
    }

    if (!$detection['laravel_found']) {
        echo '<div class="result error">';
        echo '<h4>❌ Laravel non trovato!</h4>';
        echo '<p><strong>Possibili cause:</strong></p>';
        echo '<ul>';
        echo '<li>🗂️ Laravel non è stato caricato nella cartella "private"</li>';
        echo '<li>📁 La cartella "private" non esiste</li>';
        echo '<li>🔧 Laravel non è completo (manca artisan, vendor, etc.)</li>';
        echo '<li>📍 Laravel è in una posizione diversa</li>';
        echo '</ul>';
        echo '<p><strong>Dove dovrebbe essere Laravel:</strong></p>';
        echo '<ul>';
        echo '<li>📂 <strong>/web/htdocs/www.grippa.it/private/</strong> (più probabile)</li>';
        echo '<li>📂 Con i file: artisan, .env, vendor/, app/, etc.</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="result success">✅ Laravel trovato e configurato correttamente</div>';
    }

    if (!$detection['build_exists']) {
        echo '<div class="result warning">';
        echo '<h4>📁 DOVE CARICARE BUILD:</h4>';
        echo '1. Sul tuo computer esegui: <code>npm install && npm run build</code><br>';
        echo '2. Carica TUTTA la cartella <code>build/</code> qui: <strong>' . $detection['build_should_go'] . '</strong><br>';
        echo '3. La struttura finale deve essere:<br>';
        echo '&nbsp;&nbsp;&nbsp;' . $detection['build_should_go'] . 'manifest.json<br>';
        echo '&nbsp;&nbsp;&nbsp;' . $detection['build_should_go'] . 'assets/app-xxxxx.js<br>';
        echo '&nbsp;&nbsp;&nbsp;' . $detection['build_should_go'] . 'assets/app-xxxxx.css<br>';
        echo '</div>';
    } else {
        echo '<div class="result success">✅ Directory build trovata correttamente</div>';
    }
}

elseif ($action === 'dashboard') {
    echo '<h2>📊 System Status</h2>';

    if (!LARAVEL_ROOT) {
        echo '<div class="result error">❌ Laravel non trovato! Vai su "🔍 Detection" per diagnosticare</div>';
    } else {
        $status = getSystemStatus();

        echo '<table class="status-table">';
        echo '<tr><th>Componente</th><th>Status</th><th>Descrizione</th></tr>';
        echo '<tr><td>Laravel Directory</td><td>' . $status['laravel_path'] . '</td><td>' . LARAVEL_ROOT . '</td></tr>';
        echo '<tr><td>File .env</td><td>' . $status['env_file'] . '</td><td>Configurazione ambiente</td></tr>';
        echo '<tr><td>Vendor Directory</td><td>' . $status['vendor'] . '</td><td>Dipendenze Composer</td></tr>';
        echo '<tr><td>Storage Writable</td><td>' . $status['storage_writable'] . '</td><td>Permessi scrittura storage</td></tr>';
        echo '<tr><td>Bootstrap Cache</td><td>' . $status['bootstrap_cache'] . '</td><td>Cache di sistema</td></tr>';
        echo '<tr><td>Build Directory</td><td>' . $status['build_dir'] . '</td><td>Assets Vite compilati</td></tr>';
        echo '<tr><td>Manifest File</td><td>' . $status['manifest'] . '</td><td>Manifest Vite</td></tr>';
        echo '<tr><td>Storage Link</td><td>' . $status['storage_link'] . '</td><td>Link simbolico storage</td></tr>';
        echo '</table>';

        $allGood = !in_array('❌', $status);
        if ($allGood) {
            echo '<div class="result success">🎉 Sistema completamente configurato!</div>';
        } else {
            echo '<div class="result warning">⚠️ Alcuni componenti necessitano configurazione</div>';
        }
    }
}

elseif ($action === 'generate_key') {
    echo '<div class="tool-section">';
    echo '<h3>🔑 Generatore APP_KEY</h3>';

    if (!LARAVEL_ROOT) {
        echo '<div class="result error">❌ Laravel non trovato! Impossibile procedere</div>';
    } else {
        echo '<p>Genera una nuova chiave di crittografia per Laravel</p>';

        if (isset($_POST['generate_key'])) {
            $newKey = generateAppKey();

            echo '<h4>✅ Nuova APP_KEY generata:</h4>';
            echo '<div class="key-display">' . $newKey . '</div>';

            echo '<h4>📝 Aggiorna il file .env:</h4>';
            echo '<p>Copia questa riga nel tuo file <code>.env</code>:</p>';
            echo '<div class="key-display">APP_KEY=' . $newKey . '</div>';

            if (isset($_POST['auto_update']) && $_POST['auto_update'] === '1') {
                $updated = updateEnvFile('APP_KEY', $newKey);
                if ($updated) {
                    echo '<div class="result success">✅ File .env aggiornato automaticamente!</div>';
                } else {
                    echo '<div class="result error">❌ Impossibile aggiornare .env automaticamente</div>';
                }
            }
        } else {
            echo '<form method="post">';
            echo '<label><input type="checkbox" name="auto_update" value="1" checked> Aggiorna automaticamente il file .env</label><br><br>';
            echo '<button type="submit" name="generate_key" class="btn btn-primary">🔑 Genera Nuova Chiave</button>';
            echo '</form>';
        }
    }
    echo '</div>';
}

elseif ($action === 'cache') {
    echo '<div class="tool-section">';
    echo '<h3>🗂️ Gestione Cache</h3>';

    if (!LARAVEL_ROOT) {
        echo '<div class="result error">❌ Laravel non trovato! Impossibile procedere</div>';
    } else {
        echo '<p>Pulisci le cache di Laravel (equivalente ai comandi artisan)</p>';

        if (isset($_POST['clear_all'])) {
            echo '<h4>Risultati pulizia cache:</h4>';
            echo '<div class="result">' . clearConfigCache() . '</div>';
            echo '<div class="result">' . clearRouteCache() . '</div>';
            echo '<div class="result">' . clearViewCache() . '</div>';
        } else {
            echo '<form method="post">';
            echo '<p><strong>Operazioni che verranno eseguite:</strong></p>';
            echo '<ul>';
            echo '<li>✅ Clear config cache (artisan config:clear)</li>';
            echo '<li>✅ Clear route cache (artisan route:clear)</li>';
            echo '<li>✅ Clear view cache (artisan view:clear)</li>';
            echo '</ul>';
            echo '<button type="submit" name="clear_all" class="btn btn-warning">🧹 Pulisci Tutte le Cache</button>';
            echo '</form>';
        }
    }
    echo '</div>';
}

elseif ($action === 'storage') {
    echo '<div class="tool-section">';
    echo '<h3>🔗 Storage Link</h3>';

    if (!LARAVEL_ROOT) {
        echo '<div class="result error">❌ Laravel non trovato! Impossibile procedere</div>';
    } else {
        echo '<p>Crea il link simbolico per lo storage (equivalente ad artisan storage:link)</p>';

        if (isset($_POST['create_link'])) {
            $result = createStorageLink();
            $class = strpos($result, '✅') !== false ? 'success' : 'error';
            echo '<div class="result ' . $class . '">' . $result . '</div>';

            if (strpos($result, '❌') !== false) {
                echo '<div class="result warning">';
                echo '<h4>💡 Alternative per Aruba:</h4>';
                echo '1. <strong>File Manager:</strong> Crea manualmente link simbolico da pannello Aruba<br>';
                echo '2. <strong>Supporto tecnico:</strong> Contatta Aruba per abilitare symlink<br>';
                echo '3. <strong>Workaround:</strong> Copia fisicamente i file invece del link<br>';
                echo '4. <strong>Storage diverso:</strong> Usa cloud storage (S3, etc.)';
                echo '</div>';
            }
        } else {
            echo '<p><strong>Questa operazione creerà:</strong></p>';
            echo '<ul>';
            echo '<li>📁 Link: <code>' . WEB_ROOT . '/storage</code></li>';
            echo '<li>📁 Target: <code>' . LARAVEL_ROOT . '/storage/app/public</code></li>';
            echo '</ul>';
            echo '<form method="post">';
            echo '<button type="submit" name="create_link" class="btn btn-success">🔗 Crea Storage Link</button>';
            echo '</form>';
        }
    }
    echo '</div>';
}

elseif ($action === 'vite') {
    echo '<div class="tool-section">';
    echo '<h3>⚡ Fix Manifest Vite</h3>';
    echo '<p>Corregge i percorsi nel manifest.json per la struttura Aruba</p>';

    if (isset($_POST['fix_manifest'])) {
        $result = fixViteManifest();
        $class = strpos($result, '✅') !== false ? 'success' : 'error';
        echo '<div class="result ' . $class . '">' . $result . '</div>';

        if (strpos($result, '✅') !== false) {
            echo '<h4>🎯 Prossimi passi:</h4>';
            echo '<ol>';
            echo '<li>Verifica che i CSS/JS si carichino sul sito</li>';
            echo '<li>Se ci sono ancora problemi, controlla la console browser</li>';
            echo '<li>Verifica che tutti i file siano stati caricati in /build/</li>';
            echo '</ol>';
        }
    } else {
        echo '<p><strong>Questo tool risolve:</strong></p>';
        echo '<ul>';
        echo '<li>❌ Path errati nel manifest Vite</li>';
        echo '<li>❌ CSS/JS che non si caricano</li>';
        echo '<li>❌ Errori 404 per assets</li>';
        echo '</ul>';

        echo '<p><strong>Cerca manifest SOLO qui:</strong></p>';
        echo '<ul>';
        echo '<li><strong>' . WEB_ROOT . '/build/manifest.json</strong></li>';
        echo '</ul>';

        echo '<p><strong>Se non esiste:</strong></p>';
        echo '<ol>';
        echo '<li>Compila localmente: <code>npm install && npm run build</code></li>';
        echo '<li>Carica la cartella <code>build/</code> completa qui: <strong>' . WEB_ROOT . '/build/</strong></li>';
        echo '</ol>';

        echo '<form method="post">';
        echo '<button type="submit" name="fix_manifest" class="btn btn-primary">⚡ Correggi Manifest Vite</button>';
        echo '</form>';
    }
    echo '</div>';
}

?>

        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #6c757d;">
            <p><strong>Aruba Tools Fixed v1.1</strong> - Auto-detect per tutte le strutture Aruba</p>
            <p style="color: #dc3545; font-weight: bold;">⚠️ ELIMINA questo file dopo l'uso!</p>
        </div>
    </div>
</body>
</html>
