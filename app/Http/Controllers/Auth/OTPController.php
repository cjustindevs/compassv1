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
            retry(2, function () use ($otp, $email) {
                Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($email) {
                    $message->to($email)->subject('COMPASS - Email Verification Code');
                });
            }, 250, fn (\Throwable $e) => $e instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface
                && $this->failureReason($e) === 'connection');
        } catch (\Throwable $exception) {
            RateLimiter::clear($key);
            \Illuminate\Support\Facades\Log::warning('Registration OTP mail transport failed', ['exception_type' => get_class($exception), 'reason' => $this->failureReason($exception), 'mailer' => config('mail.default')]);
            $message = match ($this->failureReason($exception)) {
                'authentication' => 'The email provider rejected the server login. The administrator needs to update the mail credentials before OTP can be sent.',
                'certificate' => 'The server could not establish a secure email connection. Please contact the administrator.',
                'provider_limit' => 'The email provider temporarily limited sending. Please try again later.',
                default => 'Unable to reach the email service. No new code was issued. You can try sending again.',
            };
            return response()->json(['message' => $message], 503);
        }
        $request->session()->forget(['registration_verified_until', 'registration_verified_at']);
        $request->session()->put('registration_otp', [
            'hash' => Hash::make($otp), 'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0,
        ]);

        $message = 'Verification code sent. Check your inbox and spam folder. It expires in 10 minutes.';
        $payload = ['message' => $message, 'retry_after' => 60];

        // Demo convenience: when the app is configured with the log mailer
        // (no real SMTP), no email is delivered anywhere. Surface the code on
        // screen so the flow remains usable. Never exposed when real SMTP is
        // configured, because the code is genuinely emailed in that case.
        if (config('mail.default') === 'log') {
            $payload['message'] = "Demo mode — your verification code is: {$otp}. It expires in 10 minutes.";
            $payload['debug_otp'] = $otp;
            \Illuminate\Support\Facades\Log::info("Registration OTP (log mailer) for {$email}: {$otp}");
        }

        return response()->json($payload);
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
    private function failureReason(\Throwable $exception): string
    {
        // Never expose/log the SMTP transcript, recipient, credentials or OTP.
        $message = strtolower($exception->getMessage());
        return match (true) {
            str_contains($message, '535'), str_contains($message, 'authenticate'), str_contains($message, '534') => 'authentication',
            str_contains($message, 'certificate') => 'certificate',
            str_contains($message, 'sending limit'), str_contains($message, 'quota'), str_contains($message, 'daily user sending') => 'provider_limit',
            default => 'connection',
        };
    }
}
