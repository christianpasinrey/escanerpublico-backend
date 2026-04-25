<?php

namespace Modules\Officials;

use Illuminate\Support\ServiceProvider;
use Modules\Officials\Console\ExtractOfficials;
use Modules\Officials\Services\CargoExtractor;
use Modules\Officials\Services\OfficialIngestor;

class OfficialsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CargoExtractor::class);
        $this->app->singleton(OfficialIngestor::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExtractOfficials::class,
            ]);
        }
    }
}
