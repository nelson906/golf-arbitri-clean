<?php

use App\Http\Controllers\User\CurriculumController;
use Illuminate\Support\Facades\Route;

Route::get('curriculum', [CurriculumController::class, 'index'])->name('curriculum');
