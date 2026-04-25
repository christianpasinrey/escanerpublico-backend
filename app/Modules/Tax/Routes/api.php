<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Http\Controllers\Calculators\IncomeTaxController;
use Modules\Tax\Http\Controllers\Calculators\InvoiceController;
use Modules\Tax\Http\Controllers\Calculators\PayrollController;
use Modules\Tax\Http\Controllers\Calculators\VatReturnController;
use Modules\Tax\Http\Controllers\Catalog\EconomicActivityController;
use Modules\Tax\Http\Controllers\Catalog\ObligationCalendarController;
use Modules\Tax\Http\Controllers\Catalog\RegimeController;
use Modules\Tax\Http\Controllers\TaxTypeController;

Route::prefix('api/v1/tax')->middleware(['throttle:api', 'limit.includes'])->group(function () {
    // M1 — Catálogo de regímenes y actividades
    Route::get('/regimes', [RegimeController::class, 'index']);
    Route::get('/regimes/{code}', [RegimeController::class, 'show']);

    Route::get('/activities', [EconomicActivityController::class, 'index']);
    Route::get('/activities/{system}/{code}', [EconomicActivityController::class, 'show'])
        ->where(['system' => 'cnae|iae']);

    Route::get('/calendar', [ObligationCalendarController::class, 'show']);

    // M2 — Catálogo de impuestos y tasas
    Route::get('/types', [TaxTypeController::class, 'index']);
    Route::get('/types/{code}', [TaxTypeController::class, 'show'])
        ->where('code', '[A-Z0-9_]+');

    // M4 — Calculadora de nómina (asalariado régimen general)
    Route::post('/payroll', PayrollController::class);

    // M5 — Calculadora de factura autónomo
    Route::post('/invoice', InvoiceController::class);

    // M6 — Calculadora de IRPF anual (modelo 100)
    Route::post('/income-tax', IncomeTaxController::class);

    // M7 — Calculadora autoliquidación IVA (modelo 303 trimestral / modelo 390 anual)
    Route::post('/vat-return', VatReturnController::class);

    // M8 (modelos 130/131 pagos fraccionados) — fase siguiente.
});
