<?php

namespace Tests\Feature\Admin;

use App\Models\{AuditLog, HelpSeeker, Helper, Session, User};
use App\Services\DashboardOverview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_account_data_without_running_case_overview_queries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['is_active' => false, 'email_verified_at' => null]);
        User::factory()->create(['is_active' => true]);
        $this->mock(DashboardOverview::class)->shouldNotReceive('forUser');
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $response->assertViewHas('primaryStats', fn ($stats) => count($stats) === 3 && $stats[0]['value'] === '3' && $stats[1]['value'] === '2' && $stats[2]['value'] === '1');
        foreach (['Active Cases', 'Case Categories', 'Metric Definitions', 'User Growth', 'Infrastructure monitoring', 'Access and security', 'Active Sessions'] as $text) $response->assertDontSee($text);
        $response->assertSee('View all audit logs')->assertSee('Recent administrative activity')->assertDontSee(route('concerns.manage'));
        $response->assertDontSee('/admin/resource-library');
    }

    public function test_account_status_is_separate_from_helper_availability_and_email_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = User::factory()->create(['role' => 'helper', 'is_active' => true, 'email_verified_at' => null]);
        Helper::create(['user_account_id' => $active->id, 'email' => $active->email, 'first_name' => 'Active', 'last_name' => 'Helper', 'status' => 'offline']);
        $inactive = User::factory()->create(['is_active' => false]);
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertViewHas('users', function ($users) use ($active, $inactive) {
                $rows = $users->getCollection()->keyBy('id');
                return $rows[$active->id]['status'] === 'active' && $rows[$inactive->id]['status'] === 'inactive';
            })
            ->assertSee('Account status')->assertSee('Confirm deactivation')->assertDontSee(route('admin.users.deactivate', $admin));
    }

    public function test_directory_paginates_and_filters_on_the_backend(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(30)->create(['role' => 'helper', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'helper', 'name' => 'Archived Helper Account', 'is_active' => false]);
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 32 && $users->count() === 25);
        $this->get(route('admin.users', ['q' => 'Archived Helper', 'role' => 'helper', 'status' => 'inactive']))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()['id'] === (string) $target->id);
        $this->get(route('admin.users', ['role' => 'invented']))->assertSessionHasErrors('role');
    }

    public function test_deactivation_requires_confirmation_and_is_idempotent_with_one_audit_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'helper']);
        $old = AuditLog::create(['action' => 'USER_CREATED', 'module' => 'users', 'description' => 'Existing historical record']);
        $this->actingAs($admin)->post(route('admin.users.deactivate', $target))->assertSessionHasErrors('confirm_deactivation');
        $this->assertTrue($target->fresh()->is_active);
        $payload = ['confirm_deactivation' => 1];
        $this->from(route('admin.users'))->post(route('admin.users.deactivate', $target), $payload)->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();
        $this->post(route('admin.users.deactivate', $target), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('audit_logs', ['id' => $old->id, 'description' => 'Existing historical record']);
        $logs = AuditLog::where('action', 'account_deactivated')->get();
        $this->assertCount(1, $logs);
        $this->assertSame($admin->id, $logs->first()->user_account_id);
        $this->assertSame('users', $logs->first()->target_type);
        $this->assertSame($target->id, (int) $logs->first()->target_id);
        $this->assertNotNull($logs->first()->created_at);
        $this->get(route('admin.audit-logs', ['category' => 'users']))->assertOk()->assertSee('Account deactivated')->assertSee('User #'.$target->id);
    }

    public function test_final_administrator_and_own_account_are_protected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $second = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.users.deactivate', $second), ['confirm_deactivation' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.users.deactivate', $admin), ['confirm_deactivation' => 1])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_protected_helper_obligations_and_session_history_remain_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $account->id, 'email' => $account->email, 'first_name' => 'Duty', 'last_name' => 'Helper']);
        $seeker = HelpSeeker::create(['user_account_id' => User::factory()->create(['role' => 'seeker'])->id, 'generated_alias' => 'PrivateAlias']);
        $session = Session::create(['helper_id' => $helper->id, 'seeker_id' => $seeker->id, 'session_status' => 'active', 'start_time' => now()]);
        $this->actingAs($admin)->post(route('admin.users.deactivate', $account), ['confirm_deactivation' => 1])->assertSessionHasErrors('account');
        $this->assertTrue($account->fresh()->is_active);
        $session->update(['session_status' => 'completed', 'documentation_status' => 'submitted', 'end_time' => now()]);
        $this->post(route('admin.users.deactivate', $account), ['confirm_deactivation' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'helper_id' => $helper->id]);
    }

    public function test_other_roles_cannot_deactivate_accounts(): void
    {
        $target = User::factory()->create(['role' => 'admin']);
        foreach (['helper', 'adviser', 'moderator', 'professional', 'seeker'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('admin.users.deactivate', $target), ['confirm_deactivation' => 1])->assertForbidden();
        }
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(0, AuditLog::where('action', 'account_deactivated')->count());
    }

    public function test_inactive_users_cannot_log_in_or_continue_authenticated_access(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false, 'password' => 'StrongPass!2026']);
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPass!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_permissions_describe_role_guards_and_controller_restrictions_without_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            $response = $this->actingAs($admin)->get(route('admin.roles-permissions', ['role' => $role]))->assertOk()->assertDontSee('role="switch"', false);
            $groups = $response->viewData('groups');
            $accounts = collect($groups['Account administration'])->keyBy('label');
            $this->assertSame($role === 'admin', $accounts['Deactivate accounts']['allowed']);
            $emergencies = collect($groups['Emergency and referral operations'])->keyBy('label');
            $this->assertSame($role === 'adviser', $emergencies['Resolve emergency cases']['allowed']);
            $reports = collect($groups['Reports and records'])->keyBy('label');
            $this->assertSame($role !== 'seeker', $reports['View role-based reports']['allowed']);
        }
        $this->get(route('admin.roles-permissions', ['role' => 'fake-role']))->assertSessionHasErrors('role');
        $this->post(route('admin.roles-permissions'))->assertMethodNotAllowed();
    }

    public function test_today_audit_filter_and_timestamps_use_philippine_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 02:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::forceCreate(['action' => 'LOCAL_DAY_EVENT', 'module' => 'users', 'created_at' => '2026-10-08 16:05:00']);
        AuditLog::forceCreate(['action' => 'PREVIOUS_DAY_EVENT', 'module' => 'users', 'created_at' => '2026-10-08 15:59:00']);
        $this->actingAs($admin)->get(route('admin.audit-logs', ['date' => 'today']))->assertOk()
            ->assertSee('Local day event')->assertDontSee('Previous day event')->assertSee('Oct 09, 2026 12:05 AM')->assertSee('Philippine Time');
    }

    public function test_report_filters_change_real_counts_and_preserve_backend_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seeker = HelpSeeker::create(['user_account_id' => User::factory()->create(['role' => 'seeker'])->id, 'generated_alias' => 'ReportAlias']);
        foreach (range(1, 17) as $i) Session::create(['seeker_id' => $seeker->id, 'session_status' => $i === 17 ? 'cancelled' : 'completed', 'start_time' => now()->subHour(), 'end_time' => now()]);
        $from = now('Asia/Manila')->format('Y-m-d');
        $response = $this->actingAs($admin)->get(route('admin.reports', ['from' => $from, 'to' => $from, 'case_status' => 'completed']))->assertOk();
        $report = $response->viewData('roleReport');
        $this->assertSame(16, $report['summary']['Cases in period']);
        $this->assertSame(16, $report['tables'][0]['records']->total());
        $this->assertSame(15, $report['tables'][0]['records']->count());
        $this->get(route('admin.reports', ['from' => $from, 'to' => $from, 'case_status' => 'completed', 'cases_page' => 2]))->assertOk()
            ->assertViewHas('roleReport', fn ($r) => $r['tables'][0]['records']->count() === 1);
        $this->get(route('admin.reports', ['from' => '2026-10-09', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('admin.reports', ['case_status' => 'invented']))->assertSessionHasErrors('case_status');
    }
}
