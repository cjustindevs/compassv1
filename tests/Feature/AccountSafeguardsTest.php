<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('staffDeletionRoutes')]
    public function test_staff_self_deletion_is_blocked_on_both_routes(string $role, string $method, string $url): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->json($method, $url, ['password' => 'password', 'confirm_delete' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
        $this->assertAuthenticatedAs($user);
    }

    public static function staffDeletionRoutes(): array
    {
        $cases = [];
        foreach (['admin', 'helper', 'adviser', 'moderator', 'professional'] as $role) {
            $cases[$role.' delete'] = [$role, 'DELETE', '/profile'];
            $cases[$role.' post'] = [$role, 'POST', '/profile/delete-account'];
        }

        return $cases;
    }

    public function test_helper_deletion_does_not_orphan_an_active_case(): void
    {
        $helper = $this->helper();
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'PrivateSeeker']);
        $case = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_status' => 'active', 'session_type' => 'chat']);

        foreach ([['DELETE', '/profile'], ['POST', '/profile/delete-account']] as [$method, $url]) {
            $this->actingAs($helper->user)->json($method, $url, ['password' => 'password', 'confirm_delete' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('account');
            $this->assertSame($helper->id, $case->fresh()->helper_id);
            $this->assertSame('active', $case->fresh()->session_status);
            $this->assertNotNull($helper->fresh());
        }
    }

    public function test_seeker_with_retained_records_still_cannot_delete_the_account(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateSeeker']);
        $case = Session::create(['seeker_id' => $seeker->id, 'session_status' => 'completed']);
        foreach ([['DELETE', '/profile'], ['POST', '/profile/delete-account']] as [$method, $url]) {
            $this->actingAs($user)->json($method, $url, ['password' => 'password', 'confirm_delete' => 1])->assertConflict();
            $this->assertNotNull($case->fresh());
            $this->assertNotNull($user->fresh());
        }
    }

    public function test_helper_cannot_change_managed_fields_through_shared_endpoints(): void
    {
        $helper = $this->helper();
        $user = $helper->user;
        foreach (['/profile', '/settings/account'] as $url) {
            $this->actingAs($user)->patchJson($url, ['name' => 'Changed', 'email' => 'changed@example.test', 'phone' => '09179999999'])
                ->assertForbidden();
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertSame($helper->email, $helper->fresh()->email);
            $this->assertSame($user->name, $user->fresh()->name);
            $this->assertNotNull($user->fresh()->email_verified_at);
        }
    }

    public function test_helper_shared_settings_keep_password_controls_but_no_basic_information_form(): void
    {
        $helper = $this->helper();
        $this->actingAs($helper->user)->get('/settings/account')->assertOk()
            ->assertSee('Email address — read-only')->assertSee('Open Helper profile')->assertSee('Change password')
            ->assertDontSee('action="'.route('settings.account.update').'"', false)
            ->assertDontSee('action="'.route('profile.delete-account').'"', false);
        $this->get('/profile/edit')->assertRedirect(route('helper.profile'));
    }

    public function test_staff_pages_explain_closure_without_showing_delete_controls(): void
    {
        $user = User::factory()->create(['role' => 'adviser']);
        foreach (['/profile/edit', '/settings/account'] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('Account closure')
                ->assertDontSee('action="'.route('profile.delete-account').'"', false);
        }
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role' => 'helper']);

        return Helper::create(['user_account_id' => $user->id, 'first_name' => 'Peer', 'last_name' => 'Helper', 'email' => $user->email]);
    }
}
