<?php

// Laravel's HandleCors middleware owns preflight and response headers.
// Keep this file side-effect free so configuration caching works.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') !== null
        ? array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS')))))
        : [
        'https://www.faydatech.com',
        'https://faydatech.com',
        'https://admin.faydatech.com',
        'https://faydamed.tech',
        'https://www.faydamed.tech',
        'https://admin.faydamed.tech',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept', 'Origin', 'X-CSRF-TOKEN'],
    'exposed_headers' => ['X-Document-Version', 'X-Completion-Certificate'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
