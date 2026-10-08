<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.demo_mode' => false, 'otp.delivery_driver' => 'resend',
            'services.resend.key' => 'test-only-api-key', 'mail.default' => 'log',
            'mail.from.address' => 'otp@projectcompass.help', 'mail.from.name' => 'COMPASS Support']);
        Http::preventStrayRequests();
        Mail::shouldReceive('send')->never();
    }

    public function test_https_delivery_uses_existing_template_and_verification_without_smtp(): void
    {
        Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'accepted-email-id'], 200)]);
        $response = $this->postJson(route('registration.otp.send'), ['email' => 'test-recipient@example.test'])
            ->assertOk()->assertJsonMissingPath('demo_mode');
        $request = Http::recorded()[0][0];
        $this->assertSame('https://api.resend.com/emails', $request->url());
        $this->assertSame('POST', $request->method());
        $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-only-api-key'));
        $this->assertSame('COMPASS Support <otp@projectcompass.help>', $request['from']);
        $this->assertSame(['test-recipient@example.test'], $request['to']);
        $this->assertSame('COMPASS - Email Verification Code', $request['subject']);
        preg_match('/class="code">\s*([0-9]{6})\s*</', $request['html'], $matches);
        $code = $matches[1];
        $this->assertStringNotContainsString($code, $response->getContent());
        $this->assertTrue(Hash::check($code, session('registration_otp.hash')));
        $this->assertSame(0, session('registration_otp.attempts'));
        $this->assertGreaterThan(now()->timestamp, session('registration_otp.expires'));
        $this->postJson(route('registration.otp.send'), ['email' => 'test-recipient@example.test'])->assertStatus(429);
        Http::assertSentCount(1);
        $this->postJson(route('registration.otp.verify'), ['otp' => $code])->assertOk();
        $this->postJson(route('registration.otp.verify'), ['otp' => $code])->assertStatus(422);
    }

    public function test_rejections_and_malformed_responses_never_issue_a_replacement_or_log_private_body(): void
    {
        $diagnostics = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $metadata) use (&$diagnostics) {
            $diagnostics[] = [$message, $metadata];
        });
        $state = ['hash' => Hash::make('345678'), 'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0];
        $this->withSession(['registration_otp' => $state]);
        $statuses = [401, 403, 422, 429, 500, 200];
        $sequence = Http::sequence();
        foreach ($statuses as $status) $sequence->push(['message' => 'private-response-content'], $status);
        Http::fake(['https://api.resend.com/emails' => $sequence]);
        foreach ($statuses as $status) {
            $this->postJson(route('registration.otp.send'), ['email' => 'failure@example.test'])->assertStatus(503)
                ->assertJsonPath('message', 'Unable to reach the email service. No new code was issued. You can try sending again.')
                ->assertDontSee('private-response-content')->assertDontSee('test-only-api-key');
            $this->assertSame($state, session('registration_otp'));
            $this->assertContains(['Registration OTP Resend API rejected delivery', ['http_status' => $status, 'reason' => match ($status) {401 => 'authentication', 403 => 'permission_or_sender', 429 => 'provider_limit', 500 => 'provider_unavailable', 200 => 'malformed_response', default => 'request_rejected'}]], $diagnostics);
        }
    }

    public function test_network_exception_releases_cooldown_and_does_not_expose_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('private-network-error'));
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('registration.otp.send'), ['email' => 'network@example.test'])
                ->assertStatus(503)->assertDontSee('private-network-error');
        }
        $this->assertNull(session('registration_otp'));
    }

    public function test_missing_api_key_does_not_contact_provider(): void
    {
        config(['services.resend.key' => null]);
        Http::fake();
        $this->postJson(route('registration.otp.send'), ['email' => 'missing-key@example.test'])->assertStatus(503);
        Http::assertNothingSent();
        $this->assertNull(session('registration_otp'));
    }
}
