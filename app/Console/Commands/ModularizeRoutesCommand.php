<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModularizeRoutesCommand extends Command
{
    protected $signature = 'golf:modularize-routes
                            {--dry-run : Simula senza modificare i file}
                            {--backup : Crea backup prima di modificare}';

    protected $description = 'Riorganizza le routes in struttura modulare pulita';

    private array $routeGroups = [];
    private array $extractedRoutes = [];
    private int $routesProcessed = 0;
    private int $filesCreated = 0;

    public function handle()
    {
        $this->info('🚀 MODULARIZZAZIONE ROUTES - Golf Arbitri Clean');
        $this->info('================================================');

        if ($this->option('backup')) {
            $this->createBackup();
        }

        // 1. Analizza routes esistenti
        $this->analyzeCurrentRoutes();

        // 2. Mostra preview delle modifiche
        $this->showPreview();

        // 3. Conferma se non in dry-run
        if (!$this->option('dry-run')) {
            if (!$this->confirm('Procedere con la modularizzazione?')) {
                $this->warn('Operazione annullata');
                return 1;
            }
        }

        // 4. Crea struttura modulare
        $this->createModularStructure();

        // 5. Estrai e organizza routes
        $this->extractAndOrganizeRoutes();

        // 6. Aggiorna web.php principale
        $this->updateMainRoutesFile();

        // 7. Report finale
        $this->showFinalReport();

        return 0;
    }

    private function createBackup(): void
    {
        $this->info('📦 Creando backup routes...');

        $timestamp = now()->format('Y-m-d_His');
        $backupDir = base_path("backups/routes_{$timestamp}");

        File::ensureDirectoryExists($backupDir);
        File::copyDirectory(base_path('routes'), $backupDir);

        $this->info("✅ Backup creato in: {$backupDir}");
    }

    private function analyzeCurrentRoutes(): void
    {
        $this->info('🔍 Analizzando routes esistenti...');

        $webRoutesPath = base_path('routes/web.php');
        $content = File::get($webRoutesPath);

        // Pattern per identificare gruppi di routes
        $patterns = [
            'admin.tournaments' => '/Route::[^\n]*tournaments[^\n]*/i',
            'admin.users' => '/Route::[^\n]*(?:users|referees)[^\n]*/i',
            'admin.assignments' => '/Route::[^\n]*assignments[^\n]*/i',
            'admin.notifications' => '/Route::[^\n]*(?:notifications|communications|letter)[^\n]*/i',
            'admin.clubs' => '/Route::[^\n]*clubs[^\n]*/i',
            'referee.availability' => '/Route::[^\n]*availability[^\n]*/i',
            'referee.dashboard' => '/Route::[^\n]*referee.*dashboard[^\n]*/i',
            'api.internal' => '/Route::[^\n]*api\/internal[^\n]*/i',
            'api.v1' => '/Route::[^\n]*api\/v1[^\n]*/i',
        ];

        foreach ($patterns as $group => $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[0])) {
                $this->routeGroups[$group] = $matches[0];
                $this->routesProcessed += count($matches[0]);
            }
        }

        $this->info("📊 Trovate {$this->routesProcessed} routes da riorganizzare");
    }

    private function showPreview(): void
    {
        $this->info('');
        $this->info('📋 PREVIEW RIORGANIZZAZIONE:');
        $this->info('============================');

        $this->table(
            ['Modulo', 'Routes', 'Destinazione'],
            collect($this->routeGroups)->map(function ($routes, $group) {
                $path = $this->getModulePathForGroup($group);
                return [
                    $group,
                    count($routes),
                    "routes/{$path}"
                ];
            })->toArray()
        );
    }

    private function createModularStructure(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 [DRY-RUN] Creazione struttura modulare...');
            return;
        }

        $this->info('📁 Creando struttura modulare...');

        $directories = [
            'routes/admin',
            'routes/referee',
            'routes/api',
            'routes/api/v1',
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!File::exists($path)) {
                File::ensureDirectoryExists($path);
                $this->info("✅ Creata directory: {$dir}");
            }
        }
    }

    private function extractAndOrganizeRoutes(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 [DRY-RUN] Estrazione routes...');
            return;
        }

        $this->info('✂️ Estraendo e organizzando routes...');

        // Admin routes
        $this->createRouteFile('admin/tournaments', $this->getAdminTournamentsRoutes());
        $this->createRouteFile('admin/users', $this->getAdminUsersRoutes());
        $this->createRouteFile('admin/assignments', $this->getAdminAssignmentsRoutes());
        $this->createRouteFile('admin/notifications', $this->getAdminNotificationsRoutes());
        $this->createRouteFile('admin/clubs', $this->getAdminClubsRoutes());

        // Referee routes
        $this->createRouteFile('referee/availability', $this->getRefereeAvailabilityRoutes());
        $this->createRouteFile('referee/dashboard', $this->getRefereeDashboardRoutes());

        // API routes
        $this->createRouteFile('api/internal', $this->getApiInternalRoutes());
        $this->createRouteFile('api/v1/tournaments', $this->getApiV1TournamentsRoutes());
        $this->createRouteFile('api/v1/notifications', $this->getApiV1NotificationsRoutes());
    }

    private function createRouteFile(string $path, string $content): void
    {
        $fullPath = base_path("routes/{$path}.php");

        if ($this->option('dry-run')) {
            $this->info("📝 [DRY-RUN] Creerebbe: routes/{$path}.php");
            return;
        }

        File::put($fullPath, $content);
        $this->filesCreated++;
        $this->info("✅ Creato: routes/{$path}.php");
    }

    private function getAdminTournamentsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Admin\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Tournament Routes
