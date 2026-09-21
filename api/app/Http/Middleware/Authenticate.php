<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return null; // Return null for API requests to prevent redirect
        }
        
        return route('login');
    }
    
    /**
     * Handle an unauthenticated user for the API
     *
     * @param \Illuminate\Http\Request $request
     * @param array $guards
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            throw new \Illuminate\Auth\AuthenticationException(
                'Unauthenticated. Please provide a valid token.'
            );
        }
        
        parent::unauthenticated($request, $guards);
    }
}
