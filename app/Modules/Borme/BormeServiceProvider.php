<?php

namespace Modules\Borme;

use Illuminate\Support\ServiceProvider;
use Modules\Borme\Services\Parsers\ActParserRegistry;
use Modules\Borme\Services\Parsers\ActParsers\AmpliacionCapitalParser;
use Modules\Borme\Services\Parsers\ActParsers\CambioDomicilioParser;
use Modules\Borme\Services\Parsers\ActParsers\CambioObjetoParser;
use Modules\Borme\Services\Parsers\ActParsers\CesesParser;
use Modules\Borme\Services\Parsers\ActParsers\ConcursoParser;
use Modules\Borme\Services\Parsers\ActParsers\ConstitucionParser;
use Modules\Borme\Services\Parsers\ActParsers\DisolucionParser;
use Modules\Borme\Services\Parsers\ActParsers\NombramientosParser;
use Modules\Borme\Services\Parsers\ActParsers\ReduccionCapitalParser;

class BormeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActParserRegistry::class, function ($app) {
            return new ActParserRegistry([
                $app->make(ConstitucionParser::class),
                $app->make(NombramientosParser::class),
                $app->make(CesesParser::class),
                $app->make(AmpliacionCapitalParser::class),
                $app->make(ReduccionCapitalParser::class),
                $app->make(CambioDomicilioParser::class),
                $app->make(CambioObjetoParser::class),
                $app->make(DisolucionParser::class),
                $app->make(ConcursoParser::class),
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Borme\Console\DebugParseBorme::class,
                \Modules\Borme\Console\SyncBorme::class,
                \Modules\Borme\Console\BackfillHistoricalBorme::class,
                \Modules\Borme\Console\ListPendingReview::class,
            ]);
        }
    }
}
