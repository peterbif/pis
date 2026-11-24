<?php
namespace App\Http\Controllers;

use App\Models\{ApprovedEmail,EmailOtp,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash,Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email:rfc|max:254', 'password' => 'required|string|max:200']);
        $credentials['email'] = Str::lower($credentials['email']);
        if (!Auth::guard('web')->attempt($credentials, false)) {
            return response()->json(['message' => 'Invalid credentials, try again!'], 422);
        }
        $request->session()->regenerate();
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->noContent();
    }

    public function requestPasswordReset(Request $request)
    {
        $values = $request->validate(['email' => 'required|email:rfc|max:254']);
        $email = Str::lower($values['email']);
        $user = User::where('email', $email)->first();

        // Always return the same response to avoid disclosing registered addresses.
        if ($user) {
            $code = (string) random_int(100000, 999999);
            EmailOtp::where('email', $email)->delete();
            EmailOtp::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'password_hash' => null,
                'expires_at' => now()->addMinutes(10),
            ]);

            try {
                Mail::raw("Hello,\n\nWe received a request to reset the password for your UCH Personnel Information System account.\n\nYour password reset code is: {$code}\n\nEnter this code on the password reset screen within 10 minutes. If you did not request this, you can ignore this email.\n\nUniversity College Hospital, Ibadan",
                    fn ($message) => $message->to($email)->subject('Your UCH password reset code'));
            } catch (Throwable $exception) {
                report($exception);
                EmailOtp::where('email', $email)->delete();
                return response()->json(['message' => 'We could not send the reset code just now. Please try again shortly.'], 503);
            }
        }

        return response()->json(['message' => 'If an account exists for that email, a password reset code has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $values = $request->validate([
            'email' => 'required|email:rfc|max:254',
            'code' => 'required|digits:6',
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $email = Str::lower($values['email']);

        $result = DB::transaction(function () use ($email, $values) {
            $otp = EmailOtp::where('email', $email)->lockForUpdate()->latest()->first();
            if (!$otp || $otp->password_hash !== null || $otp->expires_at->isPast() || $otp->attempts >= 5) {
                return ['error' => 'This reset code has expired or the maximum attempts were reached. Request a new code.'];
            }
            if (!Hash::check($values['code'], $otp->code_hash)) {
                $otp->increment('attempts');
                return ['error' => 'That code does not match. Please try again.'];
            }
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (!$user) {
                $otp->delete();
                return ['error' => 'This reset request is no longer available. Request a new code.'];
            }
            $user->update(['password' => Hash::make($values['password'])]);
            $otp->delete();
            return ['success' => true];
        });

        if (isset($result['error'])) return response()->json(['message' => $result['error']], 422);
        return response()->json(['message' => 'Your password has been reset. You can now sign in with your new password.']);
    }

    public function checkEmail(Request $request)
    {
        $values = $request->validate(['email' => 'required|email:rfc|max:254']);
        $email = Str::lower($values['email']);
        $approved = ApprovedEmail::where('email', $email)->whereNull('used_at')->exists();
        return response()->json([
            'available' => $approved && !User::where('email', $email)->exists(),
            'message' => $approved ? 'This email is approved. You can continue.' : 'This email has not been approved by your personnel administrator.',
        ]);
    }

    public function signup(Request $request)
    {
        $values = $request->validate([
            'email' => 'required|email:rfc|max:254',
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $email = Str::lower($values['email']);
        if (!ApprovedEmail::where('email', $email)->whereNull('used_at')->exists() || User::where('email', $email)->exists()) {
            return response()->json(['message' => 'This email is not on the approved personnel list. Please contact the administrators @ SmartClass Room 2.'], 422);
        }
        $code = (string) random_int(100000, 999999);
        $passwordHash = Hash::make($values['password']);
        DB::transaction(function () use ($email, $code, $passwordHash) {
            EmailOtp::where('email', $email)->delete();
            EmailOtp::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'password_hash' => $passwordHash,
                'expires_at' => now()->addMinutes(10),
            ]);
        });
        try {
            Mail::raw("Hello,\n\nWe received a request to create your UCH Personnel Information System account.\n\nYour verification code is: {$code}\n\nEnter this code on the sign-up page within 10 minutes. If you did not request this, you can ignore this email.\n\nUniversity College Hospital, Ibadan",
                fn ($message) => $message->to($email)->subject('Verify your UCH personnel account'));
        } catch (Throwable $exception) {
            report($exception);
            EmailOtp::where('email', $email)->delete();
            return response()->json(['message' => 'We could not send your verification code just now. Please try again shortly or contact your personnel administrator.'], 503);
        }
        return response()->json(['message' => 'A verification code has been sent to your email address.']);
    }

    public function verify(Request $request)
    {
        $values = $request->validate(['email' => 'required|email:rfc|max:254', 'code' => 'required|digits:6']);
        $email = Str::lower($values['email']);
        $result = DB::transaction(function () use ($email, $values) {
            $otp = EmailOtp::where('email', $email)->lockForUpdate()->latest()->first();
            if (!$otp || $otp->expires_at->isPast() || $otp->attempts >= 5) {
                return ['error' => 'This code has expired or the maximum attempts were reached. Request a new code.'];
            }
            if (!Hash::check($values['code'], $otp->code_hash)) {
                $otp->increment('attempts');
                return ['error' => 'That code does not match. Please try again.'];
            }
            $invite = ApprovedEmail::where('email', $email)->whereNull('used_at')->lockForUpdate()->first();
            if (!$invite) {
                return ['error' => 'This invitation is no longer available. Contact your administrator.'];
            }
            $user = User::create(['email' => $email, 'password' => $otp->password_hash, 'role' => 'personnel', 'email_verified_at' => now()]);
            $user->profile()->create(['data' => []]);
            $invite->update(['used_at' => now()]);
            $otp->delete();
            return ['user' => $user];
        });
        if (isset($result['error'])) return response()->json(['message' => $result['error']], 422);
        return response()->json(['message' => 'Your email is verified. Your account is ready. Please sign in with your email and password.']);
    }
}
