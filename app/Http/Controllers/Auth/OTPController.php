<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OTPController extends Controller
{
    /**
     * Send OTP to the provided email
     */
    public function sendOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email'
        ]);

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        // Store in session
        session([
            'otp' => $otp,
            'otp_email' => $request->email,
            'otp_expires_at' => $expiresAt,
            'otp_attempts' => 0
        ]);

        // Send email
        try {
            Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('COMPASS - Email Verification Code');
            });
        } catch (\Exception $e) {
            Log::error('OTP email send failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Please try again.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'email' => $request->email,
            'expires_in' => 10
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $sessionOtp = session('otp');
        $sessionEmail = session('otp_email');
        $sessionExpiry = session('otp_expires_at');
        $attempts = session('otp_attempts', 0);

        // Check if OTP exists
        if (!$sessionOtp || !$sessionEmail) {
            return response()->json([
                'success' => false,
                'message' => 'No OTP request found. Please request a new code.'
            ], 400);
        }

        // Check expiry
        if (now()->gt($sessionExpiry)) {
            session()->forget(['otp', 'otp_email', 'otp_expires_at', 'otp_attempts']);
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new code.'
            ], 400);
        }

        // Check attempts (max 3)
        if ($attempts >= 3) {
            session()->forget(['otp', 'otp_email', 'otp_expires_at', 'otp_attempts']);
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new code.'
            ], 400);
        }

        // Verify OTP
        if ($request->otp !== $sessionOtp) {
            session(['otp_attempts' => $attempts + 1]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. ' . (2 - $attempts) . ' attempts remaining.'
            ], 400);
        }

        // OTP verified - create verification token
        $token = Str::random(60);
        session([
            'verification_token' => $token,
            'verified_email' => $sessionEmail
        ]);

        // Clear OTP session data (keep token)
        session()->forget(['otp', 'otp_email', 'otp_expires_at', 'otp_attempts']);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
            'token' => $token
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Check if email already registered
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login.'
            ], 400);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        session([
            'otp' => $otp,
            'otp_email' => $request->email,
            'otp_expires_at' => $expiresAt,
            'otp_attempts' => 0
        ]);

        try {
            Mail::send('emails.otp', ['otp' => $otp], function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('COMPASS - New Verification Code');
            });
        } catch (\Exception $e) {
            Log::error('OTP resend email failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Please try again.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent.'
        ]);
    }

    /**
     * Check if email is already registered
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Email already registered.' : 'Email available.'
        ]);
    }
}