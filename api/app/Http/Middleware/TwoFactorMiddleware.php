<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'active', 401, 'Please sign in with an active account.');
        if (($user->two_factor_enabled || app(MfaService::class)->required($user)) &&
            (!$user->two_factor_enabled || !$user->token()?->mfa_verified_at)) {
            return response()->json(['status' => false, 'message' => 'Sign in again to verify two-factor authentication.', 'mfa_required' => true], 401);
        }
        return $next($request);
    }
}