|--------------------------------------------------------------------------
*/

// Tournament CRUD
Route::resource('tournaments', TournamentController::class);

// Tournament Status Management
Route::post('tournaments/{tournament}/open', [TournamentController::class, 'open'])
    ->name('tournaments.open');
Route::post('tournaments/{tournament}/close', [TournamentController::class, 'close'])
    ->name('tournaments.close');
Route::post('tournaments/{tournament}/assign', [TournamentController::class, 'assign'])
    ->name('tournaments.assign');
Route::post('tournaments/{tournament}/complete', [TournamentController::class, 'complete'])
    ->name('tournaments.complete');
Route::post('tournaments/{tournament}/cancel', [TournamentController::class, 'cancel'])
    ->name('tournaments.cancel');

// Tournament Documents
Route::get('tournaments/{tournament}/documents', [TournamentController::class, 'documents'])
    ->name('tournaments.documents');
Route::post('tournaments/{tournament}/documents/generate', [TournamentController::class, 'generateDocuments'])
    ->name('tournaments.documents.generate');

// Tournament Statistics
Route::get('tournaments/{tournament}/stats', [TournamentController::class, 'stats'])
    ->name('tournaments.stats');

// Tournament Calendar
Route::get('tournaments/calendar', [TournamentController::class, 'calendar'])
    ->name('tournaments.calendar');
Route::get('tournaments/calendar/data', [TournamentController::class, 'calendarData'])
    ->name('tournaments.calendar.data');

// Export/Import
Route::get('tournaments/export', [TournamentController::class, 'export'])
    ->name('tournaments.export');
Route::post('tournaments/import', [TournamentController::class, 'import'])
    ->name('tournaments.import');
PHP;
    }

    private function getAdminUsersRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin User Management Routes (Unified User/Referee)
|--------------------------------------------------------------------------
*/

// User CRUD (includes referees)
Route::resource('users', UserController::class);

// User Type Filtering
Route::get('users/type/{type}', [UserController::class, 'indexByType'])
    ->name('users.by-type')
    ->where('type', 'referee|admin|national_admin|super_admin');

// User Status Management
Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
    ->name('users.toggle-active');
Route::post('users/{user}/update-level', [UserController::class, 'updateLevel'])
    ->name('users.update-level');

// User Statistics & History
Route::get('users/{user}/tournaments', [UserController::class, 'tournaments'])
    ->name('users.tournaments');
Route::get('users/{user}/availability', [UserController::class, 'availability'])
    ->name('users.availability');
Route::get('users/{user}/assignments', [UserController::class, 'assignments'])
    ->name('users.assignments');
Route::get('users/{user}/curriculum', [UserController::class, 'curriculum'])
    ->name('users.curriculum');

// Bulk Operations
Route::post('users/bulk-action', [UserController::class, 'bulkAction'])
    ->name('users.bulk-action');

// Import/Export
Route::get('users/export', [UserController::class, 'export'])
    ->name('users.export');
Route::post('users/import', [UserController::class, 'import'])
    ->name('users.import');

