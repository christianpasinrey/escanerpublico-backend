<?php

use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'version' => '1.0.0',
    ]));

    // Contratos
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/stats', [ContractController::class, 'stats']);
    Route::get('/contracts/filters', [ContractController::class, 'filters']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);
});
