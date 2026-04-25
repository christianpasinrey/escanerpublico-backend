<?php

use Illuminate\Support\Facades\Route;
use Modules\Subsidies\Http\Controllers\SubsidyCallController;
use Modules\Subsidies\Http\Controllers\SubsidyGrantController;

Route::prefix('api/v1/subsidies')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    Route::get('/calls', [SubsidyCallController::class, 'index']);
    Route::get('/calls/{call}', [SubsidyCallController::class, 'show'])->whereNumber('call');

    Route::get('/grants', [SubsidyGrantController::class, 'index']);
    Route::get('/grants/{grant}', [SubsidyGrantController::class, 'show'])->whereNumber('grant');
});
