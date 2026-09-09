<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OTPController extends Controller
{
    public function sendOTP(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254']);
        $email = strtolower(trim($data['email']));
        $key = 'registration-otp:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Please wait before resending. You can still enter the code already sent.', 'retry_after' => RateLimiter::availableIn($key)], 429);
        }
        RateLimiter::hit($key, 60);
        $otp = (string) random_int(100000, 999999);
        try {
            Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($email) {
                $message->to($email)->subject('COMPASS - Email Verification Code');
            });
        } catch (\Throwable $exception) {
            RateLimiter::clear($key);
            \Illuminate\Support\Facades\Log::warning('Registration OTP mail transport failed', ['exception_type' => get_class($exception)]);
            return response()->json(['message' => 'The email could not be sent. Please try again shortly.'], 503);
        }
        $request->session()->forget(['registration_verified_until', 'registration_verified_at']);
        $request->session()->put('registration_otp', [
            'hash' => Hash::make($otp), 'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0,
        ]);
        return response()->json(['message' => 'Verification code sent. Check your inbox and spam folder. It expires in 10 minutes.', 'retry_after' => 60]);
    }

    public function verifyOTP(Request $request)
    {
        $request->validate(['otp' => 'required|digits:6']);
        $state = $request->session()->get('registration_otp');
        if (!$state || now()->timestamp >= $state['expires'] || $state['attempts'] >= 3) {
            $request->session()->forget('registration_otp');
            return response()->json(['message' => 'Code expired or attempt limit reached. Request a new code.'], 422);
        }
        if (!Hash::check((string) $request->otp, $state['hash'])) {
            $state['attempts']++;
            $request->session()->put('registration_otp', $state);
            return response()->json(['message' => 'Incorrect code. '.(3 - $state['attempts']).' attempts remaining.'], 422);
        }
        $request->session()->forget('registration_otp');
        $verifiedAt = now();
        $request->session()->put('registration_verified_at', $verifiedAt->timestamp);
        $request->session()->put('registration_verified_until', $verifiedAt->copy()->addMinutes(10)->timestamp);
        return response()->json(['message' => 'Email verified. You can now create your account.', 'verified_until' => $request->session()->get('registration_verified_until')]);
    }
}
