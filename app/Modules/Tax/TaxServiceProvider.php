<?php

namespace Modules\Tax;

use Illuminate\Support\ServiceProvider;
use Modules\Tax\Console\DetectBoeChanges;
use Modules\Tax\Console\ValidateTaxParameters;
use Modules\Tax\Services\BoeParameterDetector;
use Modules\Tax\Services\TaxParameterRepository;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaxParameterRepository::class);
        $this->app->singleton(BoeParameterDetector::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateTaxParameters::class,
                DetectBoeChanges::class,
            ]);
        }
    }
}
