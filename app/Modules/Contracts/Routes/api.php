<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\CompanyController;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\LotController;
use Modules\Contracts\Http\Controllers\OrganizationController;
use Modules\Contracts\Http\Controllers\TimelinesController;

Route::prefix('api/v1')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{external_id}', [ContractController::class, 'show'])->where('external_id', '.*');

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->whereNumber('organization');
    Route::get('/organizations/{organization}/stats', [OrganizationController::class, 'stats'])->whereNumber('organization');

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->whereNumber('company');
    Route::get('/companies/{company}/stats', [CompanyController::class, 'stats'])->whereNumber('company');

    Route::get('/lots', [LotController::class, 'index']);
});

// Endpoint pesado con su propio rate limit (30/min/IP).
Route::prefix('api/v1')->middleware(['throttle:api-heavy'])->group(function () {
    Route::get('/timelines', [TimelinesController::class, 'index']);
});
