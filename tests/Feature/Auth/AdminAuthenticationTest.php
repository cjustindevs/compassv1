<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk()
            ->assertSee('Admin Portal')
            ->assertSee('Username or email');
    }

    public function test_administrator_can_log_in_with_an_email_address(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'username' => $administrator->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($administrator);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'user_account_id' => $administrator->id,
            'action' => AuditLogger::ADMIN_LOGIN_SUCCEEDED,
            'module' => 'authentication',
            'description' => 'Administrator portal',
        ]);
    }

    public function test_administrator_can_log_in_with_an_account_name(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Compass Administrator',
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'username' => 'compass administrator',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($administrator);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_non_administrator_cannot_use_the_admin_login(): void
    {
        $helper = User::factory()->create([
            'role' => 'helper',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'username' => $helper->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('username');
        $this->assertDatabaseHas('audit_logs', [
            'user_account_id' => null,
            'action' => AuditLogger::ADMIN_LOGIN_FAILED,
            'module' => 'authentication',
            'description' => 'Account: '.$helper->email,
        ]);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_administrator_cannot_log_in_with_an_invalid_password(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'username' => $administrator->email,
            'password' => 'incorrect-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('username');
        $this->assertDatabaseHas('audit_logs', [
            'user_account_id' => null,
            'action' => AuditLogger::ADMIN_LOGIN_FAILED,
            'module' => 'authentication',
            'description' => 'Account: '.$administrator->email,
        ]);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_non_administrator_cannot_access_the_admin_dashboard(): void
    {
        $helper = User::factory()->create([
            'role' => 'helper',
        ]);

        $this->actingAs($helper)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_the_dedicated_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_administrator_can_access_the_admin_dashboard(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('System Overview')
            ->assertSee('Platform-wide activity and health')
            ->assertSee('Total Users')
            ->assertSee('User Growth')
            ->assertSee('Live event stream')
            ->assertSee('Recent logs')
            ->assertSee($administrator->name)
            ->assertSee(route('logout'));
    }
}
