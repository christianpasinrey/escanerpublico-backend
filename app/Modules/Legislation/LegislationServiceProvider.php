<?php

namespace Modules\Legislation;

use Illuminate\Support\ServiceProvider;
use Modules\Legislation\Console\SyncLegislation;
use Modules\Legislation\Search\LegislationSearchProvider;
use Modules\Legislation\Services\BoeClient;
use Modules\Legislation\Services\LegislationIngestor;

class LegislationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BoeClient::class);
        $this->app->singleton(LegislationIngestor::class);

        $this->app->tag([
            LegislationSearchProvider::class,
        ], 'search.providers');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncLegislation::class,
            ]);
        }
    }
}
