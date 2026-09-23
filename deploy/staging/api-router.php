<?php
// Temporary synthetic staging server only; never use this as production hosting.
$public = '/home/api.faydamed.tech/staging/api/public';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath($public . '/' . ltrim($uri, '/'));
$extensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'webp'];
if ($file && str_starts_with($file, $public . '/') && is_file($file)
    && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions, true)) {
    return false;
}
require $public . '/index.php';