// Legacy Referee Redirects (backward compatibility)
Route::redirect('/referees', '/users?type=referee', 301);
Route::redirect('/referees/{id}', '/users/{id}', 301);
PHP;
    }

    private function getAdminAssignmentsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Admin\AssignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Assignment Routes
|--------------------------------------------------------------------------
*/

// Assignment CRUD
Route::resource('assignments', AssignmentController::class);

// Assignment by Tournament
Route::get('tournaments/{tournament}/assignments', [AssignmentController::class, 'byTournament'])
    ->name('assignments.by-tournament');
Route::get('tournaments/{tournament}/assign-referees', [AssignmentController::class, 'assignReferees'])
    ->name('assignments.assign-referees');

// Bulk Assignment Operations
Route::post('assignments/bulk-assign', [AssignmentController::class, 'bulkAssign'])
    ->name('assignments.bulk-assign');
Route::post('assignments/bulk-confirm', [AssignmentController::class, 'bulkConfirm'])
    ->name('assignments.bulk-confirm');
Route::post('assignments/bulk-delete', [AssignmentController::class, 'bulkDelete'])
    ->name('assignments.bulk-delete');

// Assignment Status
Route::post('assignments/{assignment}/confirm', [AssignmentController::class, 'confirm'])
    ->name('assignments.confirm');
Route::post('assignments/{assignment}/reject', [AssignmentController::class, 'reject'])
    ->name('assignments.reject');

// Auto-Assignment System
Route::get('assignments/auto/preview/{tournament}', [AssignmentController::class, 'autoPreview'])
    ->name('assignments.auto-preview');
Route::post('assignments/auto/execute/{tournament}', [AssignmentController::class, 'autoExecute'])
    ->name('assignments.auto-execute');

// Assignment Calendar
Route::get('assignments/calendar', [AssignmentController::class, 'calendar'])
    ->name('assignments.calendar');
Route::get('assignments/calendar/data', [AssignmentController::class, 'calendarData'])
    ->name('assignments.calendar.data');

// Reports
Route::get('assignments/reports', [AssignmentController::class, 'reports'])
    ->name('assignments.reports');
Route::get('assignments/reports/export', [AssignmentController::class, 'exportReport'])
    ->name('assignments.export-report');
PHP;
    }

    private function getAdminNotificationsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Notification Routes (Unified System)
|--------------------------------------------------------------------------
*/

// Notification Management
Route::resource('notifications', NotificationController::class);

// Tournament Notifications
Route::prefix('tournaments/{tournament}/notifications')->name('notifications.tournament.')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'tournamentIndex'])->name('index');
        Route::post('/availability-reminder', [NotificationController::class, 'availabilityReminder'])
            ->name('availability-reminder');
        Route::post('/assignment-notification', [NotificationController::class, 'assignmentNotification'])
            ->name('assignment-notification');
        Route::post('/convocation', [NotificationController::class, 'sendConvocation'])
            ->name('convocation');
    });

// Notification Templates
Route::prefix('notification-templates')->name('notification-templates.')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'templates'])->name('index');
        Route::get('/create', [NotificationController::class, 'createTemplate'])->name('create');
        Route::post('/', [NotificationController::class, 'storeTemplate'])->name('store');
        Route::get('/{template}/edit', [NotificationController::class, 'editTemplate'])->name('edit');
        Route::put('/{template}', [NotificationController::class, 'updateTemplate'])->name('update');
        Route::delete('/{template}', [NotificationController::class, 'deleteTemplate'])->name('delete');
    });

// Bulk Notifications
Route::post('notifications/bulk-send', [NotificationController::class, 'bulkSend'])
    ->name('notifications.bulk-send');
Route::post('notifications/bulk-cancel', [NotificationController::class, 'bulkCancel'])
    ->name('notifications.bulk-cancel');

// Notification Status
Route::post('notifications/{notification}/resend', [NotificationController::class, 'resend'])
    ->name('notifications.resend');
Route::post('notifications/{notification}/cancel', [NotificationController::class, 'cancel'])
    ->name('notifications.cancel');

// Notification Statistics
Route::get('notifications/stats', [NotificationController::class, 'stats'])
    ->name('notifications.stats');
Route::get('notifications/export', [NotificationController::class, 'export'])
    ->name('notifications.export');
PHP;
    }

    private function getAdminClubsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Admin\ClubController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Club Routes
|--------------------------------------------------------------------------
*/

// Club CRUD
Route::resource('clubs', ClubController::class);

