<?php

return [

    'paths' => ['api/*', 'docs', 'openapi.json'],

    // API solo expone GET (ningún endpoint público escribe). Limitamos métodos.
    'allowed_methods' => ['GET', 'OPTIONS'],

    // API pública por diseño: cualquier origen puede consumirla.
    // Sin credenciales → el wildcard es seguro y conforme a la spec CORS.
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Cabeceras útiles para clientes JS que quieran reaccionar al rate limit.
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    // Cache preflight 1h.
    'max_age' => 3600,

    // Falso obligatoriamente con allowed_origins=['*'] (spec CORS).
    // La API no usa cookies/auth → no necesita credenciales.
    'supports_credentials' => false,

];
