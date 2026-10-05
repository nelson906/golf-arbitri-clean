<?php

use App\Http\Controllers\User\AssignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Assignments Routes
|--------------------------------------------------------------------------
| "Le Mie Assegnazioni": tutte le designazioni dell'arbitro (decisione
| 2026-10-03, P16).
|--------------------------------------------------------------------------
*/

Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
