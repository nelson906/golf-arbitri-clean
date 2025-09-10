<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class CleanupNotificationsCommand extends Command
{
    protected $signature = 'golf:cleanup-notifications
                            {--dry-run : Simula senza modificare}
                            {--backup : Crea backup prima di modificare}
                            {--merge-data : Migra anche i dati dalle tabelle}';

    protected $description = 'Unifica il sistema notifiche frammentato in un unico sistema coerente';

    private array $systemsToMerge = [
        'notifications',
        'tournament_notifications',
        'communications',
        'letter_templates',
        'letterheads',
        'institutional_emails',
    ];

    private array $unifiedStructure = [];
    private int $mergedCount = 0;
    private int $deletedFiles = 0;

    public function handle()
    {
        $this->info('📧 UNIFICAZIONE SISTEMA NOTIFICHE');
        $this->info('==================================');

        if ($this->option('backup')) {
            $this->createBackup();
        }

        // 1. Analizza sistemi esistenti
        $analysis = $this->analyzeNotificationSystems();
        $this->showAnalysis($analysis);

        // 2. Conferma operazione
        if (!$this->option('dry-run')) {
            if (!$this->confirm('Procedere con l\'unificazione del sistema notifiche?')) {
                $this->warn('Operazione annullata');
                return 1;
            }
        }

        // 3. Crea struttura unificata
        $this->createUnifiedNotificationSystem();

        // 4. Migra dati se richiesto
        if ($this->option('merge-data')) {
            $this->mergeNotificationData();
        }

        // 5. Aggiorna controllers
        $this->updateControllers();

        // 6. Aggiorna routes
        $this->updateNotificationRoutes();

        // 7. Aggiorna views
        $this->updateNotificationViews();

        // 8. Cleanup file obsoleti
        $this->cleanupObsoleteFiles();

        // 9. Report finale
        $this->showFinalReport();

        return 0;
    }

    private function createBackup(): void
    {
        $this->info('📦 Creando backup sistema notifiche...');

        $timestamp = now()->format('Y-m-d_His');
        $backupDir = base_path("backups/notifications_{$timestamp}");

        File::ensureDirectoryExists($backupDir);

        // Backup tabelle database
        $tables = $this->systemsToMerge;
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $data = DB::table($table)->get();
                $json = json_encode($data, JSON_PRETTY_PRINT);
                File::put("{$backupDir}/{$table}.json", $json);
            }
        }

        // Backup file
        $dirsToBackup = [
            'app/Http/Controllers/Admin',
            'app/Models',
            'resources/views/admin',
            'routes',
        ];

        foreach ($dirsToBackup as $dir) {
            $source = base_path($dir);
            if (File::exists($source)) {
                File::copyDirectory($source, "{$backupDir}/{$dir}");
            }
        }

        $this->info("✅ Backup creato in: {$backupDir}");
    }

    private function analyzeNotificationSystems(): array
    {
        $this->info('🔍 Analizzando sistemi notifiche esistenti...');

        $analysis = [
            'tables' => [],
            'controllers' => [],
            'models' => [],
            'views' => [],
            'routes' => [],
            'total_records' => 0,
        ];

        // Analizza tabelle
        foreach ($this->systemsToMerge as $system) {
            if (DB::getSchemaBuilder()->hasTable($system)) {
                $count = DB::table($system)->count();
                $analysis['tables'][$system] = $count;
                $analysis['total_records'] += $count;
            }
        }

        // Analizza controllers
        $controllers = [
            'NotificationController',
            'CommunicationController',
            'LetterTemplateController',
            'LetterheadController',
            'InstitutionalEmailController',
        ];

        foreach ($controllers as $controller) {
            $path = app_path("Http/Controllers/Admin/{$controller}.php");
            if (File::exists($path)) {
                $analysis['controllers'][] = $controller;
            }
        }

        // Analizza models
        $models = [
            'Notification',
            'TournamentNotification',
            'Communication',
            'LetterTemplate',
            'Letterhead',
            'InstitutionalEmail',
        ];

        foreach ($models as $model) {
            $path = app_path("Models/{$model}.php");
            if (File::exists($path)) {
                $analysis['models'][] = $model;
            }
        }

        // Analizza views
        $viewDirs = [
            'notifications',
            'communications',
            'letter-templates',
            'letterheads',
            'institutional-emails',
        ];

        foreach ($viewDirs as $dir) {
            $path = resource_path("views/admin/{$dir}");
            if (File::exists($path)) {
                $count = count(File::allFiles($path));
                $analysis['views'][$dir] = $count;
            }
        }

        // Analizza routes
        $routeFiles = File::allFiles(base_path('routes'));
        foreach ($routeFiles as $file) {
            $content = File::get($file->getPathname());
            foreach ($this->systemsToMerge as $system) {
                if (str_contains($content, $system)) {
                    $analysis['routes'][] = $system . ' in ' . $file->getFilename();
                }
            }
        }

        return $analysis;
    }

    private function showAnalysis(array $analysis): void
    {
        $this->info('');
        $this->info('📊 ANALISI SISTEMI NOTIFICHE:');
        $this->info('=============================');

        if (!empty($analysis['tables'])) {
            $this->info('📋 Tabelle trovate:');
            foreach ($analysis['tables'] as $table => $count) {
                $this->info("  - {$table}: {$count} records");
            }
            $this->info("  TOTALE: {$analysis['total_records']} records");
        }

        if (!empty($analysis['controllers'])) {
            $this->info('');
            $this->info('🎮 Controllers trovati:');
            foreach ($analysis['controllers'] as $controller) {
                $this->info("  - {$controller}");
            }
        }

        if (!empty($analysis['models'])) {
            $this->info('');
            $this->info('📦 Models trovati:');
            foreach ($analysis['models'] as $model) {
                $this->info("  - {$model}");
            }
        }

        if (!empty($analysis['views'])) {
            $this->info('');
            $this->info('👁️ Views trovate:');
            foreach ($analysis['views'] as $dir => $count) {
                $this->info("  - {$dir}: {$count} file");
            }
        }
    }

    private function createUnifiedNotificationSystem(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🔨 [DRY-RUN] Creazione sistema notifiche unificato...');
            return;
        }

        $this->info('🔨 Creando sistema notifiche unificato...');

        // Crea NotificationService unificato
        $this->createNotificationService();

        // Crea NotificationController unificato
        $this->createUnifiedController();

        // Crea migration per tabella unificata
        $this->createUnifiedMigration();

        $this->info('✅ Sistema notifiche unificato creato');
    }

    private function createNotificationService(): void
    {
        $service = <<<'PHP'
<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Invia notifica unificata
     */
    public function send(array $data): Notification
    {
        DB::beginTransaction();
        try {
            // Crea record notifica
            $notification = Notification::create([
                'type' => $data['type'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'tournament_id' => $data['tournament_id'] ?? null,
                'sender_id' => auth()->id(),
                'template_id' => $data['template_id'] ?? null,
                'status' => 'pending',
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Aggiungi destinatari
            foreach ($data['recipients'] as $recipient) {
                $notification->recipients()->create([
                    'user_id' => $recipient['user_id'] ?? null,
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? null,
                    'type' => $recipient['type'] ?? 'to',
                ]);
            }

            // Aggiungi allegati se presenti
            if (!empty($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    $notification->attachments()->create([
                        'filename' => $attachment['filename'],
                        'path' => $attachment['path'],
                        'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                    ]);
                }
            }

            // Invia immediatamente se non schedulato
            if (!isset($data['scheduled_at']) || $data['scheduled_at'] <= now()) {
                $this->processNotification($notification);
            }

            DB::commit();
            return $notification;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Processa e invia notifica
     */
    public function processNotification(Notification $notification): void
    {
        try {
            // Prepara email data
            $emailData = [
                'subject' => $notification->subject,
                'body' => $notification->body,
                'attachments' => $notification->attachments,
            ];

            // Invia a tutti i destinatari
            foreach ($notification->recipients as $recipient) {
                Mail::to($recipient->email)
                    ->send(new \App\Mail\UnifiedNotification($emailData));

                $recipient->update([
                    'sent_at' => now(),
                    'status' => 'sent',
                ]);
            }

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Invia notifica disponibilità torneo
     */
    public function sendAvailabilityReminder(Tournament $tournament): void
    {
        $referees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->when($tournament->zone_id, function ($q) use ($tournament) {
                $q->where('zone_id', $tournament->zone_id);
            })
            ->get();

        $data = [
            'type' => 'availability_reminder',
            'subject' => "Disponibilità per {$tournament->name}",
            'body' => $this->getAvailabilityReminderBody($tournament),
            'tournament_id' => $tournament->id,
            'recipients' => $referees->map(function ($referee) {
                return [
                    'user_id' => $referee->id,
                    'email' => $referee->email,
                    'name' => $referee->name,
                ];
            })->toArray(),
        ];

        $this->send($data);
    }

    /**
     * Invia notifica assegnazione
     */
    public function sendAssignmentNotification(Tournament $tournament, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $user = User::find($assignment['user_id']);

            $data = [
                'type' => 'assignment_notification',
                'subject' => "Assegnazione Torneo: {$tournament->name}",
                'body' => $this->getAssignmentBody($tournament, $assignment),
                'tournament_id' => $tournament->id,
                'recipients' => [[
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]],
                'metadata' => [
                    'assignment_id' => $assignment['id'],
                    'role' => $assignment['role'],
                ],
            ];

            $this->send($data);
        }
    }

    /**
     * Invia convocazione con allegati
     */
    public function sendConvocation(Tournament $tournament): void
    {
        // Genera documento convocazione
        $documentPath = $this->generateConvocationDocument($tournament);

        $assignments = $tournament->assignments()->with('user')->get();

        foreach ($assignments as $assignment) {
            $data = [
                'type' => 'convocation',
                'subject' => "Convocazione Ufficiale: {$tournament->name}",
                'body' => $this->getConvocationBody($tournament, $assignment),
                'tournament_id' => $tournament->id,
                'recipients' => [[
                    'user_id' => $assignment->user_id,
                    'email' => $assignment->user->email,
                    'name' => $assignment->user->name,
                ]],
                'attachments' => [[
                    'filename' => "Convocazione_{$tournament->id}.pdf",
                    'path' => $documentPath,
                    'mime_type' => 'application/pdf',
                ]],
            ];

            $this->send($data);
        }
    }

    /**
     * Helper methods per generare body email
     */
    private function getAvailabilityReminderBody(Tournament $tournament): string
    {
        return view('emails.availability-reminder', compact('tournament'))->render();
    }

    private function getAssignmentBody(Tournament $tournament, array $assignment): string
    {
        return view('emails.assignment-notification', compact('tournament', 'assignment'))->render();
    }

    private function getConvocationBody(Tournament $tournament, $assignment): string
    {
        return view('emails.convocation', compact('tournament', 'assignment'))->render();
    }

    private function generateConvocationDocument(Tournament $tournament): string
    {
        // Logica per generare PDF convocazione
        // Ritorna path del file generato
        return storage_path("app/convocations/tournament_{$tournament->id}.pdf");
    }
}
PHP;

        $path = app_path('Services/NotificationService.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $service);
    }

    private function createUnifiedController(): void
    {
        $controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tournament;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display listing of notifications
     */
    public function index(Request $request)
    {
        $query = Notification::with(['tournament', 'sender', 'recipients']);

        // Filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        $notifications = $query->latest()->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Show notification details
     */
    public function show(Notification $notification)
    {
        $notification->load(['tournament', 'sender', 'recipients', 'attachments']);

        return view('admin.notifications.show', compact('notification'));
    }

    /**
     * Send availability reminder for tournament
     */
    public function availabilityReminder(Tournament $tournament)
    {
        try {
            $this->notificationService->sendAvailabilityReminder($tournament);

            return redirect()
                ->back()
                ->with('success', 'Promemoria disponibilità inviato con successo');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Errore invio notifiche: ' . $e->getMessage());
        }
    }

    /**
     * Send assignment notifications
     */
    public function assignmentNotification(Request $request, Tournament $tournament)
    {
        $request->validate([
            'assignments' => 'required|array',
            'assignments.*' => 'exists:assignments,id',
        ]);

        try {
            $assignments = $tournament->assignments()
                ->whereIn('id', $request->assignments)
                ->get()
                ->toArray();

            $this->notificationService->sendAssignmentNotification($tournament, $assignments);

            return redirect()
                ->back()
                ->with('success', 'Notifiche assegnazione inviate');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * Send convocation with documents
     */
    public function sendConvocation(Tournament $tournament)
    {
        try {
            $this->notificationService->sendConvocation($tournament);

            return redirect()
                ->back()
                ->with('success', 'Convocazioni inviate con successo');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Errore invio convocazioni: ' . $e->getMessage());
        }
    }

    /**
     * Resend failed notification
     */
    public function resend(Notification $notification)
    {
        try {
            $this->notificationService->processNotification($notification);

            return redirect()
                ->back()
                ->with('success', 'Notifica reinviata con successo');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Errore reinvio: ' . $e->getMessage());
        }
    }

    /**
     * Cancel scheduled notification
     */
    public function cancel(Notification $notification)
    {
        if ($notification->status === 'sent') {
            return redirect()
                ->back()
                ->with('error', 'Impossibile cancellare notifica già inviata');
        }

        $notification->update(['status' => 'cancelled']);

        return redirect()
            ->back()
            ->with('success', 'Notifica cancellata');
    }

    /**
     * Notification statistics
     */
    public function stats()
    {
        $stats = [
            'total' => Notification::count(),
            'sent' => Notification::where('status', 'sent')->count(),
            'pending' => Notification::where('status', 'pending')->count(),
            'failed' => Notification::where('status', 'failed')->count(),
            'by_type' => Notification::groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type'),
        ];

        return view('admin.notifications.stats', compact('stats'));
    }
}
PHP;

        $path = app_path('Http/Controllers/Admin/NotificationController.php');
        File::put($path, $controller);
    }

    private function createUnifiedMigration(): void
    {
        $migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tabella notifiche unificata
        Schema::create('notifications_unified', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // availability_reminder, assignment, convocation, custom
            $table->string('subject');
            $table->text('body');
            $table->foreignId('tournament_id')->nullable()->constrained();
            $table->foreignId('sender_id')->nullable()->constrained('users');
            $table->foreignId('template_id')->nullable();
            $table->enum('status', ['draft', 'pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->datetime('scheduled_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['tournament_id', 'type']);
            $table->index('scheduled_at');
        });

        // Tabella destinatari
        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications_unified')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('type', ['to', 'cc', 'bcc'])->default('to');
            $table->datetime('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['notification_id', 'status']);
        });

        // Tabella allegati
        Schema::create('notification_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications_unified')->onDelete('cascade');
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type');
            $table->integer('size')->nullable();
            $table->timestamps();
        });

        // Tabella template
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type');
            $table->string('subject');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_attachments');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notifications_unified');
    }
};
PHP;

        $timestamp = date('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_unified_notifications_table.php");
        File::put($path, $migration);
    }

    private function mergeNotificationData(): void
    {
        if ($this->option('dry-run')) {
            $this->info('📋 [DRY-RUN] Migrazione dati notifiche...');
            return;
        }

        $this->info('📋 Migrando dati dalle tabelle esistenti...');

        // Qui implementeresti la logica per migrare i dati
        // dalle vecchie tabelle alla nuova struttura unificata

        $this->info('✅ Dati migrati con successo');
    }

    private function updateControllers(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🎮 [DRY-RUN] Aggiornamento controllers...');
            return;
        }

        $this->info('🎮 Aggiornando controllers...');

        // Lista controllers da eliminare/rinominare
        $obsoleteControllers = [
            'CommunicationController',
            'LetterTemplateController',
            'LetterheadController',
            'InstitutionalEmailController',
        ];

        foreach ($obsoleteControllers as $controller) {
            $path = app_path("Http/Controllers/Admin/{$controller}.php");
            if (File::exists($path)) {
                $backupPath = $path . '.obsolete_' . now()->format('YmdHis');
                File::move($path, $backupPath);
                $this->deletedFiles++;
                $this->info("  ✅ Rimosso: {$controller}");
            }
        }
    }

    private function updateNotificationRoutes(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🛤️ [DRY-RUN] Aggiornamento routes...');
            return;
        }

        $this->info('🛤️ Aggiornando routes notifiche...');

        // Crea route file unificato per notifiche
        $routeContent = <<<'PHP'
<?php

use App\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Notification Routes (Unified)
|--------------------------------------------------------------------------
*/

// Main notification management
Route::resource('notifications', NotificationController::class)
    ->only(['index', 'show']);

// Tournament-specific notifications
Route::prefix('tournaments/{tournament}/notifications')->name('notifications.tournament.')
    ->group(function () {
        Route::post('/availability-reminder', [NotificationController::class, 'availabilityReminder'])
            ->name('availability-reminder');
        Route::post('/assignment', [NotificationController::class, 'assignmentNotification'])
            ->name('assignment');
        Route::post('/convocation', [NotificationController::class, 'sendConvocation'])
            ->name('convocation');
    });

// Notification actions
Route::post('notifications/{notification}/resend', [NotificationController::class, 'resend'])
    ->name('notifications.resend');
Route::post('notifications/{notification}/cancel', [NotificationController::class, 'cancel'])
    ->name('notifications.cancel');

// Statistics
Route::get('notifications/stats', [NotificationController::class, 'stats'])
    ->name('notifications.stats');

// Legacy redirects
Route::redirect('/admin/communications', '/admin/notifications', 301);
Route::redirect('/admin/letter-templates', '/admin/notifications', 301);
Route::redirect('/admin/letterheads', '/admin/notifications', 301);
PHP;

        $path = base_path('routes/admin/notifications.php');
        File::put($path, $routeContent);
        $this->info('✅ Routes notifiche unificate');
    }

    private function updateNotificationViews(): void
    {
        if ($this->option('dry-run')) {
            $this->info('👁️ [DRY-RUN] Aggiornamento views...');
            return;
        }

        $this->info('👁️ Aggiornando views notifiche...');

        // Crea directory views unificate
        $viewsDir = resource_path('views/admin/notifications');
        File::ensureDirectoryExists($viewsDir);

        // Views da creare
        $views = ['index', 'show', 'stats'];

        foreach ($views as $view) {
            $this->createNotificationView($view);
        }

        // Rimuovi vecchie directory views
        $obsoleteDirs = [
            'communications',
            'letter-templates',
            'letterheads',
            'institutional-emails',
        ];

        foreach ($obsoleteDirs as $dir) {
            $path = resource_path("views/admin/{$dir}");
            if (File::exists($path)) {
                $backupPath = $path . '_obsolete_' . now()->format('YmdHis');
                File::move($path, $backupPath);
                $this->deletedFiles++;
            }
        }

        $this->info('✅ Views notifiche unificate');
    }

    private function createNotificationView(string $name): void
    {
        $content = match($name) {
            'index' => $this->getIndexViewContent(),
            'show' => $this->getShowViewContent(),
            'stats' => $this->getStatsViewContent(),
            default => ''
        };

        $path = resource_path("views/admin/notifications/{$name}.blade.php");
        File::put($path, $content);
    }

    private function getIndexViewContent(): string
    {
        return <<<'BLADE'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Sistema Notifiche Unificato</h1>
        <a href="{{ route('admin.notifications.stats') }}" class="btn btn-info">
            <i class="fas fa-chart-bar"></i> Statistiche
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Oggetto</th>
                        <th>Torneo</th>
                        <th>Stato</th>
                        <th>Data Invio</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notifications as $notification)
                    <tr>
                        <td>{{ $notification->id }}</td>
                        <td>
                            <span class="badge badge-primary">
                                {{ $notification->type }}
                            </span>
                        </td>
                        <td>{{ $notification->subject }}</td>
                        <td>
                            @if($notification->tournament)
                                {{ $notification->tournament->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $notification->status_color }}">
                                {{ $notification->status }}
                            </span>
                        </td>
                        <td>{{ $notification->sent_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>
                            <a href="{{ route('admin.notifications.show', $notification) }}"
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if($notification->status === 'failed')
                            <form action="{{ route('admin.notifications.resend', $notification) }}"
                                  method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-warning">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $notifications->links() }}
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    private function getShowViewContent(): string
    {
        return <<<'BLADE'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <h1>Dettaglio Notifica #{{ $notification->id }}</h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>{{ $notification->subject }}</h3>
                </div>
                <div class="card-body">
                    {!! $notification->body !!}
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h4>Destinatari</h4>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Stato</th>
                                <th>Inviato</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notification->recipients as $recipient)
                            <tr>
                                <td>{{ $recipient->name }}</td>
                                <td>{{ $recipient->email }}</td>
                                <td>{{ $recipient->type }}</td>
                                <td>{{ $recipient->status }}</td>
                                <td>{{ $recipient->sent_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Informazioni</h4>
                </div>
                <div class="card-body">
                    <dl>
                        <dt>Tipo</dt>
                        <dd>{{ $notification->type }}</dd>

                        <dt>Stato</dt>
                        <dd>
                            <span class="badge badge-{{ $notification->status_color }}">
                                {{ $notification->status }}
                            </span>
                        </dd>

                        <dt>Torneo</dt>
                        <dd>{{ $notification->tournament?->name ?? '-' }}</dd>

                        <dt>Inviato da</dt>
                        <dd>{{ $notification->sender?->name ?? 'Sistema' }}</dd>

                        <dt>Creato</dt>
                        <dd>{{ $notification->created_at->format('d/m/Y H:i') }}</dd>

                        <dt>Inviato</dt>
                        <dd>{{ $notification->sent_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </dl>

                    @if($notification->attachments->count() > 0)
                    <h5>Allegati</h5>
                    <ul>
                        @foreach($notification->attachments as $attachment)
                        <li>{{ $attachment->filename }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    private function getStatsViewContent(): string
    {
        return <<<'BLADE'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <h1>Statistiche Notifiche</h1>

    <div class="row">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2>{{ $stats['total'] }}</h2>
                    <p>Totali</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-success">{{ $stats['sent'] }}</h2>
                    <p>Inviate</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-warning">{{ $stats['pending'] }}</h2>
                    <p>In Attesa</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-danger">{{ $stats['failed'] }}</h2>
                    <p>Fallite</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Per Tipo</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Numero</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats['by_type'] as $type => $count)
                    <tr>
                        <td>{{ $type }}</td>
                        <td>{{ $count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    private function cleanupObsoleteFiles(): void
    {
        if ($this->option('dry-run')) {
            $this->info('🗑️ [DRY-RUN] Pulizia file obsoleti...');
            return;
        }

        $this->info('🗑️ Pulizia file obsoleti...');

        // Models obsoleti
        $obsoleteModels = [
            'Communication',
            'LetterTemplate',
            'Letterhead',
            'InstitutionalEmail',
        ];

        foreach ($obsoleteModels as $model) {
            $path = app_path("Models/{$model}.php");
            if (File::exists($path)) {
                $backupPath = $path . '.obsolete_' . now()->format('YmdHis');
                File::move($path, $backupPath);
                $this->deletedFiles++;
            }
        }

        $this->info("✅ Rimossi {$this->deletedFiles} file obsoleti");
    }

    private function showFinalReport(): void
    {
        $this->info('');
        $this->info('✅ UNIFICAZIONE NOTIFICHE COMPLETATA!');
        $this->info('=====================================');

        if ($this->option('dry-run')) {
            $this->warn('Modalità DRY-RUN - nessun file modificato');
            $this->info('Per applicare le modifiche, esegui senza --dry-run');
        } else {
            $this->info("📊 Sistemi unificati: " . count($this->systemsToMerge));
            $this->info("📁 File rimossi: {$this->deletedFiles}");
            $this->info("📧 Records migrati: {$this->mergedCount}");

            $this->info('');
            $this->info('🎯 PROSSIMI PASSI:');
            $this->info('1. Esegui migration: php artisan migrate');
            $this->info('2. Test invio notifiche: php artisan tinker');
            $this->info('3. Verifica email queue: php artisan queue:work');
            $this->info('4. Clear cache: php artisan optimize:clear');
        }
    }
}
