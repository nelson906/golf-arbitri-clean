<?php
use App\Http\Controllers\Admin\LetterheadController;
use Illuminate\Support\Facades\Route;

Route::prefix('letterheads')->name('letterheads.')->group(function () {
    Route::get('/', [LetterheadController::class, 'index'])->name('index');
    Route::get('/create', [LetterheadController::class, 'create'])->name('create');
    Route::post('/', [LetterheadController::class, 'store'])->name('store');
    Route::get('/{letterhead}', [LetterheadController::class, 'show'])->name('show');
    Route::get('/{letterhead}/edit', [LetterheadController::class, 'edit'])->name('edit');
    Route::put('/{letterhead}', [LetterheadController::class, 'update'])->name('update');
    Route::delete('/{letterhead}', [LetterheadController::class, 'destroy'])->name('destroy');
    Route::post('/{letterhead}/duplicate', [LetterheadController::class, 'duplicate'])->name('duplicate');
    Route::get('/{letterhead}/preview', [LetterheadController::class, 'preview'])->name('preview');
    Route::post('/{letterhead}/set-default', [LetterheadController::class, 'setDefault'])->name('set-default');
    Route::post('/{letterhead}/toggle-active', [LetterheadController::class, 'toggleActive'])->name('toggle-active');
    Route::delete('/{letterhead}/remove-logo', [LetterheadController::class, 'removeLogo'])->name('remove-logo');
    Route::get('/zone/{zone}', [LetterheadController::class, 'getByZone'])->name('by-zone');
});
