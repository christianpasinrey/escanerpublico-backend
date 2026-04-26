<?php

namespace Modules\Contracts;

use Dedoc\Scramble\Scramble;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Contracts\Console\RefreshLandingStats;
use Modules\Contracts\Console\ReprocessContracts;
use Modules\Contracts\Console\SyncContracts;
use Modules\Contracts\Http\Middleware\LimitNestedIncludes;
use Modules\Contracts\Search\CompanySearchProvider;
use Modules\Contracts\Search\ContractSearchProvider;
use Modules\Contracts\Search\OrganizationSearchProvider;
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

        // Federated search providers — picked up by Modules\Search\SearchServiceProvider
        // via container tagging; we don't reference the consumer.
        $this->app->tag([
            ContractSearchProvider::class,
            OrganizationSearchProvider::class,
            CompanySearchProvider::class,
        ], 'search.providers');
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware(
            'limit.includes',
            LimitNestedIncludes::class
        );

        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');

        // Configure Scramble: expose only the OpenAPI document at /openapi.json,
        // disable Scramble's built-in UI (we serve docs via Scalar at /docs).
        if (class_exists(Scramble::class)) {
            Scramble::configure()->expose(
                ui: false,
                document: fn (Router $router, $action) => $router->get('openapi.json', $action)->name('scramble.docs.document'),
            );
        }

        // Public docs: allow Scalar without authentication.
        Gate::define('viewScalar', fn ($user = null) => true);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncContracts::class,
                ReprocessContracts::class,
                RefreshLandingStats::class,
            ]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('landing:refresh-stats')->everyFiveMinutes();
            });
        }
    }
}
