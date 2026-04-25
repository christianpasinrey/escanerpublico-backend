<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API pública sin auth: 100 req/min por IP como abuse barrier.
        // Cloudflare actúa como WAF/edge cache; esto protege el origen
        // contra clientes que esquiven el cache.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->ip() ?? 'unknown')
                ->response(fn () => response()->json([
                    'error' => 'rate_limit_exceeded',
                    'message' => 'Más de 100 peticiones/minuto desde tu IP. Espera un momento.',
                ], 429));
        });

        // Endpoints especialmente caros (timelines, full-text search): 30/min/IP.
        RateLimiter::for('api-heavy', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip() ?? 'unknown')
                ->response(fn () => response()->json([
                    'error' => 'rate_limit_exceeded',
                    'message' => 'Endpoint pesado limitado a 30 peticiones/minuto por IP.',
                ], 429));
        });
    }
}
