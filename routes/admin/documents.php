<?php

use App\Http\Controllers\Admin\DocumentController;
use Illuminate\Support\Facades\Route;

// Admin Document Routes
Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/create', [DocumentController::class, 'create'])->name('create');
    Route::post('/', [DocumentController::class, 'store'])->name('store');
    Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
    Route::get('/{document}/edit', [DocumentController::class, 'edit'])->name('edit');
    Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
    Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
    Route::post('/upload', [DocumentController::class, 'upload'])->name('upload');
});
