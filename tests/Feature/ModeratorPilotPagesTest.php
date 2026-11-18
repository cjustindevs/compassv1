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

    public function test_moderator_dashboard_has_no_operational_status_block_but_advisers_keep_it(): void
    {
        $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()
            ->assertDontSee('Operational status and supporting metrics');

        $adviserUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        \App\Models\Adviser::create(['user_account_id' => $adviserUser->id, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => $adviserUser->email]);
        $this->actingAs($adviserUser)->get(route('adviser.dashboard'))->assertOk()
            ->assertSee('Operational status and supporting metrics');
    }

    public function test_dashboard_uses_shared_shell_and_header(): void
    {
        $response = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()
            ->assertSee('data-page-header', false)->assertSee('co-kpis', false);
        $this->assertSame(0, substr_count($response->getContent(), 'data-page-slot'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-notification-slot'));
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
        foreach (['Waiting requests', 'Available Helpers', 'Active sessions', 'Average waiting time', 'Priority requests', 'Pending assignments'] as $label) {
            $this->assertSame(1, preg_match_all('/<h3[^>]*>'.preg_quote($label, '/').'<\/h3>/', $html), $label);
        }
        $this->assertStringNotContainsString('ui-kpi', $html);
        foreach (['statQueue', 'statSessions', 'statHelpers'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }

    public function test_recently_matched_schedule_and_reassign_are_icon_buttons_opening_dialogs(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'assigned', 'priority_level' => 'low', 'request_date' => now(), 'matched_date' => now(), 'preferred_session_type' => 'chat']);

        $html = $this->actingAs($this->moderator())->get(route('moderator.queue'))->assertOk()->getContent();
        $row = substr($html, strpos($html, 'data-queue-row="'.$queue->id.'"'));
        $row = substr($row, 0, strpos($row, '</tr>'));
        $this->assertMatchesRegularExpression('/<button type="button" class="ui-icon-btn"(?=[^>]*aria-label="Schedule appointment")[^>]*data-dialog-open="schedule-'.$queue->id.'"/', $row);
        $this->assertMatchesRegularExpression('/<button type="button" class="ui-icon-btn"(?=[^>]*aria-label="Reassign Helper")[^>]*data-dialog-open="reassign-'.$queue->id.'"/', $row);
        $this->assertStringNotContainsString('type="datetime-local"', substr($row, 0, strpos($row, '<dialog')), 'The time field lives in the pop-up.');

        $this->assertMatchesRegularExpression('/<dialog class="workflow-dialog" id="schedule-'.$queue->id.'"[\s\S]*?action="'.preg_quote(route('moderator.queue.schedule'), '/').'"[\s\S]*?type="datetime-local"[\s\S]*?<\/dialog>/', $html);
        $this->assertSame(1, substr_count($html, 'id="reassign-'.$queue->id.'"'), 'Dialog and select no longer share an id.');
        $this->assertStringContainsString('<label class="sr-only" for="reassign-helper-'.$queue->id.'"', $html);
    }

    public function test_moderator_kpis_follow_qa_order_and_count_priority_and_pending_assignments(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'high', 'request_date' => now()]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'emergency', 'request_date' => now(), 'is_test_record' => true]);
        \App\Models\Session::create(['seeker_id' => $seeker->id, 'session_status' => 'helper_assigned', 'match_status' => 'awaiting_acceptance', 'created_date' => now(), 'risk_level' => 'low']);
        \App\Models\Session::create(['seeker_id' => $seeker->id, 'session_status' => 'helper_assigned', 'helper_accepted_at' => now(), 'created_date' => now(), 'risk_level' => 'low']);

        $html = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()->getContent();
        preg_match('/<div class="co-kpis">[\s\S]*?<\/div>/', $html, $kpis);
        preg_match_all('/<h3[^>]*>([^<]+)<\/h3>\s*<strong[^>]*>\s*([^<]*?)\s*<\/strong>/', $kpis[0] ?? '', $cards, PREG_SET_ORDER);
        $this->assertSame(['Waiting requests', 'Available Helpers', 'Active sessions', 'Average waiting time', 'Priority requests', 'Pending assignments'], array_column($cards, 1));
        $values = array_combine(array_column($cards, 1), array_column($cards, 2));
        $this->assertSame('2', $values['Waiting requests']);
        $this->assertSame('1', $values['Priority requests']);
        $this->assertSame('1', $values['Pending assignments']);
        $this->assertStringContainsString('co-kpi-icon', $kpis[0]);
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
            ->assertSee('ui-kpi--green', false)->assertDontSee('ui-kpi--amber', false)->assertSee('ui-list-row', false)->assertSee('ui-table--stack', false)
            ->assertSee(route('moderator.manage.unassign'), false)->assertSee(route('moderator.manage.assign'), false)
            ->assertSee('data-helper-assignment', false)->assertSee('data-stat="unassigned_helpers"', false);
    }

    public function test_reports_header_filters_chips_and_kpis(): void
    {
        $this->actingAs($this->moderator())->get(route('moderator.reports', ['priority' => 'high']))->assertOk()
            ->assertSee('data-page-header', false)->assertSee('Queue Performance')->assertSee('Export Filtered CSV')
            ->assertSee('Priority: high')->assertSee('ui-filter-bar', false)
            ->assertSee('ui-kpi--green', false)->assertDontSee('ui-kpi--red', false)->assertDontSee('ui-kpi--amber', false)
            ->assertSee('ui-table--stack', false)->assertSee('id="report-range"', false);
    }

    public function test_moderator_kpi_cards_are_all_green(): void
    {
        $this->actingAs($this->moderator());
        foreach (['moderator.manage', 'moderator.reports', 'moderator.sessions', 'moderator.emergency'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('ui-kpi--green', $html, $route);
            foreach (['ui-kpi--amber', 'ui-kpi--red', 'ui-kpi--blue', 'ui-kpi--neutral'] as $other) {
                $this->assertStringNotContainsString($other, $html, $route.' still has '.$other);
            }
        }
        $dashboard = $this->get(route('moderator.dashboard'))->assertOk()->getContent();
        foreach (['#fffbeb', '#eff6ff', '#fef2f2'] as $color) {
            $this->assertStringNotContainsString($color, $dashboard, 'dashboard KPI still uses '.$color);
        }
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

    public function test_emergency_page_and_poll_fragment_have_balanced_markup(): void
    {
        $this->actingAs($this->moderator());
        $html = $this->get(route('moderator.emergency'))->assertOk()->getContent();
        $fragment = $this->getJson(route('moderator.emergency', ['tab' => 'active']), ['Accept' => 'application/json']);
        foreach ([$html, (string) ($fragment->json('html') ?? '')] as $markup) {
            $this->assertSame(substr_count($markup, '<div'), substr_count($markup, '</div>'));
            $this->assertSame(substr_count($markup, '<section'), substr_count($markup, '</section>'));
        }
    }

    public function test_settings_save_bar_is_visible_after_validation_error_and_not_js_gated_by_default(): void
    {
        $this->actingAs($this->moderator());
        $this->assertStringNotContainsString('ui-save-bar is-visible', $this->get(route('moderator.settings'))->getContent());
        $html = $this->from(route('moderator.settings'))->followingRedirects()->put(route('moderator.settings.profile'), ['name' => '', 'email' => 'bad'])->getContent();
        $this->assertStringContainsString('ui-save-bar is-visible', $html);
        $this->assertStringNotContainsString('.ui-save-bar { display: none', file_get_contents(public_path('css/compass-components.css')));
    }
}
