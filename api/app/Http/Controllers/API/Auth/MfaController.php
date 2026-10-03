<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    public function setup(Request $request, MfaService $mfa)
    {
        $data = $request->validate(['challenge_token' => 'required|string|size:64']);
        $result = DB::transaction(function () use ($data, $mfa) {
            $challenge = $mfa->findChallenge($data['challenge_token']);
            if (!$challenge || $challenge->purpose !== 'enroll' || !($user = $mfa->validUser($challenge)) || $user->two_factor_enabled) { return null; }
            $totp = new Google2FA;
            $secret = $challenge->enrollment_secret ? Crypt::decryptString($challenge->enrollment_secret) : $totp->generateSecretKey();
            DB::table('auth_challenges')->where('id', $challenge->id)->update(['enrollment_secret' => Crypt::encryptString($secret)]);
            return ['secret' => $secret, 'otpauth_url' => $totp->getQRCodeUrl('FaydaMedTech', $user->email, $secret)];
        });
        abort_unless($result, 401, 'Sign-in expired. Start again.');
        return response()->json(['status' => true] + $result)->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, MfaService $mfa, ApiTokenService $tokens)
    {
        $data = $request->validate(['challenge_token' => 'required|string|size:64', 'code' => 'required|string|max:100']);
        $result = DB::transaction(function () use ($data, $mfa, $tokens) {
            $challenge = $mfa->findChallenge($data['challenge_token']);
            if (!$challenge || !($user = $mfa->validUser($challenge))) { return null; }
            DB::table('auth_challenges')->where('id', $challenge->id)->increment('attempts');
            $enrolling = $challenge->purpose === 'enroll';
            if ($enrolling && ($user->two_factor_enabled || !$challenge->enrollment_secret)) { return null; }
            $secret = $enrolling ? Crypt::decryptString($challenge->enrollment_secret) : null;
            if (!$mfa->verify($user, $data['code'], $secret, !$enrolling)) { return null; }
            $codes = null;
            if ($enrolling) {
                $user->two_factor_secret = Crypt::encryptString($secret);
                $user->two_factor_enabled = true;
                $user->two_factor_confirmed_at = now();
                $codes = $mfa->recoveryCodes($user);
                $tokens->revokeAll($user);
            }
            DB::table('auth_challenges')->where('user_id', $user->id)->delete();
            $user->last_login = now();
            $user->save();
            return ['status' => true, 'message' => 'Signed in', 'user' => $user, 'recovery_codes' => $codes] + $tokens->issue($user, true);
        });
        abort_unless($result, 401, 'Code invalid or sign-in expired. Try a new code or start again.');
        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function status(Request $request, MfaService $mfa)
    {
        return response()->json(['enabled' => (bool) $request->user()->two_factor_enabled, 'required' => $mfa->required($request->user())]);
    }

    public function manage(Request $request, MfaService $mfa, ApiTokenService $tokens)
    {
        $data = $request->validate(['password' => 'required|string', 'code' => 'required|string|max:100', 'action' => 'required|in:disable,recovery']);
        $result = DB::transaction(function () use ($data, $request, $mfa, $tokens) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            if (!Hash::check($data['password'], $user->password) || !$user->two_factor_enabled) { return null; }
            if ($data['action'] === 'disable' && $mfa->required($user)) { return ['blocked' => true]; }
            if (!$mfa->verify($user, $data['code'])) { return null; }
            if ($data['action'] === 'recovery') { return ['recovery_codes' => $mfa->recoveryCodes($user)]; }
            $user->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
            $tokens->revokeAll($user);
            DB::table('auth_challenges')->where('user_id', $user->id)->delete();
            return ['disabled' => true];
        });
        abort_unless($result, 401, 'Password or verification code is invalid.');
        abort_if(isset($result['blocked']), 403, 'Your organization requires two-factor authentication.');
        return response()->json(['status' => true] + $result)->header('Cache-Control', 'no-store');
    }
}
