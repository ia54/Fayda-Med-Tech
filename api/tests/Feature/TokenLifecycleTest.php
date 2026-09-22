<?php
namespace Tests\Feature;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TokenLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $key = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (!$key) { $this->markTestSkipped('Native OpenSSL key generation required; this suite runs in GitHub CI.'); }
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        app(ClientRepository::class)->createPersonalAccessClient(null, 'Synthetic tests', 'http://localhost');
        DB::table('organizations')->insert(['id' => 1, 'org_name' => 'Synthetic', 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => 'native@example.invalid']);
        Storage::fake('documents');
    }
    private function user(string $role): User
    {
        return User::create(['first_name' => 'Synthetic', 'last_name' => $role, 'email' => $role.'@example.invalid', 'password' => 'Synthetic-test-only-123!', 'role' => $role, 'organization_id' => 1, 'status' => 'active']);
    }
    private function bearer(?string $token = null): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', $token ? 'Bearer '.$token : '');
    }
    public function test_six_roles_complete_password_mfa_profile_and_private_document_journey_with_real_tokens(): void
    {
        SecuritySetting::create(['enforce_2fa_all' => true]);
        foreach (User::getAvailableRoles() as $role) {
            $this->bearer(); $user = $this->user($role); $secret = (new Google2FA)->generateSecretKey();
            $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => now()])->save();
            $challenge = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Synthetic-test-only-123!'])->assertOk()->assertJsonMissingPath('access_token')->json('challenge_token');
            $result = $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertOk();
            $this->assertNotEmpty($result->json('access_token'));
            $this->bearer($result->json('access_token'));
            $this->getJson('/api/profile')->assertOk()->assertJsonPath('user.role', $role);
            $document = $this->postJson('/api/documents', ['title' => 'Synthetic '.$role, 'file' => UploadedFile::fake()->create('record.pdf', 1, 'application/pdf')])->assertCreated()->json('data.id');
            $this->getJson('/api/documents/'.$document)->assertOk();
            $this->get('/api/documents/'.$document.'/preview')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
    }
    public function test_refresh_rotation_logout_and_revoked_access_cannot_restore_a_session(): void
    {
        $user = $this->user('client');
        $first = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Synthetic-test-only-123!'])->assertOk();
        $refresh = $first->json('refresh_token');
        $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => hash('sha256', $refresh)]);
        $this->assertDatabaseMissing('oauth_refresh_tokens', ['id' => $refresh]);
        $second = $this->postJson('/api/refresh-token', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/api/refresh-token', ['refresh_token' => $refresh])->assertUnauthorized();
        $this->bearer($first->json('access_token')); $this->getJson('/api/profile')->assertUnauthorized();
        $this->bearer($second->json('access_token')); $this->getJson('/api/profile')->assertOk(); $this->postJson('/api/logout')->assertOk();
        $this->bearer(); $this->postJson('/api/refresh-token', ['refresh_token' => $second->json('refresh_token')])->assertUnauthorized();
        $this->bearer($second->json('access_token')); $this->getJson('/api/profile')->assertUnauthorized();
    }
}
