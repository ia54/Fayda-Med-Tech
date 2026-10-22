<?php
// Copy only this entrypoint and the accompanying .htaccess to the pilot web root.
// Private configuration is outside every served root. Fail closed before Laravel loads.
declare(strict_types=1);
ini_set('display_errors', '0');
header('Cache-Control: no-store');
$configPath = dirname(__DIR__).'/fayda-synthetic-pilot/gateway.json';
$config = is_readable($configPath) ? json_decode(file_get_contents($configPath), true) : null;
$provided = $_SERVER['HTTP_X_FAYDA_PILOT_KEY'] ?? '';
if (!is_array($config) || ($config['synthetic_only'] ?? false) !== true
    || !preg_match('/^[a-f0-9]{64}$/D', $config['key'] ?? '')
    || !hash_equals($config['key'], $provided)) {
    http_response_code(403); exit;
}
$root = realpath(dirname(__DIR__).'/fayda-synthetic-pilot/releases/f2d6a0a/api');
if (!$root || !is_file($root.'/.env')) { http_response_code(503); exit; }
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($path) || !preg_match('~^/api/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*/?$~D', $path)) {
    http_response_code(404); exit;
}
unset($_SERVER['HTTP_X_FAYDA_PILOT_KEY'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
try {
define('LARAVEL_START', microtime(true));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (!config('pilot.synthetic') || config('app.debug') || config('database.connections.mysql.database') !== 'faydamed_pilot') {
    http_response_code(503); exit;
}
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo '{"message":"Pilot request unavailable."}';
}
