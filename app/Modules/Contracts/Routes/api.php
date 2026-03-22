<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\OrganizationController;
use Modules\Contracts\Http\Controllers\CompanyController;

Route::prefix('api/v1')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/stats', [ContractController::class, 'stats']);
    Route::get('/contracts/filters', [ContractController::class, 'filters']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
});
