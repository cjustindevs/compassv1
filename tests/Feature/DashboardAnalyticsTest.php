<?php

namespace Tests\Feature;

use App\Models\{Helper, HelpSeeker, PsychologyProfessional, Referral, Session, User};
use App\Services\{AdminDashboardAnalytics, AnalyticsPeriod, ProfessionalAnalytics};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 12:00', 'Asia/Manila')->utc());
    }

    private function professional(): PsychologyProfessional
    {
        $user = User::factory()->create(['role' => 'professional', 'is_active' => true]);
        return PsychologyProfessional::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Professional', 'email' => $user->email, 'license_number' => 'TEST-'.$user->id]);
    }

    private function supportCase(array $attributes = []): Session
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateAlias'.$user->id]);
        $case = Session::create($attributes + ['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'completed', 'risk_level' => 'low', 'submitted_at' => now()->subDays(2), 'created_date' => now()->subDays(2), 'start_time'=>now()->subDays(3), 'end_time' => now()->subDay()]);
        $case->forceFill($attributes)->save(); // Archive fields are deliberately not mass assignable in production.
        return $case;
    }

    private function referral(PsychologyProfessional $professional, array $attributes = [], array $caseAttributes = []): Referral
    {
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $helperUser->email]);
        $case = $this->supportCase(['helper_id' => $helper->id] + $caseAttributes);
        return Referral::create($attributes + ['session_id' => $case->id, 'helper_id' => $helper->id, 'professional_id' => $professional->id, 'approved_at' => now()->subDays(3), 'help_seeker_consent' => true, 'professional_notified_at' => now()->subDays(2), 'referral_reason' => 'Private narrative not for reporting', 'status' => 'pending_professional', 'priority_level' => 'low']);
    }

    private function dates(): array
    {
        return ['from' => '2026-10-10', 'to' => '2026-10-11'];
    }

    public function test_admin_calendar_boundaries_include_archived_records_and_reconcile_with_report_tables(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['2026-10-09 15:59:59', '2026-10-09 16:00:00', '2026-10-11 15:59:59', '2026-10-11 16:00:00'] as $time) {
            $this->supportCase(['submitted_at' => $time, 'end_time' => $time, 'archived_at' => now()]);
        }
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard', $this->dates()))->assertOk();
        $dashboard->assertViewHas('analytics', fn ($data) => $data['requests']['values'] === [1, 1] && $data['completed']['total'] === 2);
        $dashboard->assertDontSee('PrivateAlias');
        $this->get(route('admin.reports', $this->dates() + ['activity_date' => 'completed']))->assertOk()
            ->assertViewHas('roleReport', fn ($data) => $data['summary']['Sessions completed in period'] === 2 && $data['tables'][0]['records']->total() === 2);
        $this->get(route('admin.reports', $this->dates() + ['activity_date' => 'submitted']))->assertOk()
            ->assertViewHas('roleReport', fn ($data) => $data['summary']['Requests submitted in period'] === 2 && $data['tables'][0]['records']->total() === 2);
    }

    public function test_professional_events_use_their_own_dates_and_ignore_later_edits(): void
    {
        $professional = $this->professional();
        $finished = $this->referral($professional, ['professional_notified_at' => '2026-09-01 00:00:00', 'status' => 'completed', 'completed_at' => '2026-10-10 02:00:00', 'closed_date' => '2026-10-10 02:00:00', 'accepted_at' => '2026-09-01 02:00:00']);
        $reviewer = User::factory()->create(['role'=>'adviser']);
        $finished->completionReviews()->create(['professional_id'=>$professional->id,'submitted_by'=>$professional->user_account_id,
            'summary'=>'Completion evidence','review_status'=>'confirmed','reviewed_by'=>$reviewer->id,'reviewed_at'=>'2026-10-10 02:00:00']);
        $this->referral($professional, ['status' => 'accepted', 'professional_notified_at' => '2026-10-10 00:00:00', 'accepted_at' => '2026-10-10 02:00:00', 'updated_at' => now()]);
        $this->referral($professional, ['status' => 'closed', 'closed_date' => '2026-10-10 02:00:00']);
        $this->referral($professional, ['status' => 'accepted', 'accepted_at' => '2026-10-09 00:00:00']); // Invalid negative interval.
        $this->actingAs($professional->user)->get(route('professional.dashboard', $this->dates()))->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['completed']['total'] === 1 && $data['closed'] === 1 && $data['response_hours'] === 2.0 && $data['response_samples'] === 1);
        $this->get(route('professional.reports', $this->dates() + ['activity' => 'completed']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    public function test_professional_csv_and_pagination_apply_identical_consent_ownership_and_status_filters(): void
    {
        $professional = $this->professional();
        $other = $this->professional();
        $owned = collect(range(1, 16))->map(fn () => $this->referral($professional, ['status' => 'accepted', 'accepted_at' => now()->subDay()]));
        $excluded = [
            $this->referral($other, ['status' => 'accepted']),
            $this->referral($professional, ['status' => 'accepted', 'help_seeker_consent' => false]),
            $this->referral($professional, ['status' => 'accepted', 'approved_at' => null]),
            $this->referral($professional),
        ];
        $filters = $this->dates() + ['status' => 'accepted'];
        $this->actingAs($professional->user)->get(route('professional.reports', $filters))->assertOk()
            ->assertViewHas('records', fn ($page) => $page->total() === 16 && $page->perPage() === 15 && str_contains($page->url(2), 'status=accepted'))
            ->assertViewHas('analytics', fn ($data) => $data['received']['total'] === 16);
        $this->get(route('professional.reports', $filters + ['page' => 2]))->assertOk()->assertViewHas('records', fn ($page) => $page->count() === 1);
        $csv = $this->get(route('professional.reports.export', $filters + ['page' => 2]))->assertOk()->streamedContent();
        foreach ($owned as $referral) $this->assertStringContainsString($referral->session->seeker->generated_alias, $csv);
        foreach ($excluded as $referral) $this->assertStringNotContainsString($referral->session->seeker->generated_alias, $csv);
        $this->assertStringNotContainsString('Private narrative', $csv);
        $this->assertStringNotContainsString($professional->email, $csv);
    }

    public function test_archived_cases_are_historical_not_current_workload_and_missing_events_are_not_zero_durations(): void
    {
        $professional = $this->professional();
        $this->referral($professional, ['status' => 'accepted', 'professional_notified_at' => null], ['archived_at' => now()]);
        $this->referral($professional, ['status' => 'completed', 'completed_at' => null, 'closed_date' => null]);
        $this->actingAs($professional->user)->get(route('professional.dashboard', $this->dates()))->assertOk()
            ->assertViewHas('analytics', fn ($data) => $data['active_now'] === 0 && $data['response_hours'] === null && $data['missing_completion_dates'] === 1
                && $data['confirmed_completions'] === 0 && $data['legacy_completions_without_confirmation'] === 1);
        $this->get(route('professional.reports', ['from' => '2026-10-10', 'to' => '2026-10-12']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 2);
    }

    public function test_invalid_filters_and_cross_role_routes_are_rejected(): void
    {
        $professional = $this->professional();
        $this->actingAs($professional->user)->get(route('professional.reports', ['from' => '2026-10-11', 'to' => '2026-10-10']))->assertSessionHasErrors('to');
        $this->get(route('professional.reports.export', ['status' => 'pending_adviser']))->assertSessionHasErrors('status');
        $this->get(route('professional.reports.export', ['activity' => 'unknown']))->assertSessionHasErrors('activity');
        $this->get(route('professional.dashboard', ['from' => '2026-10-10']))->assertSessionHasErrors('to');
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('professional.reports'))->assertForbidden();
    }

    public function test_default_range_is_thirty_calendar_days_and_chart_failure_is_not_an_empty_dataset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('analyticsDates', ['from' => '2026-09-13', 'to' => '2026-10-12']);
        $this->mock(AdminDashboardAnalytics::class)->shouldReceive('data')->andThrow(new \RuntimeException('Test aggregation failure'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Unable to load analytics.')->assertViewHas('analytics', null);
    }
}
