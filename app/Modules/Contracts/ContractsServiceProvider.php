<?php

namespace Modules\Contracts;

use Illuminate\Support\ServiceProvider;
use Modules\Contracts\Console\SyncContracts;

class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void {}

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
