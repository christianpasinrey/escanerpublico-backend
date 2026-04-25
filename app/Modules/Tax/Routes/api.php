<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/tax')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    // Catálogo (M1, M2) — controllers se añaden en fases siguientes.
    // Calculadoras (M4-M8) — controllers se añaden en fases siguientes.
});
