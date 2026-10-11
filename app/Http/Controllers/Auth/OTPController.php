<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpMailConfiguration;
use App\Services\ResendOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class OTPController extends Controller
{
    public function sendOTP(Request $request)
    {
        $recovery = $this->isRecovery($request);
        $data = $request->validate([
            'email' => 'required|email|max:254',
            ...($recovery ? ['current_password' => 'required|current_password:web'] : []),
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'registration-otp:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Please wait before resending. You can still enter the code already sent.', 'retry_after' => RateLimiter::availableIn($key)], 429);
        }
        try {
            RateLimiter::hit($key, 60);
            $otp = (string) random_int(100000, 999999);
            $demo = app(OtpMailConfiguration::class)->demoEnabled();
            $resendApi = app(OtpMailConfiguration::class)->usesResendApi();
            $mailer = (string) config('mail.default');
            if (! $demo && ! $resendApi && ! app()->environment('testing') && ! app(OtpMailConfiguration::class)->canDeliver($mailer)) {
                RateLimiter::clear($key);
                Log::warning('Registration OTP mail configuration invalid', ['reason' => 'non_delivering_mailer', 'mailer' => $mailer]);

                return response()->json(['message' => 'Email delivery is not configured on this server. Please contact the administrator.'], 503);
            }
            try {
                if (! $demo && $resendApi) {
                    app(ResendOtpMail::class)->send($email, $otp);
                } elseif (! $demo) retry(2, function () use ($otp, $email) {
                    Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($email) {
                        $message->to($email)->subject('COMPASS - Email Verification Code');
                    });
                }, 250, fn (\Throwable $e) => $e instanceof TransportExceptionInterface
                    && $this->failureReason($e) === 'connection');
            } catch (\Throwable $exception) {
                RateLimiter::clear($key);
                Log::warning('Registration OTP mail transport failed', ['exception_type' => get_class($exception), 'reason' => $this->failureReason($exception), 'mailer' => $resendApi ? 'resend_https' : $mailer]);
                $message = match ($this->failureReason($exception)) {
                    'authentication' => 'The email provider rejected the server login. The administrator needs to update the mail credentials before OTP can be sent.',
                    'certificate' => 'The server could not establish a secure email connection. Please contact the administrator.',
                    'provider_limit' => 'The email provider temporarily limited sending. Please try again later.',
                    default => 'Unable to reach the email service. No new code was issued. You can try sending again.',
                };

                return response()->json(['message' => $message], 503);
            }
            if (! $recovery) {
                $request->session()->forget(['registration_verified_until', 'registration_verified_at', 'registration_verified_email']);
            }
            $request->session()->put($recovery ? 'recovery_otp' : 'registration_otp', [
                'email' => $email, 'hash' => Hash::make($otp), 'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0,
                ...($recovery ? [
                    'user_id' => $request->user()->id,
                    'password_fingerprint' => hash('sha256', $request->user()->password),
                ] : []),
            ]);

            $payload = ['message' => 'Verification code sent. Check your inbox and spam folder. It expires in 10 minutes.', 'retry_after' => 60];

            if ($demo) {
                $payload['demo_mode'] = true;
                $payload['message'] = 'DEMO ONLY - Verification code: '.$otp.'. No email was sent. Expires in 10 minutes.';
            }
            return response()->json($payload)->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $exception) {
            RateLimiter::clear($key);
            Log::error('Registration OTP send failed unexpectedly', ['exception_type' => get_class($exception), 'path' => $request->path()]);

            return response()->json([
                'message' => 'Unable to send the verification code right now. Please try again in a minute.',
            ], 503);
        }
    }

    public function verifyOTP(Request $request)
    {
        $recovery = $this->isRecovery($request);
        $stateKey = $recovery ? 'recovery_otp' : 'registration_otp';
        $request->validate(['otp' => 'required|digits:6']);
        $state = $request->session()->get($stateKey);
        if (! $state || now()->timestamp >= $state['expires'] || $state['attempts'] >= 3) {
            $request->session()->forget($stateKey);

            return response()->json(['message' => 'Code expired or attempt limit reached. Request a new code.'], 422);
        }
        if (! Hash::check((string) $request->otp, $state['hash'])) {
            $state['attempts']++;
            $request->session()->put($stateKey, $state);

            return response()->json(['message' => 'Incorrect code. '.(3 - $state['attempts']).' attempts remaining.'], 422);
        }
        $request->session()->forget($stateKey);
        if (empty($state['email']) || ($recovery && ($state['user_id'] ?? null) !== $request->user()->id)) {
            return response()->json(['message' => 'Please request a new code to verify your email address.'], 422);
        }
        if ($recovery) {
            app(\App\Services\AccountRecoveryService::class)->bind($request->user(), $state['email'], $state['password_fingerprint']);
            return response()->json(['message' => 'Private recovery email verified and saved. Your nickname remains your sign-in identity.'])
                ->header('Cache-Control', 'private, no-store');
        }
        $verifiedAt = now();
        $request->session()->put('registration_verified_email', $state['email']);
        $request->session()->put('registration_verified_at', $verifiedAt->timestamp);
        $request->session()->put('registration_verified_until', $verifiedAt->copy()->addMinutes(10)->timestamp);

        return response()->json(['message' => 'Email verified. You can now create your account.', 'verified_until' => $request->session()->get('registration_verified_until')]);
    }

    private function isRecovery(Request $request): bool
    {
        $recovery = $request->routeIs('settings.recovery.*');
        if ($recovery) {
            abort_unless($request->user()?->role === 'seeker' && $request->user()->is_active, 403);
        }
        return $recovery;
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
