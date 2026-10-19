<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function issue(User $user, bool $mfaVerified = false): array
    {
        $result = $user->createToken('API Access Token', ['api']);
        $access = $result->token;
        $access->expires_at = now()->addHour();
        $access->mfa_verified_at = $mfaVerified ? now() : null;
        $access->save();
        $refresh = Str::random(80);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => hash('sha256', $refresh), 'access_token_id' => $access->id,
            'revoked' => false, 'expires_at' => now()->addDays(30),
        ]);
        return ['access_token' => $result->accessToken, 'refresh_token' => $refresh, 'token_type' => 'Bearer', 'expires_in' => 3600];
    }

    public function revokeAll(User $user): void
    {
        $ids = DB::table('oauth_access_tokens')->where('user_id', $user->id)->pluck('id');
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        DB::table('oauth_access_tokens')->whereIn('id', $ids)->update(['revoked' => true]);
    }
}
