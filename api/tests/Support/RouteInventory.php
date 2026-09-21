<?php
// Minimal declaration recorder, not a replacement for Laravel integration tests.
namespace Tests\Support;

final class RouteDeclaration
{
    public function __construct(public string $method, public string $path, public array $middleware, public mixed $handler) {}
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }
}
final class RouteGroup
{
    public function __construct(private array $middleware) {}
    public function group(callable $callback): void
    {
        $previous = RouteInventory::$middleware;
        RouteInventory::$middleware = array_merge($previous, $this->middleware);
        try { $callback(); } finally { RouteInventory::$middleware = $previous; }
    }
}
final class RouteInventory
{
    public static array $routes = [];
    public static array $middleware = [];
    public static function middleware(string|array $middleware): RouteGroup
    {
        return new RouteGroup((array) $middleware);
    }
    public static function __callStatic(string $method, array $arguments): RouteDeclaration
    {
        if (!in_array($method, ['get', 'post', 'put', 'delete'], true)) throw new \LogicException('Unsupported route declaration: ' . $method);
        $route = new RouteDeclaration(strtoupper($method), '/' . ltrim($arguments[0], '/'), self::$middleware, $arguments[1]);
        self::$routes[] = $route;
        return $route;
    }
    public static function effectiveRoles(RouteDeclaration $route): array
    {
        $roles = \App\Support\RoleAccess::ROLES;
        foreach ($route->middleware as $middleware) {
            if (str_starts_with($middleware, 'role:')) {
                $allowed = explode(',', substr($middleware, 5));
                $roles = array_values(array_filter($roles, fn ($role) => \App\Support\RoleAccess::allows($role, $allowed)));
            }
        }
        return $roles;
    }
}
