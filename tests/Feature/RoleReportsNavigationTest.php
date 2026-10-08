<?php

namespace Tests\Feature;

use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoleReportsNavigationTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 19:00', 'Asia/Manila')->utc());
        config(['app.enforce_duty_hours' => true, 'app.relax_duty_hours' => false]);
    }

    private function helper(): Helper
    {
        $u = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $h = Helper::create(['user_account_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $u->email, 'availability' => 'available', 'status' => 'available', 'competency_level' => 4]);
        $this->verifiedHelperFixture($h);
        HelperSchedule::create(['helper_id' => $h->id, 'date' => now('Asia/Manila')->toDateString(), 'is_active' => true, 'created_by' => $u->id]);
        ReadinessCheck::create(['helper_id' => $h->id, 'assessment_result' => 'ready', 'assessment_date' => now(), 'valid_until' => now()->addHours(2), 'is_active' => true]);

        return $h->fresh();
    }

    private function supportCase(?Helper $h, string $state = 'completed'): Session
    {
        $u = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $s = HelpSeeker::create(['user_account_id' => $u->id, 'generated_alias' => 'PrivateSeeker'.$u->id]);

        return Session::create(['helper_id' => $h?->id, 'seeker_id' => $s->id, 'session_status' => $state, 'session_type' => 'chat', 'risk_level' => 'low', 'created_date' => now()->subMinutes(50), 'submitted_at' => now()->subMinutes(50), 'helper_accepted_at' => now()->subMinutes(45), 'start_time' => $state === 'completed' ? now()->subMinutes(40) : null, 'end_time' => $state === 'completed' ? now()->subMinutes(10) : null]);
    }

    private function moderator(): User
    {
        $u = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        Moderator::create(['user_account_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'Mod', 'email' => $u->email]);

        return $u;
    }

    public function test_helper_report_is_scoped_filtered_and_paginated_without_private_content(): void
    {
        $h = $this->helper();
        $other = $this->helper();
        $owned = $this->supportCase($h);
        $foreign = $this->supportCase($other);
        foreach (range(1, 16) as $i) {
            $this->supportCase($h);
        }
        $r = $this->actingAs($h->user)->get(route('helper.reports', ['from' => '2026-10-08', 'to' => '2026-10-08', 'case_status' => 'completed']));
        $r->assertOk()->assertSee('My Service Reports')->assertDontSee('PrivateSeeker')->assertDontSee('Emergency case activity');
        $r->assertViewHas('roleReport', fn ($d) => $d['summary']['Cases in period'] === 17 && $d['summary']['Helper acceptance time'] === '5 min' && $d['tables'][0]['records']->perPage() === 15 && $d['tables'][0]['records']->total() === 17 && str_contains($d['tables'][0]['records']->url(2), 'case_status=completed'));
        $this->get(route('helper.reports', ['helper_id' => $other->id]))->assertForbidden();
        $this->get(route('helper.reports', ['from' => '2026-10-09', 'to' => '2026-10-08']))->assertSessionHasErrors('to');
        foreach (['adviser.reports', 'moderator.reports', 'admin.reports'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        foreach (['seeker', 'professional', 'admin', 'moderator', 'adviser'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))->get(route('helper.reports'))->assertForbidden();
        }
    }

    public function test_moderator_emergency_timings_and_export_use_same_filtered_records(): void
    {
        $u = $this->moderator();
        $s = $this->supportCase(null);
        $a = EmergencyAlert::create(['session_id' => $s->id, 'seeker_id' => $s->seeker_id, 'status' => 'resolved', 'risk_level' => 'emergency', 'trigger_reason' => 'Confidential narrative', 'triggered_at' => now()->subMinutes(30), 'resolved_at' => now()->subMinutes(10)]);
        $a->forceFill(['acknowledged_at' => now()->subMinutes(25)])->save();
        $s = $this->supportCase(null);
        EmergencyAlert::create(['session_id' => $s->id, 'seeker_id' => $s->seeker_id, 'status' => 'open', 'risk_level' => 'high', 'trigger_reason' => 'Private details', 'triggered_at' => now()->subDays(3)]);
        $filters = ['from' => '2026-10-08', 'to' => '2026-10-08', 'emergency_status' => 'resolved', 'priority' => 'emergency'];
        $r = $this->actingAs($u)->get(route('moderator.reports', $filters));
        $r->assertOk()->assertDontSee('Confidential narrative')->assertDontSee('PrivateSeeker')->assertDontSee('Competency Growth');
        $r->assertViewHas('roleReport', fn ($d) => $d['summary']['Resolved emergencies'] === 1 && $d['summary']['Unresolved emergencies'] === 0 && $d['summary']['Emergency acknowledgment time'] === '5 min' && $d['summary']['Emergency resolution time'] === '20 min');
        $csv = $this->get(route('moderator.reports.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('20 min', $csv);
        $this->assertStringNotContainsString('Private details', $csv);
        $this->assertStringNotContainsString('Confidential narrative', $csv);
    }

    public function test_adviser_reports_exclude_foreign_cases_and_keep_reports_navigation(): void
    {
        $h = $this->helper();
        $other = $this->helper();
        $this->supportCase($h);
        $this->supportCase($other);
        $r = $this->actingAs($h->adviser->user)->get(route('adviser.reports'));
        $r->assertOk()->assertViewHas('roleReport', fn ($d) => $d['summary']['Cases in period'] === 1)->assertDontSee('PrivateSeeker');
        $html = $r->getContent();
        $this->assertStringNotContainsString('href="'.route('adviser.training').'"', $html);
        $this->assertStringNotContainsString('href="'.route('adviser.analytics').'"', $html);
    }

    public function test_queue_dropdown_excludes_ineligible_helpers_and_remove_controls(): void
    {
        $ready = $this->helper();
        $off = $this->helper();
        $off->update(['availability' => 'unavailable', 'first_name' => 'IneligibleMarker']);
        $s = $this->supportCase(null, 'waiting');
        $this->consentFixture($s->seeker->user);
        $q = QueueRequest::create(['seeker_id' => $s->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);
        $s->update(['queue_request_id' => $q->id]);
        $r = $this->actingAs($this->moderator())->get(route('moderator.queue'))->assertOk();
        $html = $r->getContent();
        $this->assertStringContainsString('value="'.$ready->id.'"', $html);
        $this->assertStringNotContainsString('IneligibleMarker', $html);
        $this->assertStringNotContainsString(route('moderator.queue.remove', $q->id, false), $html);
        $this->assertStringNotContainsString('href="'.route('moderator.analytics').'"', $html);
        $this->get(route('moderator.schedules'))->assertOk()->assertDontSee('Shifts on this date');
        $this->get(route('moderator.emergency'))->assertOk()->assertSee('mo-emergency-grid');
    }

    public function test_admin_reports_render_real_current_user_and_period_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->supportCase(null);
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->assertViewHas('roleReport', fn ($d) => $d['summary']['Total users'] === 2 && $d['summary']['Cases in period'] === 1 && $d['summary']['Queue-entry matching rate'] === 'No data');
    }

    public function test_dashboard_keeps_emergency_graphs_without_analytics_navigation(): void
    {
        $mod = $this->moderator();
        $this->actingAs($mod)->get(route('moderator.dashboard'))->assertOk()
            ->assertSee('Emergency trend')->assertSee('Active emergency status')->assertSee('Severity / priority')
            ->assertSee('Recent Activity')->assertDontSee('Recent emergency activity')->assertDontSee('Live Sessions')->assertDontSee('href="'.route('moderator.analytics').'"', false);
        $this->actingAs(User::factory()->create(['role' => 'helper', 'is_active' => false]))->get(route('helper.reports'))->assertForbidden();
    }
}
