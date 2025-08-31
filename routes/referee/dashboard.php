<?php
use App\Http\Controllers\Referee\DashboardController;

Route::get('/referee/dashboard', [DashboardController::class, 'index'])->name('referee.dashboard');
