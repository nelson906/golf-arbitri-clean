<?php
/**
 * 🔧 SETUP LARAVEL SENZA SSH
 * Questo script sostituisce i comandi artisan richiesti
 * Carica questo file su Aruba e visitalo via browser
 * Carica questo file su Aruba e visitalo via browser
 * Usa: ?key=laravel_setup_2024_secure per accedere
 */

define('SETUP_SECRET', 'laravel_setup_2024_secure');

if (!isset($_GET['key']) || $_GET['key'] !== SETUP_SECRET) {
    die('Access denied. Use: ?key=' . SETUP_SECRET);
}

echo '<html><head><title>Laravel Setup No-SSH</title><style>
body{font-family:Arial;margin:20px;background:#f5f5f5;}
.ok{background:#d4edda;padding:15px;margin:10px;border-left:4px solid #28a745;border-radius:5px;}
.error{background:#f8d7da;padding:15px;margin:10px;border-left:4px solid #dc3545;border-radius:5px;}
.warning{background:#fff3cd;padding:15px;margin:10px;border-left:4px solid #ffc107;border-radius:5px;}
.btn{background:#007bff;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;margin:5px;text-decoration:none;display:inline-block;}
</style></head><body>';

echo '<h1>🔧 Laravel Setup senza SSH</h1>';

$step = $_GET['step'] ?? 'welcome';

if ($step === 'welcome') {
    echo '<div class="ok">';
    echo '<h2>✅ Setup Laravel automatico</h2>';
    echo '<p>Questo script sostituisce i comandi SSH richiesti da Softaculous:</p>';
    echo '<ul>';
    echo '<li>✅ <code>php artisan key:generate</code> → Genera APP_KEY</li>';
    echo '<li>✅ <code>php artisan ui:auth</code> → Configurazione auth (se necessaria)</li>';
    echo '</ul>';
    echo '</div>';
    echo '<a href="?step=generate_key&key=' . SETUP_SECRET . '" class="btn">🔑 Genera APP_KEY</a>';
}

elseif ($step === 'generate_key') {
    echo '<h2>🔑 Generazione APP_KEY</h2>';

    // Trova il file .env
    $envFile = __DIR__ . '/.env';
    $envExampleFile = __DIR__ . '/.env.example';

    if (!file_exists($envFile) && file_exists($envExampleFile)) {
        copy($envExampleFile, $envFile);
        echo '<div class="ok">✅ File .env creato da .env.example</div>';
    }

    if (file_exists($envFile)) {
        // Genera chiave Laravel (32 bytes random, base64 encoded)
        $key = 'base64:' . base64_encode(random_bytes(32));

        // Leggi .env esistente
        $envContent = file_get_contents($envFile);

        // Sostituisci o aggiungi APP_KEY
        if (strpos($envContent, 'APP_KEY=') !== false) {
            $envContent = preg_replace('/APP_KEY=.*/', 'APP_KEY=' . $key, $envContent);
        } else {
            $envContent .= "\nAPP_KEY=" . $key;
        }

        // Salva .env aggiornato
        file_put_contents($envFile, $envContent);

        echo '<div class="ok">';
        echo '<h3>✅ APP_KEY generata con successo!</h3>';
        echo '<p><strong>Chiave:</strong> <code>' . htmlspecialchars($key) . '</code></p>';
        echo '<p>✅ File .env aggiornato automaticamente</p>';
        echo '</div>';

        echo '<a href="?step=check_auth&key=' . SETUP_SECRET . '" class="btn">➡️ Controllo Auth</a>';

    } else {
        echo '<div class="error">❌ File .env non trovato. Crea manualmente il file .env.</div>';
        echo '<div class="warning">';
        echo '<h3>🔧 Soluzione manuale:</h3>';
        echo '<p>Crea file <strong>.env</strong> con questa chiave:</p>';
        echo '<code>APP_KEY=' . 'base64:' . base64_encode(random_bytes(32)) . '</code>';
        echo '</div>';
    }
}

elseif ($step === 'check_auth') {
    echo '<h2>🔐 Controllo Sistema Autenticazione</h2>';

    // Controlla se il progetto ha già auth configurato
    $authFiles = [
        'app/Http/Controllers/Auth/LoginController.php',
        'resources/views/auth/login.blade.php',
        'routes/auth.php'
    ];

    $hasAuth = false;
    foreach ($authFiles as $file) {
        if (file_exists($file)) {
            $hasAuth = true;
            break;
        }
    }

    if ($hasAuth) {
        echo '<div class="ok">';
        echo '<h3>✅ Sistema autenticazione già presente!</h3>';
        echo '<p>Il progetto ha già il sistema di autenticazione configurato.</p>';
        echo '<p><strong>Non serve</strong> il comando <code>php artisan ui:auth</code></p>';
        echo '</div>';
    } else {
        echo '<div class="warning">';
        echo '<h3>⚠️ Sistema autenticazione non trovato</h3>';
        echo '<p>Se il tuo progetto ha bisogno di login/registrazione, dovrai configurarlo manualmente.</p>';
        echo '</div>';

        echo '<div class="ok">';
        echo '<h3>💡 Alternative al comando ui:auth:</h3>';
        echo '<ul>';
        echo '<li>✅ <strong>Laravel Breeze</strong> - Più moderno di ui:auth</li>';
        echo '<li>✅ <strong>Sistema custom</strong> - Se già presente nel progetto</li>';
        echo '<li>✅ <strong>Skip</strong> - Se non serve autenticazione</li>';
        echo '</ul>';
        echo '</div>';
    }

    echo '<a href="?step=clear_cache&key=' . SETUP_SECRET . '" class="btn">🧹 Clear Cache</a>';
}

elseif ($step === 'clear_cache') {
    echo '<h2>🧹 Pulizia Cache Laravel</h2>';

    $cacheCleared = [];
    $errors = [];

    // 1. Config Cache
    $configCache = 'bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        if (unlink($configCache)) {
            $cacheCleared[] = '✅ Config cache eliminata (bootstrap/cache/config.php)';
        } else {
            $errors[] = '❌ Impossibile eliminare config cache';
        }
    } else {
        $cacheCleared[] = '✅ Config cache non presente (OK)';
    }

    // 2. Routes Cache
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

    // 3. Packages Cache
    $packagesCache = 'bootstrap/cache/packages.php';
    if (file_exists($packagesCache)) {
        if (unlink($packagesCache)) {
            $cacheCleared[] = '✅ Packages cache eliminata';
        } else {
            $errors[] = '❌ Impossibile eliminare packages cache';
        }
    } else {
        $cacheCleared[] = '✅ Packages cache non presente (OK)';
    }

    // 4. Services Cache
    $servicesCache = 'bootstrap/cache/services.php';
    if (file_exists($servicesCache)) {
        if (unlink($servicesCache)) {
            $cacheCleared[] = '✅ Services cache eliminata';
        } else {
            $errors[] = '❌ Impossibile eliminare services cache';
        }
    } else {
        $cacheCleared[] = '✅ Services cache non presente (OK)';
    }

    // 5. View Cache
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

    // 6. Data Cache
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

    // 7. Session Cache
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

    // Risultati
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
    echo '<p><strong>Adesso Laravel ricostruirà la cache con le nuove configurazioni</strong> (incluso Pail).</p>';
    echo '<p>Il prossimo caricamento potrebbe essere un po\' più lento mentre genera le nuove cache.</p>';
    echo '</div>';

    echo '<a href="?step=fix_autoloader&key=' . SETUP_SECRET . '" class="btn" style="background:#ffc107;">🔧 Fix Autoloader</a>';
    echo '<a href="?step=clear_cache&key=' . SETUP_SECRET . '" class="btn">🧹 Clear Cache</a>';
}

elseif ($step === 'fix_autoloader') {
    echo '<h2>🔧 Fix Autoloader - Risolvi PailServiceProvider</h2>';

    $fixes = [];
    $problems = [];

    // 1. Verifica composer.json
    if (file_exists('composer.json')) {
        $composerContent = file_get_contents('composer.json');
        $composerData = json_decode($composerContent, true);

        if ($composerData) {
            $fixes[] = '✅ composer.json leggibile';

            // Controlla se Pail è nelle dipendenze
            $hasPail = false;
            if (isset($composerData['require']['laravel/pail']) || isset($composerData['require-dev']['laravel/pail'])) {
                $hasPail = true;
                $fixes[] = '✅ Laravel Pail trovato in composer.json';
            } else {
                $problems[] = '❌ Laravel Pail NON trovato in composer.json';
            }
        } else {
            $problems[] = '❌ composer.json corrotto';
        }
    } else {
        $problems[] = '❌ composer.json mancante';
    }

    // 2. Verifica composer.lock
    if (file_exists('composer.lock')) {
        $fixes[] = '✅ composer.lock presente';
    } else {
        $problems[] = '❌ composer.lock mancante';
    }

    // 3. Verifica autoload file
    if (file_exists('vendor/composer/autoload_classmap.php')) {
        $autoloadClassmap = include 'vendor/composer/autoload_classmap.php';
        if (isset($autoloadClassmap['Laravel\\Pail\\PailServiceProvider'])) {
            $fixes[] = '✅ PailServiceProvider nell\'autoloader classmap';
        } else {
            $problems[] = '❌ PailServiceProvider NON nell\'autoloader classmap';
        }
    }

    // 4. Verifica file PailServiceProvider
    $pailProviderPath = 'vendor/laravel/pail/src/PailServiceProvider.php';
    if (file_exists($pailProviderPath)) {
        $fixes[] = '✅ PailServiceProvider.php fisicamente presente';

        // Controlla contenuto
        $pailContent = file_get_contents($pailProviderPath);
        if (strpos($pailContent, 'class PailServiceProvider') !== false) {
            $fixes[] = '✅ Classe PailServiceProvider definita nel file';
        } else {
            $problems[] = '❌ Classe PailServiceProvider non trovata nel file';
        }
    } else {
        $problems[] = '❌ File PailServiceProvider.php mancante';
    }

    // 5. Verifica dove è configurato Pail
    $configFiles = ['bootstrap/app.php', 'config/app.php'];
    $pailConfigured = false;

    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if (strpos($content, 'Pail') !== false && strpos($content, '//') === false) {
                $pailConfigured = true;
                $fixes[] = "✅ Pail configurato in $configFile";

                // Mostra la linea specifica
                $lines = explode("\n", $content);
                foreach ($lines as $num => $line) {
                    if (strpos($line, 'Pail') !== false && strpos($line, '//') === false) {
                        $fixes[] = "📋 Linea " . ($num + 1) . ": " . htmlspecialchars(trim($line));
                    }
                }
            }
        }
    }

    if (!$pailConfigured) {
        $problems[] = '❌ Pail non configurato in bootstrap/app.php o config/app.php';
    }

    // Mostra risultati
    if (!empty($fixes)) {
        echo '<div class="ok">';
        echo '<h3>✅ Situazione Corretta:</h3>';
        echo '<ul>';
        foreach ($fixes as $fix) {
            echo '<li>' . $fix . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    if (!empty($problems)) {
        echo '<div class="error">';
        echo '<h3>❌ Problemi Identificati:</h3>';
        echo '<ul>';
        foreach ($problems as $problem) {
            echo '<li>' . $problem . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // 6. Soluzioni
    echo '<div class="warning">';
    echo '<h3>🔧 Soluzioni Disponibili:</h3>';

    if (in_array('❌ Laravel Pail NON trovato in composer.json', $problems)) {
        echo '<h4>PROBLEMA: Pail non in composer.json</h4>';
        echo '<p><strong>CAUSA:</strong> Hai caricato vendor/ ma non il composer.json aggiornato</p>';
        echo '<p><strong>SOLUZIONE:</strong> Carica composer.json e composer.lock dal progetto locale</p>';
    }

    if (in_array('❌ PailServiceProvider NON nell\'autoloader classmap', $problems)) {
        echo '<h4>PROBLEMA: Autoloader non aggiornato</h4>';
        echo '<p><strong>CAUSA:</strong> Composer autoloader non riconosce le nuove dipendenze</p>';
        echo '<p><strong>SOLUZIONE:</strong> Serve rigenerare autoloader (impossibile senza SSH)</p>';
    }

    if ($pailConfigured && !empty($problems)) {
        echo '<h4>ALTERNATIVA: Disabilita temporaneamente Pail</h4>';
        echo '<p>Se Pail non è essenziale, puoi commentarlo nella configurazione.</p>';
        echo '<a href="?step=disable_pail&key=' . SETUP_SECRET . '" class="btn" style="background:#dc3545;">⚠️ Disabilita Pail Temporaneo</a>';
    }

    echo '</div>';

    echo '<a href="?step=final_check&key=' . SETUP_SECRET . '" class="btn">🧪 Ricontrolla</a>';
}

elseif ($step === 'disable_pail') {
    echo '<h2>⚠️ Disabilita Pail Temporaneamente</h2>';

    $disabledFiles = [];

    // Cerca e commenta Pail in bootstrap/app.php
    if (file_exists('bootstrap/app.php')) {
        $content = file_get_contents('bootstrap/app.php');
        $originalContent = $content;

        // Commenta riferimenti a Pail
        $content = preg_replace('/^(\s*)(.*Pail.*)(;?)$/m', '$1// $2$3 // TEMPORANEAMENTE DISABILITATO', $content);

        if ($content !== $originalContent) {
            file_put_contents('bootstrap/app.php', $content);
            $disabledFiles[] = '✅ Pail commentato in bootstrap/app.php';
        }
    }

    // Cerca e commenta Pail in config/app.php
    if (file_exists('config/app.php')) {
        $content = file_get_contents('config/app.php');
        $originalContent = $content;

        // Commenta provider Pail
        $content = preg_replace('/^(\s*)(.*PailServiceProvider.*)(,?)$/m', '$1// $2$3 // TEMPORANEAMENTE DISABILITATO', $content);

        if ($content !== $originalContent) {
            file_put_contents('config/app.php', $content);
            $disabledFiles[] = '✅ PailServiceProvider commentato in config/app.php';
        }
    }

    // Pulisci cache dopo le modifiche
    $configCache = 'bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        unlink($configCache);
        $disabledFiles[] = '✅ Cache config eliminata';
    }

    if (!empty($disabledFiles)) {
        echo '<div class="warning">';
        echo '<h3>⚠️ Pail Disabilitato Temporaneamente:</h3>';
        echo '<ul>';
        foreach ($disabledFiles as $action) {
            echo '<li>' . $action . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '<div class="ok">';
        echo '<h3>📋 Risultato:</h3>';
        echo '<p>✅ Laravel dovrebbe ora funzionare senza errori Pail</p>';
        echo '<p>⚠️ <strong>Funzionalità logging avanzato disabilitata</strong></p>';
        echo '<p>💡 Per riabilitare: carica composer.json/composer.lock aggiornati dal locale</p>';
        echo '</div>';

    } else {
        echo '<div class="warning">';
        echo '<p>⚠️ Nessun riferimento Pail trovato da commentare.</p>';
        echo '</div>';
    }

    echo '<a href="?step=final_check&key=' . SETUP_SECRET . '" class="btn">🧪 Test Finale</a>';
}

elseif ($step === 'final_check') {
    echo '<h2>🧪 Test Sistema Laravel</h2>';

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

            // Test 3: Laravel Bootstrap
            if (file_exists('bootstrap/app.php')) {
                $tests['bootstrap'] = '✅ Laravel Bootstrap OK';
            } else {
                $tests['bootstrap'] = '❌ Bootstrap Laravel non trovato';
            }
        } else {
            $tests['autoload'] = '❌ Vendor Composer non trovato';
        }

        // Test 4: Storage permissions
        $storageWritable = is_writable('storage');
        $tests['storage'] = $storageWritable ? '✅ Storage scrivibile' : '⚠️ Storage non scrivibile';

        // Test 5: Cache directories
        $cacheDir = 'bootstrap/cache';
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $tests['cache'] = '✅ Cache directory OK';
        } else {
            $tests['cache'] = '⚠️ Cache directory non scrivibile';
        }

        // Test 6: Pail Dependency (dopo vendor upload)
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

        // Test 7: Sanctum Dependency
        if (file_exists('vendor/laravel/sanctum')) {
            $tests['sanctum_vendor'] = '✅ Laravel Sanctum presente in vendor/';
        } else {
            $tests['sanctum_vendor'] = '⚠️ Laravel Sanctum non trovato in vendor/';
        }

        // Test 8: Config Cache Status
        $configCache = 'bootstrap/cache/config.php';
        if (file_exists($configCache)) {
            $tests['config_cache'] = '⚠️ Config cache presente (potrebbe essere obsoleta)';
        } else {
            $tests['config_cache'] = '✅ Config cache pulita';
        }

        // Test 9: Custom Middleware Check (LARAVEL 11)
        $middlewareErrors = [];

        // Controlla se esistono middleware personalizzati
        $middlewareDir = 'app/Http/Middleware';
        if (is_dir($middlewareDir)) {
            $middlewareFiles = glob($middlewareDir . '/*.php');
            $tests['middleware_dir'] = '✅ Directory middleware presente (' . count($middlewareFiles) . ' files)';

            // Cerca specificamente referee_or_admin
            $refereeMiddleware = null;
            foreach ($middlewareFiles as $file) {
                $content = file_get_contents($file);
                if (strpos($content, 'referee_or_admin') !== false ||
                    strpos(basename($file), 'RefereeOrAdmin') !== false ||
                    strpos(basename($file), 'referee_or_admin') !== false) {
                    $refereeMiddleware = $file;
                    break;
                }
            }

            if ($refereeMiddleware) {
                $tests['referee_middleware'] = '✅ Middleware referee_or_admin trovato: ' . basename($refereeMiddleware);
            } else {
                $tests['referee_middleware'] = '❌ Middleware referee_or_admin NON trovato';
            }
        } else {
            $tests['middleware_dir'] = '❌ Directory middleware mancante';
        }

        // Controlla bootstrap/app.php per registrazione middleware (LARAVEL 11)
        $bootstrapFile = 'bootstrap/app.php';
        if (file_exists($bootstrapFile)) {
            $bootstrapContent = file_get_contents($bootstrapFile);
            if (strpos($bootstrapContent, 'referee_or_admin') !== false) {
                $tests['middleware_registered'] = '✅ Middleware referee_or_admin registrato in bootstrap/app.php';
            } else {
                $tests['middleware_registered'] = '❌ Middleware referee_or_admin NON registrato in bootstrap/app.php';
            }
            $tests['bootstrap_file'] = '✅ File bootstrap/app.php presente (Laravel 11)';
        } else {
            $tests['middleware_registered'] = '❌ File bootstrap/app.php mancante';
            $tests['bootstrap_file'] = '❌ File bootstrap/app.php mancante';
        }

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

    $hasErrors = false;
    foreach ($tests as $result) {
        if (strpos($result, '❌') !== false) {
            $hasErrors = true;
            break;
        }
    }

    if (!$hasErrors) {
        echo '<div class="ok">';
        echo '<h3>🎉 Laravel configurato correttamente!</h3>';
        echo '<p><strong>Il sistema è pronto per il tuo progetto!</strong></p>';
        echo '</div>';

        echo '<div class="warning">';
        echo '<h3>🗑️ Ultima cosa:</h3>';
        echo '<p><strong>ELIMINA questo file di setup</strong> per sicurezza!</p>';
        echo '</div>';

        echo '<div style="text-align:center;margin:30px 0;">';
        echo '<a href="/" class="btn" style="background:#28a745;font-size:18px;">🚀 Vai al Sito Laravel</a>';
        echo '</div>';

    } else {
        echo '<div class="error">';
        echo '<p>⚠️ Alcuni problemi rilevati. Controlla la configurazione.</p>';
        echo '</div>';
    }
}

echo '<div style="margin-top:40px;padding:15px;background:#e9ecef;border-radius:5px;text-align:center;">';
echo '<p><strong>Laravel Setup No-SSH v1.0</strong> - Sostituto comandi artisan</p>';
echo '<p style="color:#dc3545;">⚠️ <strong>ELIMINA questo file dopo l\'uso!</strong></p>';
echo '</div>';

echo '</body></html>';
?>
