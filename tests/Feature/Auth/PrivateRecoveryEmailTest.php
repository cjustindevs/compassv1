<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PrivateRecoveryEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.delivery_driver' => 'laravel', 'otp.demo_mode' => false]);
    }

    private function seeker(): User
    {
        return User::factory()->create([
            'role' => 'seeker', 'name' => 'CalmFox301', 'email' => 'calmfox301@compass.local',
            'password' => 'OriginalPassword!1',
        ]);
    }

    private function attach(User $user, string $email = 'private@example.test'): void
    {
        app(AccountRecoveryService::class)->bind($user, $email, hash('sha256', $user->password));
    }

    private function sendCode(User $user, string $email = 'private@example.test'): string
    {
        $code = '';
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data) use (&$code) {
            $code = $data['otp'];
        });
        $this->actingAs($user)->postJson(route('settings.recovery.send'), [
            'email' => $email, 'current_password' => 'OriginalPassword!1',
        ])->assertOk();
        return $code;
    }

    public function test_registration_saves_only_the_otp_verified_recipient_encrypted_and_hidden(): void
    {
        $this->get('/register');
        $alias = session('registration_alias');
        $code = '';
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data) use (&$code) { $code = $data['otp']; });
        $this->postJson(route('registration.otp.send'), ['email' => 'OWNER@EXAMPLE.TEST'])->assertOk();
        $this->postJson(route('registration.otp.verify'), ['otp' => $code, 'email' => 'attacker@example.test'])->assertOk();
        $this->post(route('seeker.onboarding.store'), [
            'alias' => $alias, 'email' => 'attacker@example.test', 'recovery_email' => 'attacker@example.test',
            'age' => 20, 'gender' => 'female', 'preferred_language' => 'English',
            'password' => 'OriginalPassword!1', 'password_confirmation' => 'OriginalPassword!1',
            'agree_privacy' => 1, 'agree_terms' => 1,
        ])->assertRedirect(route('request.screening'));
        $user = User::firstOrFail();
        $this->assertSame('owner@example.test', $user->recovery_email);
        $this->assertSame(strtolower($alias).'@compass.local', $user->email);
        $this->assertStringNotContainsString('owner@example.test', $user->getRawOriginal('recovery_email'));
        $this->assertStringNotContainsString('owner@example.test', $user->toJson());
        $this->assertArrayNotHasKey('recovery_email_hash', $user->toArray());
        $this->assertNull(session('registration_verified_email'));
    }

    public function test_verification_without_a_bound_email_requires_reverification(): void
    {
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->post(route('seeker.onboarding.store'), [])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_successful_resend_clears_the_previously_verified_email(): void
    {
        Mail::fake();
        $this->withSession(['registration_verified_email' => 'old@example.test', 'registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->postJson(route('registration.otp.send'), ['email' => 'new@example.test'])->assertOk();
        $this->assertNull(session('registration_verified_email'));
        $this->assertSame('new@example.test', session('registration_otp.email'));
    }

    public function test_existing_seeker_must_confirm_password_and_verify_the_exact_sent_address(): void
    {
        $user = $this->seeker();
        $this->actingAs($user)->postJson(route('settings.recovery.send'), [
            'email' => 'private@example.test', 'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $code = $this->sendCode($user, 'OWNER@EXAMPLE.TEST');
        $this->assertNull($user->fresh()->recovery_email);
        $this->postJson(route('settings.recovery.verify'), ['otp' => $code, 'email' => 'attacker@example.test'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('owner@example.test', $user->fresh()->recovery_email);
        $this->assertSame('calmfox301@compass.local', $user->fresh()->email);
        $this->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable();
    }

    public function test_recovery_code_cannot_be_reused_for_registration_or_another_account(): void
    {
        $user = $this->seeker();
        $code = $this->sendCode($user);
        $state = session('recovery_otp');
        $this->assertNull(session('registration_otp'));
        $other = User::factory()->create(['role' => 'seeker', 'password' => 'OriginalPassword!1']);
        $this->actingAs($other)->withSession(['recovery_otp' => $state])
            ->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable();
        $this->assertNull($user->fresh()->recovery_email);
        $this->assertNull($other->fresh()->recovery_email);
    }

    public function test_recovery_attempt_limit_and_expiry_do_not_change_the_saved_address(): void
    {
        $user = $this->seeker();
        $this->attach($user, 'old@example.test');
        $code = $this->sendCode($user);
        $wrong = $code === '999999' ? '888888' : '999999';
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('settings.recovery.verify'), ['otp' => $wrong])->assertUnprocessable();
        }
        $this->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable();
        $this->assertSame('old@example.test', $user->fresh()->recovery_email);
        $this->withSession(['recovery_otp' => [
            'email' => 'new@example.test', 'user_id' => $user->id, 'hash' => Hash::make($code),
            'expires' => now()->timestamp, 'attempts' => 0,
        ]])->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable();
        $this->assertSame('old@example.test', $user->fresh()->recovery_email);
    }

    public function test_password_change_during_verification_requires_a_new_code(): void
    {
        $user = $this->seeker();
        $code = $this->sendCode($user);
        $user->update(['password' => 'ChangedPassword!1']);
        $this->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable();
        $this->assertNull($user->fresh()->recovery_email);
    }

    public function test_verified_address_cannot_be_linked_to_two_accounts_or_a_staff_email(): void
    {
        $owner = $this->seeker();
        $this->attach($owner);
        $other = User::factory()->create(['role' => 'seeker', 'password' => 'OriginalPassword!1']);
        $code = $this->sendCode($other);
        $this->postJson(route('settings.recovery.verify'), ['otp' => $code])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNull($other->fresh()->recovery_email);
        $staff = User::factory()->create(['role' => 'adviser', 'email' => 'staff@example.test']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->attach($other, $staff->email);
    }

    public function test_staff_guests_and_inactive_accounts_cannot_set_seeker_recovery(): void
    {
        $this->postJson(route('settings.recovery.send'), [])->assertUnauthorized();
        foreach (['helper', 'adviser', 'admin', 'moderator', 'professional'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->postJson(route('settings.recovery.send'), [])->assertForbidden();
            $this->postJson(route('settings.recovery.verify'), ['otp' => '123456'])->assertForbidden();
        }
        $user = $this->seeker();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->postJson(route('settings.recovery.send'), [])->assertForbidden();
    }

    public function test_private_email_receives_a_valid_reset_link_for_the_nickname_identity(): void
    {
        Notification::fake();
        $user = $this->seeker();
        $this->attach($user);
        $user->refresh();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'PRIVATE@EXAMPLE.TEST'])
            ->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification) use ($user) {
            $this->assertSame('private@example.test', $user->routeNotificationFor('mail', $notification));
            $this->assertSame($user->email, $user->getEmailForPasswordReset());
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringContainsString(urlencode($user->email), $url);
            $this->assertStringNotContainsString('private%40example.test', $url);
            $this->post('/reset-password', [
                'email' => $user->email, 'token' => $notification->token,
                'password' => 'ResetPassword!1', 'password_confirmation' => 'ResetPassword!1',
            ])->assertRedirect(route('login'))->assertSessionHasNoErrors();
            $this->post('/reset-password', [
                'email' => $user->email, 'token' => $notification->token,
                'password' => 'ReplayPassword!1', 'password_confirmation' => 'ReplayPassword!1',
            ])->assertSessionHasErrors('email');
            $this->post('/login', ['email' => $user->name, 'password' => 'ResetPassword!1'])->assertRedirect();
            $this->assertAuthenticatedAs($user);
            return true;
        });
    }

    public function test_linking_a_replacement_address_revokes_outstanding_reset_tokens(): void
    {
        $user = $this->seeker();
        $this->attach($user);
        $token = Password::createToken($user);
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->attach($user, 'replacement@example.test');
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertSame('old@example.test', app(AccountRecoveryService::class)->signInAddress('old@example.test'));
    }

    public function test_legacy_or_inactive_seeker_does_not_receive_reset_mail_and_public_status_stays_neutral(): void
    {
        Notification::fake();
        $user = $this->seeker();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $message = session('status');
        Notification::assertNothingSent();
        $this->attach($user);
        $user->update(['is_active' => false]);
        $this->post('/forgot-password', ['email' => 'private@example.test'])->assertSessionHas('status', $message);
        Notification::assertNothingSent();
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', $message);
    }

    public function test_reset_mail_uses_the_existing_https_provider_when_configured(): void
    {
        config(['otp.delivery_driver' => 'resend', 'services.resend.key' => 'test-only-key',
            'mail.from.address' => 'otp@example.test', 'mail.from.name' => 'COMPASS']);
        Http::preventStrayRequests();
        Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'reset-message-id'], 200)]);
        $user = $this->seeker();
        $this->attach($user);
        $this->post('/forgot-password', ['email' => 'private@example.test'])->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['to'] === ['private@example.test']
            && $request['subject'] === 'COMPASS - Reset your password'
            && str_contains($request['html'], 'reset-password/')
            && str_starts_with($request->header('Idempotency-Key')[0], 'password-reset-'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'private@example.test']);
    }

    public function test_settings_show_setup_only_to_the_seeker_and_preserve_other_notification_routing(): void
    {
        $user = $this->seeker();
        $this->attach($user);
        $user->refresh();
        $this->actingAs($user)->get(route('settings.account'))->assertOk()
            ->assertSee('Private recovery email')->assertSee('private@example.test')->assertSee('Verify and save email');
        $this->assertSame($user->email, $user->routeNotificationForMail());
        $helper = User::factory()->create(['role' => 'helper']);
        $this->actingAs($helper)->get(route('settings.account'))->assertOk()->assertDontSee('data-recovery-email', false);
        $this->assertArrayNotHasKey('recovery_email', $user->toArray());
    }

    public function test_staff_email_changes_cannot_take_over_a_private_recovery_address(): void
    {
        $user = $this->seeker();
        $this->attach($user);
        $staff = User::factory()->create(['role' => 'moderator']);
        $originalEmail = $staff->email;
        $this->actingAs($staff)->patch(route('settings.account.update'), [
            'name' => $staff->name, 'email' => 'private@example.test',
        ])->assertSessionHasErrors('email');
        $this->assertSame($originalEmail, $staff->fresh()->email);
        $this->assertSame($user->email, app(AccountRecoveryService::class)->signInAddress('PRIVATE@example.test'));
    }

    public function test_registration_rejects_an_email_already_verified_for_another_account(): void
    {
        $user = $this->seeker();
        $this->attach($user);
        $this->get('/register');
        $this->withSession([
            'registration_verified_email' => 'private@example.test', 'registration_verified_until' => now()->addMinutes(10)->timestamp,
        ])->post(route('seeker.onboarding.store'), [
            'alias' => session('registration_alias'), 'age' => 20, 'gender' => 'female', 'preferred_language' => 'English',
            'password' => 'OriginalPassword!1', 'password_confirmation' => 'OriginalPassword!1',
            'agree_privacy' => 1, 'agree_terms' => 1,
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_recovery_lookup_is_normalized_and_uses_a_keyed_digest(): void
    {
        $service = app(AccountRecoveryService::class);
        $this->assertSame($service->emailHash('private@example.test'), $service->emailHash(' PRIVATE@EXAMPLE.TEST '));
        $this->assertNotSame(hash('sha256', 'private@example.test'), $service->emailHash('private@example.test'));
    }

    public function test_legacy_staff_email_spelling_is_preserved_for_the_password_broker(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'adviser', 'email' => 'Adviser@Example.test']);
        $this->post('/forgot-password', ['email' => 'adviser@example.test'])->assertSessionHasNoErrors();
        Notification::assertSentTo($staff, PasswordResetNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $staff->email]);
    }
}
