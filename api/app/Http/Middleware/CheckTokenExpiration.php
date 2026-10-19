<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('api')->check()) {
            $user = $request->user();
            $token = $user->token();
            
            if ($token->expires_at->isPast()) {
                // Expiry denies access; explicit revocation (logout, recovery or
                // rotation) separately invalidates the linked refresh credential.
                // Do not turn a normal access expiry into refresh revocation.
                
                return response()->json([
                    'status' => false,
                    'message' => 'Access token has expired',
                ], 401);
            }
        }
        
        return $next($request);
    }
}
