<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckRole;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccessBoundaryTest extends TestCase
{
    public function test_platform_routes_require_platform_administrator_in_the_real_router(): void
    {
        foreach ([
            ['GET', '/api/admin/security/settings'],
            ['PUT', '/api/admin/security/settings'],
            ['GET', '/api/admin/roles'],
            ['POST', '/api/admin/permissions'],
            ['GET', '/api/get-env-values'],
            ['POST', '/api/setting-env-update'],
        ] as [$method, $uri]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->assertContains('auth:api', $route->gatherMiddleware());
            $this->assertContains('role:admin', $route->gatherMiddleware(), $method . ' ' . $uri);
        }
    }

    public function test_public_registration_never_accepts_a_privileged_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Synthetic test',
            'email' => 'synthetic@example.invalid',
            'password' => 'Synthetic-test-only-123!',
            'password_confirmation' => 'Synthetic-test-only-123!',
            'role' => 'admin',
        ])->assertForbidden()->assertJson(['status' => false]);
    }

    public function test_middleware_rejects_an_authenticated_user_outside_the_route_roles(): void
    {
        $request = Request::create('/api/users');
        $request->setUserResolver(fn () => (object) ['role' => 'client']);
        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'admin', 'firm_admin');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_middleware_accepts_the_bare_parameters_passed_by_laravel(): void
    {
        $request = Request::create('/api/users');
        $request->setUserResolver(fn () => (object) ['role' => 'firm_admin']);
        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'admin', 'firm_admin');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_rejects_an_unauthenticated_request(): void
    {
        $request = Request::create('/api/users');
        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'admin');
        $this->assertSame(401, $response->getStatusCode());
    }
}
