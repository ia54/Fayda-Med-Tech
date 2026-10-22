<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use LogicException;

class SyntheticPilotServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!config('pilot.synthetic')) {
            return;
        }
        // Explicitly override even accidentally supplied delivery configuration.
        config(['mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
        Http::globalRequestMiddleware(function ($request) {
            throw new LogicException('External requests are disabled in the synthetic pilot.');
        });
    }
}
