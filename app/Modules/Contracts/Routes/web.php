<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\HealthController;
use Modules\Contracts\Http\Controllers\LandingController;

Route::get('/', [LandingController::class, 'show'])->name('landing');
Route::get('/health', [HealthController::class, 'show'])->name('health');
