<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces 2FA for roles that require it (PDF Section 16).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if 2FA is required for this user's role
        $rolesRequiring2FA = ['admin', 'firm_admin'];

        if (in_array($user->role, $rolesRequiring2FA)) {
            // Check if 2FA is enabled for the user
            if (!$user->two_factor_enabled) {
                return response()->json([
                    'status' => false,
                    'message' => 'Two-factor authentication is required for your role',
                    'require_2fa' => true
                ], 403);
            }

            // Check if 2FA was verified in this session
            if (!$request->session() || !$request->session()->get('2fa_verified')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Two-factor authentication verification required',
                    'require_2fa_verification' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
