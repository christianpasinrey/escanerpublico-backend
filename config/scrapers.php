<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de Scrapers
    |--------------------------------------------------------------------------
    |
    | Configuración centralizada para los scrapers de datos públicos.
    |
    */

    'user_agent' => env('SCRAPER_USER_AGENT', 'EscanerPublico/1.0 (+https://escanerpublico.es)'),

    'timeout' => env('SCRAPER_TIMEOUT', 30),

    'retry' => [
        'times' => env('SCRAPER_RETRY_TIMES', 3),
        'sleep' => env('SCRAPER_RETRY_SLEEP', 1000), // ms
    ],

    'rate_limit' => [
        'requests_per_minute' => env('SCRAPER_RATE_LIMIT', 10),
    ],

    'sources' => [
        'boe' => [
            'name' => 'Boletín Oficial del Estado',
            'base_url' => 'https://www.boe.es',
            'enabled' => true,
        ],
        'congreso' => [
            'name' => 'Congreso de los Diputados',
            'base_url' => 'https://www.congreso.es',
            'enabled' => true,
        ],
        'senado' => [
            'name' => 'Senado de España',
            'base_url' => 'https://www.senado.es',
            'enabled' => true,
        ],
        'contratacion' => [
            'name' => 'Plataforma de Contratación del Sector Público',
            'base_url' => 'https://contrataciondelestado.es',
            'enabled' => true,
        ],
        'tribunal_cuentas' => [
            'name' => 'Tribunal de Cuentas',
            'base_url' => 'https://www.tcu.es',
            'enabled' => true,
        ],
    ],

    'storage' => [
        'raw_path' => storage_path('app/scraper/raw'),
        'processed_path' => storage_path('app/scraper/processed'),
    ],

];
