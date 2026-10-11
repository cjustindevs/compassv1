<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification as ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'moderator']);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_registered_unknown_and_throttled_addresses_receive_the_same_public_response(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'moderator']);
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $message = session('status');
        $this->flushSession();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'unknown@example.test'])
            ->assertRedirect('/forgot-password')->assertSessionHasNoErrors()->assertSessionHas('status', $message);
        $this->flushSession();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')->assertSessionHasNoErrors()->assertSessionHas('status', $message);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_delivery_failure_does_not_reveal_account_status(): void
    {
        User::factory()->create(['email' => 'known@example.test', 'role' => 'moderator']);
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('Private provider details'));
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.test'])
            ->assertRedirect('/forgot-password')->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If an account matches that email, a password-reset link will be sent. Please check your inbox and spam folder.');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'moderator']);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'moderator']);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
