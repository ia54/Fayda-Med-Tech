<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated but has no organization_id and is not a super admin
        if ($user && !$user->organization_id && $user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'User is not associated with any organization.'
            ], 403);
        }

        return $next($request);
    }
}
