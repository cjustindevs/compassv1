<?php

namespace Tests\Feature;

use App\Events\SessionEnded;
use App\Models\AdviserFeedback;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\HelpSeekerEvaluation;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\SessionDurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HelperRefinementTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 19:00', 'Asia/Manila')->utc());
        config(['app.relax_duty_hours' => false]);
        Event::fake([SessionEnded::class]);
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Peer', 'last_name' => 'Helper', 'email' => $user->email, 'phone' => '09170000001', 'status' => 'available', 'availability' => 'available']);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => '2026-10-09', 'is_active' => true, 'created_by' => $user->id]);
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_result' => 'ready', 'assessment_date' => now(), 'valid_until' => now()->addHours(3), 'is_active' => true]);

        return $helper->fresh();
    }

    private function supportCase(Helper $helper, array $attributes = []): Session
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateSeeker'.$user->id]);

        return Session::create(array_merge(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_status' => 'completed', 'session_type' => 'chat', 'risk_level' => 'low', 'created_date' => now()->subHour(), 'start_time' => now()->subHour(), 'end_time' => now()->subMinutes(30), 'helper_accepted_at' => now()->subHour(), 'documentation_status' => 'submitted'], $attributes));
    }

    public function test_dashboard_uses_owned_upcoming_and_five_recent_records_with_separate_statuses(): void
    {
        $helper = $this->helper();
        $other = $this->helper();
        $upcoming = $this->supportCase($helper, ['session_status' => 'scheduled', 'scheduled_start' => now()->addDay(), 'start_time' => null, 'end_time' => null]);
        $this->supportCase($helper, ['session_status' => 'scheduled', 'scheduled_start' => now()->addDays(2)]);
        $foreign = $this->supportCase($other, ['session_status' => 'scheduled', 'scheduled_start' => now()->addHour()]);
        Notification::create(['user_account_id' => $helper->user_account_id, 'title' => 'Weekly seminar reminder', 'message' => 'Routine event', 'notification_type' => 'reminder']);
        Notification::create(['user_account_id' => $helper->user_account_id, 'title' => 'Session documentation is overdue', 'message' => 'Complete your documentation.', 'notification_type' => 'reminder']);
        foreach (range(1, 6) as $day) {
            $this->supportCase($helper, ['end_time' => now()->subDays($day)]);
        }
        $this->actingAs($helper->user)->get(route('helper.dashboard'))->assertOk()
            ->assertSee('Upcoming Session')->assertSee('Availability')->assertSee('Readiness')->assertSee('Recent Sessions')
            ->assertDontSee('Your Adviser')->assertDontSee('Total Sessions')->assertDontSee($foreign->reference_number)
            ->assertSee('Session documentation is overdue')->assertDontSee('Weekly seminar reminder')
            ->assertViewHas('upcomingSession', fn ($s) => $s->id === $upcoming->id)
            ->assertViewHas('recentSessions', fn ($s) => $s->count() === 5 && $s->every(fn ($row) => $row->helper_id === $helper->id));
        $this->getJson(route('helper.readiness.status'))->assertJsonPath('sidebar.availabilityLabel', 'Assignment pending');
        $helper->sessions()->where('session_status', 'scheduled')->update(['session_status' => 'cancelled']);
        $helper->update(['availability' => 'break']);
        $this->getJson(route('helper.readiness.status'))->assertJsonPath('ready', true)->assertJsonPath('sidebar.availabilityLabel', 'On break');
        ReadinessCheck::where('helper_id', $helper->id)->update(['valid_until' => now()->subMinute()]);
        $this->getJson(route('helper.readiness.status'))->assertJsonPath('ready', false)->assertJsonPath('sidebar.availabilityLabel', 'On break');
    }

    public function test_calendar_places_manila_boundary_dates_in_complete_sunday_weeks(): void
    {
        $helper = $this->helper();
        $owned = $this->supportCase($helper, ['session_status' => 'scheduled', 'scheduled_start' => Carbon::parse('2026-09-30 16:30', 'UTC')]);
        $excluded = $this->supportCase($helper, ['session_status' => 'scheduled', 'scheduled_start' => Carbon::parse('2026-10-31 16:30', 'UTC')]);
        $this->actingAs($helper->user)->get(route('helper.calendar', ['month' => 10, 'year' => 2026]))->assertOk()
            ->assertSee($owned->reference_number)->assertDontSee($excluded->reference_number)
            ->assertDontSee('Declare Duty')->assertDontSee('Sessions this month')
            ->assertViewHas('grid', fn ($grid) => count($grid[0]) === 7 && $grid[0][0]['date'] === '2026-09-27' && collect($grid)->flatten(1)->contains(fn ($cell) => $cell['date'] === '2026-10-09' && $cell['events']->contains('kind', 'duty')));
        $this->post('/helper/duty', ['date' => '2026-10-10'])->assertNotFound();
        $this->assertDatabaseCount('helper_schedules', 1);
    }

    public function test_filtered_csv_pdf_and_report_pagination_share_owned_case_records(): void
    {
        $helper = $this->helper();
        $category = ConcernCategory::create(['concern_name' => '=SUM(1,2)', 'is_active' => true]);
        $otherCategory = ConcernCategory::create(['concern_name' => 'Excluded category', 'is_active' => true]);
        $owned = $this->supportCase($helper, ['concern_id' => $category->id]);
        foreach (range(1, 16) as $i) {
            $this->supportCase($helper, ['concern_id' => $category->id]);
        }
        $foreign = $this->supportCase($this->helper(), ['concern_id' => $category->id]);
        $excluded = $this->supportCase($helper, ['concern_id' => $otherCategory->id]);
        $this->supportCase($helper, ['concern_id' => $category->id, 'session_status' => 'cancelled']);
        $filters = ['date_range' => '2026-10-09 to 2026-10-09', 'case_status' => 'completed', 'concern_id' => $category->id];
        $this->actingAs($helper->user)->get(route('helper.reports', $filters))->assertOk()
            ->assertViewHas('roleReport', fn ($d) => $d['summary']['Completed cases'] === 17 && $d['summary']['Completed session duration'] === '30 min' && $d['tables'][0]['records']->total() === 17 && $d['tables'][0]['records']->count() === 15);
        $csv = $this->get(route('helper.reports.export', $filters + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($owned->reference_number, $csv);
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString($foreign->reference_number, $csv);
        $this->assertStringNotContainsString($excluded->reference_number, $csv);
        $this->assertStringNotContainsString('PrivateSeeker', $csv);
        $pdf = $this->get(route('helper.reports.export', $filters + ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('helper.reports', ['date_range' => '2026-10-10 to 2026-10-09']))->assertSessionHasErrors('to');
        $this->get(route('helper.reports', ['date_range' => 'not a date']))->assertSessionHasErrors('date_range');
        $this->get(route('helper.reports', ['date_range' => '2025-01-01 to 2026-10-09']))->assertSessionHasErrors('date_range');
        $this->get(route('helper.reports.export', $filters + ['format' => 'exe']))->assertSessionHasErrors('format');
        $this->actingAs(User::factory()->create(['role' => 'seeker']))->get(route('helper.reports.export', $filters + ['format' => 'csv']))->assertForbidden();
    }

    public function test_profile_enforces_contact_restrictions_and_keeps_name_editing(): void
    {
        $helper = $this->helper();
        $this->actingAs($helper->user)->get(route('helper.profile'))->assertOk()->assertDontSee('Account Name')->assertDontSee('Recent Sessions')->assertSee('Read-only');
        $input = ['first_name' => 'Updated', 'last_name' => 'Helper'];
        foreach (['email' => 'changed@example.com', 'phone' => '09179999999', 'name' => 'Account change'] as $key => $value) {
            $this->put(route('helper.profile.update'), $input + [$key => $value])->assertSessionHasErrors($key);
            $this->assertSame('Peer', $helper->fresh()->first_name);
        }
        $this->put(route('helper.profile.update'), $input)->assertSessionHasNoErrors();
        $this->assertSame('Updated', $helper->fresh()->first_name);
        $this->assertSame('09170000001', $helper->fresh()->phone);
        $this->assertSame($helper->user->email, $helper->fresh()->email);
    }

    public function test_completion_reminders_are_idempotent_across_retries_and_sessions(): void
    {
        $helper = $this->helper();
        $first = $this->supportCase($helper, ['session_status' => 'active', 'end_time' => null]);
        $second = $this->supportCase($helper, ['session_status' => 'active', 'start_time' => now()->subMinutes(91), 'end_time' => null]);
        $this->actingAs($helper->user);
        $service = app(SessionDurationService::class);
        $this->assertTrue($service->complete($first));
        $this->assertFalse($service->complete($first));
        $notice = Notification::where('link', '/helper/session/'.$first->id.'/notes')->firstOrFail();
        $notice->archive();
        $this->assertFalse($service->complete($first));
        $this->assertTrue($service->expire($second));
        $this->assertFalse($service->expire($second));
        $this->assertSame(2, Notification::withoutGlobalScope('unarchived')->where('user_account_id', $helper->user_account_id)->where('title', 'Session completed')->count());
    }

    public function test_notification_pagination_read_archive_and_duplicate_scope_are_owned(): void
    {
        $helper = $this->helper();
        $session = $this->supportCase($helper);
        $data = ['user_account_id' => $helper->user_account_id, 'title' => 'Session completed', 'message' => 'Please complete your documentation.', 'notification_type' => 'reminder', 'link' => '/helper/session/'.$session->id.'/notes'];
        $first = Notification::create($data);
        Notification::create($data);
        foreach (range(1, 16) as $i) {
            Notification::create(['user_account_id' => $helper->user_account_id, 'title' => 'Assignment update '.$i, 'message' => 'Open your assignment.', 'notification_type' => 'assignment']);
        }
        $foreign = Notification::create(['user_account_id' => $this->helper()->user_account_id, 'title' => 'Foreign notice', 'message' => 'Private', 'notification_type' => 'system']);
        $this->actingAs($helper->user)->get(route('helper.notifications'))->assertOk()->assertSee('Archived')->assertDontSee('Foreign notice')
            ->assertViewHas('notifications', fn ($p) => $p->total() === 17 && $p->count() === 15);
        $this->postJson(route('helper.notifications.read', $first->id))->assertOk()->assertJsonPath('read', true);
        $this->assertSame('read', $first->fresh()->status);
        $this->postJson(route('helper.notifications.read', $foreign->id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $foreign->id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $first->id))->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $first->id]);
        $this->assertNotNull($first->fresh()->archived_at);
        $this->get(route('helper.notifications'))->assertViewHas('notifications', fn ($p) => $p->total() === 16);
        $this->get(route('notifications.archive'))->assertOk()->assertSee('Session completed');
    }

    public function test_competency_and_feedback_preserve_scales_dates_and_authorized_records(): void
    {
        $helper = $this->helper();
        $foreignHelper = $this->helper();
        $evaluation = HelperCompetencyHistory::create(['helper_id' => $helper->id, 'adviser_id' => $helper->adviser_id, 'overall_score' => 82, 'active_listening_score' => 84, 'empathy_score' => 4.1, 'competency_level' => 4, 'evaluation_date' => now()]);
        $foreignEvaluation = HelperCompetencyHistory::create(['helper_id' => $foreignHelper->id, 'adviser_id' => $foreignHelper->adviser_id, 'overall_score' => 90, 'competency_level' => 5, 'evaluation_date' => now()]);
        $session = $this->supportCase($helper);
        $report = SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Authorized summary']);
        $feedback = AdviserFeedback::create(['report_id' => $report->id, 'adviser_id' => $helper->adviser_id, 'competency_rating' => 4.1, 'competency_level' => 'very_good', 'feedback_text' => 'Good listening', 'created_date' => now()]);
        $foreignReport = SessionReport::create(['session_id' => $this->supportCase($foreignHelper)->id]);
        $foreignFeedback = AdviserFeedback::create(['report_id' => $foreignReport->id, 'adviser_id' => $foreignHelper->adviser_id, 'feedback_text' => 'Foreign feedback', 'created_date' => now()]);
        foreach (range(1, 11) as $i) {
            HelpSeekerEvaluation::create(['session_id' => $this->supportCase($helper)->id, 'overall_score' => 10, 'comments' => 'Felt supported '.$i, 'submitted_at' => now()]);
        }
        $this->actingAs($helper->user)->get(route('helper.competency'))->assertOk()->assertSee('4.1')->assertSee('4.2 / 5')->assertSee('Not recorded')->assertSee('Score Trend')
            ->assertViewHas('history', fn ($p) => $p->total() === 1 && $p->first()->id === $evaluation->id);
        $this->get(route('helper.competency.view', $foreignEvaluation->id))->assertNotFound();
        $this->get(route('helper.feedback'))->assertOk()->assertSee('Good listening')->assertSee('10.0 / 10')->assertDontSee('Foreign feedback')
            ->assertViewHas('seekerFeedback', fn ($p) => $p->total() === 11 && $p->count() === 10);
        $this->get(route('helper.feedback', ['seeker_page' => 2]))->assertViewHas('seekerFeedback', fn ($p) => $p->count() === 1)->assertViewHas('adviserFeedback', fn ($p) => $p->count() === 1);
        $this->get(route('helper.feedback', ['adviser_search' => 'missing', 'seeker_search' => 'Felt supported 11']))->assertViewHas('adviserFeedback', fn ($p) => $p->total() === 0)->assertViewHas('seekerFeedback', fn ($p) => $p->total() === 1);
        $this->get(route('helper.feedback', ['date_range' => '2026-10-01 to 2026-10-02']))->assertViewHas('adviserFeedback', fn ($p) => $p->total() === 0)->assertViewHas('seekerFeedback', fn ($p) => $p->total() === 0);
        $this->get(route('helper.feedback.view', $foreignFeedback->id))->assertNotFound();
        $this->get(route('helper.feedback.view', $feedback->id))->assertOk();
    }
}
