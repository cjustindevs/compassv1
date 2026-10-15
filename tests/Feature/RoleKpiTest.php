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
        $this->assertSame(5.0, $m['response_minutes']);
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
        Message::create(['session_id'=>$owned->id, 'sender_id'=>$helper->user_account_id, 'message_text'=>'Actual reply', 'sent_datetime'=>now()->subMinutes(45)]);
        $helperPage = $this->actingAs($helper->user)->get(route('helper.dashboard', ['period'=>'today']))->assertOk();
        $this->assertSame(1, $helperPage->viewData('performance')['metrics']['completed']);
        $this->assertSame(5.0, $helperPage->viewData('performance')['metrics']['duty_hours']);
        $this->get(route('helper.reports', ['from'=>'2026-10-15', 'to'=>'2026-10-15']))->assertOk()
            ->assertViewHas('roleReport', fn ($r) => $r['summary']['Completed cases'] === 1 && $r['summary']['Average Helper Response Time'] === '5 min');
        $adviserPage = $this->actingAs($helper->adviser->user)->get(route('adviser.dashboard', ['period'=>'today']))->assertOk();
        $cards = collect($adviserPage->viewData('overview')['cards'])->pluck('value','label');
        $this->assertSame('1', $cards['Completed sessions']);
        $this->assertSame('5 min', $cards['Average Helper Response Time']);
        $this->assertSame('No data', $cards['Average Adviser Response Time']);
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
        foreach ([3, 4] as $i => $score) {
            $record = HelperCompetencyHistory::create(['helper_id'=>$helper->id, 'adviser_id'=>$helper->adviser_id, 'overall_score'=>$score, 'evaluation_date'=>now()->subMinutes(10-$i)]);
            $record->forceFill(['rubric_version'=>CompetencyRubric::VERSION])->save();
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
        $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk()->assertSee('Feedback status')->assertSee('1 awaiting feedback')
            ->assertDontSee('Average Helper Response Time')->assertDontSee('Average competency score');
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
