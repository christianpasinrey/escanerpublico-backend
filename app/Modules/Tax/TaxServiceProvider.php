<?php

namespace Modules\Tax;

use Illuminate\Support\ServiceProvider;
use Modules\Tax\Calculators\Invoice\InvoiceCalculator;
use Modules\Tax\Calculators\Payroll\PayrollCalculator;
use Modules\Tax\Calculators\Payroll\RegimenGeneralPayroll;
use Modules\Tax\Console\DetectBoeChanges;
use Modules\Tax\Console\ReportRegimeCoverage;
use Modules\Tax\Console\ReportTypesCoverage;
use Modules\Tax\Console\SyncCnae;
use Modules\Tax\Console\SyncIae;
use Modules\Tax\Console\ValidateTaxParameters;
use Modules\Tax\Ingestion\CnaeImporter;
use Modules\Tax\Ingestion\IaeImporter;
use Modules\Tax\Services\BoeParameterDetector;
use Modules\Tax\Services\Invoice\IbanFormatter;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\Services\MinimumPersonalCalculator;
use Modules\Tax\Services\ObligationsResolver;
use Modules\Tax\Services\SocialSecurityResolver;
use Modules\Tax\Services\TaxParameterRepository;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaxParameterRepository::class);
        $this->app->singleton(BoeParameterDetector::class);
        $this->app->singleton(CnaeImporter::class);
        $this->app->singleton(IaeImporter::class);
        $this->app->singleton(ObligationsResolver::class);

        // M4 — Servicios de la calculadora de nómina
        $this->app->singleton(IrpfScaleResolver::class);
        $this->app->singleton(SocialSecurityResolver::class);
        $this->app->singleton(MinimumPersonalCalculator::class);
        $this->app->singleton(RegimenGeneralPayroll::class);
        $this->app->singleton(PayrollCalculator::class);

        // M5 — Servicios de la calculadora de factura
        $this->app->singleton(VatRateResolver::class);
        $this->app->singleton(IrpfRetentionResolver::class);
        $this->app->singleton(SurchargeEquivalenceResolver::class);
        $this->app->singleton(IbanFormatter::class);
        $this->app->singleton(InvoiceCalculator::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateTaxParameters::class,
                DetectBoeChanges::class,
                SyncCnae::class,
                SyncIae::class,
                ReportRegimeCoverage::class,
                ReportTypesCoverage::class,
            ]);
        }
    }
}
