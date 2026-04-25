<?php

namespace Modules\Subsidies;

use Illuminate\Support\ServiceProvider;
use Modules\Subsidies\Console\SyncSubsidies;
use Modules\Subsidies\Services\BdnsClient;
use Modules\Subsidies\Services\BeneficiarioParser;
use Modules\Subsidies\Services\SubsidyIngestor;

class SubsidiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BdnsClient::class);
        $this->app->singleton(BeneficiarioParser::class);
        $this->app->singleton(SubsidyIngestor::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSubsidies::class,
            ]);
        }
    }
}
