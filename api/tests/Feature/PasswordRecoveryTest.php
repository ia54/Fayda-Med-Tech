<?php
namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\{Artisan, DB, Hash, Log, Notification, Password};
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    private User $user;
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force'=>true, '--no-interaction'=>true]);
        config(['app.frontend_url'=>'https://admin.example.invalid/']);
        Notification::fake();
        $this->user = User::create(['first_name'=>'Synthetic','last_name'=>'Recovery','email'=>'recovery+test@example.invalid','password'=>Hash::make('original-synthetic-password'),'role'=>'client','status'=>'active']);
    }
    private function resetBody(string $token): array
    {
        return ['email'=>$this->user->email,'token'=>$token,'password'=>'replacement-synthetic-password','password_confirmation'=>'replacement-synthetic-password'];
    }
    public function test_requests_do_not_disclose_accounts_and_mail_links_match_the_real_route(): void
    {
        $known = $this->postJson('/api/forgot-password',['email'=>$this->user->email])->assertOk()->assertHeader('Cache-Control','no-store, private');
        $unknown = $this->postJson('/api/forgot-password',['email'=>'absent@example.invalid'])->assertOk();
        $repeat = $this->postJson('/api/forgot-password',['email'=>$this->user->email])->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        $this->assertSame($known->json(), $repeat->json());
        Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
        Notification::assertSentTo($this->user, ResetPassword::class, function ($notification) {
            $url = $notification->toMail($this->user)->actionUrl;
            $this->assertSame('admin.example.invalid', parse_url($url, PHP_URL_HOST));
            $this->assertSame('/auth/reset-password', parse_url($url, PHP_URL_PATH));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($this->user->email, $query['email']);
            $this->assertSame($notification->token, $query['token']);
            $stored = DB::table('password_reset_tokens')->where('email',$this->user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));
            return true;
        });
    }
    public function test_success_consumes_token_and_revokes_all_sessions_and_mfa_challenges(): void
    {
        DB::table('oauth_access_tokens')->insert(['id'=>'synthetic-access','user_id'=>$this->user->id,'client_id'=>1,'revoked'=>false]);
        DB::table('oauth_refresh_tokens')->insert(['id'=>'synthetic-refresh','access_token_id'=>'synthetic-access','revoked'=>false]);
        DB::table('auth_challenges')->insert(['id'=>'synthetic-challenge','user_id'=>$this->user->id,'password_fingerprint'=>'synthetic','purpose'=>'login','expires_at'=>now()->addMinutes(5)]);
        $token = Password::broker()->createToken($this->user);
        $this->postJson('/api/reset-password',$this->resetBody($token))->assertOk();
        $this->assertTrue(Hash::check('replacement-synthetic-password',$this->user->fresh()->password));
        $this->assertDatabaseHas('oauth_access_tokens',['id'=>'synthetic-access','revoked'=>true]);
        $this->assertDatabaseHas('oauth_refresh_tokens',['id'=>'synthetic-refresh','revoked'=>true]);
        $this->assertDatabaseMissing('auth_challenges',['id'=>'synthetic-challenge']);
        $this->assertDatabaseMissing('password_reset_tokens',['email'=>$this->user->email]);
        $this->postJson('/api/reset-password',$this->resetBody($token))->assertStatus(400);
    }
    public function test_failed_session_revocation_rolls_back_password_change_and_keeps_reset_token(): void
    {
        $token = Password::broker()->createToken($this->user);
        $this->mock(\App\Services\ApiTokenService::class, function ($mock) {
            $mock->shouldReceive('revokeAll')->once()->andThrow(new \RuntimeException('synthetic failure'));
        });
        $this->postJson('/api/reset-password',$this->resetBody($token))->assertStatus(500);
        $this->assertTrue(Hash::check('original-synthetic-password',$this->user->fresh()->password));
        $this->assertTrue(Password::broker()->tokenExists($this->user, $token));
    }
    public function test_expired_wrong_and_malformed_tokens_cannot_change_password(): void
    {
        $token = Password::broker()->createToken($this->user);
        $this->postJson('/api/reset-password',$this->resetBody('wrong-token'))->assertStatus(400);
        DB::table('password_reset_tokens')->update(['created_at'=>now()->subDay()]);
        $this->postJson('/api/reset-password',$this->resetBody($token))->assertStatus(400);
        $body = $this->resetBody($token);
        $body['token'] = ['invalid'];
        $this->postJson('/api/reset-password',$body)->assertStatus(422);
        $body = $this->resetBody($token);
        $body['password_confirmation'] = 'different';
        $this->postJson('/api/reset-password',$body)->assertStatus(422);
        $this->assertTrue(Hash::check('original-synthetic-password',$this->user->fresh()->password));
    }
    public function test_delivery_errors_are_sanitized_and_do_not_reveal_accounts(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('private-provider-diagnostic'));
        Log::shouldReceive('warning')->once()->with('Password reset delivery failed',['exception_type'=>\RuntimeException::class]);
        $this->postJson('/api/forgot-password',['email'=>$this->user->email])->assertOk()->assertDontSee('private-provider-diagnostic');
    }
    public function test_requests_are_rate_limited_and_legacy_test_route_is_absent(): void
    {
        for ($i=0; $i<5; $i++) $this->postJson('/api/forgot-password',['email'=>'absent@example.invalid'])->assertOk();
        $this->postJson('/api/forgot-password',['email'=>'absent@example.invalid'])->assertStatus(429);
        $this->getJson('/api/test-password-reset/recovery@example.invalid')->assertNotFound();
    }
}
