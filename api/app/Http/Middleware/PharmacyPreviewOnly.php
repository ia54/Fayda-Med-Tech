<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\HostedPharmacyPreview;

/** The new pharmacy subsystem is not yet cleared for operational dispensing. */
class PharmacyPreviewOnly
{
    public function handle(Request $request, Closure $next)
    {
        $hostedPreview = HostedPharmacyPreview::allows([
            'environment' => app()->environment(),
            'synthetic' => config('pilot.synthetic'),
            'enabled' => config('pilot.pharmacy_preview'),
            // Server-side request attribute, never a caller-supplied header/body value.
            'gateway_verified' => $request->attributes->get('fayda_pilot_gateway_verified'),
            'debug' => config('app.debug'),
            'connection' => config('database.default'),
            'host' => config('database.connections.mysql.host'),
            'database' => config('database.connections.mysql.database'),
            'url' => config('app.url'),
            'mail' => config('mail.default'),
            'queue' => config('queue.default'),
            'broadcast' => config('broadcasting.default'),
        ]);
        abort_unless(app()->environment(['local', 'testing']) || $hostedPreview, 503, 'The pharmacy subsystem is currently limited to a synthetic-data development preview.');

        return $next($request);
    }
}
