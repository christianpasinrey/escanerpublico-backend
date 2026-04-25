<?php

use Illuminate\Support\Facades\Route;
use Modules\Officials\Http\Controllers\PublicOfficialController;

Route::prefix('api/v1/officials')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    Route::get('/', [PublicOfficialController::class, 'index']);
    Route::get('/{official}', [PublicOfficialController::class, 'show'])->whereNumber('official');
});
