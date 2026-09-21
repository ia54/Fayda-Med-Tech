<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Helpers\AuditLogger;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /** Public accounts stay closed until verified organization invitations exist. */
    public function register(Request $request)
    {
        return response()->json([
            'status' => false,
            'message' => 'Account registration is managed by your organization administrator.',
        ], 403);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login user",
     *     description="Authenticate user with email and password to get access tokens",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User login credentials",
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User logged in successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User logged in successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."),
     *             @OA\Property(property="refresh_token", type="string", example="def502004a2f4..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid login credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid login credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user();
        $tokens = $this->generateTokens($user);

        // Update last login
        $user->last_login = now();
        $user->save();

        // Audit Log
        AuditLogger::log('user_login', $user);

        return response()->json([
            'status' => true,
            'message' => 'User logged in successfully',
            'user' => $user,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     description="Revoke the user's access token and log them out",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        // Audit Log before revoking
        AuditLogger::log('user_logout', $request->user());

        $request->user()->token()->revoke();

        return response()->json([
            'status' => true,
            'message' => 'User logged out successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh-token",
     *     summary="Refresh access token",
     *     description="Generate new access and refresh tokens using a valid refresh token",
     *     operationId="refreshToken",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Refresh token",
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="def502004a2f4...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."),
     *             @OA\Property(property="refresh_token", type="string", example="def502004a2f4..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired refresh token",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid or expired refresh token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function refreshToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $refreshToken = $request->refresh_token;
        $refreshTokenData = DB::table('oauth_refresh_tokens')
            ->where('id', $refreshToken)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$refreshTokenData) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        // Get the access token ID from the refresh token
        $accessTokenId = $refreshTokenData->access_token_id;

        // Get the access token to find the user ID
        $accessToken = DB::table('oauth_access_tokens')
            ->where('id', $accessTokenId)
            ->first();

        if (!$accessToken) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid token'
            ], 401);
        }

        // Find the user
        $user = User::find($accessToken->user_id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Revoke current tokens
        DB::table('oauth_access_tokens')
            ->where('id', $accessTokenId)
            ->update(['revoked' => true]);

        DB::table('oauth_refresh_tokens')
            ->where('id', $refreshToken)
            ->update(['revoked' => true]);

        // Generate new tokens
        $tokens = $this->generateTokens($user);

        return response()->json([
            'status' => true,
            'message' => 'Token refreshed successfully',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get all users",
     *     description="Retrieve a list of all users in the system (Admin only endpoint)",
     *     operationId="getUsers",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(property="users", type="array", @OA\Items(ref="#/components/schemas/User"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     )
     * )
     */
    public function user()
    {
        $users = User::all();

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'users' => $users
        ]);
    }

    /**
     * Generate access and refresh tokens for a user
     * 
     * @param User $user The user to generate tokens for
     * @return array Returns access token, refresh token, and expiration information
     */
    private function generateTokens(User $user)
    {
        $tokenResult = $user->createToken('API Access Token');
        $accessToken = $tokenResult->token;
        $accessToken->expires_at = now()->addMinutes(60);
        $accessToken->save();

        $refreshToken = Str::random(80);

        DB::table('oauth_refresh_tokens')->insert([
            'id' => $refreshToken,
            'access_token_id' => $accessToken->id,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token' => $tokenResult->accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessToken->expires_at->diffInSeconds(now()),
        ];
    }
}
