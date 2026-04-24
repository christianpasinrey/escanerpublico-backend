<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\CompanyController;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\OrganizationController;

Route::prefix('api/v1')->middleware(['limit.includes'])->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/stats', [ContractController::class, 'stats']);
    Route::get('/contracts/filters', [ContractController::class, 'filters']);
    Route::get('/contracts/{id}', [ContractController::class, 'show'])->whereNumber('id');

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{id}/stats', [OrganizationController::class, 'stats'])->whereNumber('id');
    Route::get('/organizations/{id}', [OrganizationController::class, 'show'])->whereNumber('id');

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{id}', [CompanyController::class, 'show'])->whereNumber('id');
});
