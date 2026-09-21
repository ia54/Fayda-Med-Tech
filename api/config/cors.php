<?php

// public/cors.php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'https://www.faydatech.com',
    'https://faydatech.com',
    'https://admin.faydatech.com',
    'https://faydamed.tech',
    'https://www.faydamed.tech',
    'https://admin.faydamed.tech',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

// Methods and headers you accept
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');

// Mirror requested headers if sent by browser preflight, otherwise use a safe fallback.
$reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Authorization, Content-Type, X-Requested-With, Accept, Origin, X-CSRF-TOKEN';
header('Access-Control-Allow-Headers: '.$reqHeaders);

// Optional: cache preflight for 24h
header('Access-Control-Max-Age: 86400');

// Short-circuit preflights
if (@$_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

/*
 * For non-OPTIONS requests, do nothing else here.
 * Just ensure this file is included early (before output)
 * so headers are sent with the real response as well.
 */