<?php
use App\Http\Controllers\Referee\DashboardController;
use App\Http\Controllers\User\QuadrantiController;
use Illuminate\Support\Facades\Route;


Route::get('/referee/dashboard', [DashboardController::class, 'index'])->name('referee.dashboard');

// Quadranti (Starting Times Simulator)
Route::prefix('/referee/quadranti')->name('referee.quadranti.')->group(function () {
    Route::get('/', [QuadrantiController::class, 'index'])->name('index');
    Route::post('/upload-excel', [QuadrantiController::class, 'uploadExcel'])->name('upload-excel');
});
