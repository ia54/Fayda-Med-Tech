<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Token;

class SessionTimeoutMiddleware
{
    /**
     * Session timeout in minutes (configurable)
     * PDF Section 16: Session timeout and forced re-login
     */
    protected $timeout;

    public function __construct()
    {
        $this->timeout = config('session.lifetime', 120);
    }

    /**
     * Handle an incoming request.
     *
     * Tracks last activity via Passport token metadata (since API doesn't use sessions)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('api')->check()) {
            $user = $request->user();
            $tokenId = $user->token()->id ?? null;

            if ($tokenId) {
                $cacheKey = "user_last_activity_{$tokenId}";
                $lastActivity = Cache::get($cacheKey);

                // Check if session has expired
                if ($lastActivity) {
                    $lastActivityTime = \Carbon\Carbon::parse($lastActivity);
                    if ($lastActivityTime->lt(now()->subMinutes($this->timeout))) {
                        $token = Token::find($tokenId);
                        if ($token) {
                            $token->revoke(); // Passport use revoke() instead of delete() for tokens usually
                        }
                        Cache::forget($cacheKey);

                        return response()->json([
                            'status' => false,
                            'message' => 'Session expired due to inactivity. Please login again.',
                            'session_timeout' => true,
                        ], 401);
                    }
                }

                // Update last activity timestamp in Cache (TTL matches session lifetime)
                Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes($this->timeout + 10));
            }
        }

        return $next($request);
    }
}