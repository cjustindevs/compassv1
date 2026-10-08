<?php

namespace App\Console\Commands;

use App\Services\OtpMailConfiguration;
use App\Services\ResendOtpMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

class DiagnoseOtpMail extends Command
{
    protected $signature = 'compass:diagnose-otp {--connect : Test SMTP connection and authentication without sending email}';

    protected $description = 'Check effective OTP mail settings without exposing credentials or sending a code';

    public function handle(): int
    {
        if (app(OtpMailConfiguration::class)->usesResendApi()) {
            $ready = app(ResendOtpMail::class)->configured();
            $this->table(['Check', 'Effective value'], [
                ['Environment', app()->environment()],
                ['Config cached', app()->configurationIsCached() ? 'yes' : 'no'],
                ['OTP delivery', 'Resend HTTPS API'],
                ['OTP demo enabled', app(OtpMailConfiguration::class)->demoEnabled() ? 'yes (local/testing only)' : 'no'],
                ['Resend API key configured', filled(config('services.resend.key')) ? 'yes' : 'no'],
                ['Key and sender configured', $ready ? 'yes (delivery not tested)' : 'NO'],
            ]);
            $this->info('No email sent. Verify delivery using registration and the Resend dashboard. SMTP is not used for OTP.');
            if ($this->option('connect')) {
                $this->warn('--connect is SMTP-only and does not probe the Resend API or send a test email.');
            }
            return $ready ? self::SUCCESS : self::FAILURE;
        }
        $name = (string) config('mail.default');
        $config = config('mail.mailers.'.$name, []);
        $ready = app(OtpMailConfiguration::class)->canDeliver($name);
        $this->table(['Check', 'Effective value'], [
            ['Environment', app()->environment()], ['Config cached', app()->configurationIsCached() ? 'yes' : 'no'],
            ['OTP demo enabled', app(OtpMailConfiguration::class)->demoEnabled() ? 'yes (local/testing only)' : 'no'],
            ['Application URL', preg_replace('/[?#].*/', '', (string) config('app.url'))],
            ['Mailer', $name], ['Transport', $config['transport'] ?? 'missing'], ['Delivery-capable configuration', $ready ? 'yes (delivery not yet tested)' : 'NO'],
            ['SMTP port', (string) ($config['port'] ?? 'not applicable')], ['SMTP scheme', (string) ($config['scheme'] ?? 'automatic')],
            ['SMTP host configured', empty($config['host']) ? 'no' : 'yes'], ['SMTP username configured', empty($config['username']) ? 'no' : 'yes'],
            ['SMTP password configured', empty($config['password']) ? 'no' : 'yes'], ['Mail URL override', empty($config['url']) ? 'no' : 'yes'],
            ['Sender configured', filled(config('mail.from.address')) ? 'yes' : 'no'], ['OpenSSL', extension_loaded('openssl') ? 'available' : 'missing'],
            ['Session driver', (string) config('session.driver')], ['Secure session cookie', config('session.secure') ? 'yes' : 'no'],
        ]);
        if (! $ready) {
            $this->error('Select a delivering mailer. log/array fallbacks cannot deliver verification mail.');

            return self::FAILURE;
        }
        if (! $this->option('connect')) {
            $this->info('No email sent. Use --connect to test SMTP from this server.');

            return self::SUCCESS;
        }
        try {
            $transport = Mail::mailer($name)->getSymfonyTransport();
            if (! $transport instanceof SmtpTransport) {
                $this->warn('Connection check supports a direct SMTP mailer only. No email was sent.');

                return self::FAILURE;
            }
            try {
                $transport->start();
                $this->info('SMTP connection and configured authentication succeeded. No email sent; inbox delivery is not verified.');
            } finally {
                $transport->stop();
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, '535'),str_contains($message, 'authenticate'),str_contains($message, '534') => 'SMTP credentials rejected. Check the hosted username and app password/provider credentials.',
                str_contains($message, 'certificate') => 'TLS certificate validation failed. Check the server CA bundle and hostname; do not disable TLS verification.',
                default => 'Connection or transport setup failed. Check host, port, scheme, DNS and hosting outbound SMTP restrictions.',
            };
            $this->error($reason);

            return self::FAILURE;
        }
    }
}
