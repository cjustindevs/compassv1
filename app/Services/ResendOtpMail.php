<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResendOtpMail
{
    public function configured(): bool
    {
        return filled(config('services.resend.key'))
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && filled(config('mail.from.name'));
    }

    public function send(string $email, string $otp): void
    {
        if (! $this->configured()) {
            Log::warning('Registration OTP Resend API configuration invalid', ['reason' => 'missing_key_or_sender']);
            throw new \RuntimeException('OTP delivery configuration missing');
        }

        try {
            $response = Http::withToken(config('services.resend.key'))->acceptJson()
                ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])
                ->withHeaders(['Idempotency-Key' => 'registration-otp-'.Str::uuid()])
                ->post('https://api.resend.com/emails', [
                    'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                    'to' => [$email], 'subject' => 'COMPASS - Email Verification Code',
                    'html' => view('emails.otp', compact('otp'))->render(),
                ]);
        } catch (\Throwable $exception) {
            // Exception text and API bodies can contain credentials or message content.
            Log::warning('Registration OTP Resend API request failed', [
                'reason' => 'connection_or_request', 'exception_type' => get_class($exception),
            ]);
            throw new \RuntimeException('OTP delivery request failed');
        }

        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id) || trim($id) === '') {
            Log::warning('Registration OTP Resend API rejected delivery', [
                'http_status' => $response->status(),
                'reason' => match (true) {
                    $response->status() === 401 => 'authentication',
                    $response->status() === 403 => 'permission_or_sender',
                    $response->status() === 429 => 'provider_limit',
                    $response->status() >= 500 => 'provider_unavailable',
                    $response->successful() => 'malformed_response',
                    default => 'request_rejected',
                },
            ]);
            throw new \RuntimeException('OTP delivery not accepted');
        }
    }
}
