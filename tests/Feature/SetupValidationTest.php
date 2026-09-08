<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SetupValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_idempotent_and_all_six_roles_can_log_in_without_the_vault(): void
    {
        $this->seed(TestUsersSeeder::class);
        $this->seed(TestUsersSeeder::class);
        $this->assertDatabaseCount('users', 6);
        config(['database.connections.identity_vault' => ['driver' => 'sqlite', 'database' => '/unavailable/identity.sqlite']]);
        DB::purge('identity_vault');
        foreach ([
            ['admin@compass.local', 'Admin@123', 'admin'],
            ['maria.santos@compass.local', 'Adviser@123', 'adviser'],
            ['rina@compass.local', 'Helper@123', 'helper'],
            ['SilentWillow52', 'Seeker@123', 'seeker'],
            ['moderator@compass.local', 'Moderator@123', 'moderator'],
            ['anna.cruz@compass.local', 'Professional@123', 'professional'],
        ] as [$login, $password, $role]) {
            $this->post('/login', ['email' => $login, 'password' => $password])->assertRedirect(route($role . '.dashboard'));
            $this->assertAuthenticated();
            $this->get(route($role . '.dashboard'))->assertOk();
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_seeker_profile_updates_cannot_store_real_name_or_email(): void
    {
        $this->seed(TestUsersSeeder::class);
        $seeker = User::where('role', 'seeker')->firstOrFail();
        $this->actingAs($seeker)->get('/profile/edit')->assertRedirect(route('settings.account'));
        $this->actingAs($seeker)->patch('/profile', ['name' => 'Private Name', 'email' => 'private@example.com'])->assertForbidden();
        $this->assertSame('SilentWillow52', $seeker->fresh()->name);
        $this->assertSame('silentwillow52@compass.local', $seeker->fresh()->email);
    }

    public function test_disabled_accounts_cannot_sign_in(): void
    {
        $this->seed(TestUsersSeeder::class);
        User::where('email', 'silentwillow52@compass.local')->update(['is_active' => false]);
        $this->post('/login', ['email' => 'SilentWillow52', 'password' => 'Seeker@123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
