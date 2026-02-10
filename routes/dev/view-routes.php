<?php

/**
 * Laravel View Previewer Routes
 *
 * Copy this file to: routes/dev/view-routes.php
 * Then include in web.php:
 *
 * if (app()->environment(['local', 'staging'])) {
 *     require __DIR__.'/dev/view-routes.php';
 * }
 */

use Illuminate\Support\Facades\Route;

require_once __DIR__.'/view-helpers.php';

// ============================================
// VIEW LIST & PREVIEW
// ============================================

Route::get('/dev/view-preview/{view?}', function ($view = null) {

    if (! $view) {
        $viewsPath = resource_path('views');
        $allViews = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($viewsPath.'/', '', $file->getPathname());
                if (str_contains($relativePath, '.blade.php')) {
                    $viewName = str_replace('.blade.php', '', $relativePath);
                    $viewName = str_replace('/', '.', $viewName);
                    $allViews[] = [
                        'name' => $viewName,
                        'path' => $relativePath,
                        'size' => $file->getSize(),
                    ];
                }
            }
        }

        usort($allViews, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return view('dev.view-list', ['views' => $allViews]);
    }

    $viewName = str_replace('/', '.', $view);

    if (! view()->exists($viewName)) {
        abort(404, "View '$viewName' non trovata");
    }

    DebugCollector::clear();

    set_error_handler(function () {
        return true;
    });
    error_reporting(0);

    try {
        $allVariables = analyzeViewRecursive($viewName);

        $data = ['errors' => new \Illuminate\Support\ViewErrorBag];
        foreach ($allVariables as $varName) {
            if (! isset($data[$varName])) {
                $data[$varName] = generateValue($varName);
            }
        }

        $fakeUser = new UniversalValue(['id' => 1, 'name' => 'Mock User', 'is_admin' => true]);
        \Illuminate\Support\Facades\Auth::setUser($fakeUser);

        ob_start();
        $rendered = false;

        try {
            echo view($viewName, $data)->render();
            $rendered = true;
        } catch (\Throwable $e) {
            // Partial output
        }

        $renderedView = ob_get_clean();

        restore_error_handler();
        error_reporting(E_ALL);
        \Illuminate\Support\Facades\Auth::logout();

        if (! empty(trim($renderedView))) {
            if (DebugCollector::hasIssues()) {
                $renderedView .= view('dev.debug-panel', [
                    'issues' => DebugCollector::getIssues(),
                    'viewName' => $viewName,
                ])->render();
            }

            return response($renderedView);
        }

        // Source analysis
        $viewFile = resource_path('views/'.str_replace('.', '/', $viewName).'.blade.php');

        if (! file_exists($viewFile)) {
            return response("<div style='padding:40px;'>View file non trovato</div>");
        }

        $source = file_get_contents($viewFile);
        $lines = explode("\n", $source);

        $hasForm = str_contains($source, '<form') || str_contains($source, '@csrf');
        $hasTable = str_contains($source, '<table') || str_contains($source, 'thead');
        $hasCards = str_contains($source, 'card') || str_contains($source, 'bg-white');
        $hasButtons = str_contains($source, 'button') || str_contains($source, 'btn');
        $hasComponents = str_contains($source, '<x-') || str_contains($source, '@component');

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>$viewName - Analisi</title>
            <style>
                body { font-family: system-ui; margin: 0; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .subtitle { color: #6b7280; }
                .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 30px; }
                .badge { padding: 15px; border-radius: 6px; text-align: center; }
                .badge-yes { background: #dcfce7; }
                .badge-no { background: #f3f4f6; }
                .badge-icon { font-size: 32px; }
                .badge-label { font-size: 14px; margin-top: 5px; }
                .stats { background: #f9fafb; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
                .stats-title { font-weight: 600; margin-bottom: 10px; }
                .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
                .code-container { margin-top: 20px; }
                .code-header { cursor: pointer; font-weight: 600; padding: 10px; background: #f3f4f6; border-radius: 6px; }
                .code-content { background: #1f2937; color: #10b981; padding: 20px; border-radius: 6px; overflow-x: auto; margin-top: 10px; font-size: 12px; line-height: 1.5; }
                .tip { margin-top: 20px; padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='title'>📄 $viewName</div>
                    <div class='subtitle'>Questa view non può essere renderizzata, ma ecco cosa contiene:</div>
                </div>

                <div class='grid'>
                    <div class='badge ".($hasForm ? 'badge-yes' : 'badge-no')."'>
                        <div class='badge-icon'>".($hasForm ? '✅' : '❌')."</div>
                        <div class='badge-label'>Forms</div>
                    </div>
                    <div class='badge ".($hasTable ? 'badge-yes' : 'badge-no')."'>
                        <div class='badge-icon'>".($hasTable ? '✅' : '❌')."</div>
                        <div class='badge-label'>Tabelle</div>
                    </div>
                    <div class='badge ".($hasCards ? 'badge-yes' : 'badge-no')."'>
                        <div class='badge-icon'>".($hasCards ? '✅' : '❌')."</div>
                        <div class='badge-label'>Cards</div>
                    </div>
                    <div class='badge ".($hasButtons ? 'badge-yes' : 'badge-no')."'>
                        <div class='badge-icon'>".($hasButtons ? '✅' : '❌')."</div>
                        <div class='badge-label'>Bottoni</div>
                    </div>
                    <div class='badge ".($hasComponents ? 'badge-yes' : 'badge-no')."'>
                        <div class='badge-icon'>".($hasComponents ? '✅' : '❌')."</div>
                        <div class='badge-label'>Componenti</div>
                    </div>
                </div>

                <div class='stats'>
                    <div class='stats-title'>📊 Statistiche:</div>
                    <div class='stats-grid'>
                        <div><strong>Righe:</strong> ".count($lines).'</div>
                        <div><strong>Dimensione:</strong> '.number_format(strlen($source) / 1024, 2).' KB</div>
                        <div><strong>Extends:</strong> '.(preg_match('/@extends/', $source) ? '✅ Sì' : '❌ No')."</div>
                    </div>
                </div>

                <details open class='code-container'>
                    <summary class='code-header'>📝 Codice Sorgente</summary>
                    <pre class='code-content'>".htmlspecialchars($source)."</pre>
                </details>

                <div class='tip'>
                    <strong>💡 Suggerimento:</strong> Analizza il codice sopra per capire se questa view è utile o può essere eliminata.
                </div>
            </div>
        </body>
        </html>
        ";

        return response($html);

    } catch (\Throwable $e) {
        restore_error_handler();
        error_reporting(E_ALL);
        try {
            \Illuminate\Support\Facades\Auth::logout();
        } catch (\Throwable $e2) {
        }

        return response()->view('dev.view-error', [
            'view' => $viewName,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }

})->name('dev.view-preview')->where('view', '.*');

// ============================================
// TEST ALL VIEWS
// ============================================

Route::get('/dev/view-test-all', function () {
    set_time_limit(300);

    $viewsPath = resource_path('views');
    $results = ['success' => [], 'failed' => [], 'partial' => []];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace($viewsPath.'/', '', $file->getPathname());
        if (! str_contains($relativePath, '.blade.php')) {
            continue;
        }

        $viewName = str_replace('.blade.php', '', $relativePath);
        $viewName = str_replace('/', '.', $viewName);

        error_reporting(0);
        @ini_set('display_errors', '0');

        $status = 'success';
        $error = null;

        try {
            ob_start();

            $data = ['errors' => new \Illuminate\Support\ViewErrorBag];
            $allVars = @analyzeViewRecursive($viewName);

            foreach ($allVars as $var) {
                if (! isset($data[$var])) {
                    $data[$var] = generateValue($var);
                }
            }

            $fakeUser = new UniversalValue(['id' => 1, 'name' => 'Test', 'is_admin' => true]);
            @\Illuminate\Support\Facades\Auth::setUser($fakeUser);

            @eval('echo view($viewName, $data)->render();');

            @\Illuminate\Support\Facades\Auth::logout();

            ob_end_clean();

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            @ob_end_clean();

            try {
                @\Illuminate\Support\Facades\Auth::logout();
            } catch (\Throwable $e2) {
            }
        }

        error_reporting(E_ALL);

        $results[$status][] = [
            'name' => $viewName,
            'path' => $relativePath,
            'size' => $file->getSize(),
            'error' => $error,
        ];
    }

    return view('dev.view-test-results', [
        'results' => $results,
        'total' => count($results['success']) + count($results['failed']) + count($results['partial']),
        'successCount' => count($results['success']),
        'failedCount' => count($results['failed']),
        'partialCount' => count($results['partial']),
    ]);
})->name('dev.view-test-all');

// ============================================
// NEW: ORPHANED VIEWS DETECTOR
// ============================================

Route::get('/dev/view-orphaned', function () {
    set_time_limit(300);

    $results = detectOrphanedViews();

    return view('dev.view-orphaned', [
        'orphaned' => $results['orphaned'],
        'used' => $results['used'],
        'total' => $results['total'],
        'orphanedCount' => $results['orphaned_count'],
        'usedCount' => $results['used_count'],
    ]);
})->name('dev.view-orphaned');

// ============================================
// NEW: PERFORMANCE BENCHMARKS
// ============================================

Route::get('/dev/view-performance/{view?}', function ($view = null) {
    set_time_limit(300);

    if (! $view) {
        // Benchmark all views
        $results = benchmarkAllViews(5);

        return view('dev.view-performance-all', [
            'results' => $results['results'],
            'total' => $results['total_views'],
            'slowest' => $results['slowest'],
            'fastest' => $results['fastest'],
            'summary' => $results['summary'],
        ]);
    }

    // Benchmark single view
    $viewName = str_replace('/', '.', $view);

    if (! view()->exists($viewName)) {
        abort(404, "View '$viewName' not found");
    }

    // Generate mock data
    $variables = analyzeViewRecursive($viewName);
    $data = ['errors' => new \Illuminate\Support\ViewErrorBag];
    foreach ($variables as $varName) {
        if (! isset($data[$varName])) {
            $data[$varName] = generateValue($varName);
        }
    }

    $result = measureViewPerformance($viewName, $data, 20);

    return view('dev.view-performance-single', [
        'result' => $result,
    ]);
})->name('dev.view-performance')->where('view', '.*');
