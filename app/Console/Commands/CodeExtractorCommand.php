<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class CodeExtractorCommand extends Command
{
    protected $signature = 'golf:extract-code {--analyze} {--extract} {--clean}';
    protected $description = 'Estrae selettivamente codice funzionante dal progetto disordinato';

    private array $extractedMethods = [];
    private array $businessLogic = [];
    private array $duplicates = [];

    public function handle()
    {
        $this->info('🔍 CODE EXTRACTOR - Approccio Chirurgico');
        $this->info('=======================================');

        if ($this->option('analyze')) {
            return $this->analyzeExistingCode();
        }

        if ($this->option('extract')) {
            return $this->extractBusinessLogic();
        }

        if ($this->option('clean')) {
            return $this->cleanAndMerge();
        }

        // Processo completo
        $this->analyzeExistingCode();
        $this->extractBusinessLogic();
        $this->cleanAndMerge();

        return 0;
    }

    /**
     * STEP 1: Analizza il codice esistente per identificare cosa tenere
     */
    private function analyzeExistingCode()
    {
        $this->info('🔍 STEP 1: Analizzando codice esistente...');

        // Analizza RefereeController (da tenere)
        $this->analyzeController('RefereeController', [
            'keep' => ['store', 'update', 'show', 'checkRefereeAccess', 'generateRefereeCode'],
            'merge_into' => 'UserController',
            'business_logic' => true
        ]);

        // Analizza TournamentController
        $this->analyzeController('TournamentController', [
            'keep' => ['store', 'update', 'destroy'],
            'standardize' => true,
            'add_crud_actions' => true
        ]);

        // Analizza NotificationController (da tenere tutto)
        $this->analyzeController('NotificationController', [
            'keep' => 'all',
            'business_logic' => true,
            'critical' => true
        ]);

        $this->showAnalysisReport();
        return 0;
    }

    private function analyzeController($controllerName, $config)
    {
        $path = app_path("Http/Controllers/Admin/{$controllerName}.php");

        if (!File::exists($path)) {
            $this->warn("Controller {$controllerName} non trovato");
            return;
        }

        $this->line("📂 Analizzando {$controllerName}...");

        try {
            require_once $path;
            $fullClassName = "App\\Http\\Controllers\\Admin\\{$controllerName}";

            if (!class_exists($fullClassName)) {
                $this->error("Classe {$fullClassName} non trovabile");
                return;
            }

            $reflection = new ReflectionClass($fullClassName);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            $keepMethods = $config['keep'] ?? [];
            if ($keepMethods === 'all') {
                $keepMethods = array_map(fn($m) => $m->getName(), $methods);
            }

            foreach ($methods as $method) {
                $methodName = $method->getName();

                // Skip metodi ereditati da Controller base
                if ($method->getDeclaringClass()->getName() !== $fullClassName) {
                    continue;
                }

                if (in_array($methodName, $keepMethods) || $keepMethods === 'all') {
                    $this->extractedMethods[$controllerName][$methodName] = [
                        'source' => $this->getMethodSource($method),
                        'config' => $config,
                        'line_start' => $method->getStartLine(),
                        'line_end' => $method->getEndLine(),
                    ];

                    $this->line("  ✅ {$methodName} - da tenere");
                } else {
                    $this->line("  ❌ {$methodName} - da scartare");
                }
            }

        } catch (\Exception $e) {
            $this->error("Errore analisi {$controllerName}: " . $e->getMessage());
        }
    }

    private function getMethodSource(ReflectionMethod $method)
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);
        return implode("", array_slice($source, $startLine, $length));
    }

    /**
     * STEP 2: Estrai la business logic funzionante
     */
    private function extractBusinessLogic()
    {
        $this->info('🔧 STEP 2: Estraendo business logic...');

        // Crea UserController unificato con logica da RefereeController
        $this->createUnifiedUserController();

        // Pulisci controllers esistenti
        $this->standardizeExistingControllers();

        // Estrai helper methods
        $this->extractHelperMethods();

        $this->line('✅ Business logic estratta');
        return 0;
    }

    private function createUnifiedUserController()
    {
        $this->info('👥 Creando UserController unificato...');

        if (!isset($this->extractedMethods['RefereeController'])) {
            $this->error('Nessun metodo estratto da RefereeController');
            return;
        }

        $refereeLogic = $this->extractedMethods['RefereeController'];

        // Template base per UserController
        $userControllerContent = $this->generateUserControllerTemplate($refereeLogic);

        $outputPath = app_path('Http/Controllers/Admin/UserController.php');

        $this->line("📝 Scrivendo UserController in {$outputPath}");
        File::put($outputPath, $userControllerContent);

        // Backup RefereeController originale
        $originalPath = app_path('Http/Controllers/Admin/RefereeController.php');
        if (File::exists($originalPath)) {
            File::move($originalPath, $originalPath . '.extracted_backup');
            $this->line("💾 Backup RefereeController creato");
        }
    }

    private function generateUserControllerTemplate($refereeLogic)
    {
        $imports = 'use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use App\Models\Referee;
use App\Traits\CrudActions;
use App\Helpers\Http\RefereeLevelsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;';

        $methods = '';

        // Estrai i metodi mantenendo il codice originale
        foreach ($refereeLogic as $methodName => $methodInfo) {
            $source = $methodInfo['source'];

            // Sostituzioni minime per adattare a UserController
            $source = str_replace('$referee', '$user', $source);
            $source = str_replace('referee)', 'user)', $source);
            $source = str_replace("'referee'", "'user'", $source);

            $methods .= "\n    " . $source . "\n";
        }

        return "<?php

namespace App\Http\Controllers\Admin;

{$imports}

/**
 * UserController Unificato - Estratto da RefereeController funzionante
 * Mantiene tutta la business logic esistente
 */
class UserController extends Controller
{
    use CrudActions;

    protected \$model = User::class;
    protected \$viewPrefix = 'admin.users';
    protected \$routePrefix = 'admin.users';
{$methods}
}";
    }

    /**
     * STEP 3: Pulizia e merge finale
     */
    private function cleanAndMerge()
    {
        $this->info('🧹 STEP 3: Pulizia e merge finale...');

        // Aggiorna routes per puntare al nuovo UserController
        $this->updateRoutesForUserController();

        // Crea alias views per compatibilità
        $this->createViewAliases();

        // Cleanup files obsoleti
        $this->cleanupObsoleteFiles();

        $this->line('✅ Pulizia completata');
        return 0;
    }

    private function updateRoutesForUserController()
    {
        $webRoutesPath = base_path('routes/web.php');

        if (!File::exists($webRoutesPath)) {
            return;
        }

        $content = File::get($webRoutesPath);

        // Sostituzioni chirurgiche solo per referee routes
        $replacements = [
            "Route::resource('referees', RefereeController::class)"
                => "Route::resource('users', UserController::class)\n    ->parameter('users', 'user')",
            'RefereeController::class' => 'UserController::class',
            'admin.referees' => 'admin.users'
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        // Aggiungi redirect per backward compatibility
        if (!str_contains($content, '// Referee backward compatibility')) {
            $content .= "\n// Referee backward compatibility\n";
            $content .= "Route::redirect('/admin/referees', '/admin/users?type=referee', 301);\n";
        }

        File::put($webRoutesPath, $content);
        $this->line('🛤️ Routes aggiornate');
    }

    private function createViewAliases()
    {
        // Crea symlink o copie delle views per compatibilità
        $refereeViewsPath = resource_path('views/admin/referees');
        $userViewsPath = resource_path('views/admin/users');

        if (File::exists($refereeViewsPath) && !File::exists($userViewsPath)) {
            File::copyDirectory($refereeViewsPath, $userViewsPath);
            $this->line('👁️ Views copiate da referees a users');
        }
    }

    private function cleanupObsoleteFiles()
    {
        $obsoleteFiles = [
            // Lista file da pulire dopo verifica
        ];

        foreach ($obsoleteFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->line("🗑️ Rimosso: {$file}");
            }
        }
    }

    private function showAnalysisReport()
    {
        $this->info('');
        $this->info('📊 REPORT ANALISI:');
        $this->info('================');

        foreach ($this->extractedMethods as $controller => $methods) {
            $this->line("📂 {$controller}:");
            foreach ($methods as $method => $info) {
                $this->line('  ✅ {$method} ({$info["line_end"] - $info["line_start"]} linee)');
            }
        }

        $this->info('');
        $this->info('🎯 RACCOMANDAZIONI:');
        $this->line('1. RefereeController: Logica solida, da trasferire in UserController');
        $this->line('2. NotificationController: Mantieni tutto, è critico');
        $this->line('3. TournamentController: Standardizza con CrudActions');
        $this->line('4. Views: Crea aliases per compatibilità');

        $this->warn('');
        $this->warn('⚠️ Esegui backup prima di procedere con --extract');
    }

    private function standardizeExistingControllers()
    {
        $controllersToStandardize = ['TournamentController', 'ClubController'];

        foreach ($controllersToStandardize as $controller) {
            $this->standardizeController($controller);
        }
    }

    private function standardizeController($controllerName)
    {
        $path = app_path("Http/Controllers/Admin/{$controllerName}.php");

        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        // Aggiungi CrudActions trait se mancante
        if (!str_contains($content, 'use CrudActions')) {
            $content = str_replace(
                'use Illuminate\Http\RedirectResponse;',
                "use Illuminate\Http\RedirectResponse;\nuse App\Traits\CrudActions;",
                $content
            );

            $content = str_replace(
                "class {$controllerName} extends Controller\n{",
                "class {$controllerName} extends Controller\n{\n    use CrudActions;\n",
                $content
            );
        }

        File::put($path, $content);
        $this->line("🔧 {$controllerName} standardizzato");
    }

    private function extractHelperMethods()
    {
        // Estrai metodi helper comuni che potrebbero essere condivisi
        $this->line('🔧 Estraendo helper methods...');

        // Questo potrebbe essere un trait separato per metodi comuni
        $helperMethods = [
            'generateRefereeCode',
            'checkRefereeAccess',
            'getAvailableZones'
        ];

        // Per ora li lasciamo nel controller, ma potrebbero diventare un trait
        $this->line('Helper methods identificati: ' . implode(', ', $helperMethods));
    }
}
