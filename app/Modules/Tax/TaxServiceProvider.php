<?php

namespace Modules\Tax;

use Illuminate\Support\ServiceProvider;
use Modules\Tax\Services\TaxParameterRepository;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaxParameterRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
