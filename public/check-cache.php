<?php
/**
 * Ultra Simple Cache Checker - No Laravel Required
 * CANCELLA DOPO L'USO!
 */

if (!isset($_GET['key']) || $_GET['key'] !== 'golf_arbitri_2025') {
    die('Add ?key=golf_arbitri_2025 to URL');
}

$basePath = dirname(__DIR__);
$cacheDir = $basePath . '/bootstrap/cache';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Cache Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .found { color: red; font-weight: bold; }
        .not-found { color: green; }
        .warning { background: #ffffcc; padding: 10px; border: 1px solid #ff9900; margin: 20px 0; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        .btn { background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Laravel Route Cache Check</h1>
    
    <h2>Cache Directory: <?php echo htmlspecialchars($cacheDir); ?></h2>
    
    <?php
    $cacheFiles = [
        'routes-v7.php' => 'Laravel 7 Route Cache',
        'routes.php' => 'Route Cache',
        'packages.php' => 'Package Cache',
        'services.php' => 'Service Cache',
        'config.php' => 'Config Cache'
    ];
    
    $foundAny = false;
    ?>
    
    <h3>Cache Files Status:</h3>
    <ul>
    <?php foreach ($cacheFiles as $file => $description): ?>
        <?php 
        $fullPath = $cacheDir . '/' . $file;
        $exists = file_exists($fullPath);
        if ($exists) $foundAny = true;
        ?>
        <li class="<?php echo $exists ? 'found' : 'not-found'; ?>">
            <?php echo htmlspecialchars($file); ?> - <?php echo htmlspecialchars($description); ?>
            <?php if ($exists): ?>
                (FOUND - <?php echo number_format(filesize($fullPath)); ?> bytes, 
                Modified: <?php echo date('Y-m-d H:i:s', filemtime($fullPath)); ?>)
            <?php else: ?>
                (Not found)
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
    
    <?php if ($foundAny): ?>
        <div class="warning">
            <h3>⚠️ CACHE FILES FOUND!</h3>
            <p>This is likely causing your 404 error. The route cache is outdated.</p>
            <p><strong>Solution:</strong></p>
            <ol>
                <li>Use <a href="clear-cache.php?key=golf_arbitri_2025" class="btn">Clear Cache Script</a></li>
                <li>Or manually delete the files via FTP from: <code><?php echo htmlspecialchars($cacheDir); ?></code></li>
            </ol>
        </div>
    <?php else: ?>
        <div style="background: #d4edda; padding: 10px; border: 1px solid #c3e6cb;">
            <h3>✅ No cache files found</h3>
            <p>The 404 error might be caused by:</p>
            <ul>
                <li>OPcache (PHP cache) - needs clearing from hosting panel</li>
                <li>File permissions issue</li>
                <li>ModSecurity blocking the URL</li>
                <li>Route file not being loaded correctly</li>
            </ul>
        </div>
    <?php endif; ?>
    
    <h3>Route File Check:</h3>
    <?php
    $routeFile = $basePath . '/routes/admin/notifications.php';
    if (file_exists($routeFile)): ?>
        <p class="not-found">✅ notifications.php exists (<?php echo filesize($routeFile); ?> bytes)</p>
        <?php
        $content = @file_get_contents($routeFile);
        if ($content && strpos($content, 'find-by-tournament') !== false): ?>
            <p class="not-found">✅ Route definition 'find-by-tournament' found in file</p>
        <?php else: ?>
            <p class="found">❌ Route definition 'find-by-tournament' NOT found in file</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="found">❌ notifications.php NOT FOUND at expected location</p>
    <?php endif; ?>
    
    <h3>Server Info:</h3>
    <pre>
PHP Version: <?php echo PHP_VERSION; ?>

Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>

Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>

Script Path: <?php echo __FILE__; ?>
    </pre>
    
    <hr>
    <p><strong style="color: red;">SECURITY WARNING:</strong> Delete this file immediately after use!</p>
</body>
</html>