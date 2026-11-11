<?php

namespace Tests\Feature;

use App\Models\HelpSeeker;
use App\Models\QueueRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorPilotPagesTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator', 'is_active' => true]);
    }

    public function test_dashboard_uses_shared_shell_and_header(): void
    {
        $response = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()
            ->assertSee('data-page-header', false)->assertSee('co-kpis', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-page-slot'));
    }

    public function test_attention_strip_lists_only_waiting_work(): void
    {
        $this->actingAs($this->moderator());
        $this->get(route('moderator.dashboard'))->assertOk()->assertDontSee('Needs your attention')->assertDontSee('ui-attention', false);

        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);

        $this->get(route('moderator.dashboard'))->assertOk()->assertSee('Needs your attention')
            ->assertSee('Waiting in queue')->assertSee(route('moderator.queue'), false)->assertDontSee('Emergencies open')->assertDontSee('Emergency open');
    }

    public function test_attention_queue_count_matches_the_kpi_and_ignores_test_records(): void
    {
        $this->actingAs($this->moderator());
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now(), 'is_test_record' => true]);

        $html = $this->get(route('moderator.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/ui-attention-count">\s*1\s*<\/strong>\s*<span class="ui-attention-label">Waiting in queue/', $html);
        $this->assertMatchesRegularExpression('/Waiting requests<\/h3>\s*<strong\s+id="statQueue"\s*>\s*1\s*</', $html);
    }

    public function test_each_moderator_kpi_appears_once_and_stays_live(): void
    {
        $html = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()->getContent();
        foreach (['Waiting requests', 'Available Helpers', 'Active sessions', 'Active emergencies'] as $label) {
            $this->assertSame(1, preg_match_all('/<h3[^>]*>'.preg_quote($label, '/').'<\/h3>/', $html), $label);
        }
        $this->assertStringNotContainsString('ui-kpi', $html);
        foreach (['statQueue', 'statSessions', 'statHelpers', 'statEmergency'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }

    public function test_manage_team_matches_reference_structure(): void
    {
        $au = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        $adviser = \App\Models\Adviser::create(['user_account_id' => $au->id, 'first_name' => 'Ada', 'last_name' => 'Adviser', 'email' => $au->email]);
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        \App\Models\Helper::create(['user_account_id' => $user->id, 'first_name' => 'Maya', 'last_name' => 'Cordero', 'email' => $user->email, 'adviser_id' => $adviser->id]);
        $user2 = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        \App\Models\Helper::create(['user_account_id' => $user2->id, 'first_name' => 'Free', 'last_name' => 'Helper', 'email' => $user2->email]);

        $this->actingAs($this->moderator())->get(route('moderator.manage'))->assertOk()
            ->assertSee('Helpers without an Adviser')->assertDontSee('Unassigned Pool')
            ->assertSee('ui-kpi--amber', false)->assertSee('ui-list-row', false)->assertSee('ui-table--stack', false)
            ->assertSee(route('moderator.manage.unassign'), false)->assertSee(route('moderator.manage.assign'), false)
            ->assertSee('data-helper-assignment', false)->assertSee('data-stat="unassigned_helpers"', false);
    }

    public function test_reports_header_filters_chips_and_kpis(): void
    {
        $this->actingAs($this->moderator())->get(route('moderator.reports', ['priority' => 'high']))->assertOk()
            ->assertSee('data-page-header', false)->assertSee('Queue Performance')->assertSee('Export Filtered CSV')
            ->assertSee('Priority: high')->assertSee('ui-filter-bar', false)
            ->assertSee('ui-kpi--red', false)->assertSee('ui-kpi--amber', false)->assertSee('ui-kpi--green', false)
            ->assertSee('ui-table--stack', false)->assertSee('id="report-range"', false);
    }

    public function test_helper_workload_keeps_hooks_on_moderator_and_adviser_schedule(): void
    {
        $adviser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        \App\Models\Adviser::create(['user_account_id' => $adviser->id, 'first_name' => 'Ada', 'last_name' => 'Adviser', 'email' => $adviser->email]);

        foreach ([[$this->moderator(), 'moderator.schedules'], [$adviser, 'adviser.schedule']] as [$user, $route]) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSee('id="helperWorkload"', false)->assertSee('data-helper-workload', false)
                ->assertSee('data-workload-filter', false)->assertSee('data-workload-body', false)
                ->assertSee('data-workload-pages', false)->assertSee('data-workload-summary', false)
                ->assertSee('name="workload_search"', false)->assertSee('name="workload_status"', false)
                ->assertSee('Showing')->assertSee('ui-table--stack', false)->assertSee('ui-filter-bar', false);
        }
    }

    public function test_queue_and_sessions_use_shared_table(): void
    {
        $this->actingAs($this->moderator());
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'high', 'request_date' => now()]);
        \App\Models\Session::create(['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'active', 'risk_level' => 'low', 'created_date' => now()]);

        $this->get(route('moderator.queue'))->assertOk()->assertSee('data-page-header', false)->assertSee('ui-table--stack', false)
            ->assertSee('id="statWaiting"', false)->assertSee('data-queue-helper-options', false)->assertSee('P2 · High');
        $this->get(route('moderator.sessions'))->assertOk()->assertSee('data-page-header', false)->assertSee('ui-table--stack', false)
            ->assertSee('id="sessionList"', false)->assertSee('ui-kpi', false);
    }

    public function test_session_detail_has_back_link_and_summary(): void
    {
        $this->actingAs($this->moderator());
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        $session = \App\Models\Session::create(['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'active', 'risk_level' => 'high', 'created_date' => now()]);
        $session->load(['seeker', 'helper', 'concern', 'messages', 'incidents', 'evaluation']);

        $html = view('moderator.session-detail', ['session' => $session, 'errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('ui-back-link', $html);
        $this->assertStringContainsString('Back to Active Sessions', $html);
        $this->assertStringContainsString(route('moderator.sessions'), $html);
        $this->assertStringContainsString('Alias'.$user->id, $html);
        $this->assertStringContainsString('data-page-header', $html);
    }

    public function test_remaining_moderator_pages_use_page_header(): void
    {
        $this->actingAs($this->moderator());
        foreach (['moderator.emergency', 'moderator.schedules', 'moderator.referrals.unassigned', 'moderator.reconnections', 'moderator.notifications', 'moderator.settings', 'queue.performance'] as $name) {
            $html = $this->get(route($name))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-page-header'), "{$name} should render exactly one shared page header");
        }
    }

    public function test_settings_forms_have_save_bar(): void
    {
        $html = $this->actingAs($this->moderator())->get(route('moderator.settings'))->assertOk()->getContent();
        foreach (['moderator.settings.profile', 'moderator.settings.password', 'moderator.settings.notifications'] as $name) {
            $this->assertMatchesRegularExpression('#<form[^>]+action="'.preg_quote(route($name), '#').'"(?:(?!</form>).)*?ui-save-bar#s', $html, $name);
        }
        $this->assertSame(3, substr_count($html, 'ui-save-bar"'));
    }
}
