<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.roles-permissions'))
            ->assertRedirect(route('admin.login'));
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
            ->assertSee('Role-based access control matrix')
            ->assertSee('Duplicate role')
            ->assertSee('Reset')
            ->assertSee('Save')
            ->assertSee('Editing role')
            ->assertSee('Administrator')
            ->assertSee('Referral Access')
            ->assertSee('Clinical Notes')
            ->assertSee('Completed Cases')
            ->assertSee('aria-current="page"', false)
            ->assertSee('role="switch"', false)
            ->assertSee('Toggle Read permission for Dashboard')
            ->assertSee(route('admin.roles-permissions'));

        $content = $response->getContent();

        $this->assertSame(60, substr_count($content, 'role="switch"'));
        $this->assertSame(13, substr_count($content, 'aria-checked="true"'));
        $this->assertSame(47, substr_count($content, 'aria-checked="false"'));
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
