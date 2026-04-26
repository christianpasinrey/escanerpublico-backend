<?php

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\SearchController;

Route::prefix('api/v1')->middleware(['throttle:api'])->group(function () {
    Route::get('/search', SearchController::class);
});
