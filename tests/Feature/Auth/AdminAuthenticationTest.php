<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_login_screen_is_the_only_visible_login_experience(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('One secure sign-in for every COMPASS portal')
            ->assertSee('Sign in to continue.')
            ->assertSee(route('login'))
            ->assertSee(route('password.request'))
            ->assertSee('Remember me')
            ->assertSee('Create Account')
            ->assertSee('data-password-toggle', false)
            ->assertDontSee('Admin Portal')
            ->assertDontSee('Login as')
            ->assertDontSee('name="role"', false);
    }

    public function test_legacy_admin_login_url_redirects_to_shared_login(): void
    {
        $this->get(route('admin.login'))
            ->assertRedirect(route('login'));

        $this->assertFalse(Route::has('admin.login.store'));
    }

    public function test_administrator_logs_in_through_shared_form_and_is_audited(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->post(route('login'), [
            'email' => strtoupper($administrator->email),
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($administrator);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'user_account_id' => $administrator->id,
            'action' => AuditLogger::ADMIN_LOGIN_SUCCEEDED,
            'module' => 'authentication',
            'description' => 'Shared COMPASS login',
        ]);
    }

    public function test_helper_is_redirected_to_helper_dashboard(): void
    {
        $this->assertRoleRedirect('helper', 'helper.dashboard');
    }

    public function test_moderator_is_redirected_to_moderator_dashboard(): void
    {
        $this->assertRoleRedirect('moderator', 'moderator.dashboard');
    }

    public function test_adviser_is_redirected_to_adviser_dashboard(): void
    {
        $this->assertRoleRedirect('adviser', 'adviser.dashboard');
    }

    public function test_professional_is_redirected_to_professional_dashboard(): void
    {
        $this->assertRoleRedirect('professional', 'professional.dashboard');
    }

    public function test_help_seeker_is_redirected_to_seeker_dashboard(): void
    {
        $this->assertRoleRedirect('seeker', 'seeker.dashboard');
    }

    public function test_intended_admin_url_cannot_override_a_helpers_role_destination(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login'), [
                'email' => $helper->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('helper.dashboard'));

        $this->assertAuthenticatedAs($helper);
    }

    public function test_non_administrator_cannot_access_the_admin_dashboard(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_admin_route_uses_shared_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_visiting_login_goes_to_their_portal(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_invalid_admin_password_is_rejected_generically_and_audited(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $administrator->email,
            'password' => 'incorrect-password',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', [
            'user_account_id' => null,
            'action' => AuditLogger::ADMIN_LOGIN_FAILED,
            'module' => 'authentication',
            'description' => 'Account: '.$administrator->email,
        ]);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_shared_login_keeps_laravel_rate_limiting(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => $administrator->email,
                'password' => 'incorrect-password',
            ]);
        }

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $administrator->email,
            'password' => 'incorrect-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $message = session('errors')->get('email')[0];
        $this->assertStringContainsString('Too many login attempts', $message);
        $this->assertGuest();
    }

    public function test_successful_login_regenerates_the_session_identifier(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $this->withSession(['pre_login_marker' => true]);
        $originalSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_logout_returns_every_role_to_shared_login(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_administrator_can_still_access_the_protected_admin_dashboard(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('System Overview')
            ->assertSee($administrator->name)
            ->assertSee(route('logout'));
    }

    private function assertRoleRedirect(string $role, string $routeName): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route($routeName));
    }
}