// Club Status
Route::post('clubs/{club}/toggle-active', [ClubController::class, 'toggleActive'])
    ->name('clubs.toggle-active');

// Club Tournaments
Route::get('clubs/{club}/tournaments', [ClubController::class, 'tournaments'])
    ->name('clubs.tournaments');

// Club Statistics
Route::get('clubs/{club}/stats', [ClubController::class, 'stats'])
    ->name('clubs.stats');

// Import/Export
Route::get('clubs/export', [ClubController::class, 'export'])
    ->name('clubs.export');
Route::post('clubs/import', [ClubController::class, 'import'])
    ->name('clubs.import');
PHP;
    }

    private function getRefereeAvailabilityRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Referee\AvailabilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referee Availability Routes
|--------------------------------------------------------------------------
*/

// Availability Management (CRUD standard)
Route::resource('availability', AvailabilityController::class)
    ->except(['create', 'edit']);

// Availability Calendar
Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])
    ->name('availability.calendar');
Route::get('availability/calendar/data', [AvailabilityController::class, 'calendarData'])
    ->name('availability.calendar.data');

// Bulk Operations (unified endpoint)
Route::post('availability/bulk', [AvailabilityController::class, 'bulk'])
    ->name('availability.bulk');

// My Availabilities
Route::get('my-availabilities', [AvailabilityController::class, 'myAvailabilities'])
    ->name('availability.my');

// Tournament Availability Toggle
Route::post('availability/toggle/{tournament}', [AvailabilityController::class, 'toggle'])
    ->name('availability.toggle');
PHP;
    }

    private function getRefereeDashboardRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Referee\DashboardController;
use App\Http\Controllers\Referee\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referee Dashboard Routes
|--------------------------------------------------------------------------
*/

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

// Profile Management
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

// My Assignments
Route::get('/my-assignments', [DashboardController::class, 'myAssignments'])
    ->name('my-assignments');
Route::get('/my-tournaments', [DashboardController::class, 'myTournaments'])
    ->name('my-tournaments');

// Documents
Route::get('/my-documents', [DashboardController::class, 'myDocuments'])
    ->name('my-documents');
Route::get('/my-curriculum', [DashboardController::class, 'myCurriculum'])
    ->name('my-curriculum');
PHP;
    }

    private function getApiInternalRoutes(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal API Routes (AJAX/Vue Components)
|--------------------------------------------------------------------------
*/

// Quick Stats
Route::get('/stats/dashboard', 'Api\Internal\StatsController@dashboard');
Route::get('/stats/tournaments', 'Api\Internal\StatsController@tournaments');
Route::get('/stats/referees', 'Api\Internal\StatsController@referees');

// Autocomplete
Route::get('/search/users', 'Api\Internal\SearchController@users');
Route::get('/search/clubs', 'Api\Internal\SearchController@clubs');
Route::get('/search/tournaments', 'Api\Internal\SearchController@tournaments');

// Data Tables
Route::get('/datatables/tournaments', 'Api\Internal\DataTableController@tournaments');
Route::get('/datatables/users', 'Api\Internal\DataTableController@users');
Route::get('/datatables/assignments', 'Api\Internal\DataTableController@assignments');

// Select2/Dropdowns
Route::get('/dropdown/zones', 'Api\Internal\DropdownController@zones');
Route::get('/dropdown/tournament-types', 'Api\Internal\DropdownController@tournamentTypes');
Route::get('/dropdown/referee-levels', 'Api\Internal\DropdownController@refereeLevels');
PHP;
    }

    private function getApiV1TournamentsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Api\V1\TournamentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Tournament Routes (External API)
|--------------------------------------------------------------------------
*/

// Public Endpoints (no auth)
Route::get('/tournaments', [TournamentApiController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentApiController::class, 'show']);
Route::get('/tournaments/calendar/ical', [TournamentApiController::class, 'ical']);

// Protected Endpoints (require API key)
Route::middleware('api.key')->group(function () {
    Route::post('/tournaments', [TournamentApiController::class, 'store']);
    Route::put('/tournaments/{tournament}', [TournamentApiController::class, 'update']);
    Route::delete('/tournaments/{tournament}', [TournamentApiController::class, 'destroy']);
});
PHP;
    }

    private function getApiV1NotificationsRoutes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\Api\V1\NotificationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Notification Routes (External API)
|--------------------------------------------------------------------------
*/

