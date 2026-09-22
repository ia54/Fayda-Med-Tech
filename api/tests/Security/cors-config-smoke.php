<?php
// Standalone declaration check; Laravel supplies env() in the application.
if (!function_exists('env')) {
    function env($key, $default = null) { return $default; }
}
// This must return configuration even during a browser preflight request.
$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['HTTP_ORIGIN'] = 'https://untrusted.example.invalid';
$config = require __DIR__ . '/../../config/cors.php';
if (!is_array($config)) throw new RuntimeException('CORS configuration must return an array');
if (in_array('*', $config['allowed_origins'], true) || in_array($_SERVER['HTTP_ORIGIN'], $config['allowed_origins'], true)) {
    throw new RuntimeException('Untrusted origin allowed');
}
if (!in_array('https://admin.faydamed.tech', $config['allowed_origins'], true)) throw new RuntimeException('Portal origin missing');
if (!in_array('Authorization', $config['allowed_headers'], true)) throw new RuntimeException('Bearer header missing');
echo "PASS: CORS configuration returns during OPTIONS and retains explicit origin/header allowlists.\n";
