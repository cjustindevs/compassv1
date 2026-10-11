<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_nickname_formats_share_the_five_failure_limit(): void
    {
        Event::fake([Lockout::class]);
        User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        $formats = [
            'GentleBear1463',
            'gentlebear1463@compass.local',
            'GentleBear1463@compasslocal',
            '  GENTLEBEAR1463  ',
            'GENTLEBEAR1463@COMPASS.LOCAL',
        ];

        foreach ($formats as $login) {
            $this->postJson('/login', ['email' => $login, 'password' => 'wrong-password'])
                ->assertUnprocessable()->assertJsonPath('errors.email.0', trans('auth.failed'));
        }

        foreach ($formats as $login) {
            $response = $this->postJson('/login', ['email' => $login, 'password' => 'password'])
                ->assertUnprocessable();
            $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));
            $this->assertGuest();
        }
        Event::assertDispatchedTimes(Lockout::class, count($formats));
    }

    public function test_lockout_expires_at_the_existing_sixty_second_deadline(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'GentleBear1463', 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->travel(59)->seconds();
        $response = $this->postJson('/login', ['email' => 'gentlebear1463@compass.local', 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertSame(trans('auth.throttle', ['seconds' => 1, 'minutes' => 1]), $response->json('errors.email.0'));
        $this->assertGuest();

        $this->travel(2)->seconds();
        $this->post('/login', ['email' => 'GentleBear1463@compasslocal', 'password' => 'password'])
            ->assertRedirect(route('seeker.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_failures_from_equivalent_formats(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        foreach (['GentleBear1463', 'gentlebear1463@compass.local'] as $login) {
            $this->postJson('/login', ['email' => $login, 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->post('/login', ['email' => 'GentleBear1463@compasslocal', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'gentlebear1463@compass.local', 'password' => 'wrong-password'])
                ->assertUnprocessable()->assertJsonPath('errors.email.0', trans('auth.failed'));
        }
        $response = $this->postJson('/login', ['email' => 'GentleBear1463', 'password' => 'password'])->assertUnprocessable();
        $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));
        $this->assertGuest();
    }

    public function test_a_locked_account_does_not_block_another_account_on_the_same_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        $other = User::factory()->create(['role' => 'seeker', 'email' => 'calmfox123@compass.local']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'GentleBear1463', 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $response = $this->postJson('/login', ['email' => 'GentleBear1463@compasslocal', 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));

        $this->post('/login', ['email' => 'CalmFox123', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($other);
    }

    public function test_the_existing_ip_scope_is_preserved(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'GentleBear1463', 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
        $this->post('/login', ['email' => 'gentlebear1463@compass.local', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_nickname_formats_share_a_limit_without_exposing_account_existence(): void
    {
        foreach (['UnknownNickname', 'unknownnickname@compass.local', 'UnknownNickname@compasslocal', 'UnknownNickname', 'UnknownNickname'] as $login) {
            $this->postJson('/login', ['email' => $login, 'password' => 'wrong-password'])
                ->assertUnprocessable()->assertJsonPath('errors.email.0', trans('auth.failed'));
        }
        $response = $this->postJson('/login', ['email' => 'unknownnickname@compass.local', 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertStringContainsString('Too many login attempts', $response->json('errors.email.0'));
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    #[DataProvider('nicknameFormats')]
    public function test_existing_supported_nickname_formats_still_log_in(string $login): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        $this->post('/login', ['email' => $login, 'password' => 'password'])
            ->assertRedirect(route('seeker.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public static function nicknameFormats(): array
    {
        return [
            'nickname' => ['GentleBear1463'],
            'internal email' => ['gentlebear1463@compass.local'],
            'compact internal email' => ['GentleBear1463@compasslocal'],
            'nickname with spaces and capitals' => ['  GENTLEBEAR1463  '],
        ];
    }

    #[DataProvider('roles')]
    public function test_email_login_and_role_redirects_are_preserved(string $role): void
    {
        // Preserve authentication for the exact stored staff email, including its case.
        $user = User::factory()->create(['role' => $role, 'email' => 'Existing.Account@example.test', 'remember_token' => null]);
        $this->post('/login', ['email' => '  Existing.Account@example.test  ', 'password' => 'password', 'remember' => true])
            ->assertRedirect(route($role.'.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public static function roles(): array
    {
        $roles = array_keys(User::ROLE_LABELS);

        return array_combine($roles, array_map(fn ($role) => [$role], $roles));
    }

    public function test_inactive_accounts_remain_blocked(): void
    {
        User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local', 'is_active' => false]);
        $this->postJson('/login', ['email' => 'GentleBear1463', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', trans('auth.failed'));
        $this->assertGuest();
    }

    public function test_existing_live_device_still_blocks_a_second_login(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'email' => 'gentlebear1463@compass.local']);
        DB::table('sessions')->insert([
            'id' => 'other-live-device', 'user_id' => $user->id, 'ip_address' => '192.0.2.20',
            'user_agent' => 'Test browser', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);
        $this->from('/login')->post('/login', ['email' => 'GentleBear1463@compasslocal', 'password' => 'password'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'This account is already signed in on another device. Sign out there first, or try again in a few minutes.']);
        $this->assertGuest();
        $this->assertDatabaseHas('sessions', ['id' => 'other-live-device', 'user_id' => $user->id]);
    }

    public function test_administrator_login_auditing_is_preserved(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'email' => 'administrator@example.test']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['user_account_id' => $user->id, 'action' => AuditLogger::ADMIN_LOGIN_FAILED]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('audit_logs', ['user_account_id' => $user->id, 'action' => AuditLogger::ADMIN_LOGIN_SUCCEEDED]);
    }
}
