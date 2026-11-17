<?php

namespace Tests\Feature;

use App\Models\{EmergencyAlert, Helper, HelperCompetencyHistory, HelperSchedule, HelpSeeker, HelpSeekerEvaluation, Message, QueueRequest, Referral, Session, User};
use App\Services\{AdviserAnalytics, AnalyticsPeriod, CompetencyRubric, DashboardOverview, SessionPerformance, TechnicalDashboardSummary};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoleKpiTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-15 20:00', 'Asia/Manila')->utc());
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role'=>'helper']);
        $helper = Helper::create(['user_account_id'=>$user->id, 'first_name'=>'Measured', 'last_name'=>'Helper', 'email'=>$user->email]);
        $this->verifiedHelperFixture($helper);
        return $helper->fresh();
    }

    private function supportCase(Helper $helper, string $status = 'completed'): Session
    {
        $user = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$user->id, 'generated_alias'=>'Private'.$user->id]);
        $queue = QueueRequest::create(['seeker_id'=>$seeker->id, 'request_status'=>'assigned', 'priority_level'=>'low', 'queued_at'=>now()->subMinutes(48)]);
        return Session::create(['seeker_id'=>$seeker->id, 'helper_id'=>$helper->id, 'queue_request_id'=>$queue->id, 'session_status'=>$status,
            'submitted_at'=>now()->subMinutes(50), 'created_date'=>now()->subMinutes(50), 'start_time'=>now()->subMinutes(40), 'end_time'=>now()->subMinutes(20), 'risk_level'=>'low']);
    }

    public function test_known_events_count_once_and_keep_helper_response_separate_from_adviser_actions(): void
    {
        $helper = $this->helper();
        $session = $this->supportCase($helper);
        $this->supportCase($helper, 'cancelled');
        Message::create(['session_id'=>$session->id, 'sender_id'=>$session->seeker->user_account_id, 'message_text'=>'Seeker first', 'sent_datetime'=>now()->subMinutes(49)]);
        foreach ([45, 25] as $minutes) Message::create(['session_id'=>$session->id, 'sender_id'=>$helper->user_account_id, 'message_text'=>'Actual reply', 'sent_datetime'=>now()->subMinutes($minutes)]);
        foreach (['pending_adviser','pending_consent'] as $status) Referral::create(['session_id'=>$session->id, 'helper_id'=>$helper->id, 'adviser_id'=>$helper->adviser_id,
            'referral_reason'=>'Synthetic recommendation', 'referral_date'=>now(), 'status'=>$status, 'reviewed_at'=>$status === 'pending_consent' ? now() : null, 'approved_at'=>$status === 'pending_consent' ? now() : null]);
        HelpSeekerEvaluation::create(['session_id'=>$session->id, 'overall_score'=>10, 'submitted_at'=>now()]);
        EmergencyAlert::create(['session_id'=>$session->id, 'seeker_id'=>$session->seeker_id, 'trigger_reason'=>'Synthetic', 'triggered_at'=>now()->subMinutes(10)]);
        $period = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'today']));
        $service = app(SessionPerformance::class);
        $result = $service->summarize($service->cohort(Session::query(), $period)->get(), collect([$helper->id]), $period);
        $m = $result['metrics'];
        $this->assertSame(1, $m['completed']);
        $this->assertSame(15.0, $m['response_minutes']);
        $this->assertSame(8.0, $m['waiting_minutes']);
        $this->assertSame(20.0, $m['duration_minutes']);
        $this->assertSame(50.0, $m['completion_rate']);
        $this->assertSame(100.0, $m['referral_rate']);
        $this->assertSame(100.0, $m['referral_approval_rate']);
        $this->assertSame(5.0, $m['satisfaction']);
        $this->assertSame(50.0, $m['emergency_rate']);
        $this->assertSame(1, $result['samples']['response_minutes']);
        $this->assertSame(1, $result['samples']['referral_approval_rate']);
        $this->assertSame(2, $m['documentation_pending']);
    }

    public function test_helper_and_adviser_dashboard_metrics_match_their_reports_without_foreign_cases(): void
    {
        $helper = $this->helper();
        HelperSchedule::create(['helper_id'=>$helper->id, 'date'=>'2026-10-15', 'shift_start'=>'18:00', 'shift_end'=>'23:00', 'is_active'=>true, 'created_by'=>$helper->user_account_id]);
        $owned = $this->supportCase($helper);
        $foreign = $this->supportCase($this->helper());
        Message::create(['session_id'=>$owned->id, 'sender_id'=>$helper->user_account_id, 'message_text'=>'Actual reply', 'sent_datetime'=>now()->subMinutes(35)]);
        $helperPage = $this->actingAs($helper->user)->get(route('helper.dashboard', ['period'=>'today']))->assertOk();
        $this->assertSame(1, $helperPage->viewData('performance')['metrics']['completed']);
        $this->assertSame(5.0, $helperPage->viewData('performance')['metrics']['duty_hours']);
        $this->get(route('helper.reports', ['from'=>'2026-10-15', 'to'=>'2026-10-15']))->assertOk()
            ->assertViewHas('roleReport', fn ($r) => $r['summary']['Completed cases'] === 1 && $r['summary']['Average Helper Response Time'] === '5 min');
        $adviserPage = $this->actingAs($helper->adviser->user)->get(route('adviser.dashboard', ['period'=>'today']))->assertOk();
        $cards = collect($adviserPage->viewData('overview')['cards'])->pluck('value','label');
        $this->assertSame('1', $cards['Completed sessions']);
        $this->assertSame('5 min', $cards['Average Helper Response Time']);
        $this->assertSame('No data', $cards['Average referral review time']);
        $this->get(route('adviser.reports', ['period'=>'today']))->assertOk()->assertViewHas('metrics', fn ($m) => $m['completed'] === 1 && $m['response_minutes'] === 5.0 && $m['duty_hours'] === 5.0);
    }

    public function test_missing_measurements_are_not_zero_and_competency_growth_requires_comparable_history(): void
    {
        $helper = $this->helper();
        $period = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'today']));
        $service = app(SessionPerformance::class);
        $result = $service->summarize(Session::query()->get(), collect([$helper->id]), $period);
        foreach (['response_minutes','satisfaction','competency','completion_rate','documentation_rate','workload'] as $key) $this->assertNull($result['metrics'][$key]);
        $this->assertNull($result['growth']);
        $this->actingAs($helper->adviser->user);
        foreach ([3, 4] as $i => $score) {
            $case = $this->supportCase($helper);
            $report = \App\Models\SessionReport::create(['session_id'=>$case->id, 'session_summary'=>'Recorded summary', 'personal_reflection'=>'Recorded reflection']);
            $record = app(\App\Services\AdviserEvaluationService::class)->save($report,
                array_fill_keys(array_keys(CompetencyRubric::CRITERIA), $score) + ['recommended_action'=>'Follow up']);
            $record->update(['evaluation_date'=>now()->subMinutes(10-$i)]);
        }
        $result = $service->summarize(Session::query()->get(), collect([$helper->id]), $period);
        $this->assertSame(3.5, $result['metrics']['competency']);
        $this->assertSame(1.0, $result['growth']);
    }

    public function test_manila_boundaries_exclude_next_day_and_future_records(): void
    {
        $helper = $this->helper();
        foreach (['2026-10-14 23:59:59','2026-10-15 00:00:00','2026-10-15 23:59:59','2026-10-16 00:00:00'] as $at) {
            $this->supportCase($helper)->update(['end_time'=>Carbon::parse($at,'Asia/Manila')->utc()]);
        }
        $period = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'today']));
        $this->assertSame(1, app(SessionPerformance::class)->cohort(Session::query(), $period)->count());
    }

    public function test_semester_and_all_time_use_configured_or_recorded_dates(): void
    {
        User::factory()->create(['created_at'=>Carbon::parse('2024-01-01 00:00', 'Asia/Manila')->utc()]);
        $all = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'all']));
        $this->assertSame('2024-01-01', $all->dates()['from']);
        config(['analytics.semester_start'=>'2026-08-01','analytics.semester_end'=>'2026-12-20']);
        $semester = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'semester']));
        $this->assertSame(['from'=>'2026-08-01','to'=>'2026-10-15'], $semester->dates());
    }

    public function test_admin_does_not_report_preview_uptime_or_fake_backup_success(): void
    {
        $period = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'today']));
        $technical = app(TechnicalDashboardSummary::class)->data($period);
        $this->assertSame('Not monitored', $technical['health']);
        $this->assertSame('Not configured', $technical['backup']);
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard', ['period'=>'today']))->assertOk()->assertSee('Not monitored')->assertSee('Not configured')->assertDontSee('99.98');
    }

    public function test_seeker_dashboard_shows_only_own_status_and_no_staff_performance(): void
    {
        $session = $this->supportCase($this->helper());
        $user = $session->seeker->user;
        $this->consentFixture($user);
        $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk()->assertSee('Feedback due')->assertViewHas('seekerIndicators', fn ($cards) => collect($cards)->firstWhere('label','Feedback due')['raw'] === 1.0)
            ->assertDontSee('Average Helper Response Time')->assertDontSee('Average competency score');
    }

    public function test_seeker_kpis_are_active_request_queue_position_and_completed_sessions_with_tasks_apart(): void
    {
        $helper = $this->helper();
        $done = $this->supportCase($helper);
        $user = $done->seeker->user;
        $this->consentFixture($user);
        $ahead = QueueRequest::create(['seeker_id'=>$done->seeker_id, 'request_status'=>'waiting', 'priority_level'=>'high', 'request_date'=>now()->subMinutes(30)]);
        $mine = QueueRequest::create(['seeker_id'=>$done->seeker_id, 'request_status'=>'waiting', 'priority_level'=>'low', 'request_date'=>now()->subMinutes(5)]);
        $pending = Session::create(['seeker_id'=>$done->seeker_id, 'queue_request_id'=>$mine->id, 'session_status'=>Session::STATUS_WAITING,
            'submitted_at'=>now()->subMinutes(5), 'created_date'=>now()->subMinutes(5), 'risk_level'=>'low']);

        $cards = collect(app(\App\Services\DashboardIndicators::class)->seeker($user, AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'monthly']))));
        $kpis = $cards->reject(fn ($c) => !empty($c['task']))->values();
        $this->assertSame(['Active request', 'Queue position', 'Completed sessions'], $kpis->pluck('label')->all());
        $this->assertSame($pending->status_label, $kpis[0]['value']);
        $this->assertSame('#2', $kpis[1]['value']);
        $this->assertSame(1.0, $kpis[2]['raw']);
        $this->assertSame(['Feedback due', 'Referral decisions needed', 'Upcoming appointments'], $cards->filter(fn ($c) => !empty($c['task']))->pluck('label')->values()->all());

        $html = $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('class="seeker-tasks"', $html);
        $this->assertStringContainsString('1 Feedback due', strip_tags(preg_replace('/\s+/', ' ', $html)));
        $this->assertStringNotContainsString('Referral decisions needed', $html, 'Tasks with nothing to do stay hidden.');
        $ahead->delete();
    }

    public function test_seeker_without_a_request_sees_none_and_a_dash_for_queue_position(): void
    {
        $done = $this->supportCase($this->helper());
        $cards = collect(app(\App\Services\DashboardIndicators::class)->seeker($done->seeker->user, AnalyticsPeriod::fromRequest(Request::create('/', 'GET'))))->keyBy('label');
        $this->assertSame('None', $cards['Active request']['value']);
        $this->assertSame('—', $cards['Queue position']['value']);
    }

    public function test_helper_kpis_are_completed_competency_and_assigned_sessions(): void
    {
        $helper = $this->helper();
        $this->supportCase($helper);
        $this->supportCase($helper, 'active');
        $this->supportCase($helper, 'helper_assigned');
        $html = $this->actingAs($helper->user)->get(route('helper.dashboard', ['period'=>'today']))->assertOk()->getContent();
        preg_match('/<div class="performance-kpis"[\s\S]*?<\/div>/', $html, $kpis);
        $text = trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $kpis[0] ?? '')));
        $this->assertMatchesRegularExpression('/^Completed sessions 1 .*Competency score \(1–5\) .*Assigned sessions 2\b/u', $text);
        $this->assertStringNotContainsString('Helpfulness rating', $kpis[0] ?? '');
        $this->assertStringNotContainsString('Documentation completion', $kpis[0] ?? '');
    }

    public function test_adviser_kpis_follow_qa_order_with_total_sessions_active_helpers_and_satisfaction(): void
    {
        $helper = $this->helper();
        $case = $this->supportCase($helper);
        HelpSeekerEvaluation::create(['session_id'=>$case->id, 'overall_score'=>10, 'submitted_at'=>now()->subMinutes(10)]);
        $html = $this->actingAs($helper->adviser->user)->get(route('adviser.dashboard', ['period'=>'today']))->assertOk()->getContent();
        preg_match('/<div class="co-kpis">[\s\S]*?<\/div>/', $html, $kpis);
        preg_match_all('/<h3[^>]*>([^<]+)<\/h3>\s*<strong[^>]*>\s*([^<]*?)\s*<\/strong>/', $kpis[0] ?? '', $cards, PREG_SET_ORDER);
        $this->assertSame(['Average Helper Response Time', 'Average waiting time', 'Total sessions', 'Completed sessions', 'Active Helpers', 'Average satisfaction rating (/5)'], array_column($cards, 1));
        $values = array_combine(array_column($cards, 1), array_column($cards, 2));
        $this->assertSame('1', $values['Total sessions']);
        $this->assertSame('1', $values['Completed sessions']);
        $this->assertSame('1', $values['Active Helpers']);
        $this->assertSame('5', $values['Average satisfaction rating (/5)']);
    }

    public function test_admin_kpis_are_total_active_inactive_accounts_and_system_status(): void
    {
        $admin = User::factory()->create(['role'=>'admin', 'is_active'=>true]);
        User::factory()->create(['is_active'=>false]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('primaryStats', fn ($stats) => collect($stats)->pluck('label')->all() === ['Total users', 'Active accounts', 'Inactive accounts', 'System status']
                && $stats[0]['raw'] === 2.0 && $stats[1]['raw'] === 1.0 && $stats[2]['raw'] === 1.0 && $stats[3]['value'] === 'Not monitored')
            ->assertSee('performance-kpi-icon', false);
    }

    public function test_professional_completion_submission_is_not_counted_as_completed(): void
    {
        $helper = $this->helper();
        $session = $this->supportCase($helper);
        $user = User::factory()->create(['role'=>'professional']);
        $professional = \App\Models\PsychologyProfessional::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Professional','email'=>$user->email]);
        $referral = Referral::create(['session_id'=>$session->id,'helper_id'=>$helper->id,'adviser_id'=>$helper->adviser_id,'professional_id'=>$professional->id,
            'referral_reason'=>'Synthetic support','referral_date'=>now(),'approved_at'=>now(),'help_seeker_consent'=>true,'status'=>'in_progress']);
        $referral->completionReviews()->create(['professional_id'=>$professional->id,'submitted_by'=>$user->id,'summary'=>'Synthetic completion report','review_status'=>'pending']);
        $this->actingAs($user)->get(route('professional.dashboard',['period'=>'today']))->assertOk()->assertSee('Awaiting Adviser Review')
            ->assertViewHas('analytics', fn ($a) => $a['awaiting_review_now'] === 1 && $a['completed']['total'] === 0 && $a['active_now'] === 1);
        $this->getJson(route('professional.dashboard.stats'))->assertOk()->assertJsonPath('awaiting_review', 1);
    }
}
