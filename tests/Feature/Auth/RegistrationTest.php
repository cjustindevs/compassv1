<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_retries_have_a_separate_limit_and_return_to_the_form(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.71']);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('registration.alias.shuffle'))->assertOk();
        }
        for ($i = 0; $i < 10; $i++) {
            $this->from(route('register'))->post(route('seeker.onboarding.store'), [])
                ->assertRedirect(route('register'))->assertSessionHasErrors('email');
        }
        $this->post(route('seeker.onboarding.store'), ['age' => 22, 'password' => 'DoNotFlash!1'])
            ->assertRedirect(route('register'))->assertSessionHasErrors('registration');
        $this->assertSame(22, session('_old_input.age'));
        $this->assertNull(session('_old_input.password'));
        $this->travel(61)->seconds();
        $this->from(route('register'))->post(route('seeker.onboarding.store'), [])
            ->assertSessionHasErrors('email');
    }

    public function test_registration_page_has_email_verification_and_shuffle(): void
    {
        $this->get(route('seeker.register'))->assertOk()->assertSee('Email for verification')->assertSee('Shuffle alias');
        $first = session('registration_alias');
        $response = $this->postJson(route('registration.alias.shuffle'))->assertOk();
        $this->assertNotSame($first, $response->json('alias'));
        $this->assertSame(session('registration_alias'), $response->json('alias'));
    }

    public function test_unverified_registration_is_rejected(): void
    {
        $this->post(route('seeker.onboarding.store'), [])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_sent_code_is_hashed_and_can_only_be_verified_once(): void
    {
        $code = null;
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data, $callback) use (&$code) { $code = $data['otp']; });
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->postJson(route('registration.otp.send'), ['email' => 'otp-test@example.com'])->assertOk();
        $this->assertNull(session('registration_verified_until'));
        $this->assertNotSame($code, session('registration_otp.hash'));
        $this->postJson(route('registration.otp.send'), ['email' => 'otp-test@example.com'])->assertStatus(429);
        $this->postJson(route('registration.otp.verify'), ['otp' => $code])->assertOk();
        $this->assertGreaterThan(now()->timestamp, session('registration_verified_until'));
        $this->postJson(route('registration.otp.verify'), ['otp' => $code])->assertStatus(422);
    }

    public function test_expired_code_and_attempt_limit_are_enforced(): void
    {
        $state = ['hash' => Hash::make('123456'), 'expires' => now()->timestamp, 'attempts' => 0];
        $this->withSession(['registration_otp' => $state])->postJson(route('registration.otp.verify'), ['otp' => '123456'])->assertStatus(422);
        $state['expires'] = now()->addMinutes(10)->timestamp;
        $this->withSession(['registration_otp' => $state]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('registration.otp.verify'), ['otp' => '999999'])->assertStatus(422);
        }
        $this->postJson(route('registration.otp.verify'), ['otp' => '123456'])->assertStatus(422);
        $this->assertNull(session('registration_verified_until'));
    }

    public function test_failed_mail_delivery_preserves_previous_verification(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Unavailable'));
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->postJson(route('registration.otp.send'), ['email' => 'failure-test@example.com'])->assertStatus(503);
        $this->assertNull(session('registration_otp'));
        $this->assertGreaterThan(now()->timestamp, session('registration_verified_until'));
    }

    public function test_two_verified_seekers_can_register_from_the_same_ip(): void
    {
        foreach (['CalmFox101', 'BraveOwl202'] as $alias) {
            $this->withSession([
                'registration_alias' => $alias,
                'registration_verified_until' => now()->addMinutes(10)->timestamp,
            ])->post(route('seeker.onboarding.store'), [
                'alias' => $alias, 'age' => 20, 'gender' => 'female',
                'preferred_language' => 'English', 'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ])->assertRedirect(route('seeker.consent'))->assertSessionHasNoErrors();

            $this->assertAuthenticated();
            $this->assertNull(session('registration_verified_until'));
            $this->post('/logout');
        }

        $this->assertSame(2, \App\Models\HelpSeeker::where('registration_ip', '127.0.0.1')->count());
    }

    public function test_registration_persists_the_otp_verification_time(): void
    {
        $this->freezeTime();
        $verifiedAt = now()->copy();
        $this->withSession(['registration_otp' => [
            'hash' => Hash::make('123456'), 'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0,
        ]])->postJson(route('registration.otp.verify'), ['otp' => '123456'])->assertOk();
        $this->travel(2)->minutes();
        $this->get(route('register'));
        $this->post(route('seeker.onboarding.store'), [
            'alias' => session('registration_alias'), 'age' => 20, 'gender' => 'female',
            'preferred_language' => 'English', 'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertRedirect(route('seeker.consent'));
        $user = \App\Models\User::firstOrFail();
        $this->assertSame($verifiedAt->timestamp, $user->email_verified_at->timestamp);
        $this->assertTrue($user->email_verified_at->lt($user->created_at));
        $this->assertNull(session('registration_verified_at'));
    }

    public function test_alias_cannot_be_replaced_with_arbitrary_text(): void
    {
        $this->get(route('register'));
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->post(route('seeker.onboarding.store'), [
                'alias' => 'UnapprovedAlias', 'age' => 20, 'gender' => 'female',
                'preferred_language' => 'English', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            ])->assertSessionHasErrors('alias');
        $this->assertDatabaseCount('users', 0);
    }
}
