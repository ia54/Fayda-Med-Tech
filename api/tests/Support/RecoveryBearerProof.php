<?php
namespace Tests\Support;

use App\Http\Middleware\TwoFactorMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\{Artisan, Crypt, DB};
use League\OAuth2\Server\{AuthorizationServer, ResourceServer};

/** Real cryptographic bearer validation with generated in-memory test keys only. */
trait RecoveryBearerProof
{
    private function recoveryBearerSessions(array $actors): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $this->assertNotFalse($key); $this->assertTrue(openssl_pkey_export($key, $private));
        $public = openssl_pkey_get_details($key)['key'];
        config(['passport.public_key' => $public, 'passport.private_key' => $private]);
        app()->forgetInstance(AuthorizationServer::class); app()->forgetInstance(ResourceServer::class);
        $this->assertSame(0, Artisan::call('passport:client', ['--personal' => true, '--name' => 'SYNTHETIC recovery bearer only', '--no-interaction' => true]));
        $sessions = [];
        foreach ($actors as $role => $id) {
            $user = User::findOrFail($id);
            $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => Crypt::encryptString('SYNTHETIC-RECOVERY-MFA-STATE')])->save();
            $issued = $user->createToken('SYNTHETIC recovery only', ['api']);
            $issued->token->forceFill(['mfa_verified_at' => now()])->save();
            $sessions[$role] = ['bearer' => $issued->accessToken, 'id' => $issued->token->id];
        }
        $this->withMiddleware(TwoFactorMiddleware::class);
        app('auth')->forgetGuards();
        return $sessions;
    }
    private function recoveryBearerRequest(string $url, ?string $bearer = null)
    {
        // Discard actingAs from fixture creation and cached request guards.
        app('auth')->forgetGuards();
        return $this->getJson($url, $bearer === null ? [] : ['Authorization' => 'Bearer '.$bearer]);
    }
    private function assertRecoveryBearerBoundaries(array $sessions, string $url, string $bytes): void
    {
        foreach (['pharmacist' => 200, 'technician' => 200, 'biller' => 403, 'attorney' => 403, 'firm' => 403,
            'provider' => 403, 'foreign' => 404, 'unassigned' => 404] as $role => $status) {
            $response = $this->recoveryBearerRequest($url, $sessions[$role]['bearer'])->assertStatus($status);
            if ($status === 200) {
                $this->assertSame($bytes, $response->streamedContent());
                $this->assertSame(hash('sha256', $bytes), hash('sha256', $response->streamedContent()));
            }
        }
        $this->recoveryBearerRequest($url)->assertUnauthorized();
    }
    private function assertRecoveryBearerFailureModes(array $sessions, string $url, string $bytes, int $userId): void
    {
        $session = $sessions['pharmacist']; $token = (array) DB::table('oauth_access_tokens')->find($session['id']);
        DB::table('oauth_access_tokens')->where('id', $session['id'])->update(['mfa_verified_at' => null]);
        $this->recoveryBearerRequest($url, $session['bearer'])->assertUnauthorized()->assertJsonPath('mfa_required', true);
        DB::table('oauth_access_tokens')->where('id', $session['id'])->update(['mfa_verified_at' => $token['mfa_verified_at'], 'revoked' => true]);
        $this->recoveryBearerRequest($url, $session['bearer'])->assertUnauthorized();
        DB::table('oauth_access_tokens')->where('id', $session['id'])->update(['revoked' => false, 'expires_at' => now()->subMinute()]);
        $this->recoveryBearerRequest($url, $session['bearer'])->assertUnauthorized();
        DB::table('oauth_access_tokens')->where('id', $session['id'])->update(['expires_at' => $token['expires_at']]);
        $parts = explode('.', $session['bearer']); $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';
        $this->recoveryBearerRequest($url, implode('.', $parts))->assertUnauthorized();
        $public = config('passport.public_key');
        $wrong = openssl_pkey_new(['private_key_bits' => 2048]); $this->assertNotFalse($wrong);
        config(['passport.public_key' => openssl_pkey_get_details($wrong)['key']]); app()->forgetInstance(ResourceServer::class);
        $this->recoveryBearerRequest($url, $session['bearer'])->assertUnauthorized();
        config(['passport.public_key' => $public]); app()->forgetInstance(ResourceServer::class);
        $this->assertSame($bytes, $this->recoveryBearerRequest($url, $session['bearer'])->assertOk()->streamedContent());
        $ciphertext = User::findOrFail($userId)->two_factor_secret;
        $encrypter = Crypt::getFacadeRoot();
        try {
            Crypt::swap(new \Illuminate\Encryption\Encrypter(random_bytes(32), config('app.cipher')));
            try { Crypt::decryptString($ciphertext); $this->fail('Wrong application encryption key accepted.'); }
            catch (\Illuminate\Contracts\Encryption\DecryptException $error) { $this->assertNotSame('', $error->getMessage()); }
        } finally { Crypt::swap($encrypter); }
        $this->assertSame('SYNTHETIC-RECOVERY-MFA-STATE', Crypt::decryptString($ciphertext));
    }
}
