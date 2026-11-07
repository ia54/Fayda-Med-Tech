<?php

namespace Tests\Feature;

use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\MfaService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaTest extends TestCase
{
    private User $user;
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        DB::table('organizations')->insert(['id' => 1, 'org_name' => 'Synthetic', 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => 'org@example.invalid']);
        $this->user = User::create(['first_name' => 'Synthetic', 'last_name' => 'Tester', 'email' => 'mfa@example.invalid', 'password' => 'Synthetic-test-only-123!', 'role' => 'firm_admin', 'organization_id' => 1, 'status' => 'active']);
        // MFA behavior is tested against real storage and HTTP, token issuance is isolated here.
        $this->mock(ApiTokenService::class, function ($mock) {
            $mock->shouldReceive('issue')->andReturn(['access_token' => 'synthetic-token', 'refresh_token' => 'synthetic-refresh', 'token_type' => 'Bearer', 'expires_in' => 3600]);
            $mock->shouldReceive('revokeAll')->andReturnNull();
        });
    }
    private function login(): string
    {
        return $this->postJson('/api/login', ['email' => $this->user->email, 'password' => 'Synthetic-test-only-123!'])
            ->assertOk()->assertJson(['mfa_required' => true])->assertJsonMissingPath('access_token')->json('challenge_token');
    }
    private function enable(): string
    {
        $secret = (new Google2FA)->generateSecretKey();
        $this->user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => now()])->save();
        return $secret;
    }
    public function test_password_cannot_access_application_and_enrollment_requires_valid_totp(): void
    {
        $challenge = $this->login();
        $this->getJson('/api/profile')->assertUnauthorized();
        $secret = $this->postJson('/api/auth/mfa/setup', ['challenge_token' => $challenge])->assertOk()->json('secret');
        $this->assertNotSame($secret, DB::table('auth_challenges')->value('enrollment_secret'));
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => 'invalid'])->assertUnauthorized();
        $result = $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertOk()->assertJsonCount(10, 'recovery_codes')->assertJsonMissingPath('user.two_factor_secret')->assertJsonMissingPath('user.two_factor_recovery_codes');
        $this->assertTrue($this->user->fresh()->two_factor_enabled);
        $this->assertStringNotContainsString($result->json('recovery_codes.0'), $this->user->fresh()->two_factor_recovery_codes);
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertUnauthorized();
    }
    public function test_attempt_limit_expiration_and_password_changes_invalidate_challenges(): void
    {
        $this->enable();
        $challenge = $this->login();
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => 'invalid'])->assertUnauthorized();
        $this->assertNull(app(MfaService::class)->findChallenge($challenge));
        $challenge = $this->login();
        DB::table('auth_challenges')->update(['expires_at' => now()->subMinute()]);
        $this->assertNull(app(MfaService::class)->findChallenge($challenge));
        $challenge = $this->login();
        $this->user->update(['password' => 'Changed-synthetic-password!']);
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => 'invalid'])->assertUnauthorized();
    }
    public function test_recovery_codes_and_authenticator_steps_are_single_use(): void
    {
        $secret = $this->enable();
        $codes = app(MfaService::class)->recoveryCodes($this->user);
        $challenge = $this->login();
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $codes[0]])->assertOk();
        $challenge = $this->login();
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $codes[0]])->assertUnauthorized();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $code])->assertOk();
        $challenge = $this->login();
        $this->postJson('/api/auth/mfa/verify', ['challenge_token' => $challenge, 'code' => $code])->assertUnauthorized();
    }
    public function test_policy_and_verified_token_are_enforced_for_all_six_roles(): void
    {
        SecuritySetting::create(['enforce_2fa_all' => true]);
        $this->enable();
        foreach (User::getAvailableRoles() as $role) {
            $user = $this->user->fresh(); $user->role = $role;
            $user->withAccessToken(new Token(['expires_at' => now()->addHour()]));
            $this->actingAs($user, 'api');
            $this->getJson('/api/profile')->assertUnauthorized();
            $user->withAccessToken(new Token(['expires_at' => now()->addHour(), 'mfa_verified_at' => now()]));
            $this->actingAs($user, 'api');
            $this->getJson('/api/profile')->assertOk()->assertJsonMissingPath('user.two_factor_secret');
        }
    }
    public function test_inactive_accounts_and_oauth_password_grant_cannot_bypass_mfa(): void
    {
        $this->user->update(['status' => 'inactive']);
        $this->postJson('/api/login', ['email' => $this->user->email, 'password' => 'Synthetic-test-only-123!'])->assertUnauthorized();
        $this->postJson('/oauth/token', ['grant_type' => 'password'])->assertNotFound();
    }
    public function test_required_mfa_cannot_be_disabled_and_recovery_rotation_replaces_old_codes(): void
    {
        $this->enable();
        $codes = app(MfaService::class)->recoveryCodes($this->user);
        $this->user->withAccessToken(new Token(['expires_at' => now()->addHour(), 'mfa_verified_at' => now()]));
        $this->actingAs($this->user, 'api');
        $data = ['password' => 'Synthetic-test-only-123!', 'code' => $codes[0]];
        $this->postJson('/api/auth/mfa/manage', $data + ['action' => 'disable'])->assertForbidden();
        $this->postJson('/api/auth/mfa/manage', $data + ['action' => 'recovery'])->assertOk()->assertJsonCount(10, 'recovery_codes');
        $this->assertFalse(app(MfaService::class)->verify($this->user->fresh(), $codes[1]));
    }
}
