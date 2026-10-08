<?php
namespace Tests\Feature;

use App\Models\{User, Session, HelpSeeker, Helper, EmergencyAlert, IncidentReport, ReadinessCheck, HelperSchedule, QueueRequest};
use App\Services\{DashboardOverview, HelperMatchingService, HelperEligibilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\SeekerWorkflowFixtures;

    private function helper(): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $user->email, 'availability' => 'available', 'status' => 'offline', 'competency_level' => 4]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'is_active' => true, 'created_by' => $user->id]);
        return $helper->fresh();
    }
    private function caseFor(?Helper $helper, string $status = 'active'): Session
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateAlias'.$user->id]);
        return Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper?->id, 'session_status' => $status, 'session_type' => 'chat', 'risk_level' => 'low', 'created_date' => now()]);
    }
    private function cards(array $overview): array
    {
        return collect($overview['cards'])->pluck('value', 'label')->all();
    }
    public function test_emergency_summary_uses_terminal_states_and_explicit_event_times(): void
    {
        $user = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $case = $this->caseFor(null);
        $alert = EmergencyAlert::create(['session_id' => $case->id, 'seeker_id' => $case->seeker_id, 'trigger_reason' => 'Test observation', 'status' => 'resolved', 'triggered_at' => now()->subMinutes(30), 'resolved_at' => now()->subMinutes(10), 'risk_level' => 'emergency']);
        $alert->forceFill(['acknowledged_at' => now()->subMinutes(25)])->save();
        EmergencyAlert::create(['session_id' => $case->id, 'seeker_id' => $case->seeker_id, 'trigger_reason' => 'Test observation', 'status' => 'pending', 'triggered_at' => now(), 'risk_level' => 'emergency']);
        $overview = app(DashboardOverview::class)->forUser($user);
        $cards = $this->cards($overview);
        $this->assertSame(1, $cards['Active emergencies']);
        $this->assertSame(1, $cards['Resolved emergencies']);
        $this->assertSame('5 min', $cards['Average response']);
        $this->assertSame('20 min', $cards['Average resolution']);
        $this->assertStringNotContainsString('PrivateAlias', json_encode($overview));
    }
    public function test_empty_admin_overview_and_real_queue_rate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $overview = app(DashboardOverview::class)->forUser($admin);
        $this->assertSame('No data', $this->cards($overview)['Matching success rate']);
        $this->assertSame('No data', $this->cards($overview)['Average match response']);
        $case = $this->caseFor(null, 'completed');
        foreach ([true, false] as $matched) QueueRequest::create(['seeker_id' => $case->seeker_id, 'request_status' => $matched ? 'assigned' : 'waiting', 'priority_level' => 'low', 'request_date' => now()->subMinutes(8), 'matched_date' => $matched ? now()->subMinutes(3) : null]);
        $cards = $this->cards(app(DashboardOverview::class)->forUser($admin));
        $this->assertSame('50%', $cards['Matching success rate']);
        $this->assertSame(1, $cards['Successful matches']);
        $this->assertSame('5 min', $cards['Average match response']);
        $this->assertSame(1, $cards['Resolved cases']);
    }
    public function test_adviser_overview_excludes_unrelated_cases(): void
    {
        $helper = $this->helper();
        $this->caseFor($helper, 'completed');
        $other = $this->helper();
        $this->caseFor($other);
        $overview = app(DashboardOverview::class)->forUser($helper->adviser->user);
        $this->assertSame(1, $this->cards($overview)['Resolved cases']);
        $this->assertSame(0, $this->cards($overview)['Active cases']);
        $this->assertCount(1, $overview['recent']);
    }
    public function test_legacy_resolved_emergency_is_excluded_from_open_incidents_without_migration(): void
    {
        $case = $this->caseFor(null);
        IncidentReport::create(['session_id' => $case->id, 'user_account_id' => $case->seeker->user_account_id, 'incident_category' => 'classification_emergency', 'description' => 'Private report', 'risk_level' => 'emergency', 'status' => 'open']);
        DB::table('emergency_alerts')->insert(['session_id' => $case->id, 'seeker_id' => $case->seeker_id, 'trigger_reason' => 'Test observation', 'status' => 'resolved', 'triggered_at' => now(), 'resolved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, IncidentReport::open()->count());
    }
    public function test_matching_uses_latest_readiness_and_preserves_availability_rules(): void
    {
        config(['app.relax_duty_hours' => false, 'app.enforce_duty_hours' => false]);
        $helper = $this->helper();
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now()->addMinute(), 'valid_until' => now()->addHour(), 'assessment_result' => 'ready', 'is_active' => false]);
        $current = ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHour(), 'assessment_result' => 'ready', 'is_active' => true]);
        $this->assertSame(1, app(HelperMatchingService::class)->countEligibleHelpers('low'));
        $this->assertSame(1, app(HelperEligibilityService::class)->countAvailable([$helper]));
        $helper->update(['availability' => 'break']);
        config(['app.relax_duty_hours' => true]);
        $this->assertSame(0, app(HelperMatchingService::class)->countEligibleHelpers('low'));
        $helper->update(['availability' => 'available']);
        $current->update(['valid_until' => now()->subMinute()]);
        $this->assertSame(0, app(HelperMatchingService::class)->countEligibleHelpers('low'));
    }
    public function test_eligible_seeker_queue_matches_without_bypassing_readiness(): void
    {
        config(['app.relax_duty_hours' => false, 'app.enforce_duty_hours' => false]);
        $helper = $this->helper();
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHour(), 'assessment_result' => 'ready', 'is_active' => true]);
        $case = $this->caseFor(null, 'waiting');
        $this->consentFixture($case->seeker->user);
        $queue = QueueRequest::create(['seeker_id' => $case->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);
        $case->update(['queue_request_id' => $queue->id, 'submitted_at' => now()]);
        $matched = app(HelperMatchingService::class)->processQueueRequest($queue);
        $this->assertInstanceOf(Session::class, $matched);
        $this->assertSame($helper->id, $matched->helper_id);
        $this->assertSame('assigned', $queue->fresh()->request_status);
        $this->assertSame('Assignment pending', app(\App\Services\HelperSidebarStats::class)->forHelper($helper)['availabilityLabel']);
        $this->assertSame(0, app(HelperMatchingService::class)->countEligibleHelpers('low'));
    }
    public function test_dashboards_render_with_real_overviews(): void
    {
        $helper = $this->helper();
        $this->caseFor($helper, 'completed');
        $this->actingAs($helper->adviser->user)->get(route('adviser.dashboard'))
            ->assertOk()->assertSee('Cases by status')->assertSee('No data')
            ->assertSee('<polyline', false)->assertSee('co-donut-total')->assertSee('View chart data and definition')
            ->assertSee('css/dashboard-overview.css')->assertDontSee('At a glance');
        $moderator = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        \App\Models\Moderator::create(['user_account_id' => $moderator->id, 'first_name' => 'Test', 'last_name' => 'Moderator', 'email' => $moderator->email]);
        $this->actingAs($moderator)->get(route('moderator.dashboard'))->assertOk()->assertSee('Emergency trend');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Users by role');
    }

    public function test_overview_rejects_other_roles(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DashboardOverview::class)->forUser($user);
    }
}