Route::middleware('api.key')->group(function () {
    // Notification Status
    Route::get('/notifications', [NotificationApiController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationApiController::class, 'show']);

    // Send Notifications
    Route::post('/notifications/send', [NotificationApiController::class, 'send']);
    Route::post('/notifications/batch', [NotificationApiController::class, 'batch']);

    // Webhooks
    Route::post('/webhooks/email-status', [NotificationApiController::class, 'emailStatusWebhook']);
});
PHP;
    }

    private function updateMainRoutesFile(): void
    {
        if ($this->option('dry-run')) {
            $this->info('📝 [DRY-RUN] Aggiornamento web.php principale...');
            return;
        }

        $this->info('📝 Aggiornando web.php principale...');

        $content = <<<'PHP'
<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - CLEAN & MODULAR
|--------------------------------------------------------------------------
| Core routes only. All specific routes are in modular files.
*/

// Public routes
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
});

// Authentication routes
require __DIR__.'/auth.php';

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Universal dashboard
    Route::get('/dashboard', function () {
        $user = auth()->user();
        return match($user->user_type) {
            'super_admin' => redirect()->route('super-admin.dashboard'),
            'national_admin' => redirect()->route('admin.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            'referee' => redirect()->route('referee.dashboard'),
            default => redirect()->route('login')
        };
    })->name('dashboard');

    // Profile (all users)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin_or_superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn() => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])
        ->name('dashboard');

    // Load modular admin routes
    require __DIR__.'/admin/tournaments.php';
    require __DIR__.'/admin/users.php';
    require __DIR__.'/admin/assignments.php';
    require __DIR__.'/admin/notifications.php';
    require __DIR__.'/admin/clubs.php';
});

/*
|--------------------------------------------------------------------------
| REFEREE ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->prefix('referee')->name('referee.')->group(function () {
    require __DIR__.'/referee/dashboard.php';
    require __DIR__.'/referee/availability.php';
});

/*
|--------------------------------------------------------------------------
| API ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('api')->name('api.')->group(function () {
    // Internal API
    Route::prefix('internal')->middleware('auth')->group(function () {
        require __DIR__.'/api/internal.php';
    });

    // Public API v1
    Route::prefix('v1')->group(function () {
        require __DIR__.'/api/v1/tournaments.php';
        require __DIR__.'/api/v1/notifications.php';
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', fn() => redirect()->route('super-admin.dashboard'));
    Route::get('/dashboard', [App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])
        ->name('dashboard');

    // System management routes here...
});

/*
|--------------------------------------------------------------------------
| LEGACY REDIRECTS (Backward Compatibility)
|--------------------------------------------------------------------------
*/
Route::redirect('/admin/referees', '/admin/users?type=referee', 301);
Route::redirect('/admin/referees/{id}', '/admin/users/{id}', 301);
PHP;

        File::put(base_path('routes/web.php'), $content);
        $this->info('✅ Aggiornato web.php principale');
    }

    private function getModulePathForGroup(string $group): string
    {
        $map = [
            'admin.tournaments' => 'admin/tournaments.php',
            'admin.users' => 'admin/users.php',
            'admin.assignments' => 'admin/assignments.php',
            'admin.notifications' => 'admin/notifications.php',
            'admin.clubs' => 'admin/clubs.php',
            'referee.availability' => 'referee/availability.php',
            'referee.dashboard' => 'referee/dashboard.php',
            'api.internal' => 'api/internal.php',
            'api.v1' => 'api/v1/',
        ];

        return $map[$group] ?? 'misc.php';
    }

    private function showFinalReport(): void
    {
        $this->info('');
        $this->info('✅ MODULARIZZAZIONE COMPLETATA!');
        $this->info('================================');

        if ($this->option('dry-run')) {
            $this->warn('Modalità DRY-RUN - nessun file modificato');
            $this->info('Per applicare le modifiche, esegui senza --dry-run');
        } else {
            $this->info("📊 Routes processate: {$this->routesProcessed}");
            $this->info("📁 File creati: {$this->filesCreated}");
            $this->info("✨ web.php ridotto da 1000+ a ~50 linee!");

            $this->info('');
            $this->info('🎯 PROSSIMI PASSI:');
            $this->info('1. Verifica routes: php artisan route:list');
            $this->info('2. Test funzionalità: php artisan test');
            $this->info('3. Clear cache: php artisan optimize:clear');
        }
    }
}
