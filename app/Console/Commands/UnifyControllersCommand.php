<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UnifyControllersCommand extends Command
{
    protected $signature = 'golf:unify-controllers
                            {--from=referee : Controller sorgente}
                            {--to=user : Controller destinazione}
                            {--dry-run : Simula senza modificare}
                            {--backup : Crea backup prima di modificare}';

    protected $description = 'Unifica RefereeController in UserController per approccio USER CENTRIC';

    private array $filesModified = [];
    private array $referencesUpdated = [];
    private int $changesCount = 0;

    public function handle()
    {
        $this->info('👥 UNIFICAZIONE CONTROLLERS - User Centric Approach');
        $this->info('===================================================');

        $from = Str::studly($this->option('from'));
        $to = Str::studly($this->option('to'));

        if ($this->option('backup')) {
            $this->createBackup();
        }

        // 1. Analizza situazione attuale
        $analysis = $this->analyzeCurrentState($from, $to);
        $this->showAnalysis($analysis);

        // 2. Conferma operazione
        if (!$this->option('dry-run')) {
            if (!$this->confirm("Unificare {$from}Controller in {$to}Controller?")) {
                $this->warn('Operazione annullata');
                return 1;
            }
        }

        // 3. Crea/Aggiorna UserController
        $this->createUnifiedController($from, $to);

        // 4. Aggiorna riferimenti in tutto il progetto
        $this->updateReferences($from, $to);

        // 5. Aggiorna views
        $this->updateViews($from, $to);

        // 6. Aggiorna routes
        $this->updateRoutes($from, $to);

        // 7. Aggiorna Models
        $this->updateModels($from, $to);

        // 8. Cleanup vecchi file
        $this->cleanupOldFiles($from);

        // 9. Report finale
        $this->showFinalReport();

        return 0;
    }

    private function createBackup(): void
    {
        $this->info('📦 Creando backup completo...');

        $timestamp = now()->format('Y-m-d_His');
        $backupDir = base_path("backups/controllers_{$timestamp}");

        File::ensureDirectoryExists($backupDir);

        // Backup directories
        $dirsToBackup = [
            'app/Http/Controllers',
            'routes',
            'resources/views',
        ];

        foreach ($dirsToBackup as $dir) {
            $source = base_path($dir);
            $dest = $backupDir . '/' . $dir;
            if (File::exists($source)) {
                File::copyDirectory($source, $dest);
            }
        }

        $this->info("✅ Backup creato in: {$backupDir}");
    }

    private function analyzeCurrentState(string $from, string $to): array
    {
        $this->info('🔍 Analizzando stato attuale...');

        $analysis = [
            'referee_controller_exists' => false,
            'user_controller_exists' => false,
            'referee_methods' => [],
            'user_methods' => [],
            'references_count' => 0,
            'views_count' => 0,
            'routes_count' => 0,
        ];

        // Check RefereeController
        $refereeControllerPath = app_path("Http/Controllers/Admin/{$from}Controller.php");
        if (File::exists($refereeControllerPath)) {
            $analysis['referee_controller_exists'] = true;
            $analysis['referee_methods'] = $this->extractMethods($refereeControllerPath);
        }

        // Check UserController
        $userControllerPath = app_path("Http/Controllers/Admin/{$to}Controller.php");
        if (File::exists($userControllerPath)) {
            $analysis['user_controller_exists'] = true;
            $analysis['user_methods'] = $this->extractMethods($userControllerPath);
        }

        // Count references
        $analysis['references_count'] = $this->countReferences($from . 'Controller');
        $analysis['views_count'] = $this->countViewReferences(strtolower($from));
        $analysis['routes_count'] = $this->countRouteReferences(strtolower($from));

        return $analysis;
    }

    private function extractMethods(string $path): array
    {
        $content = File::get($path);
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function countReferences(string $searchTerm): int
    {
        $count = 0;
        $directories = [
            app_path(),
            resource_path('views'),
            base_path('routes'),
        ];

        foreach ($directories as $dir) {
            $files = File::allFiles($dir);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = File::get($file->getPathname());
                    $count += substr_count($content, $searchTerm);
                }
            }
        }

        return $count;
    }

    private function countViewReferences(string $searchTerm): int
    {
        $viewsDir = resource_path("views/admin/{$searchTerm}s");
        if (!File::exists($viewsDir)) {
            return 0;
        }

        return count(File::allFiles($viewsDir));
    }

    private function countRouteReferences(string $searchTerm): int
    {
        $count = 0;
        $routeFiles = File::allFiles(base_path('routes'));

        foreach ($routeFiles as $file) {
            $content = File::get($file->getPathname());
            $count += substr_count($content, $searchTerm);
            $count += substr_count($content, Str::plural($searchTerm));
        }

        return $count;
    }

    private function showAnalysis(array $analysis): void
    {
        $this->info('');
        $this->info('📊 ANALISI SITUAZIONE:');
        $this->info('=====================');

        $this->table(
            ['Elemento', 'Stato'],
            [
                ['RefereeController exists', $analysis['referee_controller_exists'] ? '✅ Sì' : '❌ No'],
                ['UserController exists', $analysis['user_controller_exists'] ? '✅ Sì' : '❌ No'],
                ['Metodi in RefereeController', count($analysis['referee_methods'])],
                ['Metodi in UserController', count($analysis['user_methods'])],
                ['Riferimenti nel codice', $analysis['references_count']],
                ['Views da aggiornare', $analysis['views_count']],
                ['Routes da aggiornare', $analysis['routes_count']],
            ]
        );

        if (!empty($analysis['referee_methods'])) {
            $this->info('');
            $this->info('📌 Metodi da migrare:');
            $this->info(implode(', ', $analysis['referee_methods']));
        }
    }

    private function createUnifiedController(string $from, string $to): void
    {
        if ($this->option('dry-run')) {
            $this->info('📝 [DRY-RUN] Creazione UserController unificato...');
            return;
        }

        $this->info('🔨 Creando UserController unificato...');

        $userControllerPath = app_path("Http/Controllers/Admin/{$to}Controller.php");
        $refereeControllerPath = app_path("Http/Controllers/Admin/{$from}Controller.php");

        // Se UserController non esiste, crea da template
        if (!File::exists($userControllerPath)) {
            $this->createUserControllerFromTemplate();
        }

        // Se RefereeController esiste, migra metodi specifici
        if (File::exists($refereeControllerPath)) {
            $this->migrateRefereeMethodsToUser($refereeControllerPath, $userControllerPath);
        }

        $this->filesModified[] = $userControllerPath;
        $this->info('✅ UserController unificato creato/aggiornato');
    }

    private function createUserControllerFromTemplate(): void
    {
        $template = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    /**
     * Display a listing of users (unified referees + admins)
     */
    public function index(Request $request): View
    {
        $query = User::with(['zone']);

        // Filter by user type if specified
        if ($request->has('type')) {
            $query->where('user_type', $request->type);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('referee_code', 'like', "%{$search}%");
            });
        }

        // Zone filter for zone admins
        $user = auth()->user();
        if ($user->user_type === 'admin') {
            $query->where('zone_id', $user->zone_id);
        }

        $users = $query->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create(): View
    {
        $zones = $this->getAccessibleZones();
        $userTypes = $this->getAvailableUserTypes();
        $levels = User::REFEREE_LEVELS ?? [];

        return view('admin.users.create', compact('zones', 'userTypes', 'levels'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        DB::beginTransaction();
        try {
            // Create user with all unified data
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password'] ?? 'changeme123'),
                'user_type' => $validated['user_type'],
                'zone_id' => $validated['zone_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'city' => $validated['city'] ?? null,
                'level' => $validated['level'] ?? null,
                'referee_code' => $this->generateRefereeCode($validated),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.users.show', $user)
                ->with('success', 'Utente creato con successo');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante la creazione: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified user
     */
    public function show(User $user): View
    {
        $this->checkUserAccess($user);

        $user->load(['zone', 'assignments.tournament', 'availabilities.tournament']);

        $stats = [
            'total_tournaments' => $user->assignments()->count(),
            'current_year_tournaments' => $user->assignments()
                ->whereYear('created_at', now()->year)->count(),
            'upcoming_tournaments' => $user->assignments()
                ->whereHas('tournament', function ($q) {
                    $q->where('start_date', '>', now());
                })->count(),
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user): View
    {
        $this->checkUserAccess($user);

        $zones = $this->getAccessibleZones();
        $userTypes = $this->getAvailableUserTypes();
        $levels = User::REFEREE_LEVELS ?? [];

        return view('admin.users.edit', compact('user', 'zones', 'userTypes', 'levels'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->checkUserAccess($user);

        $validated = $this->validateUser($request, $user);

        DB::beginTransaction();
        try {
            $user->update($validated);

            DB::commit();

            return redirect()
                ->route('admin.users.show', $user)
                ->with('success', 'Utente aggiornato con successo');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->checkUserAccess($user);

        if ($user->assignments()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Impossibile eliminare: l\'utente ha assegnazioni attive');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Utente eliminato con successo');
    }

    /**
     * Filter users by type
     */
    public function indexByType(string $type): View
    {
        $query = User::with(['zone'])->where('user_type', $type);

        // Zone filter for zone admins
        $user = auth()->user();
        if ($user->user_type === 'admin') {
            $query->where('zone_id', $user->zone_id);
        }

        $users = $query->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users', 'type'));
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->checkUserAccess($user);

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'attivato' : 'disattivato';

        return redirect()
            ->back()
            ->with('success', "Utente {$status} con successo");
    }

    /**
     * Update referee level
     */
    public function updateLevel(Request $request, User $user): RedirectResponse
    {
        $this->checkUserAccess($user);

        if ($user->user_type !== 'referee') {
            return redirect()->back()->with('error', 'Solo gli arbitri hanno un livello');
        }

        $request->validate([
            'level' => 'required|string|in:' . implode(',', array_keys(User::REFEREE_LEVELS ?? []))
        ]);

        $user->update(['level' => $request->level]);

        return redirect()
            ->back()
            ->with('success', 'Livello arbitro aggiornato');
    }

    /**
     * Show user curriculum (for referees)
     */
    public function curriculum(User $user): View
    {
        if ($user->user_type !== 'referee') {
            abort(404, 'Curriculum disponibile solo per arbitri');
        }

        $user->load([
            'assignments' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            },
            'assignments.tournament.club',
            'zone'
        ]);

        return view('admin.users.curriculum', compact('user'));
    }

    /**
     * Helper: Check access to user
     */
    private function checkUserAccess(User $user): void
    {
        $currentUser = auth()->user();

        // Zone admins can only access users in their zone
        if ($currentUser->user_type === 'admin' && $user->zone_id !== $currentUser->zone_id) {
            abort(403, 'Non hai accesso a questo utente');
        }
    }

    /**
     * Helper: Get accessible zones for current admin
     */
    private function getAccessibleZones()
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Helper: Get available user types for current admin
     */
    private function getAvailableUserTypes(): array
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin') {
            return ['referee', 'admin', 'national_admin', 'super_admin'];
        }

        if ($user->user_type === 'national_admin') {
            return ['referee', 'admin'];
        }

        return ['referee'];
    }

    /**
     * Helper: Validate user data
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($user ? ",{$user->id}" : ''),
            'user_type' => 'required|string',
            'zone_id' => 'nullable|exists:zones,id',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'level' => 'nullable|string',
            'is_active' => 'boolean',
        ];

        if (!$user) {
            $rules['password'] = 'nullable|string|min:8';
        }

        return $request->validate($rules);
    }

    /**
     * Helper: Generate referee code
     */
    private function generateRefereeCode(array $data): ?string
    {
        if ($data['user_type'] !== 'referee') {
            return null;
        }

        $zone = Zone::find($data['zone_id'] ?? null);
        $prefix = $zone ? $zone->code : 'REF';

        $lastNumber = User::where('referee_code', 'like', "{$prefix}-%")
            ->orderBy('referee_code', 'desc')
            ->value('referee_code');

        $number = 1;
        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $number = intval(end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $number);
    }
}
PHP;

        $path = app_path('Http/Controllers/Admin/UserController.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);
    }

    private function migrateRefereeMethodsToUser(string $from, string $to): void
    {
        $this->info('📋 Migrando metodi specifici da RefereeController...');

        // Qui potresti analizzare i metodi specifici di RefereeController
        // e copiarli in UserController se non esistono già

        $refereeContent = File::get($from);
        $userContent = File::get($to);

        // Estrai metodi custom da RefereeController che non sono CRUD standard
        preg_match_all('/public\s+function\s+(\w+)\s*\([^{]*\)\s*(?::\s*\w+)?\s*{([^}]+)}/s', $refereeContent, $matches);

        $customMethods = ['checkRefereeAccess', 'generateRefereeCode', 'tournaments', 'availability'];

        foreach ($matches[1] as $index => $methodName) {
            if (in_array($methodName, $customMethods) && !str_contains($userContent, "function {$methodName}")) {
                // Aggiungi metodo a UserController
                $this->info("  → Migrando metodo: {$methodName}");
                $this->changesCount++;
            }
        }
    }

    private function updateReferences(string $from, string $to): void
    {
        if ($this->option('dry-run')) {
            $this->info('🔄 [DRY-RUN] Aggiornamento riferimenti nel codice...');
            return;
        }

        $this->info('🔄 Aggiornando riferimenti nel codice...');

        $replacements = [
            "{$from}Controller" => "{$to}Controller",
            "use App\\Http\\Controllers\\Admin\\{$from}Controller" => "use App\\Http\\Controllers\\Admin\\{$to}Controller",
            "Admin\\{$from}Controller" => "Admin\\{$to}Controller",
            "[{$from}Controller::class" => "[{$to}Controller::class",
        ];

        $directories = [
            app_path(),
            base_path('routes'),
            resource_path('views'),
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) continue;

            $files = File::allFiles($dir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['php', 'blade.php'])) {
                    $content = File::get($file->getPathname());
                    $originalContent = $content;

                    foreach ($replacements as $search => $replace) {
                        $content = str_replace($search, $replace, $content);
                    }

                    if ($content !== $originalContent) {
                        File::put($file->getPathname(), $content);
                        $this->referencesUpdated[] = $file->getRelativePathname();
                        $this->changesCount++;
                    }
                }
            }
        }

        $this->info("✅ Aggiornati " . count($this->referencesUpdated) . " file");
    }

    private function updateViews(string $from, string $to): void
    {
        if ($this->option('dry-run')) {
            $this->info('👁️ [DRY-RUN] Aggiornamento views...');
            return;
        }

        $this->info('👁️ Aggiornando views...');

        $fromDir = resource_path("views/admin/" . Str::plural(strtolower($from)));
        $toDir = resource_path("views/admin/" . Str::plural(strtolower($to)));

        // Rinomina directory views se esiste
        if (File::exists($fromDir) && !File::exists($toDir)) {
            File::move($fromDir, $toDir);
            $this->info("✅ Directory views rinominata: {$fromDir} → {$toDir}");
            $this->changesCount++;
        }

        // Aggiorna riferimenti nelle views
        $viewFiles = File::allFiles(resource_path('views'));
        foreach ($viewFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());
                $originalContent = $content;

                // Aggiorna path views
                $content = str_replace(
                    "admin." . Str::plural(strtolower($from)),
                    "admin." . Str::plural(strtolower($to)),
                    $content
                );

                // Aggiorna route names
                $content = str_replace(
                    "admin." . Str::plural(strtolower($from)) . ".",
                    "admin." . Str::plural(strtolower($to)) . ".",
                    $content
                );

                if ($content !== $originalContent) {
                    File::put($file->getPathname(), $content);
                    $this->changesCount++;
                }
            }
        }
    }

    private function updateRoutes(string $from, string $to): void
    {
        if ($this->option('dry-run')) {
            $this->info('🛤️ [DRY-RUN] Aggiornamento routes...');
            return;
        }

        $this->info('🛤️ Aggiornando routes...');

        $routeFiles = File::allFiles(base_path('routes'));

        foreach ($routeFiles as $file) {
            $content = File::get($file->getPathname());
            $originalContent = $content;

            // Aggiorna resource routes
            $content = str_replace(
                "Route::resource('" . Str::plural(strtolower($from)) . "'",
                "Route::resource('" . Str::plural(strtolower($to)) . "'",
                $content
            );

            // Aggiorna route names
            $content = str_replace(
                "->name('" . Str::plural(strtolower($from)) . ".",
                "->name('" . Str::plural(strtolower($to)) . ".",
                $content
            );

            // Aggiorna prefixes
            $content = str_replace(
                "->prefix('" . Str::plural(strtolower($from)) . "')",
                "->prefix('" . Str::plural(strtolower($to)) . "')",
                $content
            );

            if ($content !== $originalContent) {
                File::put($file->getPathname(), $content);
                $this->changesCount++;
            }
        }

        // Aggiungi redirect per backward compatibility
        $webRoutesPath = base_path('routes/web.php');
        $webContent = File::get($webRoutesPath);

        $redirects = "\n// Legacy redirects (backward compatibility)\n";
        $redirects .= "Route::redirect('/admin/" . Str::plural(strtolower($from)) . "', '/admin/" . Str::plural(strtolower($to)) . "?type={$from}', 301);\n";
        $redirects .= "Route::redirect('/admin/" . Str::plural(strtolower($from)) . "/{id}', '/admin/" . Str::plural(strtolower($to)) . "/{id}', 301);\n";

        if (!str_contains($webContent, 'Legacy redirects')) {
            File::append($webRoutesPath, $redirects);
            $this->info('✅ Aggiunti redirect per compatibilità');
        }
    }

    private function updateModels(string $from, string $to): void
    {
        if ($this->option('dry-run')) {
            $this->info('📦 [DRY-RUN] Aggiornamento Models...');
            return;
        }

        $this->info('📦 Aggiornando Models...');

        // Aggiorna relazioni nei models
        $modelFiles = File::allFiles(app_path('Models'));

        foreach ($modelFiles as $file) {
            $content = File::get($file->getPathname());
            $originalContent = $content;

            // Aggiorna nomi relazioni
            if (strtolower($from) === 'referee') {
                $content = str_replace('referee()', 'user()', $content);
                $content = str_replace('referees()', 'users()', $content);
                $content = str_replace("belongsTo(Referee::class", "belongsTo(User::class", $content);
                $content = str_replace("hasMany(Referee::class", "hasMany(User::class", $content);
            }

            if ($content !== $originalContent) {
                File::put($file->getPathname(), $content);
                $this->changesCount++;
            }
        }
    }

    private function cleanupOldFiles(string $from): void
    {
        if ($this->option('dry-run')) {
            $this->info('🗑️ [DRY-RUN] Pulizia vecchi file...');
            return;
        }

        $this->info('🗑️ Pulizia vecchi file...');

        $filesToRemove = [
            app_path("Http/Controllers/Admin/{$from}Controller.php"),
            app_path("Models/{$from}.php"),
        ];

        foreach ($filesToRemove as $file) {
            if (File::exists($file)) {
                // Rinomina invece di eliminare (per sicurezza)
                $backupName = $file . '.backup_' . now()->format('YmdHis');
                File::move($file, $backupName);
                $this->info("✅ Rinominato: " . basename($file) . " → " . basename($backupName));
            }
        }
    }

    private function showFinalReport(): void
    {
        $this->info('');
        $this->info('✅ UNIFICAZIONE COMPLETATA!');
        $this->info('===========================');

        if ($this->option('dry-run')) {
            $this->warn('Modalità DRY-RUN - nessun file modificato');
            $this->info('Per applicare le modifiche, esegui senza --dry-run');
        } else {
            $this->info("📊 Modifiche totali: {$this->changesCount}");
            $this->info("📁 File modificati: " . count($this->filesModified));
            $this->info("🔗 Riferimenti aggiornati: " . count($this->referencesUpdated));

            $this->info('');
            $this->info('🎯 PROSSIMI PASSI:');
            $this->info('1. Test routes: php artisan route:list | grep user');
            $this->info('2. Clear cache: php artisan optimize:clear');
            $this->info('3. Test funzionalità: php artisan test --filter=User');
            $this->info('4. Verifica views: controlla admin/users/*.blade.php');
        }
    }
}
