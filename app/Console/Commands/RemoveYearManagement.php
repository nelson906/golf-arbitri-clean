<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RemoveYearManagement extends Command
{
    protected $signature = 'cleanup:year-management {--dry-run : Mostra modifiche senza applicarle}';
    protected $description = 'Rimuove tutti i riferimenti alla gestione per anni dal progetto';

    private $patternsToRemove = [
        // Patterns da rimuovere dai file
        '/session\([\'"]selected_year[\'"].*?\)/' => 'null',
        '/\$selected_year\s*=.*?;/' => '',
        '/whereYear\([^)]+\)/' => '',
        '/year\([^)]+\)/' => 'null',
        '/\->where\([\'"]status[\'"],.*?\)/' => '',
        '/\->where\([\'"]date[\'"],.*?\)/' => '',
        '/\->whereMonth\([^)]+\)/' => '',
        '/\->whereDate\([^)]+\)/' => '',
    ];

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Scansione file per rimozione gestione anni...');

        // File da controllare
        $directories = [
            'app/Http/Controllers',
            'resources/views',
        ];

        $modifiedFiles = [];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!File::exists($path)) continue;

            $files = File::allFiles($path);

            foreach ($files as $file) {
                $filePath = $file->getPathname();
                $extension = $file->getExtension();

                // Solo file PHP e Blade
                if (!in_array($extension, ['php', 'blade.php'])) continue;

                $content = File::get($filePath);
                $originalContent = $content;

                // Applica sostituzioni
                foreach ($this->patternsToRemove as $pattern => $replacement) {
                    $content = preg_replace($pattern, $replacement, $content);
                }

                // Rimuovi linee vuote multiple
                $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);

                if ($content !== $originalContent) {
                    $modifiedFiles[] = str_replace(base_path() . '/', '', $filePath);

                    if (!$dryRun) {
                        // Backup
                        File::copy($filePath, $filePath . '.backup_year');
                        // Salva modifiche
                        File::put($filePath, $content);
                    }
                }
            }
        }

        // Report
        if (empty($modifiedFiles)) {
            $this->info('✅ Nessun file da modificare!');
        } else {
            $this->info(sprintf('📝 %d file da modificare:', count($modifiedFiles)));
            foreach ($modifiedFiles as $file) {
                $this->line("   - {$file}");
            }

            if ($dryRun) {
                $this->warn('⚠️ Modalità dry-run: nessuna modifica applicata');
                $this->info('Esegui senza --dry-run per applicare le modifiche');
            } else {
                $this->info('✅ Modifiche applicate con backup (.backup_year)');
            }
        }

        // Rimuovi elementi UI
        $this->removeYearSelector();

        return 0;
    }

    private function removeYearSelector()
    {
        $this->info('🔍 Rimozione year selector da layout...');

        $layoutFile = resource_path('views/layouts/admin.blade.php');

        if (File::exists($layoutFile)) {
            $content = File::get($layoutFile);

            // Rimuovi il year selector
            $content = preg_replace(
                '/<div class="flex items-center ml-auto mr-4">.*?<\/select>\s*<\/div>/s',
                '',
                $content
            );

            // Rimuovi script changeYear
            $content = preg_replace(
                '/<script>.*?function changeYear.*?<\/script>/s',
                '',
                $content
            );

            File::put($layoutFile, $content);
            $this->info('✅ Year selector rimosso dal layout');
        }
    }
}
