<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\SessionReconnection;
use App\Models\User;
use App\Services\DashboardOverview;
use App\Services\ModeratorActivity;
use App\Services\ModeratorEmergencyCases;
use App\Services\StaleQueueRequests;
use App\Services\SupportAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModeratorRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator', 'is_active' => true]);
    }

    private function supportCase(string $status = 'waiting'): Session
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);

        return Session::create(['seeker_id' => $seeker->id, 'session_status' => $status, 'risk_level' => 'low', 'session_type' => 'chat', 'submitted_at' => now(), 'created_date' => now()]);
    }

    private function alert(Session $session, string $status): EmergencyAlert
    {
        return EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'status' => $status, 'risk_level' => 'emergency', 'trigger_reason' => 'Private narrative must not appear', 'triggered_at' => now(), 'resolved_at' => in_array($status, ['resolved', 'closed']) ? now() : null]);
    }

    public function test_emergency_counts_share_one_definition_and_do_not_count_mirrors_or_terminal_cases(): void
    {
        $user = $this->moderator();
        $case = $this->supportCase();
        $this->alert($case, 'pending');
        $this->alert($case, 'acknowledged')->forceFill(['acknowledged_at' => now()])->save();
        IncidentReport::create(['session_id' => $case->id, 'user_account_id' => $user->id, 'incident_category' => 'emergency_flag', 'status' => 'open', 'risk_level' => 'emergency', 'description' => 'Private incident']);
        foreach (['resolved', 'closed', 'cancelled', 'archived'] as $state) {
            $this->alert($this->supportCase(), $state);
        }
        $legacy = $this->supportCase();
        IncidentReport::create(['session_id' => $legacy->id, 'user_account_id' => $user->id, 'incident_category' => 'emergency_flag', 'status' => 'open', 'risk_level' => 'emergency', 'description' => 'Private incident', 'reported_at' => now()]);
        IncidentReport::create(['session_id' => $this->supportCase()->id, 'user_account_id' => $user->id, 'incident_category' => 'other', 'status' => 'open', 'risk_level' => 'low', 'description' => 'Not an emergency']);
        $this->alert($this->supportCase(), 'pending')->forceFill(['archived_at' => now()])->save();
        $this->assertSame(2, app(ModeratorEmergencyCases::class)->active()->count());
        $overview = app(DashboardOverview::class)->forUser($user);
        $this->assertSame(2, collect($overview['cards'])->firstWhere('label', 'Active emergencies')['value']);
        $this->assertSame(2, array_sum($overview['charts'][0]['values']));
        $this->actingAs($user)->get(route('moderator.dashboard'))->assertOk()->assertDontSee('Average response')->assertDontSee('Average resolution')->assertDontSee('Live Sessions')->assertSee('View All');
        $this->get(route('moderator.emergency'))->assertOk()->assertViewHas('openIncidents', fn ($rows) => $rows->total() === 2)->assertDontSee('Private incident')->assertDontSee('Private narrative');
        $this->get(route('moderator.reports', ['tab' => 'safety', 'emergency_status' => 'active']))->assertOk()->assertViewHas('roleReport', fn ($r) => $r['summary']['Unresolved emergencies'] === 2);
    }

    public function test_emergency_polling_detects_status_changes_even_when_the_active_count_stays_the_same(): void
    {
        $user=$this->moderator();
        $alert=$this->alert($this->supportCase(),'pending');
        $this->actingAs($user);
        $before=$this->get(route('moderator.emergency.stats'))->assertOk()->json();
        $dashboardBefore=$this->get(route('moderator.dashboard.stats'))->assertOk()->json();
        $alert->forceFill(['acknowledged_at'=>now(),'status'=>'acknowledged'])->save();
        $after=$this->get(route('moderator.emergency.stats'))->assertOk()->json();
        $dashboardAfter=$this->get(route('moderator.dashboard.stats'))->assertOk()->json();
        $this->assertSame($before['open'],$after['open']);
        $this->assertNotSame($before['signature'],$after['signature']);
        $this->assertNotSame($dashboardBefore['overview_signature'],$dashboardAfter['overview_signature']);
        $alert->update(['status'=>'resolved','resolved_at'=>now()]);
        $this->get(route('moderator.emergency.stats'))->assertOk()->assertJsonPath('open',0);
    }

    public function test_waiting_queue_expires_after_24_hours_once_and_preserves_emergency_review(): void
    {
        $session = $this->supportCase();
        $queue = QueueRequest::create(['seeker_id' => $session->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()->subHours(25)]);
        $session->update(['queue_request_id' => $queue->id]);
        $emergency = $this->supportCase('emergency');
        $emergency->update(['risk_level' => 'emergency', 'requires_immediate_action' => true]);
        $alert = $this->alert($emergency, 'pending');
        $emergencyQueue = QueueRequest::create(['seeker_id' => $emergency->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'emergency', 'request_date' => now()->subHours(25)]);
        $emergency->update(['queue_request_id' => $emergencyQueue->id]);
        $new = QueueRequest::create(['seeker_id' => $this->supportCase()->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()->subHours(23)]);
        $active = $this->supportCase('active');
        $activeQueue = QueueRequest::create(['seeker_id' => $active->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()->subHours(25)]);
        $active->update(['queue_request_id' => $activeQueue->id]);
        $scheduled = $this->supportCase();
        $scheduledQueue = QueueRequest::create(['seeker_id' => $scheduled->seeker_id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()->subHours(25)]);
        $scheduled->update(['queue_request_id' => $scheduledQueue->id, 'scheduled_start' => now()->addDay()]);
        $this->assertSame(2, app(StaleQueueRequests::class)->expire());
        $this->assertSame('expired', $queue->fresh()->request_status);
        $this->assertNotNull($queue->fresh()->expired_at);
        $this->assertSame('cancelled', $session->fresh()->session_status);
        $this->assertSame('expired', $emergencyQueue->fresh()->request_status);
        $this->assertSame('emergency', $emergency->fresh()->session_status);
        $this->assertSame('pending', $alert->fresh()->status);
        foreach ([$new, $activeQueue, $scheduledQueue] as $q) {
            $this->assertSame('waiting', $q->fresh()->request_status);
        }
        $this->assertSame(0, app(StaleQueueRequests::class)->expire());
        $this->assertSame(2, AuditLog::where('action', 'request_expired')->count());
        $this->assertSame(5, QueueRequest::count());
    }

    public function test_archive_retains_history_is_idempotent_and_cannot_archive_active_records(): void
    {
        $user = $this->moderator();
        $resolved = $this->alert($this->supportCase(), 'resolved');
        $data = ['record_type' => 'emergency_alerts', 'record_id' => $resolved->id];
        $this->actingAs($user)->post(route('moderator.archive.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($resolved->fresh()->archived_at);
        $this->assertSame('resolved', $resolved->fresh()->status);
        $this->post(route('moderator.archive.store'), $data)->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'record_archived')->count());
        $this->get(route('moderator.reports', ['tab' => 'safety', 'archive' => 'archived']))->assertOk()->assertSee('Archived')->assertSee('Restore history');
        $this->post(route('moderator.archive.store'), $data + ['restore' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($resolved->fresh()->archived_at);
        $this->assertSame('resolved', $resolved->fresh()->status);
        $open = $this->alert($this->supportCase(), 'pending');
        $this->post(route('moderator.archive.store'), ['record_type' => 'emergency_alerts', 'record_id' => $open->id])->assertSessionHasErrors('archive');
        $this->actingAs(User::factory()->create(['role' => 'helper', 'is_active' => true]))->post(route('moderator.archive.store'), $data)->assertForbidden();
    }

    public function test_settings_loads_without_a_profile_and_updates_the_current_moderator(): void
    {
        $user = $this->moderator();
        $this->actingAs($user)->get(route('moderator.settings'))->assertOk();
        $this->put(route('moderator.settings.profile'), ['name' => 'Test Moderator', 'first_name' => 'Test', 'last_name' => 'Moderator', 'email' => $user->email])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Test', $user->fresh()->moderator->first_name);
        $this->get(route('moderator.settings'))->assertOk();
    }

    public function test_manage_uses_backend_pagination_and_preserves_search(): void
    {
        foreach (range(1, 17) as $i) {
            $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
            Helper::create(['user_account_id' => $user->id, 'first_name' => 'Pagination', 'last_name' => 'Helper'.$i, 'email' => $user->email]);
        }
        $this->actingAs($this->moderator())->get(route('moderator.manage', ['search' => 'Pagination', 'adviser' => 'unassigned']))->assertOk()
            ->assertViewHas('helpers', fn ($r) => $r->total() === 17 && $r->count() === 15 && str_contains($r->url(2), 'search=Pagination'));
        $this->get(route('moderator.manage', ['page' => 2, 'search' => 'Pagination']))->assertOk()->assertViewHas('helpers', fn ($r) => $r->count() === 2);
    }

    public function test_reports_date_range_uses_manila_boundaries_and_activity_is_shared(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 12:00', 'Asia/Manila')->utc());
        $user = $this->moderator();
        $inside = $this->supportCase('completed');
        $inside->update(['submitted_at' => '2026-10-08 16:01:00', 'created_date' => '2026-10-08 16:01:00']);
        $outside = $this->supportCase('completed');
        $outside->update(['submitted_at' => '2026-10-08 15:59:00', 'created_date' => '2026-10-08 15:59:00']);
        $this->actingAs($user);
        SupportAudit::record('session_completed', $inside);
        $this->get(route('moderator.reports', ['date_range' => '2026-10-09 to 2026-10-09']))->assertOk()->assertViewHas('roleReport', fn ($r) => $r['summary']['Cases in period'] === 1)->assertSee('Date Range');
        $this->get(route('moderator.reports', ['tab' => 'activity', 'date_range' => '2026-10-09 to 2026-10-09']))->assertOk()->assertSee('Session Completed')->assertDontSee('Incoming queue activity');
        $this->assertSame(1, app(ModeratorActivity::class)->query()->where('action', 'session_completed')->count());
        $this->get(route('moderator.reports', ['date_range' => '2026-10-10 to 2026-10-09']))->assertSessionHasErrors('to');
    }

    public function test_connection_review_filters_real_session_and_offer_states(): void
    {
        $user = $this->moderator();
        $helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'first_name' => 'Original', 'last_name' => 'Helper', 'email' => $helperUser->email]);
        foreach (['active', 'cancelled', 'completed'] as $state) {
            $session = $this->supportCase($state);
            SessionReconnection::create(['session_id' => $session->id, 'original_helper_id' => $helper->id, 'status' => $state === 'active' ? 'requested' : 'closed', 'detected_at' => now()]);
            if ($state === 'active') {
                $this->actingAs($user);
                SupportAudit::record('replacement_offer_declined', $session);
            }
        }
        foreach (['active' => 'Active', 'declined' => 'Last replacement offer declined', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $state => $label) {
            $this->actingAs($user)->get(route('moderator.reconnections', ['state' => $state]))->assertOk()->assertSee($label)->assertViewHas('incidents', fn ($rows) => $rows->total() === 1);
        }
    }

    public function test_schedule_shows_the_saved_appointment_without_exposing_identity(): void
    {
        $case = $this->supportCase('helper_assigned');
        $case->update(['scheduled_start' => now()->addMinutes(15)]);
        $this->actingAs($this->moderator())->get(route('moderator.schedules', ['date' => now('Asia/Manila')->toDateString()]))->assertOk()->assertSee('Scheduled support sessions')->assertSee($case->seeker->generated_alias)->assertViewHas('scheduledSessions', fn ($rows) => $rows->total() === 1);
    }

    public function test_notifications_are_concise_and_emergencies_remain_individually_actionable(): void
    {
        $user = $this->moderator();
        Notification::create(['user_account_id' => $user->id, 'title' => 'New Emergency Alert', 'message' => 'An emergency requires attention. Sensitive extended narrative must not appear in the summary.', 'notification_type' => 'emergency', 'link' => '/moderator/emergency']);
        foreach (range(1, 2) as $i) {
            Notification::create(['user_account_id' => $user->id, 'title' => 'Queue updated', 'message' => 'Waiting requests need attention.', 'notification_type' => 'system', 'link' => '/moderator/queue']);
        }
        $this->actingAs($user)->get(route('moderator.notifications'))->assertOk()->assertSee('2 updates: Queue updated')->assertSee('New Emergency Alert')->assertSee('An emergency requires attention.')->assertDontSee('Sensitive extended narrative')->assertSee('moderator-emergency-notification')->assertSee('PHT');
    }
}
