<?php
require __DIR__ . '/../../app/Support/RoleAccess.php';
require __DIR__ . '/../Support/RouteInventory.php';
use Tests\Support\RouteInventory;
class_alias(RouteInventory::class, 'Illuminate\\Support\\Facades\\Route');
require __DIR__ . '/../../routes/api.php';

$checked = 0;
$inventory = [];
$seen = [];
foreach (RouteInventory::$routes as $route) {
    $identity = $route->method . ' ' . $route->path;
    if (isset($seen[$identity])) throw new RuntimeException('Duplicate route shadows an existing access policy: ' . $identity);
    $seen[$identity] = true;
    $roles = RouteInventory::effectiveRoles($route);
    $platformOnly = preg_match('#^/admin/(security(?:/|$)|roles(?:/|$)|permissions(?:/|$))#', $route->path)
        || in_array($route->path, ['/setting-update', '/get-env-values', '/setting-env-update'], true);
    if ($platformOnly) {
        if ($roles !== ['admin'] || !in_array('auth:api', $route->middleware, true)) {
            throw new RuntimeException('Platform boundary failed: ' . $route->method . ' ' . $route->path);
        }
        $checked++;
    }
    if (str_starts_with($route->path, '/api-credentials') && !in_array('tenant', $route->middleware, true)) {
        throw new RuntimeException('Credential route missing tenant boundary');
    }
    $inventory[] = ['method' => $route->method, 'path' => $route->path, 'middleware' => $route->middleware, 'effective_roles' => $roles];
}
if ($checked < 20) throw new RuntimeException('Expected platform routes missing from inventory');
if (in_array('--json', $argv, true)) echo json_encode($inventory, JSON_PRETTY_PRINT), "\n";
else echo 'PASS: ' . $checked . ' platform-only route declarations; inventoried ' . count($inventory) . " routes. Laravel request-level tests remain required.\n";
