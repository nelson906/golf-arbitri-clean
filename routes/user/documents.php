<?php

use App\Http\Controllers\User\DocumentController;
use Illuminate\Support\Facades\Route;

// User Document Routes
Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
});
