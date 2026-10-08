<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    private function loginPayload(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }

    private function seedOtherSession(User $user, int $lastActivity): string
    {
        $id = (string) Str::uuid();

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => $lastActivity,
        ]);

        return $id;
    }

    public function test_login_succeeds_when_no_other_live_session_exists(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);

        $this->post('/login', $this->loginPayload($user))
            ->assertRedirect(route('seeker.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_denied_while_another_device_session_is_live(): void
    {
        $user = User::factory()->create(['role' => 'helper']);
        $this->seedOtherSession($user, now()->getTimestamp());

        $this->post('/login', $this->loginPayload($user))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_purges_stale_sessions_from_other_devices(): void
    {
        $user = User::factory()->create(['role' => 'adviser']);
        $staleId = $this->seedOtherSession($user, now()->subMinutes(10)->getTimestamp());

        $this->post('/login', $this->loginPayload($user))
            ->assertRedirect(route('adviser.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('sessions', ['id' => $staleId]);
    }

    public function test_live_threshold_is_five_minutes_of_idle(): void
    {
        $user = User::factory()->create(['role' => 'moderator']);

        $this->seedOtherSession($user, now()->subMinutes(4)->getTimestamp());
        $this->post('/login', $this->loginPayload($user))->assertSessionHasErrors('email');
        $this->assertGuest();

        $staleId = $this->seedOtherSession($user, now()->subMinutes(6)->getTimestamp());
        $this->post('/login', $this->loginPayload($user))->assertRedirect(route('moderator.dashboard'));
        $this->assertDatabaseMissing('sessions', ['id' => $staleId]);
    }
}