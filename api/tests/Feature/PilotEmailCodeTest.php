<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\PilotEmailCode;
use App\Services\ApiTokenService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PilotEmailCodeTest extends TestCase
{
    private User $user;
    private string $code = '';
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force'=>true]);
        DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic','org_type'=>'provider','subscription_plan'=>'test','email'=>'org@example.invalid']);
        $this->user = User::create(['first_name'=>'Synthetic','last_name'=>'Tester','email'=>'test@example.com','password'=>'Synthetic-test-only-123!','role'=>'pharmacist','organization_id'=>1,'status'=>'active']);
        // Storage remains disposable SQLite; production scope is separately tested below.
        $service = \Mockery::mock(PilotEmailCode::class)->makePartial();
        $service->shouldReceive('enabled')->andReturn(true);
        $this->app->instance(PilotEmailCode::class, $service);
        config(['mail.mailers.pilot_login.transport'=>'sendmail', 'pilot.login_from'=>'no-reply@example.com']);
        Mail::shouldReceive('mailer')->with('pilot_login')->andReturnSelf();
        Mail::shouldReceive('raw')->andReturnUsing(function ($body, $callback) {
            preg_match('/code is: ([0-9]{6})/', $body, $matches);
            $this->code = $matches[1];
        });
        $this->mock(ApiTokenService::class, function ($mock) {
            $mock->shouldReceive('issue')->andReturn(['access_token'=>'synthetic-token']);
            $mock->shouldReceive('revokeAll')->andReturnNull();
        });
    }
    private function login(): string
    {
        return $this->postJson('/api/login', ['email'=>$this->user->email,'password'=>'Synthetic-test-only-123!'])
            ->assertOk()->assertJson(['method'=>'email','mfa_required'=>true,'enrollment_required'=>false])
            ->assertJsonMissingPath('access_token')->json('challenge_token');
    }
    private function verify(string $token, ?string $code = null)
    {
        return $this->postJson('/api/auth/mfa/verify', ['challenge_token'=>$token,'code'=>$code ?? $this->code]);
    }
    public function test_code_is_hashed_and_single_use(): void
    {
        $token = $this->login();
        $this->assertNotSame($this->code, DB::table('auth_challenges')->value('enrollment_secret'));
        $this->postJson('/api/auth/mfa/setup', ['challenge_token'=>$token])->assertUnauthorized();
        $this->verify($token)->assertOk()->assertJson(['access_token'=>'synthetic-token']);
        $this->assertTrue($this->user->fresh()->two_factor_enabled);
        $this->verify($token)->assertUnauthorized();
    }
    public function test_five_failed_attempts_block_correct_code(): void
    {
        $token = $this->login();
        for ($i=0; $i<5; $i++) $this->verify($token, '000000')->assertUnauthorized();
        $this->verify($token)->assertUnauthorized();
    }
    public function test_expiry_and_resend_cooldown(): void
    {
        $token = $this->login();
        $this->postJson('/api/login', ['email'=>$this->user->email,'password'=>'Synthetic-test-only-123!'])->assertStatus(429);
        $this->travel(6)->minutes();
        $this->verify($token)->assertUnauthorized();
        $next = $this->login();
        $this->verify($token)->assertUnauthorized();
        $this->verify($next)->assertOk();
    }
    public function test_email_and_password_changes_invalidate_codes(): void
    {
        $token = $this->login();
        $this->user->update(['email'=>'changed@example.com']);
        $this->verify($token)->assertUnauthorized();
        $this->user->update(['email'=>'test@example.com','password'=>'Changed-password-123!']);
        $this->verify($token)->assertUnauthorized();
    }
    public function test_unavailable_mail_transport_does_not_leave_a_usable_challenge(): void
    {
        config(['mail.mailers.pilot_login.transport'=>'array']);
        $this->postJson('/api/login', ['email'=>$this->user->email,'password'=>'Synthetic-test-only-123!'])->assertStatus(503);
        $this->assertSame(0, DB::table('auth_challenges')->count());
    }
    public function test_scope_rejects_other_accounts_and_nonpilot_database(): void
    {
        config(['pilot.synthetic'=>true,'pilot.email_login'=>true,'pilot.login_email'=>'test@example.com',
            'database.default'=>'mysql','database.connections.mysql.host'=>'localhost',
            'database.connections.mysql.database'=>'faydamed_pilot','app.debug'=>false,'app.url'=>'https://pilot-api.faydamed.tech']);
        $service = new PilotEmailCode;
        $this->assertTrue($service->enabled($this->user));
        $this->user->role='admin'; $this->assertFalse($service->enabled($this->user));
        $this->user->role='pharmacist';
        config(['database.connections.mysql.database'=>'production']);
        $this->assertFalse($service->enabled($this->user));
    }
}
