<?php

use Illuminate\Support\Facades\Route;
use Modules\Legislation\Http\Controllers\BoeItemController;
use Modules\Legislation\Http\Controllers\BoeSummaryController;
use Modules\Legislation\Http\Controllers\LegislationNormController;

Route::prefix('api/v1/legislation')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    Route::get('/norms', [LegislationNormController::class, 'index']);
    Route::get('/norms/{norm}', [LegislationNormController::class, 'show'])->whereNumber('norm');

    Route::get('/summaries', [BoeSummaryController::class, 'index']);
    Route::get('/summaries/{summary}', [BoeSummaryController::class, 'show'])->whereNumber('summary');

    Route::get('/items', [BoeItemController::class, 'index']);
    Route::get('/items/{item}', [BoeItemController::class, 'show'])->whereNumber('item');
});
