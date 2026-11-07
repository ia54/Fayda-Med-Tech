<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Password};
use Tests\Feature\PharmacyWorkflowTest;

class CloudAuthFixtureTest extends PharmacyWorkflowTest
{
    use CloudBrowserFixture;

    public function test_seed_cloud_auth_browser_fixture(): void
    {
        $directory = $this->browserDirectory('auth.sqlite');
        $password = 'SYNTHETIC-only-Cloud-2026!';
        $users = [];
        foreach (['administrator' => 'firm_admin', 'reset_client' => 'client', 'forgot_client' => 'client'] as $name => $role) {
            $users[$name] = User::create(['first_name' => 'Synthetic', 'last_name' => $name,
                'email' => $name.'@example.invalid', 'password' => $password, 'role' => $role,
                'organization_id' => 1, 'status' => 'active']);
        }
        // Issue only a reset-link fixture. No authenticated browser session is seeded.
        $reset = Password::broker()->createToken($users['reset_client']);
        $this->browserSessions([]); // Validate the known synthetic key and create the personal client only.
        $this->assertDatabaseCount('oauth_access_tokens', 0);
        $this->assertDatabaseCount('oauth_refresh_tokens', 0);
        $this->exportBrowserDatabase($directory, 'auth.sqlite', [
            'password' => $password, 'reset_token' => $reset,
            'accounts' => array_map(fn ($user) => ['id' => $user->id, 'email' => $user->email], $users),
            'sessions' => ['visitor' => ['user' => null, 'token' => null, 'expiresAt' => null]],
        ]);
    }
}
