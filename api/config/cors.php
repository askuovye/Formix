<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| Restrito às origens da SPA. `supports_credentials` é obrigatório para o
| Sanctum em modo SPA (cookies de sessão e XSRF-TOKEN).
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'Idempotency-Key',
        'X-Edit-Token',
    ],

    // Content-Disposition: o painel lê o nome do PDF baixado pelo Axios (D35).
    'exposed_headers' => ['Retry-After', 'Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
