<?php

// Laravel's HandleCors middleware owns preflight and response headers.
// Keep this file side-effect free so configuration caching works.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
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
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
