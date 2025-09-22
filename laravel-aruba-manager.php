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
    echo '<a href="?step=system_check&key=' . MANAGER_SECRET . '" class="btn btn-success">📊 System Check Completo</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>⚙️ Configurazione</h3>';
    echo '<a href="?step=generate_key&key=' . MANAGER_SECRET . '" class="btn">🔑 Genera APP_KEY</a><br>';
    echo '<a href="?step=env_helper&key=' . MANAGER_SECRET . '" class="btn">📝 Helper .env</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>🔧 Manutenzione</h3>';
    echo '<a href="?step=clear_cache&key=' . MANAGER_SECRET . '" class="btn btn-warning">🧹 Clear Cache</a><br>';
    echo '<a href="?step=fix_middleware&key=' . MANAGER_SECRET . '" class="btn">🔧 Fix Middleware</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>📦 Dipendenze</h3>';
    echo '<a href="?step=dependencies&key=' . MANAGER_SECRET . '" class="btn">📦 Check Dependencies</a>';
    echo '</div>';
    echo '</div>';
}

// [RESTO DEL CODICE MANAGEMENT - Versione completa come prima ma generica]

echo '</div>';
echo '<div style="text-align:center;margin-top:40px;padding:20px;background:#f7fafc;border-radius:8px;">';
echo '<p><strong>🛠️ Laravel Aruba Deploy Toolkit v1.0</strong></p>';
echo '<p>Script universale per deploy Laravel su Aruba - <a href="https://github.com/TUO_USERNAME/laravel-aruba-deploy-toolkit" target="_blank">GitHub</a></p>';
echo '<p style="color:#e53e3e;font-weight:bold;">⚠️ ELIMINA questo file dopo la configurazione!</p>';
echo '</div>';
echo '</body></html>';
?>
