<?php

namespace Modules\Search;

use Illuminate\Support\ServiceProvider;
use Modules\Search\Services\FederatedSearchService;

/**
 * Provider raíz del módulo Search.
 *
 * Lo único que hace aquí es bindear el FederatedSearchService con los
 * providers etiquetados en el container. Cada módulo de dominio (Contracts,
 * Subsidies, Legislation, Officials…) etiqueta su propio SearchProvider
 * en su ServiceProvider con `$this->app->tag([X::class], 'search.providers')`.
 *
 * El servicio recoge la lista vía `$app->tagged('search.providers')` sin
 * conocer las clases concretas — desacoplamiento total.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FederatedSearchService::class, function ($app) {
            return new FederatedSearchService(
                providers: $app->tagged('search.providers'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
