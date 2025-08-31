<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateViewsToBladeLayout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-views-to-blade-layout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
public function handle()
{
    $this->info('Migrazione layout views a Blade unificato...');

    // 1. Trova tutti i file che estendono layout vecchi
    $viewFiles = glob(resource_path('views/**/*.blade.php'), GLOB_BRACE);

    foreach ($viewFiles as $file) {
        $content = file_get_contents($file);

        // Sostituzioni automatiche
        $content = str_replace('@extends(\'layouts.app\')', '@extends(\'layouts.admin\')', $content);
        $content = str_replace('@extends("layouts.app")', '@extends(\'layouts.admin\')', $content);

        // Elimina navigation/menu duplicati
        $content = preg_replace('/<nav.*?<\/nav>/s', '', $content);
        $content = preg_replace('/{{-- Navigation.*?--}}/s', '', $content);

        file_put_contents($file, $content);
        $this->line("Aggiornato: " . basename($file));
    }

    // 2. Rimuovi file layout obsoleti
    $oldLayouts = [
        'resources/views/layouts/app.blade.php',
        'resources/views/partials/navigation.blade.php',
        'resources/views/partials/sidebar.blade.php'
    ];

    foreach ($oldLayouts as $oldLayout) {
        if (file_exists($oldLayout)) {
            rename($oldLayout, $oldLayout . '.backup');
            $this->warn("Backup creato: $oldLayout.backup");
        }
    }

    $this->info('Migrazione layout completata!');
}}
