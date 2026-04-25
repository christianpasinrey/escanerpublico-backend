<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function (): void {
            // Rutas MCP (Model Context Protocol) — servidor agente compatible
            // con Claude Desktop, ChatGPT, Cursor, etc.
            require __DIR__.'/../routes/ai.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi('api');

        // Security headers en todas las respuestas.
        $middleware->append(SecurityHeaders::class);

        // Trust Cloudflare proxy → request->ip() devuelve la IP real del cliente.
        // Necesario para que el rate limit por IP no agrupe a TODO el tráfico
        // bajo la IP de CF.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
