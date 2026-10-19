<?php

namespace App\Services;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function required(User $user): bool
    {
        $settings = SecuritySetting::first();
        return (bool) ($settings?->enforce_2fa_all ||
            (($settings?->enforce_2fa_admin ?? true) && in_array($user->role, ['admin', 'firm_admin'], true)));
    }

    public function challenge(User $user, bool $enroll = false): array
    {
        $token = Str::random(64);
        $purpose = $user->two_factor_enabled ? 'verify' : 'enroll';
        DB::transaction(function () use ($user, $token, $purpose) {
            DB::table('auth_challenges')->where('user_id', $user->id)->delete();
            DB::table('auth_challenges')->where('expires_at', '<', now())->delete();
            DB::table('auth_challenges')->insert([
                'id' => hash('sha256', $token), 'user_id' => $user->id,
                'password_fingerprint' => hash('sha256', $user->password),
                'purpose' => $purpose, 'expires_at' => now()->addMinutes(5),
            ]);
        });
        return ['status' => true, 'mfa_required' => true, 'enrollment_required' => $purpose === 'enroll',
            'challenge_token' => $token, 'expires_in' => 300];
    }

    public function findChallenge(string $token): ?object
    {
        return DB::table('auth_challenges')->where('id', hash('sha256', $token))
            ->where('expires_at', '>', now())->where('attempts', '<', 5)->lockForUpdate()->first();
    }

    public function validUser(object $challenge): ?User
    {
        $user = User::lockForUpdate()->find($challenge->user_id);
        if (!$user || $user->status !== 'active' || !hash_equals($challenge->password_fingerprint, hash('sha256', $user->password))) {
            return null;
        }
        return $user;
    }

    public function recoveryCodes(User $user): array
    {
        $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 10));
        $user->two_factor_recovery_codes = json_encode(array_map(fn ($code) => hash('sha256', $code), $codes));
        $user->save();
        return $codes;
    }

    // Call under a user row lock so a TOTP or recovery code cannot be replayed concurrently.
    public function verify(User $user, string $code, ?string $secret = null, bool $allowRecovery = true): bool
    {
        if ($allowRecovery && !preg_match('/^\d{6}$/', $code)) {
            $hash = hash('sha256', trim($code));
            $codes = json_decode($user->two_factor_recovery_codes ?: '[]', true) ?: [];
            foreach ($codes as $index => $saved) {
                if (hash_equals($saved, $hash)) {
                    unset($codes[$index]);
                    $user->two_factor_recovery_codes = json_encode(array_values($codes));
                    $user->save();
                    return true;
                }
            }
            return false;
        }
        if (!preg_match('/^\d{6}$/', $code)) { return false; }
        $secret ??= $user->two_factor_secret ? Crypt::decryptString($user->two_factor_secret) : null;
        if (!$secret) { return false; }
        $step = (new Google2FA)->verifyKeyNewer($secret, $code, $user->two_factor_last_step ?? 0, 1);
        if ($step === false) { return false; }
        $user->two_factor_last_step = $step;
        $user->save();
        return true;
    }
}
