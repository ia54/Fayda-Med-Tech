<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!RoleAccess::allows($user->role, $roles)) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have the required permissions to access this resource',
            ], 403);
        }
        return $next($request);
    }
}
