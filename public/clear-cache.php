<?php

/**
 * Clear Laravel Cache on Aruba without SSH
 * CANCELLA QUESTO FILE DOPO L'USO!
 */

// Security check - cambia questa chiave!
if (! isset($_GET['key']) || $_GET['key'] !== 'golf_arbitri_2025') {
    http_response_code(401);
    exit('<h1>Unauthorized</h1><p>Add ?key=golf_arbitri_2025 to URL</p>');
}

// Get base path
$basePath = dirname(__DIR__);
$bootstrapCache = $basePath.'/bootstrap/cache';

echo '<h1>Laravel Cache Cleaner for Aruba</h1>';
echo '<pre>';

// Files to delete
$filesToDelete = [
    'routes-v7.php',
    'routes.php',
    'packages.php',
    'services.php',
    'events.scanned.php',
    'config.php',
];

$deletedCount = 0;

echo "Bootstrap cache path: $bootstrapCache\n\n";

foreach ($filesToDelete as $file) {
    $fullPath = $bootstrapCache.'/'.$file;

    if (file_exists($fullPath)) {
        echo "Found: $file";

        if (@unlink($fullPath)) {
            echo " - DELETED ✓\n";
            $deletedCount++;
        } else {
            echo " - FAILED TO DELETE ✗\n";
            // Try alternative method
            @file_put_contents($fullPath, '<?php return [];');
            if (filesize($fullPath) < 20) {
                echo "  -> Cleared content instead ✓\n";
                $deletedCount++;
            }
        }
    } else {
        echo "Not found: $file\n";
    }
}

echo "\n=================================\n";
echo "Total files deleted/cleared: $deletedCount\n\n";

// Check if .gitignore exists (to verify we're in the right directory)
if (file_exists($bootstrapCache.'/.gitignore')) {
    echo "✓ Bootstrap cache directory verified\n";
} else {
    echo "⚠ Warning: .gitignore not found in bootstrap/cache\n";
}

// Check if route definition exists in file (without loading Laravel)
echo "\n=================================\n";
echo "Checking route file...\n\n";

$routeFile = $basePath.'/routes/admin/notifications.php';
if (file_exists($routeFile)) {
    echo "✓ Route file exists: $routeFile\n";
    $content = file_get_contents($routeFile);
    if (strpos($content, 'find-by-tournament') !== false) {
        echo "✓ Route 'find-by-tournament' is defined in the file\n";
    } else {
        echo "✗ Route 'find-by-tournament' NOT found in file\n";
    }
} else {
    echo "✗ Route file NOT FOUND\n";
}

echo "\n=================================\n";
echo "IMPORTANT: After clearing cache, you must:\n";
echo "1. Access your website homepage to rebuild the cache\n";
echo "2. Test the route again\n";
echo "3. If still 404, check with Aruba support about:\n";
echo "   - OPcache clearing\n";
echo "   - ModSecurity rules\n";
echo "   - URL pattern restrictions\n";

echo '</pre>';

echo '<hr>';
echo '<h2>Next Steps:</h2>';
echo '<ol>';
echo '<li>Refresh your website homepage to rebuild the cache</li>';
echo '<li>Test the problematic route again</li>';
echo '<li><strong>DELETE THIS FILE immediately for security!</strong></li>';
echo '</ol>';

// Also try to clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '<p>✓ OPcache cleared</p>';
}
