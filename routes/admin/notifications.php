<?php
/*
|--------------------------------------------------------------------------
| routes/admin/notifications.php - Notification System Routes
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

// NOTIFICATIONS MANAGEMENT
Route::prefix('notifications')->name('notifications.')->group(function () {

    // CRUD Base
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/create', [NotificationController::class, 'create'])->name('create');
    Route::post('/', [NotificationController::class, 'store'])->name('store');
    Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');

    // Status Management
    Route::post('/{notification}/resend', [NotificationController::class, 'resend'])->name('resend');
    Route::post('/{notification}/cancel', [NotificationController::class, 'cancel'])->name('cancel');

    // Bulk Operations
    Route::post('/bulk-notify', [NotificationController::class, 'bulkNotify'])->name('bulk-notify');
    Route::post('/bulk-cancel', [NotificationController::class, 'bulkCancel'])->name('bulk-cancel');

    // Tournament-specific notifications
    Route::prefix('tournament')->name('tournament.')->group(function () {
        Route::post('/{tournament}/availability-reminder', [NotificationController::class, 'availabilityReminder'])
            ->name('availability-reminder');
        Route::post('/{tournament}/assignment-notification', [NotificationController::class, 'assignmentNotification'])
            ->name('assignment-notification');
        Route::post('/{tournament}/deadline-reminder', [NotificationController::class, 'deadlineReminder'])
            ->name('deadline-reminder');
        Route::post('/{tournament}/custom', [NotificationController::class, 'customTournamentNotification'])
            ->name('custom');
    });

    // Templates Management
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [NotificationController::class, 'templates'])->name('index');
        Route::get('/create', [NotificationController::class, 'createTemplate'])->name('create');
        Route::post('/', [NotificationController::class, 'storeTemplate'])->name('store');
        Route::get('/{template}/edit', [NotificationController::class, 'editTemplate'])->name('edit');
        Route::put('/{template}', [NotificationController::class, 'updateTemplate'])->name('update');
        Route::delete('/{template}', [NotificationController::class, 'destroyTemplate'])->name('destroy');
        Route::post('/{template}/use', [NotificationController::class, 'useTemplate'])->name('use');
    });

    // Statistics & Analytics
    Route::get('/statistics', [NotificationController::class, 'statistics'])->name('statistics');
    Route::get('/delivery-report', [NotificationController::class, 'deliveryReport'])->name('delivery-report');
    Route::get('/engagement-analytics', [NotificationController::class, 'engagementAnalytics'])->name('engagement');

    // Queue Management
    Route::prefix('queue')->name('queue.')->group(function () {
        Route::get('/', [NotificationController::class, 'queueStatus'])->name('status');
        Route::get('/failed', [NotificationController::class, 'failedJobs'])->name('failed');
        Route::post('/retry-failed', [NotificationController::class, 'retryFailedJobs'])->name('retry-failed');
        Route::post('/clear-failed', [NotificationController::class, 'clearFailedJobs'])->name('clear-failed');
    });

    // Mass Communication System
    Route::prefix('mass')->name('mass.')->group(function () {
        Route::get('/', [NotificationController::class, 'massNotification'])->name('index');
        Route::post('/send', [NotificationController::class, 'sendMassNotification'])->name('send');
        Route::get('/audience-builder', [NotificationController::class, 'audienceBuilder'])->name('audience-builder');
        Route::post('/preview', [NotificationController::class, 'previewNotification'])->name('preview');

        // Predefined mass notifications
        Route::post('/zone-announcement', [NotificationController::class, 'zoneAnnouncement'])->name('zone-announcement');
        Route::post('/referee-newsletter', [NotificationController::class, 'refereeNewsletter'])->name('referee-newsletter');
        Route::post('/emergency-notification', [NotificationController::class, 'emergencyNotification'])->name('emergency');
    });

    // Automation & Scheduling
    Route::prefix('automation')->name('automation.')->group(function () {
        Route::get('/', [NotificationController::class, 'automation'])->name('index');
        Route::get('/scheduled', [NotificationController::class, 'scheduled'])->name('scheduled');
        Route::get('/recurring', [NotificationController::class, 'recurring'])->name('recurring');
        Route::post('/create-rule', [NotificationController::class, 'createAutomationRule'])->name('create-rule');
        Route::delete('/rule/{rule}', [NotificationController::class, 'destroyRule'])->name('destroy-rule');
    });
});

// Email & SMS Settings (System-wide notification configuration)
Route::prefix('communication-settings')->name('communication-settings.')->group(function () {
    Route::get('/', [NotificationController::class, 'settings'])->name('index');
    Route::put('/email', [NotificationController::class, 'updateEmailSettings'])->name('update-email');
    Route::put('/sms', [NotificationController::class, 'updateSmsSettings'])->name('update-sms');
    Route::post('/test-connection', [NotificationController::class, 'testConnection'])->name('test-connection');
    Route::get('/delivery-providers', [NotificationController::class, 'deliveryProviders'])->name('providers');
});

// Tournament Notifications System (Document Management)
Route::prefix('tournament-notifications')->name('tournament-notifications.')->group(function () {
    // Lista e ricerca
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/find-by-tournament/{tournament}', [NotificationController::class, 'findByTournament'])->name('find-by-tournament');
    
    // Operazioni su tournament
    Route::post('/{tournament}/store', [NotificationController::class, 'store'])->name('store');
    Route::post('/{tournament}/prepare', [NotificationController::class, 'prepare'])->name('prepare');
    
    // Document Management Routes (MESSE PRIMA per evitare conflitti)
    Route::get('/{notification}/documents-status', [NotificationController::class, 'documentsStatus'])->name('documents-status');
    Route::get('/{notification}/download/{type}', [NotificationController::class, 'downloadDocument'])->name('download-document');
    Route::post('/{notification}/upload/{type}', [NotificationController::class, 'uploadDocument'])->name('upload-document');
    Route::post('/{notification}/generate/{type}', [NotificationController::class, 'generateDocument'])->name('generate-document');
    Route::post('/{notification}/regenerate/{type}', [NotificationController::class, 'regenerateDocument'])->name('regenerate-document');
    Route::delete('/{notification}/document/{type}', [NotificationController::class, 'deleteDocument'])->name('delete-document');
    
    // Operazioni su notification
    Route::post('/{notification}/send', [NotificationController::class, 'send'])->name('send');
    Route::post('/{notification}/resend', [NotificationController::class, 'resend'])->name('resend');
    Route::get('/{notification}/edit', [NotificationController::class, 'edit'])->name('edit');
    Route::put('/{notification}', [NotificationController::class, 'update'])->name('update');
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    Route::get('/{notification}', [NotificationController::class, 'show'])->name('show'); // MESSA PER ULTIMA
});
