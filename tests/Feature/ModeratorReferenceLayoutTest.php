<?php

namespace Tests\Feature;

use App\Models\ConcernCategory;
use App\Models\EmergencyAlert;
use App\Models\EmergencyResource;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\SupportAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModeratorReferenceLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 12:00:00', 'Asia/Manila')->utc());
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'is_active' => true]));
    }

    private function supportCase(string $status = 'waiting', string $risk = 'low'): Session
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'SafeAlias'.$user->id]);

        return Session::create(['seeker_id' => $seeker->id, 'session_status' => $status, 'risk_level' => $risk, 'session_type' => 'chat', 'submitted_at' => now(), 'created_date' => now()]);
    }

    private function alert(string $status, array $attributes = []): EmergencyAlert
    {
        $session = $this->supportCase();

        return EmergencyAlert::create(array_merge(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'status' => $status, 'risk_level' => 'emergency', 'trigger_reason' => 'Secret clinical narrative', 'triggered_at' => now()->subMinutes(20)], $attributes));
    }

    public function test_case_overview_totals_are_real_and_scheduled_cases_are_not_pending(): void
    {
        foreach (['completed', 'evaluated', 'waiting', 'cancelled', 'no_show', 'active'] as $status) {
            $this->supportCase($status);
        }
        $scheduled = $this->supportCase();
        $scheduled->update(['scheduled_start' => now()->addDay()]);
        $this->get(route('moderator.reports'))->assertOk()->assertSee('Case Overview')->assertSee('Activity Log')->assertSee('mo-bars', false)->assertSee('mo-donut', false)
            ->assertViewHas('roleReport', fn ($r) => $r['summary']['Cases in period'] === 7 && $r['summary']['Pending requests'] === 1 && $r['summary']['Completed cases'] === 2 && array_sum($r['charts']['status']['values']) === 7 && $r['charts']['status']['values']['Scheduled'] === 1);
    }

    public function test_archived_emergency_filter_includes_retained_archive_markers(): void
    {
        $this->alert('resolved', ['resolved_at' => now()])->forceFill(['archived_at' => now()])->save();
        $this->alert('resolved', ['resolved_at' => now()]);
        $this->get(route('moderator.reports', ['emergency_status' => 'archived']))->assertOk()->assertViewHas('roleReport', fn ($r) => $r['summary']['Emergencies'] === 1 && $r['summary']['Unresolved emergencies'] === 0);
        $scheduled = $this->supportCase();
        $scheduled->update(['scheduled_start' => now()->addDay()]);
        SupportAudit::record('session_scheduled', $scheduled);
        $this->get(route('moderator.reports', ['case_status' => 'scheduled', 'activity' => 'cases']))->assertOk()->assertViewHas('roleReport', fn ($r) => $r['summary']['Cases in period'] === 1 && $r['charts']['status']['values']['Scheduled'] === 1 && collect($r['tables'])->firstWhere('group', 'activity')['records']->first()->record_id == $scheduled->id);
    }

    public function test_referrals_and_categories_use_actual_records_without_disclosing_clinical_information(): void
    {
        $user = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $user->email]);
        $category = ConcernCategory::firstOrCreate(['concern_name' => 'Test academic category']);
        $session = $this->supportCase('completed');
        $session->update(['helper_id' => $helper->id, 'concern_id' => $category->id]);
        Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id, 'referral_reason' => 'Secret referral narrative', 'referral_date' => now(), 'status' => 'completed']);
        $this->get(route('moderator.reports', ['overview' => 'referrals']))->assertOk()->assertDontSee('Secret referral narrative')->assertViewHas('roleReport', fn ($r) => $r['charts']['referrals']['values'] === ['Completed' => 1]);
        $this->get(route('moderator.reports', ['overview' => 'categories']))->assertOk()->assertSee('Test academic category')->assertViewHas('roleReport', fn ($r) => array_sum($r['charts']['categories']['values']) === 1);
        $this->get(route('moderator.reports', ['overview' => 'referrals', 'priority' => 'emergency']))->assertOk()->assertViewHas('roleReport', fn ($r) => $r['charts']['referrals']['values'] === []);
    }

    public function test_invalid_ranges_filters_and_pagination_return_validation_errors(): void
    {
        foreach ([['date_range' => ['bad'], 'error' => 'date_range'], ['date_range' => 'not a date', 'error' => 'date_range'], ['date_range' => '2026-02-30 to 2026-03-01', 'error' => 'from'], ['date_range' => '2026-10-09 to 2026-10-08', 'error' => 'to'], ['date_range' => '2024-01-01 to 2026-10-09', 'error' => 'date_range'], ['case_status' => 'unknown', 'error' => 'case_status'], ['overview' => ['status'], 'error' => 'overview'], ['priority' => 'urgent', 'error' => 'priority'], ['activity_page' => -1, 'error' => 'activity_page']] as $input) {
            $error = $input['error'];
            unset($input['error']);
            $this->getJson(route('moderator.reports', $input))->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->get(route('moderator.reports', ['date_range' => '2025-10-09 to 2026-10-09']))->assertOk();
        $this->get(route('moderator.reports', ['overview' => '', 'tab' => '', 'activity' => '']))->assertOk()->assertSee('Case Overview');
    }

    public function test_activity_filters_use_context_and_preserve_event_outcomes_and_pagination(): void
    {
        $completed = $this->supportCase('completed', 'high');
        $waiting = $this->supportCase('waiting', 'low');
        SupportAudit::record('session_completed', $completed);
        SupportAudit::record('request_submitted', $waiting);
        $this->get(route('moderator.reports', ['priority' => 'high', 'activity' => 'cases']))->assertOk()->assertSee($completed->reference_number)->assertViewHas('roleReport', function ($r) use ($completed) {
            $log = collect($r['tables'])->firstWhere('group', 'activity')['records'];

            self::assertSame(2, $log->total());
            self::assertSame($completed->id, (int) $log->first()->record_id);
            self::assertSame('completed', $log->first()->record_status);
            self::assertSame('success', $log->first()->status);

            return true;
        });
        $this->get(route('moderator.reports', ['search' => strtolower($completed->reference_number)]))->assertOk()->assertViewHas('roleReport', fn ($r) => collect($r['tables'])->firstWhere('group', 'activity')['records']->total() === 2);
        $this->get(route('moderator.reports', ['case_status' => 'waiting']))->assertOk()->assertViewHas('roleReport', fn ($r) => collect($r['tables'])->firstWhere('group', 'activity')['records']->first()->record_id == $waiting->id);
        foreach (range(1, 16) as $i) {
            SupportAudit::record('request_submitted', $waiting);
        }
        $this->get(route('moderator.reports', ['activity' => 'cases', 'activity_page' => 2, 'priority' => 'low']))->assertOk()->assertViewHas('roleReport', function ($r) {
            $log = collect($r['tables'])->firstWhere('group', 'activity')['records'];

            return $log->count() === 3 && str_contains($log->url(1), 'priority=low');
        });
    }

    public function test_emergency_workflow_partitions_open_records_and_excludes_terminal_cases(): void
    {
        $this->alert('pending');
        $this->alert('notified', ['adviser_notified' => true, 'adviser_notified_at' => now()->subMinutes(15)]);
        $this->alert('acknowledged')->forceFill(['acknowledged_at' => now()->subMinutes(10)])->save();
        $this->alert('referred', ['professional_referred' => true, 'professional_referred_at' => now()->subMinutes(5)]);
        $this->alert('resolved', ['resolved_at' => now()->subMinutes(2)]);
        $this->alert('closed', ['resolved_at' => now()->subDays(31)]);
        $this->alert('cancelled');
        $this->alert('pending')->forceFill(['archived_at' => now()])->save();
        $this->get(route('moderator.emergency'))->assertOk()->assertSee('Emergency Workflow')->assertSee('Priority Distribution')->assertDontSee('Secret clinical narrative')
            ->assertViewHas('stats', fn ($r) => $r['open'] === 4 && $r['resolved_30d'] === 1 && $r['escalated_today'] === 1 && $r['average_response'] === '10 min')
            ->assertViewHas('workflow', fn ($r) => $r === ['Detected' => 1, 'Notified' => 1, 'Reviewing' => 1, 'Referral' => 1, 'Closed' => 1])
            ->assertViewHas('priorityDistribution', fn ($r) => array_sum($r) === 4);
    }

    public function test_empty_states_missing_timestamps_and_public_contacts_are_handled(): void
    {
        EmergencyResource::create(['agency_name' => 'Published hotline', 'hotline' => '12345', 'description' => 'Public support', 'status' => 'active'])->forceFill(['visibility' => 'public'])->save();
        EmergencyResource::create(['agency_name' => 'Private hotline', 'hotline' => '54321', 'status' => 'active'])->forceFill(['visibility' => 'private'])->save();
        $this->get(route('moderator.emergency'))->assertOk()->assertSee('No active emergency cases')->assertSee('Published hotline')->assertDontSee('Private hotline')->assertViewHas('stats', fn ($r) => $r['average_response'] === 'No data');
        $this->get(route('moderator.reports'))->assertOk()->assertSee('No records match the selected filters')->assertSee('No activity matches these filters');
        $this->alert('acknowledged')->forceFill(['acknowledged_at' => now()->addHour()])->save();
        $this->alert('acknowledged')->forceFill(['acknowledged_at' => now()->subHour()])->save();
        $this->get(route('moderator.emergency'))->assertOk()->assertViewHas('stats', fn ($r) => $r['average_response'] === 'No data');
    }
}
