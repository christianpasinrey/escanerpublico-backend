<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Http\Controllers\Catalog\EconomicActivityController;
use Modules\Tax\Http\Controllers\Catalog\ObligationCalendarController;
use Modules\Tax\Http\Controllers\Catalog\RegimeController;

Route::prefix('api/v1/tax')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    // M1 — Catálogo
    Route::get('/regimes', [RegimeController::class, 'index']);
    Route::get('/regimes/{code}', [RegimeController::class, 'show']);

    Route::get('/activities', [EconomicActivityController::class, 'index']);
    Route::get('/activities/{system}/{code}', [EconomicActivityController::class, 'show'])
        ->where(['system' => 'cnae|iae']);

    Route::get('/calendar', [ObligationCalendarController::class, 'show']);

    // M2 (catálogo de impuestos), M3 (parámetros) y M4-M8 (calculadoras) — fases siguientes.
});
