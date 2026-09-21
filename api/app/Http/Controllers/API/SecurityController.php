<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @OA\Tag(
 *     name="Security",
 *     description="API Endpoints for Platform Security Monitoring and Policy Management"
 * )
 */
class SecurityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/security/settings",
     *     summary="Get security policy settings",
     *     description="Retrieve current password complexity and 2FA requirements",
     *     operationId="getSecuritySettings",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function getSettings()
    {
        $settings = SecuritySetting::first();
        
        if (!$settings) {
            $settings = SecuritySetting::create([
                'min_password_length' => 8,
                'require_uppercase' => true,
                'require_numbers' => true,
                'require_symbols' => true,
                'enforce_2fa_all' => false,
                'enforce_2fa_admin' => true,
                'two_fa_grace_period' => 7,
                'session_timeout' => 120,
            ]);
        }

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/security/settings",
     *     summary="Update security policy",
     *     operationId="updateSecuritySettings",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="min_password_length", type="integer"),
     *             @OA\Property(property="require_uppercase", type="boolean"),
     *             @OA\Property(property="enforce_2fa_all", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Settings updated")
     * )
     */
    public function updateSettings(Request $request)
    {
        $settings = SecuritySetting::first();
        if (!$settings) {
            $settings = new SecuritySetting();
        }

        $settings->fill($request->only([
            'min_password_length',
            'require_uppercase',
            'require_numbers',
            'require_symbols',
            'enforce_2fa_all',
            'enforce_2fa_admin',
            'two_fa_grace_period',
            'session_timeout',
        ]));

        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Security settings updated successfully',
            'settings' => $settings
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/security/stats",
     *     summary="Get security metrics",
     *     description="Retrieve security score, 2FA adoption rate, and failed login counts",
     *     operationId="getSecurityStats",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function getSecurityStats()
    {
        $totalUsers = User::count();
        $usersWith2fa = User::whereNotNull('two_factor_secret')
            ->where('two_factor_secret', '!=', '')
            ->count();
            
        $failedLogins24h = AuditLog::where('event', 'login_failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        
        // Dynamic security score calculation
        $score = 70; // Base score
        if ($totalUsers > 0) {
            $faPercentage = ($usersWith2fa / $totalUsers) * 100;
            $score += ($faPercentage * 0.2); // Up to 20 points for 2FA
        }
        
        if ($failedLogins24h == 0) $score += 10;
        elseif ($failedLogins24h < 5) $score += 5;
        
        // Add points for having security settings configured
        $settings = SecuritySetting::first();
        if ($settings) {
            if ($settings->enforce_2fa_admin) $score += 5;
            if ($settings->min_password_length >= 10) $score += 5;
        }
        
        $score = min(100, round($score));

        // Count active sessions from oauth_access_tokens
        $activeSessions = 0;
        if (Schema::hasTable('oauth_access_tokens')) {
            $activeSessions = DB::table('oauth_access_tokens')
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->count();
        }
        
        // Fallback to recent logins if no active tokens found (useful for testing)
        if ($activeSessions === 0) {
            $activeSessions = User::where('last_login', '>=', now()->subHours(2))->count();
        }
        
        // Ensure at least 1 if the current user is logged in
        if ($activeSessions === 0) $activeSessions = 1;

        return response()->json([
            'success' => true,
            'stats' => [
                'security_score' => $score,
                'two_fa_percentage' => $totalUsers > 0 ? round(($usersWith2fa / $totalUsers) * 100) : 0,
                'total_users' => $totalUsers,
                'users_with_2fa' => $usersWith2fa,
                'failed_logins_24h' => $failedLogins24h,
                'active_sessions' => $activeSessions
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/security/events",
     *     summary="List recent security events",
     *     description="Retrieve a log of logins, 2FA changes, and policy updates",
     *     operationId="getSecurityEvents",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function getSecurityEvents()
    {
        $events = AuditLog::with(['user', 'organization'])
            ->whereIn('event', ['login', 'login_failed', 'logout', 'password_reset', '2fa_enabled', '2fa_disabled', 'security_policy_updated'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'events' => $events
        ]);
    }
}
