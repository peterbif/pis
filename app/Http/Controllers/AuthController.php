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
            return response()->json(['message' => 'This email is not on the approved personnel list. Please contact your administrator.'], 422);
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
