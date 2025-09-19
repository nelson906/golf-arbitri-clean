<?php
// app/Console/Commands/ScanTechnicalOrphansCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ScanTechnicalOrphansCommand extends Command
{
    protected $signature = 'scan:technical-orphans {--fix : Applica fix automatici sicuri}';

    protected $description = 'Scansiona aspetti tecnici che potrebbero causare errori 500 silenziosi';

    private $issues = [];
    private $fixableIssues = [];

    public function handle()
    {
        $this->info('🔬 SCANSIONE TECNICA RIFERIMENTI ORFANI');
        $this->info('═══════════════════════════════════════');
        $this->info('');

        // 1. Foreign Keys orfane
        $this->scanForeignKeys();

        // 2. Indici database con nomi riferiti
        $this->scanDatabaseIndexes();

        // 3. Job/Queue references
        $this->scanJobReferences();

        // 4. Cache key references
        $this->scanCacheReferences();

        // 5. Event/Listener references
        $this->scanEventListeners();

        // 6. Service Provider bindings
        $this->scanServiceProviders();

        // 7. Validation rules custom
        $this->scanValidationRules();

        // 8. Observer registrations
        $this->scanModelObservers();

        // 9. Factory/Seeder references
        $this->scanFactorySeederReferences();

        // 10. Asset references (CSS/JS)
        $this->scanAssetReferences();

        // Report e fix opzionali
        $this->generateTechnicalReport();

        if ($this->option('fix') && !empty($this->fixableIssues)) {
            $this->applyAutomaticFixes();
        }

        return count($this->issues) > 0 ? 1 : 0;
    }

    private function scanForeignKeys()
    {
        $this->info('🔗 Scansionando Foreign Keys...');

        try {
            // Ottieni tutte le foreign keys
            $tables = DB::select('SHOW TABLES');
            $dbName = DB::getDatabaseName();

            foreach ($tables as $table) {
                $tableName = $table->{"Tables_in_{$dbName}"};

                $foreignKeys = DB::select("
                    SELECT
                        CONSTRAINT_NAME,
                        COLUMN_NAME,
                        REFERENCED_TABLE_NAME,
                        REFERENCED_COLUMN_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE
                        CONSTRAINT_SCHEMA = ? AND
                        TABLE_NAME = ? AND
                        REFERENCED_TABLE_NAME IS NOT NULL
                ", [$dbName, $tableName]);

                foreach ($foreignKeys as $fk) {
                    // Verifica se la tabella referenziata esiste ancora
                    if (in_array($fk->REFERENCED_TABLE_NAME, ['letter_templates', 'letterheads'])) {
                        $this->addIssue('foreign_key', $tableName,
                            "FK {$fk->CONSTRAINT_NAME} referenzia tabella inesistente {$fk->REFERENCED_TABLE_NAME}",
                            true, "ALTER TABLE {$tableName} DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Impossibile scansionare FK: " . $e->getMessage());
        }
    }

    private function scanDatabaseIndexes()
    {
        $this->info('📊 Scansionando indici database...');

        try {
            $tables = DB::select('SHOW TABLES');
            $dbName = DB::getDatabaseName();

            foreach ($tables as $table) {
                $tableName = $table->{"Tables_in_{$dbName}"};

                $indexes = DB::select("SHOW INDEX FROM {$tableName}");

                foreach ($indexes as $index) {
                    // Cerca indici con nomi che contengono riferimenti template/letterhead
                    $suspiciousPatterns = ['template', 'letterhead'];

                    foreach ($suspiciousPatterns as $pattern) {
                        if (stripos($index->Key_name, $pattern) !== false ||
                            stripos($index->Column_name, $pattern) !== false) {

                            $this->addIssue('database_index', $tableName,
                                "Indice sospetto: {$index->Key_name} su colonna {$index->Column_name}",
                                false);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Impossibile scansionare indici: " . $e->getMessage());
        }
    }

    private function scanJobReferences()
    {
        $this->info('⚡ Scansionando Job/Queue references...');

        $jobFiles = File::glob(base_path('app/Jobs/*.php'));

        foreach ($jobFiles as $jobFile) {
            $content = File::get($jobFile);

            $suspiciousPatterns = ['LetterTemplate', 'Letterhead'];

            foreach ($suspiciousPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $this->addIssue('job_reference', $jobFile,
                        "Job contiene riferimento a {$pattern}",
                        false);
                }
            }
        }

        // Verifica job in coda database
        if (Schema::hasTable('jobs')) {
            $suspiciousJobs = DB::table('jobs')
                ->where('payload', 'like', '%LetterTemplate%')
                ->orWhere('payload', 'like', '%Letterhead%')
                ->count();

            if ($suspiciousJobs > 0) {
                $this->addIssue('queued_jobs', 'database',
                    "{$suspiciousJobs} job in coda contengono riferimenti orfani",
                    true, "Pulizia manuale job queue richiesta");
            }
        }
    }

    private function scanCacheReferences()
    {
        $this->info('🗄️ Scansionando cache references...');

        // Scansiona file per cache keys
        $phpFiles = File::glob(base_path('app/**/*.php'));

        foreach ($phpFiles as $file) {
            $content = File::get($file);

            $patterns = [
                "/Cache::.*['\"][^'\"]*template/i",
                "/Cache::.*['\"][^'\"]*letterhead/i",
                "/cache\s*\([^)]*template/i",
                "/cache\s*\([^)]*letterhead/i",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $this->addIssue('cache_reference', $file,
                            "Cache key sospetta: {$match}",
                            false);
                    }
                }
            }
        }
    }

    private function scanEventListeners()
    {
        $this->info('📢 Scansionando Event/Listeners...');

        $eventServiceProvider = base_path('app/Providers/EventServiceProvider.php');

        if (File::exists($eventServiceProvider)) {
            $content = File::get($eventServiceProvider);

            $suspiciousPatterns = ['LetterTemplate', 'Letterhead'];

            foreach ($suspiciousPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $this->addIssue('event_listener', $eventServiceProvider,
                        "EventServiceProvider contiene riferimento a {$pattern}",
                        false);
                }
            }
        }

        // Scansiona directory Listeners
        $listenerFiles = File::glob(base_path('app/Listeners/*.php'));

        foreach ($listenerFiles as $file) {
            $content = File::get($file);

            foreach (['LetterTemplate', 'Letterhead'] as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $this->addIssue('listener_reference', $file,
                        "Listener contiene riferimento a {$pattern}",
                        false);
                }
            }
        }
    }

    private function scanServiceProviders()
    {
        $this->info('🏗️ Scansionando Service Providers...');

        $providerFiles = File::glob(base_path('app/Providers/*.php'));

        foreach ($providerFiles as $file) {
            $content = File::get($file);

            $suspiciousPatterns = ['LetterTemplate', 'Letterhead'];

            foreach ($suspiciousPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $this->addIssue('service_provider', $file,
                        "Service Provider contiene riferimento a {$pattern}",
                        false);
                }
            }
        }
    }

    private function scanValidationRules()
    {
        $this->info('✅ Scansionando validation rules...');

        // Scansiona Request classes
        $requestFiles = File::glob(base_path('app/Http/Requests/*.php'));

        foreach ($requestFiles as $file) {
            $content = File::get($file);

            $patterns = [
                "/exists:letter_templates/",
                "/exists:letterheads/",
                "/unique:letter_templates/",
                "/unique:letterheads/",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $this->addIssue('validation_rule', $file,
                        "Validation rule orfana: {$matches[0]}",
                        true, "Rimuovere regola di validazione");
                }
            }
        }
    }

    private function scanModelObservers()
    {
        $this->info('👁️ Scansionando Model Observers...');

        $observerFiles = File::glob(base_path('app/Observers/*.php'));

        foreach ($observerFiles as $file) {
            $content = File::get($file);

            if (strpos($content, 'LetterTemplate') !== false ||
                strpos($content, 'Letterhead') !== false) {

                $this->addIssue('model_observer', $file,
                    "Observer per modello che sarà rimosso",
                    true, "Rimuovere observer file");
            }
        }

        // Verifica registrazioni observer in AppServiceProvider
        $appServiceProvider = base_path('app/Providers/AppServiceProvider.php');
        if (File::exists($appServiceProvider)) {
            $content = File::get($appServiceProvider);

            $patterns = [
                "/LetterTemplate::observe/",
                "/Letterhead::observe/",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $this->addIssue('observer_registration', $appServiceProvider,
                        "Observer registration orfana",
                        true, "Rimuovere registrazione observer");
                }
            }
        }
    }

    private function scanFactorySeederReferences()
    {
        $this->info('🌱 Scansionando Factory/Seeder...');

        // Factory files
        $factoryFiles = File::glob(base_path('database/factories/*.php'));

        foreach ($factoryFiles as $file) {
            if (strpos(basename($file), 'LetterTemplate') !== false ||
                strpos(basename($file), 'Letterhead') !== false) {

                $this->addIssue('factory_file', $file,
                    "Factory file per modello rimosso",
                    true, "Rimuovere factory file");
            }
        }

        // Seeder files
        $seederFiles = File::glob(base_path('database/seeders/*.php'));

        foreach ($seederFiles as $file) {
            $content = File::get($file);

            if (strpos($content, 'LetterTemplate') !== false ||
                strpos($content, 'Letterhead') !== false) {

                $this->addIssue('seeder_reference', $file,
                    "Seeder contiene riferimenti orfani",
                    false);
            }
        }
    }

    private function scanAssetReferences()
    {
        $this->info('🎨 Scansionando asset references...');

        // CSS files
        $cssFiles = File::glob(public_path('css/*.css'));

        foreach ($cssFiles as $file) {
            $content = File::get($file);

            $patterns = [
                '/letter-template/',
                '/letterhead/',
                '/template-management/'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $this->addIssue('css_reference', $file,
                        "CSS contiene selettori orfani",
                        false);
                }
            }
        }

        // JS files
        $jsFiles = File::glob(public_path('js/*.js'));

        foreach ($jsFiles as $file) {
            $content = File::get($file);

            if (strpos($content, 'letter-template') !== false ||
                strpos($content, 'letterhead') !== false) {

                $this->addIssue('js_reference', $file,
                    "JavaScript contiene riferimenti orfani",
                    false);
            }
        }
    }

    private function addIssue(string $type, string $location, string $description, bool $fixable, string $fix = null)
    {
        $issue = [
            'type' => $type,
            'location' => str_replace(base_path() . '/', '', $location),
            'description' => $description,
            'fixable' => $fixable,
            'fix' => $fix
        ];

        $this->issues[] = $issue;

        if ($fixable) {
            $this->fixableIssues[] = $issue;
        }
    }

    private function generateTechnicalReport()
    {
        $this->info("\n🔬 REPORT TECNICO");
        $this->info('═══════════════');

        if (empty($this->issues)) {
            $this->info('✅ Nessun problema tecnico rilevato!');
            return;
        }

        $this->error("⚠️ Rilevati " . count($this->issues) . " problemi tecnici");

        $byType = collect($this->issues)->groupBy('type');

        foreach ($byType as $type => $issues) {
            $fixableCount = $issues->where('fixable', true)->count();
            $this->warn("\n🔧 " . strtoupper(str_replace('_', ' ', $type)) . " ({$issues->count()} totali, {$fixableCount} fixabili):");

            foreach ($issues as $issue) {
                $icon = $issue['fixable'] ? '🔧' : '⚠️';
                $this->line("  {$icon} {$issue['location']}");
                $this->line("     {$issue['description']}");

                if ($issue['fixable'] && $issue['fix']) {
                    $this->line("     💡 Fix: {$issue['fix']}");
                }
            }
        }

        if (!empty($this->fixableIssues)) {
            $this->info("\n💡 Esegui con --fix per applicare correzioni automatiche sicure");
        }
    }

    private function applyAutomaticFixes()
    {
        $this->info("\n🔧 APPLICAZIONE FIX AUTOMATICI");
        $this->info('═══════════════════════════════');

        foreach ($this->fixableIssues as $issue) {
            try {
                switch ($issue['type']) {
                    case 'foreign_key':
                        if (strpos($issue['fix'], 'ALTER TABLE') === 0) {
                            DB::statement($issue['fix']);
                            $this->info("✅ FK rimossa: {$issue['location']}");
                        }
                        break;

                    case 'factory_file':
                    case 'model_observer':
                        if (File::exists(base_path($issue['location']))) {
                            File::delete(base_path($issue['location']));
                            $this->info("✅ File rimosso: {$issue['location']}");
                        }
                        break;

                    default:
                        $this->warn("⚠️ Fix manuale richiesto per: {$issue['location']}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Errore fix {$issue['location']}: " . $e->getMessage());
            }
        }
    }
}
