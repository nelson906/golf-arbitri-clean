<?php

/*
|--------------------------------------------------------------------------
| routes/admin/notifications.php - Notification System Routes
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

// Tournament Notifications System (Document Management)
Route::prefix('tournament-notifications')->name('tournament-notifications.')->group(function () {
    // Lista e ricerca
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/find-by-tournament/{tournament}', [NotificationController::class, 'findByTournament'])->name('find-by-tournament');

    // Document routes
    Route::get('/{notification}/documents-status', [NotificationController::class, 'documentsStatus'])
        ->name('documents-status');
    Route::post('/{notification}/generate/{type}', [NotificationController::class, 'generateDocument'])
        ->name('generate-document');
    Route::delete('/{notification}/document/{type}', [NotificationController::class, 'deleteDocument'])
        ->name('delete-document');
    Route::post('/{notification}/upload/{type}', [NotificationController::class, 'uploadDocument'])
        ->name('upload-document');
    Route::get('/{notification}/download/{type}', [NotificationController::class, 'downloadDocument'])
        ->name('download-document');

    // Clause management (AJAX)
    Route::post('/{notification}/save-clauses', [NotificationController::class, 'saveClauses'])
        ->name('save-clauses');

    // Core notification operations
    Route::post('/{notification}/send', [NotificationController::class, 'send'])->name('send');
    Route::post('/{notification}/resend', [NotificationController::class, 'resend'])->name('resend');
    Route::get('/{notification}/edit', [NotificationController::class, 'edit'])->name('edit');
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
});
