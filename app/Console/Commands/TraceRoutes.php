<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TraceRoutes extends Command
{
    protected $signature = 'golf:trace-routes';
    protected $description = 'Traccia tutte le route dalle view ai controller';

    private $routeMap = [];
    private $unusedRoutes = [];

    public function handle()
    {
        $this->info('🔍 Analisi Route in corso...');

        // Step 1: Mappa tutte le route definite
        $this->mapDefinedRoutes();

        // Step 2: Trova route usate nelle view
        $this->scanViews();

        // Step 3: Trova route orfane
        $this->findOrphanRoutes();

        // Step 4: Output report
        $this->generateReport();
    }

    private function mapDefinedRoutes()
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name) {
                $this->routeMap[$name] = [
                    'uri' => $route->uri(),
                    'action' => $route->getActionName(),
                    'methods' => $route->methods(),
                    'used_in_views' => [],
                    'middleware' => $route->middleware()
                ];
            }
        }
        $this->info("✓ Trovate " . count($this->routeMap) . " route nominate");
    }

    private function scanViews()
    {
        $viewFiles = $this->getAllViewFiles();

        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(resource_path('views/'), '', $file);

            // Cerca route()
            preg_match_all("/route\(['\"]([^'\"]+)['\"]/", $content, $matches);
            foreach ($matches[1] as $routeName) {
                if (isset($this->routeMap[$routeName])) {
                    $this->routeMap[$routeName]['used_in_views'][] = $relativePath;
                }
            }

            // Cerca url()
            preg_match_all("/url\(['\"]\/([^'\"]+)['\"]/", $content, $urlMatches);
            foreach ($urlMatches[1] as $url) {
                $this->checkUrlAgainstRoutes($url, $relativePath);
            }
        }
    }

    private function getAllViewFiles()
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function findOrphanRoutes()
    {
        foreach ($this->routeMap as $name => $route) {
            if (empty($route['used_in_views'])) {
                $this->unusedRoutes[] = $name;
            }
        }
    }

    private function generateReport()
    {
        // Route non usate
        if (count($this->unusedRoutes) > 0) {
            $this->error("\n⚠️  ROUTE NON USATE NELLE VIEW (" . count($this->unusedRoutes) . "):");
            foreach ($this->unusedRoutes as $route) {
                $this->line("  ❌ {$route} → " . $this->routeMap[$route]['action']);
            }
        }

        // Route duplicate (stesso controller/metodo)
        $this->findDuplicates();

        // Genera file di report
        $this->generateHtmlReport();
    }

    private function findDuplicates()
    {
        $actions = [];
        foreach ($this->routeMap as $name => $route) {
            $action = $route['action'];
            if (!isset($actions[$action])) {
                $actions[$action] = [];
            }
            $actions[$action][] = $name;
        }

        $this->warn("\n🔄 ROUTE DUPLICATE (stesso controller):");
        foreach ($actions as $action => $routes) {
            if (count($routes) > 1) {
                $this->line("  {$action}:");
                foreach ($routes as $route) {
                    $this->line("    - {$route}");
                }
            }
        }
    }

    private function generateHtmlReport()
    {
        $html = view('reports.route-trace', [
            'routes' => $this->routeMap,
            'unused' => $this->unusedRoutes
        ])->render();

        file_put_contents(storage_path('app/route-trace-report.html'), $html);
        $this->info("\n📊 Report HTML generato: storage/app/route-trace-report.html");
    }
}
