<?php

namespace Tests\Feature\Admin;

use App\Models\Helper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.users'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_non_administrator_cannot_access_user_management(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_real_user_directory(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
        ]);
        $helperUser = User::factory()->create([
            'name' => 'Maya Cordero',
            'email' => 'maya.c@university.edu',
            'role' => 'helper',
        ]);
        Helper::create([
            'user_account_id' => $helperUser->id,
            'first_name' => 'Maya',
            'last_name' => 'Cordero',
            'email' => $helperUser->email,
            'status' => 'busy',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('User management')
            ->assertSee('Directory')
            ->assertSee('Maya Cordero')
            ->assertSee('maya.c@university.edu')
            ->assertSee('Busy')
            ->assertSee('Create user')
            ->assertSee('Import CSV')
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.users'));
    }

    public function test_administrator_can_create_a_universal_user_account(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)->post(route('admin.users.store'), [
            'first_name' => 'Elena',
            'last_name' => 'Cruz',
            'email' => 'ELENA.CRUZ@UNIVERSITY.EDU',
            'role' => 'adviser',
            'account_status' => 'active',
        ]);

        $response->assertRedirect(route('admin.users'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Elena Cruz',
            'email' => 'elena.cruz@university.edu',
            'role' => 'adviser',
        ]);

        $this->assertNotNull(User::where('email', 'elena.cruz@university.edu')->firstOrFail()->email_verified_at);
    }

    public function test_pending_account_is_created_without_email_verification(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)->post(route('admin.users.store'), [
            'first_name' => 'Karla',
            'last_name' => 'Uy',
            'email' => 'karla.uy@university.edu',
            'role' => 'moderator',
            'account_status' => 'pending',
        ])->assertRedirect(route('admin.users'));

        $this->assertNull(User::where('email', 'karla.uy@university.edu')->firstOrFail()->email_verified_at);
    }

    public function test_create_user_rejects_duplicate_email_and_invalid_role(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create();

        $response = $this->actingAs($administrator)
            ->from(route('admin.users'))
            ->post(route('admin.users.store'), [
                'first_name' => 'Duplicate',
                'last_name' => 'Account',
                'email' => $existing->email,
                'role' => 'super-admin',
                'account_status' => 'active',
            ]);

        $response->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors(['email', 'role']);
    }
}
