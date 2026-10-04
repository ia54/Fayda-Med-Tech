<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        return response()->json(['status' => false, 'message' => 'Account registration is managed by your organization administrator.'], 403);
    }

    public function login(Request $request, MfaService $mfa, ApiTokenService $tokens)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'enroll_mfa' => 'sometimes|boolean']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password) || $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Invalid login credentials'], 401);
        }
        if ($user->two_factor_enabled || $mfa->required($user) || ($data['enroll_mfa'] ?? false)) {
            return response()->json($mfa->challenge($user))->header('Cache-Control', 'no-store');
        }
        $result = DB::transaction(function () use ($user, $tokens) {
            $user->last_login = now();
            $user->save();
            return $tokens->issue($user);
        });
        return response()->json(['status' => true, 'message' => 'Signed in', 'user' => $user] + $result)->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        DB::transaction(function () use ($request) {
            $token = $request->user()->token();
            DB::table('oauth_refresh_tokens')->where('access_token_id', $token->id)->update(['revoked' => true]);
            $token->revoke();
        });
        return response()->json(['status' => true, 'message' => 'Signed out']);
    }

    public function refreshToken(Request $request, ApiTokenService $tokens, MfaService $mfa)
    {
        $data = $request->validate(['refresh_token' => 'required|string|max:200']);
        $result = DB::transaction(function () use ($data, $tokens, $mfa) {
            $refresh = DB::table('oauth_refresh_tokens')->where('id', hash('sha256', $data['refresh_token']))
                ->where('revoked', false)->where('expires_at', '>', now())->lockForUpdate()->first();
            if (!$refresh) { return null; }
            $access = DB::table('oauth_access_tokens')->where('id', $refresh->access_token_id)->where('revoked', false)->lockForUpdate()->first();
            if (!$access || !($user = User::find($access->user_id)) || $user->status !== 'active') { return null; }
            if (($user->two_factor_enabled || $mfa->required($user)) && (!$user->two_factor_enabled || !$access->mfa_verified_at)) { return null; }
            DB::table('oauth_access_tokens')->where('id', $access->id)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->where('id', $refresh->id)->update(['revoked' => true]);
            return $tokens->issue($user, (bool) $access->mfa_verified_at);
        });
        abort_unless($result, 401, 'Sign in again. The refresh token is invalid or expired.');
        return response()->json(['status' => true, 'message' => 'Session refreshed'] + $result)->header('Cache-Control', 'no-store');
    }

    public function user()
    {
        return response()->json(['status' => true, 'message' => 'Users retrieved successfully', 'users' => User::all()]);
    }
}
