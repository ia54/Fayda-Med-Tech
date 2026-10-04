<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function register(): void
    {
        // Custom MFA-aware endpoints are the only token issuance flow.
        Passport::ignoreRoutes();
    }

    public function boot(): void
    {
        $this->registerPolicies();
        
        // Token lifetimes
        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        
        // Enable password grant
        Passport::personalAccessTokensExpireIn(now()->addHour());
        
        // Ensure token validation checks for expired tokens
        Passport::tokensCan([
            'api' => 'Access API endpoints',
        ]);
    }
}
