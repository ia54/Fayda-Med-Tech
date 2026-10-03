<?php
// SPA fallback for the private synthetic website copy.
$public = '/home/faydamed.tech/staging/frontend/dist';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath($public . '/' . ltrim($uri, '/'));
if ($file && str_starts_with($file, $public . '/') && is_file($file)
    && !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['php', 'env'], true)
    && !str_starts_with(basename($file), '.')) {
    return false;
}
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
readfile($public . '/index.html');
