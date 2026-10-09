<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.roles-permissions'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_roles_and_permissions(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.roles-permissions'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_accessible_permission_matrix(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('admin.roles-permissions'));

        $response
            ->assertOk()
            ->assertSee('Roles &amp; Permissions', false)
            ->assertSee('Existing role responsibilities and access rules.')
            ->assertSee('Read-only summary')
            ->assertSee('Deactivate accounts')
            ->assertSee('Allowed in scope')
            ->assertSee('Not allowed')
            ->assertDontSee('role="switch"', false)
            ->assertDontSee('Duplicate role');
        foreach (User::ROLE_LABELS as $label) $response->assertSee($label);
    }

    public function test_permission_page_does_not_expose_an_unimplemented_save_endpoint(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)
            ->get(route('admin.roles-permissions'));

        $response
            ->assertOk()
            ->assertDontSee('action="/api/admin', false)
            ->assertDontSee('data-rbac-endpoint', false);
    }
}
