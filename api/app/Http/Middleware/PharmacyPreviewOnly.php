<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** The new pharmacy subsystem is not yet cleared for operational dispensing. */
class PharmacyPreviewOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'The pharmacy subsystem is currently limited to a synthetic-data development preview.');

        return $next($request);
    }
}
