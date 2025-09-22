<?php

/**
 * 🛠️ LARAVEL ARUBA MANAGER - Toolkit Deploy Universale
 * Script management completo per qualsiasi progetto Laravel su Aruba
 *
 * @version 1.0
 * @author Laravel Aruba Deploy Toolkit
 */

define('MANAGER_SECRET', 'laravel_aruba_manager_2024');

if (!isset($_GET['key']) || $_GET['key'] !== MANAGER_SECRET) {
    die('🔒 Access denied. Use: ?key=' . MANAGER_SECRET);
}

echo '<html><head><title>Laravel Aruba Manager</title><style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;background:#f8fafc;color:#2d3748;}
.container{max-width:800px;margin:0 auto;padding:20px;}
.card{background:white;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);padding:20px;margin:15px 0;}
.ok{background:#f0fff4;border-left:4px solid #38a169;color:#22543d;}
.error{background:#fef5e7;border-left:4px solid #e53e3e;color:#742a2a;}
.warning{background:#fffaf0;border-left:4px solid #ed8936;color:#7b341e;}
.btn{display:inline-block;padding:8px 16px;background:#4299e1;color:white;text-decoration:none;border-radius:6px;margin:5px;border:none;cursor:pointer;font-size:14px;}
.btn:hover{background:#3182ce;}
.btn-success{background:#38a169;}.btn-success:hover{background:#2f855a;}
.btn-warning{background:#ed8936;}.btn-warning:hover{background:#dd6b20;}
.btn-danger{background:#e53e3e;}.btn-danger:hover{background:#c53030;}
input,select{padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;margin:5px 0;width:300px;}
h1{color:#2b6cb0;border-bottom:2px solid #bee3f8;padding-bottom:10px;}
h2{color:#2c5aa0;margin-top:25px;}
ul{line-height:1.6;}li{margin:5px 0;}
.header{text-align:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:30px;border-radius:8px;margin-bottom:20px;}
.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:15px;margin:20px 0;}
</style></head><body>';

echo '<div class="container">';
echo '<div class="header">';
echo '<h1>🛠️ Laravel Aruba Manager</h1>';
echo '<p>Deploy Toolkit - Gestione completa Laravel su Aruba senza SSH</p>';
echo '<p><small>Version 1.0 - <a href="https://github.com/username/laravel-aruba-deploy-toolkit" style="color:#bee3f8;" target="_blank">GitHub Toolkit</a></small></p>';
echo '</div>';

$step = $_GET['step'] ?? 'dashboard';

if ($step === 'dashboard') {
    echo '<div class="card">';
    echo '<h2>📊 Dashboard Sistema</h2>';
    echo '<p>Manager universale per progetti Laravel su hosting Aruba. Toolkit testato per risolvere tutti i problemi comuni del deploy senza SSH.</p>';
    echo '</div>';

    echo '<div class="status-grid">';
    echo '<div class="card">';
    echo '<h3>🔍 Diagnostica</h3>';
    echo '<p>Verifica completa dello stato sistema Laravel</p>';
    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn btn-success">📊 System Check Completo</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>⚙️ Configurazione</h3>';
    echo '<p>Setup chiavi e configurazioni base</p>';
    echo '<a href="?step=generate_key&key=' . MANAGER_SECRET . '" class="btn">🔑 Genera APP_KEY</a><br>';
    echo '<a href="?step=env_helper&key=' . MANAGER_SECRET . '" class="btn">📝 Helper .env</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>🔧 Manutenzione</h3>';
    echo '<p>Pulizia cache e fix problemi comuni</p>';
    echo '<a href="?step=clear_cache&key=' . MANAGER_SECRET . '" class="btn btn-warning">🧹 Clear Cache</a><br>';
    echo '<a href="?step=fix_middleware&key=' . MANAGER_SECRET . '" class="btn">🔧 Fix Middleware</a>';
    echo '<a href="?step=storage_link&key=' . MANAGER_SECRET . '" class="btn">🔗 Storage Link</a>';
    echo '<a href="?step=maintenance&key=' . MANAGER_SECRET . '" class="btn btn-warning">🚧 Modalità Manutenzione</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>📦 Dipendenze</h3>';
    echo '<p>Verifica Sanctum, Pail, Breeze</p>';
    echo '<a href="?step=dependencies&key=' . MANAGER_SECRET . '" class="btn">📦 Check Dependencies</a>';
    echo '</div>';
    echo '</div>';
} elseif ($step === 'system_check') {
    echo '<div class="card">';
    echo '<h2>📊 System Check Completo</h2>';

    $tests = [];

    try {
        // Test 1: APP_KEY
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'APP_KEY=base64:') !== false) {
                $tests['app_key'] = '✅ APP_KEY configurata correttamente';
            } else {
                $tests['app_key'] = '❌ APP_KEY mancante o malformata';
            }
        } else {
            $tests['app_key'] = '❌ File .env non trovato';
        }

        // Test 2: Autoload
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
            $tests['autoload'] = '✅ Autoload Composer OK';

            if (file_exists('bootstrap/app.php')) {
                $tests['bootstrap'] = '✅ Laravel Bootstrap OK';
            } else {
                $tests['bootstrap'] = '❌ Bootstrap Laravel non trovato';
            }
        } else {
            $tests['autoload'] = '❌ Vendor Composer non trovato';
        }

        // Test 3: Dependencies Critical
        if (file_exists('vendor/laravel/sanctum')) {
            $tests['sanctum'] = '✅ Laravel Sanctum presente';
        } else {
            $tests['sanctum'] = '⚠️ Laravel Sanctum non trovato';
        }

        if (file_exists('vendor/laravel/pail')) {
            $tests['pail_vendor'] = '✅ Laravel Pail presente in vendor/';

            try {
                if (class_exists('Laravel\Pail\PailServiceProvider')) {
                    $tests['pail_class'] = '✅ PailServiceProvider caricabile';
                } else {
                    $tests['pail_class'] = '⚠️ PailServiceProvider non caricabile';
                }
            } catch (Exception $e) {
                $tests['pail_class'] = '❌ Errore Pail: ' . $e->getMessage();
            }
        } else {
            $tests['pail_vendor'] = '⚠️ Laravel Pail non trovato in vendor/';
        }

        // Test 4: Middleware Check
        $middlewareDir = 'app/Http/Middleware';
        if (is_dir($middlewareDir)) {
            $middlewareFiles = glob($middlewareDir . '/*.php');
            $tests['middleware_dir'] = '✅ Directory middleware (' . count($middlewareFiles) . ' files)';

            // Cerca middleware personalizzati comuni
            $customMiddleware = [];
            foreach ($middlewareFiles as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($name, ['Authenticate', 'EncryptCookies', 'PreventRequestsDuringMaintenance', 'RedirectIfAuthenticated', 'TrimStrings', 'TrustHosts', 'TrustProxies', 'ValidateSignature', 'VerifyCsrfToken'])) {
                    $customMiddleware[] = $name;
                }
            }

            if (!empty($customMiddleware)) {
                $tests['custom_middleware'] = '✅ Middleware personalizzati: ' . implode(', ', $customMiddleware);
            }
        } else {
            $tests['middleware_dir'] = '❌ Directory middleware mancante';
        }

        // Test 5: Bootstrap middleware registration (Laravel 11)
        if (file_exists('bootstrap/app.php')) {
            $bootstrapContent = file_get_contents('bootstrap/app.php');
            if (strpos($bootstrapContent, '->withMiddleware(') !== false) {
                $tests['middleware_section'] = '✅ Sezione withMiddleware presente';
            } else {
                $tests['middleware_section'] = '⚠️ Sezione withMiddleware mancante (Laravel 11)';
            }
            $tests['bootstrap_file'] = '✅ File bootstrap/app.php presente (Laravel 11)';
        } else {
            $tests['middleware_section'] = '❌ File bootstrap/app.php mancante';
            $tests['bootstrap_file'] = '❌ File bootstrap/app.php mancante';
        }

        // Test 6: Storage & Cache
        $tests['storage'] = is_writable('storage') ? '✅ Storage scrivibile' : '⚠️ Storage non scrivibile';
        $tests['cache_dir'] = (is_dir('bootstrap/cache') && is_writable('bootstrap/cache')) ? '✅ Cache directory OK' : '⚠️ Cache directory problemi';

        // Test 7: Config Cache Status
        $configCache = 'bootstrap/cache/config.php';
        $tests['config_cache'] = file_exists($configCache) ? '⚠️ Config cache presente (potrebbe essere obsoleta)' : '✅ Config cache pulita';
    } catch (Exception $e) {
        $tests['error'] = '❌ Errore: ' . $e->getMessage();
    }

    echo '<div class="ok">';
    echo '<h3>📊 Risultati Test Sistema:</h3>';
    echo '<ul>';
    foreach ($tests as $test => $result) {
        echo '<li>' . $result . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    // Raccomandazioni automatiche
    $hasErrors = false;
    $recommendations = [];

    foreach ($tests as $key => $result) {
        if (strpos($result, '❌') !== false) {
            $hasErrors = true;

            if ($key === 'app_key') {
                $recommendations[] = '🔑 <a href="?step=generate_key&key=' . MANAGER_SECRET . '" class="btn">Genera APP_KEY</a>';
            }
            if ($key === 'sanctum' || $key === 'pail_vendor') {
                $recommendations[] = '📦 <a href="?step=dependencies&key=' . MANAGER_SECRET . '" class="btn">Fix Dependencies</a>';
            }
        }

        if (strpos($result, '⚠️') !== false) {
            if ($key === 'config_cache' || $key === 'pail_class') {
                $recommendations[] = '🧹 <a href="?step=clear_cache&key=' . MANAGER_SECRET . '" class="btn btn-warning">Clear Cache</a>';
            }
            if ($key === 'middleware_section') {
                $recommendations[] = '🔧 <a href="?step=fix_middleware&key=' . MANAGER_SECRET . '" class="btn">Fix Middleware</a>';
            }
        }
    }

    if (!empty($recommendations)) {
        echo '<div class="warning">';
        echo '<h3>💡 Azioni Consigliate:</h3>';
        foreach (array_unique($recommendations) as $rec) {
            echo $rec . ' ';
        }
        echo '</div>';
    }

    if (!$hasErrors) {
        echo '<div class="ok">';
        echo '<h3>🎉 Sistema Laravel OK!</h3>';
        echo '<div style="text-align:center;margin:20px 0;">';
        echo '<a href="/" class="btn btn-success" style="font-size:18px;padding:15px 30px;">🚀 Vai al Sito</a>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'generate_key') {
    echo '<div class="card">';
    echo '<h2>🔑 Generazione APP_KEY</h2>';

    $envFile = __DIR__ . '/.env';
    $envExampleFile = __DIR__ . '/.env.example';

    if (!file_exists($envFile) && file_exists($envExampleFile)) {
        copy($envExampleFile, $envFile);
        echo '<div class="ok">✅ File .env creato da .env.example</div>';
    }

    if (file_exists($envFile)) {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envContent = file_get_contents($envFile);

        if (strpos($envContent, 'APP_KEY=') !== false) {
            $envContent = preg_replace('/APP_KEY=.*/', 'APP_KEY=' . $key, $envContent);
        } else {
            $envContent .= "\nAPP_KEY=" . $key;
        }

        file_put_contents($envFile, $envContent);

        echo '<div class="ok">';
        echo '<h3>✅ APP_KEY generata con successo!</h3>';
        echo '<p><strong>Chiave:</strong> <code>' . htmlspecialchars($key) . '</code></p>';
        echo '<p>✅ File .env aggiornato automaticamente</p>';
        echo '</div>';
    } else {
        echo '<div class="error">❌ File .env non trovato. Crea manualmente il file .env.</div>';
        echo '<div class="warning">';
        echo '<h3>🔧 Soluzione manuale:</h3>';
        echo '<p>Crea file <strong>.env</strong> con questa chiave:</p>';
        echo '<code>APP_KEY=' . 'base64:' . base64_encode(random_bytes(32)) . '</code>';
        echo '</div>';
    }
    echo '</div>';

    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn">🧪 Verifica Sistema</a>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'clear_cache') {
    echo '<div class="card">';
    echo '<h2>🧹 Pulizia Cache Laravel</h2>';

    $cacheCleared = [];
    $errors = [];

    // Config Cache
    $configCache = 'bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        if (unlink($configCache)) {
            $cacheCleared[] = '✅ Config cache eliminata';
        } else {
            $errors[] = '❌ Impossibile eliminare config cache';
        }
    } else {
        $cacheCleared[] = '✅ Config cache non presente (OK)';
    }

    // Routes Cache
    $routesCache = glob('bootstrap/cache/routes-*.php');
    if (!empty($routesCache)) {
        $routesCleared = 0;
        foreach ($routesCache as $file) {
            if (unlink($file)) {
                $routesCleared++;
            }
        }
        $cacheCleared[] = "✅ Routes cache eliminata ($routesCleared files)";
    } else {
        $cacheCleared[] = '✅ Routes cache non presente (OK)';
    }

    // Packages & Services Cache
    $otherCaches = ['bootstrap/cache/packages.php', 'bootstrap/cache/services.php'];
    foreach ($otherCaches as $cache) {
        if (file_exists($cache)) {
            if (unlink($cache)) {
                $cacheCleared[] = '✅ ' . basename($cache) . ' eliminata';
            } else {
                $errors[] = '❌ Impossibile eliminare ' . basename($cache);
            }
        }
    }

    // View Cache
    $viewCacheDir = 'storage/framework/views';
    if (is_dir($viewCacheDir)) {
        $viewFiles = glob($viewCacheDir . '/*.php');
        $viewsCleared = 0;
        foreach ($viewFiles as $file) {
            if (unlink($file)) {
                $viewsCleared++;
            }
        }
        if ($viewsCleared > 0) {
            $cacheCleared[] = "✅ View cache eliminata ($viewsCleared files)";
        } else {
            $cacheCleared[] = '✅ View cache già pulita';
        }
    } else {
        $errors[] = '⚠️ Directory view cache non trovata';
    }

    // Data Cache
    $dataCacheDir = 'storage/framework/cache/data';
    if (is_dir($dataCacheDir)) {
        $dataFiles = glob($dataCacheDir . '/*');
        $dataCleared = 0;
        foreach ($dataFiles as $file) {
            if (is_file($file) && unlink($file)) {
                $dataCleared++;
            }
        }
        if ($dataCleared > 0) {
            $cacheCleared[] = "✅ Data cache eliminata ($dataCleared files)";
        } else {
            $cacheCleared[] = '✅ Data cache già pulita';
        }
    }

    // Session Cache
    $sessionCacheDir = 'storage/framework/sessions';
    if (is_dir($sessionCacheDir)) {
        $sessionFiles = glob($sessionCacheDir . '/*');
        $sessionsCleared = 0;
        foreach ($sessionFiles as $file) {
            if (is_file($file) && unlink($file)) {
                $sessionsCleared++;
            }
        }
        if ($sessionsCleared > 0) {
            $cacheCleared[] = "✅ Session cache eliminata ($sessionsCleared files)";
        } else {
            $cacheCleared[] = '✅ Session cache già pulita';
        }
    }

    echo '<div class="ok">';
    echo '<h3>🧹 Cache Laravel Pulita:</h3>';
    echo '<ul>';
    foreach ($cacheCleared as $result) {
        echo '<li>' . $result . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    if (!empty($errors)) {
        echo '<div class="warning">';
        echo '<h3>⚠️ Alcuni problemi:</h3>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    echo '<div class="warning">';
    echo '<h3>🔄 Importante:</h3>';
    echo '<p><strong>Laravel ricostruirà la cache</strong> al prossimo caricamento con le nuove configurazioni.</p>';
    echo '<p>Il prossimo caricamento potrebbe essere più lento mentre genera le nuove cache.</p>';
    echo '</div>';
    echo '</div>';

    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn">🧪 Verifica Sistema</a>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'fix_middleware') {
    echo '<div class="card">';
    echo '<h2>🔧 Fix Middleware Laravel 11</h2>';

    if (isset($_POST['middleware_name']) && isset($_POST['middleware_class'])) {
        // Registra middleware
        $middlewareName = $_POST['middleware_name'];
        $middlewareClass = $_POST['middleware_class'];

        $bootstrapFile = 'bootstrap/app.php';

        if (!file_exists($bootstrapFile)) {
            echo '<div class="error">❌ File bootstrap/app.php non trovato</div>';
        } else {
            $bootstrapContent = file_get_contents($bootstrapFile);

            if (strpos($bootstrapContent, '->withMiddleware(') !== false) {
                // Sezione già presente, aggiungi alias
                if (strpos($bootstrapContent, $middlewareName) === false) {
                    $pattern = '/(->withMiddleware\(function \(.*?\$middleware.*?\) \{)(.*?)(\}\))/s';
                    if (preg_match($pattern, $bootstrapContent, $matches)) {
                        $before = $matches[1];
                        $middlewareBody = $matches[2];
                        $after = $matches[3];

                        if (strpos($middlewareBody, '$middleware->alias(') !== false) {
                            $newMiddlewareBody = preg_replace(
                                '/(\$middleware->alias\(\[)(.*?)(\]\);)/s',
                                '$1$2' . "\n            '$middlewareName' => \\$middlewareClass::class," . '$3',
                                $middlewareBody
                            );
                        } else {
                            $newAlias = "\n        \$middleware->alias([
            '$middlewareName' => \\$middlewareClass::class,
        ]);";
                            $newMiddlewareBody = $middlewareBody . $newAlias;
                        }

                        $newBootstrapContent = $before . $newMiddlewareBody . $after;
                        file_put_contents($bootstrapFile, $newBootstrapContent);

                        echo '<div class="ok">✅ Middleware registrato: ' . $middlewareName . '</div>';
                    } else {
                        echo '<div class="error">❌ Impossibile modificare sezione withMiddleware</div>';
                    }
                } else {
                    echo '<div class="warning">⚠️ Middleware già registrato</div>';
                }
            } else {
                // Aggiungi sezione completa
                $pattern = '/(->withRouting\(.*?\)\s*)/s';
                if (preg_match($pattern, $bootstrapContent, $matches)) {
                    $newSection = $matches[1] . "->withMiddleware(function (Middleware \$middleware) {
        \$middleware->alias([
            '$middlewareName' => \\$middlewareClass::class,
        ]);
    })
    ";
                    $newBootstrapContent = str_replace($matches[1], $newSection, $bootstrapContent);
                    file_put_contents($bootstrapFile, $newBootstrapContent);

                    echo '<div class="ok">✅ Sezione withMiddleware aggiunta!</div>';
                } else {
                    echo '<div class="error">❌ Impossibile aggiungere sezione middleware</div>';
                }
            }

            // Pulisci cache
            if (file_exists('bootstrap/cache/config.php')) {
                unlink('bootstrap/cache/config.php');
                echo '<div class="ok">✅ Cache config eliminata</div>';
            }
        }

        echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn">🧪 Verifica Sistema</a>';
    } else {
        // Form registrazione middleware
        echo '<div class="warning">';
        echo '<h3>🔧 Registrazione Middleware Laravel 11</h3>';
        echo '<p>Registra middleware personalizzati in bootstrap/app.php con la sintassi corretta per Laravel 11.</p>';
        echo '</div>';

        echo '<form method="post">';
        echo '<label><strong>Nome Alias Middleware:</strong></label><br>';
        echo '<input type="text" name="middleware_name" placeholder="es: referee_or_admin" required><br>';
        echo '<label><strong>Classe Middleware:</strong></label><br>';
        echo '<input type="text" name="middleware_class" placeholder="es: App\\Http\\Middleware\\RefereeOrAdmin" required><br>';
        echo '<button type="submit" class="btn btn-success">📝 Registra Middleware</button>';
        echo '</form>';

        // Mostra middleware esistenti
        $middlewareDir = 'app/Http/Middleware';
        if (is_dir($middlewareDir)) {
            $middlewareFiles = glob($middlewareDir . '/*.php');
            echo '<div class="ok">';
            echo '<h4>📂 Middleware Disponibili:</h4>';
            echo '<ul>';
            foreach ($middlewareFiles as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($name, ['Authenticate', 'EncryptCookies', 'PreventRequestsDuringMaintenance'])) {
                    echo '<li><strong>' . $name . '</strong> → App\\Http\\Middleware\\' . $name . '</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'dependencies') {
    echo '<div class="card">';
    echo '<h2>📦 Dependencies Check</h2>';

    $deps = [
        'laravel/sanctum' => 'vendor/laravel/sanctum',
        'laravel/pail' => 'vendor/laravel/pail',
        'laravel/breeze' => 'vendor/laravel/breeze'
    ];

    echo '<div class="ok">';
    echo '<h3>📊 Status Dipendenze:</h3>';
    echo '<ul>';
    foreach ($deps as $name => $path) {
        if (file_exists($path)) {
            echo '<li>✅ ' . $name . ' presente</li>';
        } else {
            echo '<li>❌ ' . $name . ' mancante</li>';
        }
    }
    echo '</ul>';
    echo '</div>';

    echo '<div class="warning">';
    echo '<h3>💡 Soluzione Dipendenze Mancanti:</h3>';
    echo '<ol>';
    echo '<li>Nel progetto <strong>locale</strong>: <code>composer require [dipendenza]</code></li>';
    echo '<li>Ottimizza vendor: <code>composer install --no-dev --optimize-autoloader</code></li>';
    echo '<li>Crea ZIP: <code>zip -r vendor-updated.zip vendor/</code></li>';
    echo '<li><strong>Carica e sostituisci</strong> vendor/ su Aruba</li>';
    echo '<li>Torna qui e clicca "Clear Cache"</li>';
    echo '</ol>';
    echo '</div>';

    echo '<div class="ok">';
    echo '<h3>🚀 Script Automatico (Toolkit):</h3>';
    echo '<p>Se hai il <strong>Laravel Aruba Deploy Toolkit</strong>:</p>';
    echo '<code>./prepare-vendor.sh</code>';
    echo '<p>Genera automaticamente <code>vendor-aruba-ready.zip</code> ottimizzato!</p>';
    echo '</div>';
    echo '</div>';

    echo '<a href="?step=clear_cache&key=' . MANAGER_SECRET . '" class="btn btn-warning">🧹 Clear Cache</a>';
    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn">🧪 Verifica Sistema</a>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'env_helper') {
    echo '<div class="card">';
    echo '<h2>📝 Helper Configurazione .env</h2>';

    echo '<div class="warning">';
    echo '<h3>⚙️ Template .env per Aruba:</h3>';
    echo '<pre style="background:#f7fafc;padding:15px;border-radius:6px;overflow-x:auto;">
APP_NAME="Il Tuo Progetto Laravel"
APP_ENV=production
APP_KEY=                    # ← Usa "Genera APP_KEY"
APP_DEBUG=false
APP_URL=https://tuodominio.it

# Database Aruba (PERSONALIZZA)
DB_CONNECTION=mysql
DB_HOST=31.11.39.189        # ← Verifica nel pannello Aruba
DB_PORT=3306
DB_DATABASE=               # ← Nome database Aruba
DB_USERNAME=               # ← Username database
DB_PASSWORD=               # ← Password database

# Asset Configuration
ASSET_URL=https://tuodominio.it
VITE_APP_URL=https://tuodominio.it

# Cache & Session
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Log
LOG_CHANNEL=single
LOG_LEVEL=error

# Mail SMTP Aruba
MAIL_MAILER=smtp
MAIL_HOST=smtp.aruba.it
MAIL_PORT=587
MAIL_USERNAME=              # ← La tua email
MAIL_PASSWORD=              # ← Password email
MAIL_FROM_ADDRESS=noreply@tuodominio.it
MAIL_FROM_NAME="${APP_NAME}"
</pre>';
    echo '</div>';

    echo '<div class="ok">';
    echo '<h3>📋 Configurazioni Critiche Aruba:</h3>';
    echo '<ul>';
    echo '<li><strong>APP_ENV=production</strong> - Sempre in produzione</li>';
    echo '<li><strong>APP_DEBUG=false</strong> - Mai true in produzione</li>';
    echo '<li><strong>DB_HOST=31.11.39.189</strong> - Host MySQL Aruba standard</li>';
    echo '<li><strong>CACHE_STORE=database</strong> - Usa database per cache</li>';
    echo '<li><strong>SESSION_DRIVER=database</strong> - Sessioni in database</li>';
    echo '<li><strong>MAIL_HOST=smtp.aruba.it</strong> - SMTP Aruba</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';

    echo '<a href="?step=generate_key&key=' . MANAGER_SECRET . '" class="btn btn-success">🔑 Genera APP_KEY</a>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'storage_link') {
    echo '<div class="card">';
    echo '<h2>🔗 Setup Storage Link</h2>';

    $publicStoragePath = __DIR__ . '/storage';
    $laravelStoragePath = __DIR__ . '/storage/app/public';

    if (isset($_POST['create_link'])) {
        $results = [];
        $errors = [];

        // Crea directory target se non esiste
        if (!is_dir($laravelStoragePath)) {
            if (mkdir($laravelStoragePath, 0755, true)) {
                $results[] = '✅ Directory storage/app/public creata';
            } else {
                $errors[] = '❌ Impossibile creare directory storage/app/public';
            }
        }

        // Crea link
        if (!file_exists($publicStoragePath)) {
            if (symlink($laravelStoragePath, $publicStoragePath)) {
                $results[] = '✅ Storage link creato con successo!';
            } else {
                $errors[] = '❌ Link simbolico fallito. Contatta supporto Aruba per abilitare symlink';
            }
        } else {
            $results[] = '⚠️ Storage link già presente';
        }

        if (!empty($results)) {
            echo '<div class="ok"><ul>';
            foreach ($results as $result) {
                echo '<li>' . $result . '</li>';
            }
            echo '</ul></div>';
        }

        if (!empty($errors)) {
            echo '<div class="error"><ul>';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul></div>';
        }
    } else {
        echo '<div class="warning">';
        echo '<h3>📋 Storage Link Info:</h3>';
        echo '<p><strong>Equivale a:</strong> <code>php artisan storage:link</code></p>';
        echo '<p><strong>Crea link:</strong> /storage → /storage/app/public</p>';
        echo '</div>';

        // Stato attuale
        echo '<div class="ok">';
        echo '<h4>📊 Stato Attuale:</h4>';
        echo '<ul>';
        echo '<li>Directory target: ' . (is_dir($laravelStoragePath) ? '✅ Presente' : '❌ Mancante') . '</li>';
        echo '<li>Link pubblico: ' . (is_link($publicStoragePath) ? '✅ Presente' : '❌ Mancante') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<form method="post">';
        echo '<button type="submit" name="create_link" class="btn btn-success">🔗 Crea Storage Link</button>';
        echo '</form>';
    }

    echo '</div>';
    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn">🧪 Verifica Sistema</a>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
} elseif ($step === 'maintenance') {
    echo '<div class="card">';
    echo '<h2>🚧 Modalità Manutenzione</h2>';

    $maintenanceFile = __DIR__ . '/maintenance.html';

    if (isset($_POST['create_maintenance'])) {
        $maintenanceContent = '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sito in Manutenzione</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .container {
            text-align: center;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 60px 40px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 20px;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        h1 {
            font-size: 2.5em;
            margin: 0 0 20px 0;
            font-weight: 300;
        }
        p {
            font-size: 1.2em;
            margin: 20px 0;
            opacity: 0.9;
            line-height: 1.6;
        }
        .email {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin: 30px 0;
        }
        .back-btn {
            margin-top: 30px;
            padding: 12px 30px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚧</div>
        <h1>Sito in Manutenzione</h1>
        <p>Stiamo effettuando degli aggiornamenti per migliorare la tua esperienza.</p>
        <p>Il sito tornerà online a breve.</p>

        <div class="email">
            <strong>Per urgenze:</strong><br>
            info@tuodominio.it
        </div>

        <p><small>Ci scusiamo per l\'inconveniente</small></p>
    </div>
</body>
</html>';

        if (file_put_contents($maintenanceFile, $maintenanceContent)) {
            echo '<div class="ok">✅ File maintenance.html creato!</div>';
            echo '<div class="warning">';
            echo '<h4>📋 Come usare:</h4>';
            echo '<ol>';
            echo '<li>Rinomina <strong>index.php</strong> in <strong>index.php.backup</strong></li>';
            echo '<li>Rinomina <strong>maintenance.html</strong> in <strong>index.html</strong></li>';
            echo '<li>Il sito mostrerà la pagina di manutenzione</li>';
            echo '<li>Per ripristinare: inverti le operazioni</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="error">❌ Impossibile creare file maintenance.html</div>';
        }
    } elseif (isset($_POST['remove_maintenance'])) {
        if (file_exists($maintenanceFile)) {
            if (unlink($maintenanceFile)) {
                echo '<div class="ok">✅ File maintenance.html eliminato!</div>';
            } else {
                echo '<div class="error">❌ Impossibile eliminare maintenance.html</div>';
            }
        }
    } else {
        echo '<div class="warning">';
        echo '<h3>🚧 Gestione Modalità Manutenzione</h3>';
        echo '<p>Crea una pagina di manutenzione elegante per mostrare durante aggiornamenti o deploy.</p>';
        echo '</div>';

        // Stato file
        echo '<div class="ok">';
        echo '<h4>📊 Stato:</h4>';
        if (file_exists($maintenanceFile)) {
            echo '<p>✅ File maintenance.html presente</p>';
            echo '<p><a href="maintenance.html" target="_blank" class="btn">👁️ Anteprima</a></p>';
        } else {
            echo '<p>❌ File maintenance.html non presente</p>';
        }
        echo '</div>';

        echo '<form method="post" style="margin: 20px 0;">';
        if (!file_exists($maintenanceFile)) {
            echo '<button type="submit" name="create_maintenance" class="btn btn-warning">🚧 Crea Pagina Manutenzione</button>';
        } else {
            echo '<button type="submit" name="remove_maintenance" class="btn btn-danger">🗑️ Elimina Pagina Manutenzione</button>';
        }
        echo '</form>';

        echo '<div class="warning">';
        echo '<h4>💡 Procedura Manutenzione:</h4>';
        echo '<ol>';
        echo '<li><strong>Prima del deploy:</strong> Crea pagina manutenzione</li>';
        echo '<li><strong>Attiva:</strong> Rinomina index.php → index.php.backup</li>';
        echo '<li><strong>Attiva:</strong> Rinomina maintenance.html → index.html</li>';
        echo '<li><strong>Fai deploy/aggiornamenti</strong></li>';
        echo '<li><strong>Ripristina:</strong> Inverti le operazioni</li>';
        echo '</ol>';
        echo '</div>';
    }

    echo '</div>';
    echo '<a href="?step=dashboard&key=' . MANAGER_SECRET . '" class="btn">⬅️ Dashboard</a>';
}
echo '</div>';
echo '<div style="text-align:center;margin-top:40px;padding:20px;background:#f7fafc;border-radius:8px;">';
echo '<p><strong>🛠️ Laravel Aruba Deploy Toolkit v1.0</strong></p>';
echo '<p>Script universale per deploy Laravel su Aruba senza SSH</p>';
echo '<p><a href="https://github.com/username/laravel-aruba-deploy-toolkit" target="_blank" style="color:#4299e1;">📚 Documentazione GitHub</a></p>';
echo '<p style="color:#e53e3e;font-weight:bold;">⚠️ RICORDA: Elimina questo file dopo la configurazione per sicurezza!</p>';
echo '</div>';

echo '</body></html>';
