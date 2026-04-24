<?php

return [
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    'base_url' => env('APP_URL', 'https://api.escanerpublico.es'),
];
