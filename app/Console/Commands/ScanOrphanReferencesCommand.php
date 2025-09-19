<?php
// app/Console/Commands/ScanOrphanReferencesCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class ScanOrphanReferencesCommand extends Command
{
    protected $signature = 'scan:orphans {targets?* : Template/Letterhead per default} {--detailed : Mostra dettagli completi}';

    protected $description = 'Scansiona riferimenti orfani che potrebbero causare errori dopo rimozione';

    private $orphans = [];
    private $targets = [
        'LetterTemplate',
        'Letterhead',
        'LetterTemplateController',
        'LetterheadController',
        'TemplateManagementController'
    ];

    public function handle()
    {
        $customTargets = $this->argument('targets');
        if (!empty($customTargets)) {
            $this->targets = $customTargets;
        }

        $detailed = $this->option('detailed');

        $this->info('🔍 SCANSIONE RIFERIMENTI ORFANI');
        $this->info('═══════════════════════════════');
        $this->info('Targets: ' . implode(', ', $this->targets));
        $this->info('');

        // 1. Scansiona USE statements
        $this->scanUseStatements();

        // 2. Scansiona chiamate dirette a classi
        $this->scanDirectClassCalls();

        // 3. Scansiona route references
        $this->scanRouteReferences();

        // 4. Scansiona view references
        $this->scanViewReferences();

        // 5. Scansiona facade/helper calls
        $this->scanFacadeCalls();

        // 6. Scansiona policy/middleware
        $this->scanPolicyMiddleware();

        // 7. Scansiona config references
        $this->scanConfigReferences();

        // 8. Scansiona relazioni database
        $this->scanDatabaseRelations();

        // Report finale
        $this->generateReport($detailed);

        return count($this->orphans) > 0 ? 1 : 0;
    }

    private function scanUseStatements()
    {
        $this->info('📁 Scansionando USE statements...');

        $phpFiles = $this->getPhpFiles(['app/', 'routes/', 'config/', 'database/']);

        foreach ($phpFiles as $file) {
            $content = File::get($file);

            foreach ($this->targets as $target) {
                // Pattern per use statements
                $patterns = [
                    "/use\s+App\\\\.*{$target}[;\s]/",
                    "/use\s+.*\\\\{$target}[;\s]/",
                    "/\\\\{$target}::/",
                    "/{$target}::/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $this->addOrphan('use_statement', $file, $target, $match[0], $this->getLineNumber($content, $match[1]));
                        }
                    }
                }
            }
        }
    }

    private function scanDirectClassCalls()
    {
        $this->info('🎯 Scansionando chiamate dirette...');

        $phpFiles = $this->getPhpFiles(['app/', 'routes/']);

        foreach ($phpFiles as $file) {
            $content = File::get($file);

            foreach ($this->targets as $target) {
                $patterns = [
                    "/new\s+{$target}\s*\(/",
                    "/{$target}::\w+/",
                    "/\\\${$target}/",
                    "/'{$target}'/",
                    "/\"{$target}\"/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $this->addOrphan('direct_call', $file, $target, $match[0], $this->getLineNumber($content, $match[1]));
                        }
                    }
                }
            }
        }
    }

    private function scanRouteReferences()
    {
        $this->info('🛣️ Scansionando route references...');

        $files = array_merge(
            $this->getPhpFiles(['routes/']),
            $this->getBladeFiles(['resources/views/'])
        );

        foreach ($files as $file) {
            $content = File::get($file);

            $routePatterns = [
                'letter-templates',
                'letterheads',
                'templates.management'
            ];

            foreach ($routePatterns as $route) {
                $patterns = [
                    "/route\s*\(\s*['\"].*{$route}/",
                    "/['\"]admin\.{$route}/",
                    "/@{$route}/",
                    "/url\s*\(\s*['\"].*{$route}/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $this->addOrphan('route_reference', $file, $route, $match[0], $this->getLineNumber($content, $match[1]));
                        }
                    }
                }
            }
        }
    }

    private function scanViewReferences()
    {
        $this->info('👁️ Scansionando view references...');

        $phpFiles = $this->getPhpFiles(['app/', 'routes/']);

        foreach ($phpFiles as $file) {
            $content = File::get($file);

            $viewPatterns = [
                'letter-templates',
                'letterheads'
            ];

            foreach ($viewPatterns as $view) {
                $patterns = [
                    "/view\s*\(\s*['\"].*{$view}/",
                    "/@include\s*\(\s*['\"].*{$view}/",
                    "/@extends\s*\(\s*['\"].*{$view}/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $this->addOrphan('view_reference', $file, $view, $match[0], $this->getLineNumber($content, $match[1]));
                        }
                    }
                }
            }
        }
    }

    private function scanFacadeCalls()
    {
        $this->info('🏛️ Scansionando facade calls...');

        $phpFiles = $this->getPhpFiles(['app/']);

        foreach ($phpFiles as $file) {
            $content = File::get($file);

            foreach ($this->targets as $target) {
                // Cerca Model::find(), Model::create(), etc.
                $pattern = "/{$target}::\w+\s*\(/";

                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $this->addOrphan('facade_call', $file, $target, $match[0], $this->getLineNumber($content, $match[1]));
                    }
                }
            }
        }
    }

    private function scanPolicyMiddleware()
    {
        $this->info('🛡️ Scansionando Policy/Middleware...');

        // Scansiona AuthServiceProvider per policy
        $authServiceProvider = base_path('app/Providers/AuthServiceProvider.php');
        if (File::exists($authServiceProvider)) {
            $content = File::get($authServiceProvider);

            foreach ($this->targets as $target) {
                if (strpos($content, $target) !== false) {
                    $this->addOrphan('policy_registration', $authServiceProvider, $target, "Policy registration", 0);
                }
            }
        }

        // Scansiona middleware references
        $kernelFile = base_path('app/Http/Kernel.php');
        if (File::exists($kernelFile)) {
            $content = File::get($kernelFile);

            foreach ($this->targets as $target) {
                if (strpos($content, $target) !== false) {
                    $this->addOrphan('middleware_registration', $kernelFile, $target, "Middleware registration", 0);
                }
            }
        }
    }

    private function scanConfigReferences()
    {
        $this->info('⚙️ Scansionando config references...');

        $configFiles = File::glob(base_path('config/*.php'));

        foreach ($configFiles as $file) {
            $content = File::get($file);

            foreach ($this->targets as $target) {
                if (preg_match_all("/{$target}/", $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $this->addOrphan('config_reference', $file, $target, $match[0], $this->getLineNumber($content, $match[1]));
                    }
                }
            }
        }
    }

    private function scanDatabaseRelations()
    {
        $this->info('🗄️ Scansionando relazioni database...');

        $models = $this->getPhpFiles(['app/Models/']);

        foreach ($models as $modelFile) {
            $content = File::get($modelFile);

            foreach ($this->targets as $target) {
                $patterns = [
                    "/belongsTo\s*\(\s*{$target}/",
                    "/hasMany\s*\(\s*{$target}/",
                    "/hasOne\s*\(\s*{$target}/",
                    "/morphTo\s*\(\s*{$target}/",
                    "/'{$target}'/",
                    "/\"{$target}\"/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $this->addOrphan('database_relation', $modelFile, $target, $match[0], $this->getLineNumber($content, $match[1]));
                        }
                    }
                }
            }
        }
    }

    private function getPhpFiles(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $path = base_path($directory);
            if (!is_dir($path)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function getBladeFiles(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $path = base_path($directory);
            if (!is_dir($path)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function addOrphan(string $type, string $file, string $target, string $match, int $line)
    {
        $this->orphans[] = [
            'type' => $type,
            'file' => str_replace(base_path() . '/', '', $file),
            'target' => $target,
            'match' => trim($match),
            'line' => $line
        ];
    }

    private function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    private function generateReport(bool $detailed)
    {
        $this->info("\n📊 RISULTATI SCANSIONE");
        $this->info('═════════════════════');

        if (empty($this->orphans)) {
            $this->info('✅ Nessun riferimento orfano trovato!');
            return;
        }

        $this->error("❌ Trovati " . count($this->orphans) . " riferimenti orfani");

        // Raggruppa per tipo
        $byType = collect($this->orphans)->groupBy('type');

        foreach ($byType as $type => $orphans) {
            $this->warn("\n🔍 " . strtoupper(str_replace('_', ' ', $type)) . " ({$orphans->count()}):");

            if ($detailed) {
                foreach ($orphans as $orphan) {
                    $this->line("  📁 {$orphan['file']}:{$orphan['line']}");
                    $this->line("     🎯 {$orphan['target']} → {$orphan['match']}");
                }
            } else {
                // Versione compatta
                $fileGroups = $orphans->groupBy('file');
                foreach ($fileGroups as $file => $fileOrphans) {
                    $targets = $fileOrphans->pluck('target')->unique()->implode(', ');
                    $this->line("  📁 {$file} → {$targets}");
                }
            }
        }

        // Suggerimenti risoluzione
        $this->info("\n💡 SUGGERIMENTI RISOLUZIONE:");
        $this->info("   🔧 php artisan scan:orphans --detailed  (per dettagli completi)");
        $this->info("   🔧 grep -r 'LetterTemplate' app/  (ricerca manuale)");
        $this->info("   🔧 find . -name '*.php' -exec grep -l 'Letterhead' {} \\;");
    }
}
