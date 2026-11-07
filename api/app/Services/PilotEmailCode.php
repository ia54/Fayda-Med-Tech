<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Opt-in email sign-in for one approved synthetic pilot pharmacist only. */
class PilotEmailCode
{
    public function enabled(User $user): bool
    {
        $email = config('pilot.login_email');
        return config('pilot.synthetic') === true && config('pilot.email_login') === true
            && config('database.default') === 'mysql'
            && config('database.connections.mysql.host') === 'localhost'
            && config('app.debug') === false
            && config('app.url') === 'https://pilot-api.faydamed.tech'
            && config('database.connections.mysql.database') === 'faydamed_pilot'
            && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
            && !str_ends_with(strtolower($email), '.invalid')
            && hash_equals(strtolower($email), strtolower($user->email))
            && $user->role === 'pharmacist';
    }

    public static function digest(string $token, string $code, string $email, string $key): string
    {
        return hash_hmac('sha256', $token.'|'.$code.'|'.strtolower($email), $key);
    }

    public function challenge(User $user): array
    {
        abort_unless($this->enabled($user), 503, 'Email sign-in is not configured.');
        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($user, $token, $code) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('auth_challenges')->where('user_id', $user->id)
                ->where('purpose', 'email')->where('expires_at', '>', now()->addMinutes(4))->exists(),
                429, 'Please wait one minute before requesting another email code.');
            DB::table('auth_challenges')->where('user_id', $user->id)->delete();
            DB::table('auth_challenges')->insert([
                'id' => hash('sha256', $token), 'user_id' => $user->id,
                'password_fingerprint' => hash('sha256', $user->password),
                'purpose' => 'email', 'expires_at' => now()->addMinutes(5),
                'enrollment_secret' => self::digest($token, $code, $user->email, config('app.key')),
            ]);
        });
        try {
            abort_unless(in_array(config('mail.mailers.pilot_login.transport'), ['smtp', 'sendmail'], true), 503);
            Mail::mailer('pilot_login')->raw(
                "Your FaydaMedTech sign-in code is: {$code}\n\nIt expires in five minutes. Do not share this code.\nIf you did not request it, ignore this email.",
                fn ($message) => $message->to($user->email)->subject('Your FaydaMedTech sign-in code')
                    ->from(config('pilot.login_from'), 'FaydaMedTech')
            );
        } catch (\Throwable $e) {
            DB::table('auth_challenges')->where('id', hash('sha256', $token))->delete();
            abort(503, 'We could not send your sign-in code. Please try again later.');
        }
        return ['status'=>true, 'mfa_required'=>true, 'enrollment_required'=>false,
            'method'=>'email', 'challenge_token'=>$token, 'expires_in'=>300];
    }

    public function verify(User $user, object $challenge, string $token, string $code): bool
    {
        return $this->enabled($user) && $challenge->purpose === 'email'
            && preg_match('/^[0-9]{6}$/D', $code) === 1
            && hash_equals((string) $challenge->enrollment_secret,
                self::digest($token, $code, $user->email, config('app.key')));
    }
}
