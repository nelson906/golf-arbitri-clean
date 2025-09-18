<?php
// app/Console/Commands/DismantleTemplatesCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class DismantleTemplatesCommand extends Command
{
    protected $signature = 'dismantle:templates {--dry-run : Show what will be done without executing} {--force : Skip confirmations}';

    protected $description = 'Smantella completamente il sistema Template/Letterhead dal progetto';

    private $filesToRemove = [
        'app/Http/Controllers/Admin/LetterTemplateController.php',
        'app/Http/Controllers/Admin/LetterheadController.php',
        'app/Http/Controllers/Admin/TemplateManagementController.php',
        'app/Models/LetterTemplate.php',
        'app/Models/Letterhead.php',
    ];

    private $directoriesToRemove = [
        'resources/views/admin/letter-templates',
        'resources/views/admin/letterheads',
    ];

    private $storageDirectoriesToRemove = [
        'letterheads/logos',
        'letterheads',
        'templates'
    ];

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🚨 SMANTELLAMENTO SISTEMA TEMPLATE/LETTERHEAD');
        $this->info('═══════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Nessuna modifica verrà applicata');
        }

        // 1. Pre-flight checks
        if (!$this->preFlightChecks()) {
            return 1;
        }

        // 2. Conferma utente
        if (!$force && !$dryRun) {
            if (!$this->confirm('⚠️  Sei sicuro di voler procedere? Questa operazione è IRREVERSIBILE!')) {
                $this->info('❌ Operazione annullata.');
                return 0;
            }
        }

        // 3. Backup check
        if (!$dryRun && !$this->verifyBackup()) {
            return 1;
        }

        // 4. Esecuzione smantellamento
        $this->info("\n🔥 Inizio smantellamento...\n");

        $this->removeControllers($dryRun);
        $this->removeModels($dryRun);
        $this->removeViews($dryRun);
        $this->removeStorage($dryRun);
        $this->removeDatabaseTables($dryRun);
        $this->cleanRoutes($dryRun);
        $this->clearCaches($dryRun);

        // 5. Verifica finale
        if (!$dryRun) {
            $this->finalVerification();
        }

        $this->info("\n✅ SMANTELLAMENTO COMPLETATO CON SUCCESSO!");
        $this->info("🔍 Verifica manuale il menu admin e le funzionalità core.");

        return 0;
    }

    private function preFlightChecks(): bool
    {
        $this->info('🔍 Pre-flight checks...');

        // Verifica ambiente
        if (config('app.env') === 'production') {
            $this->error('❌ ERRORE: Non eseguire in produzione senza test approfonditi!');
            return false;
        }

        // Verifica Git status
        if (!$this->checkGitStatus()) {
            $this->warn('⚠️  Repository Git non pulito. Si consiglia di commitare prima.');
            if (!$this->confirm('Continuare comunque?')) {
                return false;
            }
        }

        // Verifica esistenza file target
        $existingFiles = collect($this->filesToRemove)
            ->filter(fn($file) => File::exists(base_path($file)))
            ->count();

        if ($existingFiles === 0) {
            $this->warn('⚠️  Nessun file template trovato. Forse già rimossi?');
            return $this->confirm('Continuare comunque?');
        }

        $this->info("✅ Trovati {$existingFiles} file da rimuovere");
        return true;
    }

    private function verifyBackup(): bool
    {
        $this->info('🔍 Verifica backup...');

        // Check per backup recenti
        $backupFiles = collect(File::glob(storage_path('app/backup/*.sql')))
            ->filter(fn($file) => File::lastModified($file) > (time() - 3600)) // Ultimo ora
            ->count();

        if ($backupFiles === 0) {
            $this->error('❌ ERRORE: Nessun backup recente trovato!');
            $this->info('💡 Esegui: php artisan backup:run --only-db');
            return false;
        }

        $this->info('✅ Backup verificato');
        return true;
    }

    private function removeControllers(bool $dryRun): void
    {
        $this->info('📁 Rimozione Controllers...');

        $controllers = [
            'app/Http/Controllers/Admin/LetterTemplateController.php',
            'app/Http/Controllers/Admin/LetterheadController.php',
            'app/Http/Controllers/Admin/TemplateManagementController.php'
        ];

        foreach ($controllers as $controller) {
            $path = base_path($controller);
            if (File::exists($path)) {
                if (!$dryRun) {
                    File::delete($path);
                    $this->line("  ✅ Rimosso: {$controller}");
                } else {
                    $this->line("  🔍 [DRY-RUN] Rimuoverà: {$controller}");
                }
            }
        }
    }

    private function removeModels(bool $dryRun): void
    {
        $this->info('📄 Rimozione Models...');

        $models = ['app/Models/LetterTemplate.php', 'app/Models/Letterhead.php'];

        foreach ($models as $model) {
            $path = base_path($model);
            if (File::exists($path)) {
                if (!$dryRun) {
                    File::delete($path);
                    $this->line("  ✅ Rimosso: {$model}");
                } else {
                    $this->line("  🔍 [DRY-RUN] Rimuoverà: {$model}");
                }
            }
        }
    }

    private function removeViews(bool $dryRun): void
    {
        $this->info('🎨 Rimozione Views...');

        foreach ($this->directoriesToRemove as $directory) {
            $path = base_path($directory);
            if (File::isDirectory($path)) {
                if (!$dryRun) {
                    File::deleteDirectory($path);
                    $this->line("  ✅ Rimossa directory: {$directory}");
                } else {
                    $this->line("  🔍 [DRY-RUN] Rimuoverà directory: {$directory}");
                }
            }
        }
    }

    private function removeStorage(bool $dryRun): void
    {
        $this->info('💾 Pulizia Storage...');

        foreach ($this->storageDirectoriesToRemove as $directory) {
            if (Storage::disk('public')->exists($directory)) {
                if (!$dryRun) {
                    Storage::disk('public')->deleteDirectory($directory);
                    $this->line("  ✅ Rimossa directory storage: {$directory}");
                } else {
                    $this->line("  🔍 [DRY-RUN] Rimuoverà storage: {$directory}");
                }
            }
        }
    }

    private function removeDatabaseTables(bool $dryRun): void
    {
        $this->info('🗄️ Rimozione tabelle database...');

        $tables = ['letter_templates', 'letterheads'];

        foreach ($tables as $table) {
            try {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    if (!$dryRun) {
                        DB::statement("DROP TABLE {$table}");
                        $this->line("  ✅ Rimossa tabella: {$table}");
                    } else {
                        $this->line("  🔍 [DRY-RUN] Rimuoverà tabella: {$table}");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Errore rimozione tabella {$table}: " . $e->getMessage());
            }
        }
    }

    private function cleanRoutes(bool $dryRun): void
    {
        $this->info('🛣️ Pulizia Routes...');

        if (!$dryRun) {
            $routeFile = base_path('routes/web.php');
            if (File::exists($routeFile)) {
                $content = File::get($routeFile);

                // Pattern per rimuovere blocchi route template/letterhead
                $patterns = [
                    '/Route::prefix\(\'letter-templates\'\).*?}\);/s',
                    '/Route::prefix\(\'letterheads\'\).*?}\);/s',
                    '/Route::get\(\'\/templates\/management\'.*?\);/s',
                    '/Route::get\(\'\/templates\/\{template\}\/preview\'.*?\);/s',
                ];

                foreach ($patterns as $pattern) {
                    $content = preg_replace($pattern, '', $content);
                }

                File::put($routeFile, $content);
                $this->line("  ✅ Routes pulite");
            }
        } else {
            $this->line("  🔍 [DRY-RUN] Pulirà routes template/letterhead");
        }
    }

    private function clearCaches(bool $dryRun): void
    {
        $this->info('🧹 Pulizia caches...');

        if (!$dryRun) {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            $this->line("  ✅ Cache pulite");
        } else {
            $this->line("  🔍 [DRY-RUN] Pulirà tutte le cache");
        }
    }

    private function finalVerification(): void
    {
        $this->info('🔍 Verifica finale...');

        // Verifica nessun file residuo
        $residualFiles = collect($this->filesToRemove)
            ->filter(fn($file) => File::exists(base_path($file)));

        if ($residualFiles->count() > 0) {
            $this->warn('⚠️  File residui trovati:');
            $residualFiles->each(fn($file) => $this->line("    - {$file}"));
        }

        // Verifica tabelle
        $residualTables = collect(['letter_templates', 'letterheads'])
            ->filter(fn($table) => DB::getSchemaBuilder()->hasTable($table));

        if ($residualTables->count() > 0) {
            $this->warn('⚠️  Tabelle residue:');
            $residualTables->each(fn($table) => $this->line("    - {$table}"));
        }

        if ($residualFiles->count() === 0 && $residualTables->count() === 0) {
            $this->info('✅ Verifica finale: SISTEMA PULITO');
        }
    }

    private function checkGitStatus(): bool
    {
        try {
            $output = shell_exec('git status --porcelain 2>/dev/null');
            return empty(trim($output));
        } catch (\Exception $e) {
            return true; // Assume OK se non può verificare
        }
    }
}
