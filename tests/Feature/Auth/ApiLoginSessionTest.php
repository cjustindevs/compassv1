<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LoginSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiLoginSessionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'seeker', 'email' => 'calmfox123@compass.local']);
    }

    private function login(): string
    {
        return $this->postJson('/api/login', ['login' => 'CalmFox123', 'password' => 'password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('access_token');
    }

    public function test_api_login_is_blocked_by_an_existing_web_device(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => 'CalmFox123', 'password' => 'password'])->assertRedirect();
        $this->postJson('/api/login', ['login' => 'CalmFox123', 'password' => 'password'])
            ->assertConflict()->assertJsonPath('message', LoginSessionService::DEVICE_MESSAGE)->assertJsonMissingPath('access_token');
        $this->assertDatabaseCount('sessions', 1);
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_device_blocks_web_and_second_api_logins(): void
    {
        $user = $this->user();
        $token = $this->login();
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
        $this->app['auth']->forgetGuards();
        $this->from('/login')->post('/login', ['email' => 'CalmFox123', 'password' => 'password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->postJson('/api/login', ['login' => 'CalmFox123', 'password' => 'password'])->assertConflict();
        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_api_logout_revokes_the_token_and_allows_a_new_login(): void
    {
        $this->user();
        $token = $this->login();
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->assertDatabaseCount('sessions', 0);
        $this->login();
    }

    public function test_password_change_revokes_token_and_releases_the_api_device(): void
    {
        $user = $this->user();
        $token = $this->login();
        $user->update(['password' => 'NewPassword123!']);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->assertDatabaseCount('sessions', 0);
        $this->postJson('/api/login', ['login' => 'CalmFox123', 'password' => 'NewPassword123!'])->assertOk();
    }

    public function test_password_fingerprint_rejects_token_even_if_an_update_bypasses_model_events(): void
    {
        $user = $this->user();
        $token = $this->login();
        DB::table('users')->where('id', $user->id)->update(['password' => \Illuminate\Support\Facades\Hash::make('NewPassword123!')]);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_web_logout_also_revokes_api_access(): void
    {
        $user = $this->user();
        $token = $this->login();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_deactivated_then_reactivated_account_does_not_reuse_an_old_token(): void
    {
        $user = $this->user();
        $token = $this->login();
        $user->update(['is_active' => false]);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $user->update(['is_active' => true]);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->login();
    }

    public function test_stale_api_device_can_be_replaced_by_web_login_and_its_token_stays_revoked(): void
    {
        $user = $this->user();
        $token = $this->login();
        DB::table('sessions')->where('user_id', $user->id)->update(['last_activity' => now()->subMinutes(6)->timestamp]);
        $this->post('/login', ['email' => 'CalmFox123', 'password' => 'password'])->assertRedirect(route('seeker.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_requests_refresh_activity_without_creating_duplicate_sessions(): void
    {
        $user = $this->user();
        $token = $this->login();
        DB::table('sessions')->where('user_id', $user->id)->update(['last_activity' => now()->subMinutes(4)->timestamp]);
        $this->withToken($token)->getJson('/api/user')->assertOk();
        $this->withToken($token)->getJson('/api/user')->assertOk();
        $this->assertDatabaseCount('sessions', 1);
        $this->assertSame(now()->timestamp, DB::table('sessions')->where('user_id', $user->id)->value('last_activity'));
    }

    public function test_bad_credentials_do_not_reserve_a_device(): void
    {
        $this->user();
        $this->postJson('/api/login', ['login' => 'CalmFox123', 'password' => 'wrong-password'])->assertUnauthorized();
        $this->assertDatabaseCount('sessions', 0);
    }
}
