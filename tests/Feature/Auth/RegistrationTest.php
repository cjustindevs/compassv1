<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_email_registration_is_retired_without_storing_identity_or_sending_mail(): void
    {
        Mail::fake();
        foreach (['send-otp', 'verify-otp', 'resend-otp', 'check-email', 'register-seeker'] as $endpoint) {
            $this->postJson('/api/' . $endpoint, ['email' => 'private@example.com', 'name' => 'Private Name'])->assertStatus(410);
        }
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    public function test_registration_page_uses_pseudonymous_onboarding(): void
    {
        $this->get(route('seeker.register'))->assertOk()->assertDontSee('name="email"', false);
    }
}
