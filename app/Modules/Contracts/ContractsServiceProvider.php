<?php

namespace Modules\Contracts;

use Illuminate\Support\ServiceProvider;
use Modules\Contracts\Console\SyncContracts;
use Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor;
use Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor;
use Modules\Contracts\Services\Parser\Extractors\LotsExtractor;
use Modules\Contracts\Services\Parser\Extractors\NoticesExtractor;
use Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProcessExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProjectExtractor;
use Modules\Contracts\Services\Parser\Extractors\ResultsExtractor;
use Modules\Contracts\Services\Parser\Extractors\TermsExtractor;
use Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor;
use Modules\Contracts\Services\Parser\PlacspEntryParser;
use Modules\Contracts\Services\Parser\PlacspStreamParser;

class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TombstoneExtractor::class);
        $this->app->singleton(OrganizationExtractor::class);
        $this->app->singleton(ProjectExtractor::class);
        $this->app->singleton(LotsExtractor::class);
        $this->app->singleton(ProcessExtractor::class);
        $this->app->singleton(ResultsExtractor::class);
        $this->app->singleton(TermsExtractor::class);
        $this->app->singleton(CriteriaExtractor::class);
        $this->app->singleton(NoticesExtractor::class);
        $this->app->singleton(DocumentsExtractor::class);
        $this->app->singleton(PlacspEntryParser::class);
        $this->app->singleton(PlacspStreamParser::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncContracts::class,
            ]);
        }
    }
}
