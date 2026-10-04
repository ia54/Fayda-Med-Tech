<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for User Identity and Access Management"
 * )
 */
class PasswordResetController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/forgot-password",
     *     summary="Request password reset link",
     *     description="Sends a reset link to the user's email if they exist",
     *     operationId="forgotPassword",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reset link sent successfully")
     * )
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:254',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            // Do not disclose accounts, tokens, addresses or provider diagnostics.
            \Illuminate\Support\Facades\Log::warning('Password reset delivery failed', ['exception_type' => get_class($exception)]);
        }
        return response()->json([
            'status' => true,
            'message' => 'If this email matches an account, reset instructions will be sent. If they do not arrive, contact your administrator.',
        ])->header('Cache-Control', 'no-store');

    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *     path="/api/reset-password",
     *     summary="Reset password using token",
     *     description="Updates user password after verifying the reset token",
     *     operationId="resetPassword",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password reset successfully")
     * )
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:256',
            'email' => 'required|string|email|max:254',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = \Illuminate\Support\Facades\DB::transaction(fn () => Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
                app(\App\Services\ApiTokenService::class)->revokeAll($user);
                \Illuminate\Support\Facades\DB::table('auth_challenges')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        ));

        return $status === Password::PASSWORD_RESET
            ? response()->json([
                'status' => true,
                'message' => 'Password reset successfully'
            ])
            : response()->json([
                'status' => false,
                'message' => 'Unable to reset password',
                'error' => 'This reset link is invalid or expired. Request a new link.'
            ], 400);
    }
}
