<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Services\EmergencyReviewReminders;
use App\Services\EmergencyStaffNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EmergencyResolutionSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->withoutVite();
        $mod = User::factory()->create(['role' => 'moderator']);
        $user = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Review', 'last_name' => 'Owner', 'email' => $user->email]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'VisibleDemoAlias']);
        $session = Session::create(['seeker_id' => $seeker->id, 'review_adviser_id' => $adviser->id, 'session_status' => 'active', 'risk_level' => 'emergency', 'created_date' => now()]);
        $alert = EmergencyAlert::create(['seeker_id' => $seeker->id, 'session_id' => $session->id, 'adviser_id' => $adviser->id, 'trigger_reason' => 'Private assessment must stay private.']);
        app(EmergencyStaffNotifications::class)->deliver($alert);

        return [$mod, $user, $session, $alert];
    }

    public function test_adviser_resolution_clears_moderator_popup_list_and_counts_but_keeps_history_and_chat(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        $notice = Notification::where('user_account_id', $mod->id)->sole();
        $this->assertSame($alert->id, $notice->emergency_alert_id);
        $popup = $this->actingAs($mod)->getJson(route('emergency-notice.next'))->assertOk()->assertJsonPath('notice.id', $notice->id);
        $this->getJson(route('notifications.feed'))->assertJsonPath('workflow.emergency_count', 1)->assertJsonPath('fresh.0.urgent', true);
        $before = $this->getJson(route('moderator.emergency.stats'))->assertJsonPath('open', 1)->json('signature');
        $this->actingAs($adviser)->post(route('adviser.emergencies.resolve', $alert), ['resolution_notes' => 'Documented emergency resolution and support follow-up.'])->assertSessionHasNoErrors();
        $this->actingAs($mod)->getJson(route('emergency-notice.next', ['current' => $popup->json('notice.reference')]))->assertJsonPath('notice', null);
        $feed = $this->getJson(route('notifications.feed', ['after' => 0]))->assertJsonPath('workflow.emergency_count', 0)->assertJsonPath('fresh.0.urgent', false)->assertJsonCount(0, 'workflow.active_emergency_notice_ids');
        $this->assertSame($notice->id, $feed->json('recent.0.id'));
        $after = $this->getJson(route('moderator.emergency.stats'))->assertJsonPath('open', 0)->assertHeader('Cache-Control', 'no-store, private')->json('signature');
        $this->assertNotSame($before, $after);
        $dashboard = $this->getJson(route('moderator.dashboard.stats'))->assertOk()->assertJsonPath('emergency_open', 0)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('Active emergencies', $dashboard->json('overview_html'));
        $this->assertStringNotContainsString('Private assessment', $dashboard->json('overview_html'));
        $this->get(route('moderator.dashboard'))->assertOk()->assertSee('js/moderator-emergency.js')->assertDontSee('window.location.reload()', false);
        $this->get(route('moderator.emergency'))->assertOk()->assertSee('No active emergency cases')->assertDontSee('Private assessment');
        $fragment = $this->getJson(route('moderator.emergency'))->assertOk()->json('html');
        $this->assertStringContainsString('No active emergency cases', $fragment);
        $this->assertStringNotContainsString('<script', $fragment);
        $this->assertStringNotContainsString('Private assessment', $fragment);
        $this->assertDatabaseHas('notifications', ['id' => $notice->id, 'status' => 'unread']);
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertSame('emergency', $session->fresh()->risk_level);
        $count = Notification::count();
        config(['emergency.reminder_minutes' => 1]);
        $this->travel(10)->minutes();
        app(EmergencyReviewReminders::class)->run();
        $this->assertSame($count, Notification::count());
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
    }

    public function test_unknown_legacy_notices_are_history_only_and_older_precisely_linked_notices_follow_current_status(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        foreach ([['link' => '/moderator/emergency', 'message' => 'An emergency needs attention.'],
            ['link' => '/moderator/emergency', 'message' => 'Emergency #'.$alert->id.' requires staff attention.'],
            ['link' => '/adviser/emergencies/'.$alert->id, 'message' => 'Old precise case notice.']] as $data) {
            Notification::create($data + ['user_account_id' => $mod->id, 'notification_type' => 'emergency', 'title' => 'Historical emergency']);
        }
        $this->actingAs($mod)->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $feed = $this->getJson(route('notifications.feed', ['after' => 0]))->assertOk();
        $this->assertSame([false], array_values(array_unique(array_column($feed->json('fresh'), 'urgent'))));
        $this->assertCount(4, $feed->json('recent'));
    }

    public function test_resolving_one_case_keeps_another_active_and_a_new_emergency_in_same_session_is_delivered(): void
    {
        [$mod, $adviser, $session, $old] = $this->fixture();
        $otherSession = Session::create(['seeker_id' => $session->seeker_id, 'session_status' => 'active', 'created_date' => now()]);
        $other = EmergencyAlert::create(['session_id' => $otherSession->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $old->adviser_id, 'trigger_reason' => 'Other active case']);
        app(EmergencyStaffNotifications::class)->deliver($other);
        $otherNotice = Notification::where('user_account_id', $mod->id)->where('emergency_alert_id', $other->id)->sole();
        $this->actingAs($mod)->postJson(route('emergency-notice.dismiss'), ['reference' => Crypt::encryptString((string) $otherNotice->id)])->assertNoContent();
        $old->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->getJson(route('notifications.feed'))->assertJsonPath('workflow.emergency_count', 1);
        $new = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $old->adviser_id, 'trigger_reason' => 'Distinct new report']);
        app(EmergencyStaffNotifications::class)->deliver($new);
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice.case_key', 'alert:'.$new->id);
        $this->getJson(route('notifications.feed'))->assertJsonPath('workflow.emergency_count', 2);
        $this->get(route('moderator.emergency'))->assertViewHas('openIncidents', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $other->id]);
    }

    public function test_dismissal_suppresses_existing_duplicates_but_not_new_reports_and_does_not_mark_read(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        app(EmergencyStaffNotifications::class)->deliver($alert, true);
        $popup = $this->actingAs($mod)->getJson(route('emergency-notice.next'))->assertOk();
        $this->postJson(route('emergency-notice.dismiss'), ['reference' => $popup->json('notice.reference')])->assertNoContent();
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $this->assertSame(2, Notification::where('user_account_id', $mod->id)->whereNotNull('popup_dismissed_at')->unread()->count());
        app(EmergencyStaffNotifications::class)->deliver($alert, true);
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice.case_key', 'alert:'.$alert->id);
        $other = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($other)->postJson(route('emergency-notice.dismiss'), ['reference' => $popup->json('notice.reference')])->assertNotFound();
        $this->getJson(route('emergency-notice.next', ['current' => $popup->json('notice.reference')]))->assertJsonPath('notice', null);
        $badId = \Illuminate\Support\Facades\Crypt::encryptString('invalid-notice-id');
        $this->getJson(route('emergency-notice.next', ['current' => $badId]))->assertOk()->assertJsonPath('notice', null);
        $this->postJson(route('emergency-notice.dismiss'), ['reference' => $badId])->assertNotFound();
    }

    public function test_repair_only_changes_confirmed_stale_mirrors_and_is_idempotent(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        $alert->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => 'Authoritative resolution.']);
        $stale = IncidentReport::forceCreate(['session_id' => $session->id, 'user_account_id' => $mod->id, 'emergency_alert_id' => $alert->id, 'incident_category' => 'emergency_flag', 'status' => 'open', 'description' => 'Original retained narrative', 'resolution_summary' => 'Existing retained notes']);
        $unrelated = IncidentReport::create(['session_id' => $session->id, 'user_account_id' => $mod->id, 'incident_category' => 'other', 'status' => 'open', 'description' => 'Unrelated issue']);
        $this->artisan('emergencies:reconcile-resolved')->expectsOutputToContain('Would reconcile 1')->assertSuccessful();
        $this->assertSame('open', $stale->fresh()->status);
        $this->artisan('emergencies:reconcile-resolved', ['--apply' => true])->expectsOutputToContain('Reconciled 1')->assertSuccessful();
        $this->assertSame('resolved', $stale->fresh()->status);
        $this->assertSame('Original retained narrative', $stale->fresh()->description);
        $this->assertSame('Existing retained notes', $stale->fresh()->resolution_summary);
        $this->assertSame('open', $unrelated->fresh()->status);
        $this->artisan('emergencies:reconcile-resolved', ['--apply' => true])->expectsOutputToContain('Reconciled 0')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'emergency_incident_reconciled')->count());
    }

    public function test_repair_does_not_close_ambiguous_incidents_when_a_new_emergency_is_open(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $new = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $alert->adviser_id, 'trigger_reason' => 'New emergency still open']);
        $ambiguous = IncidentReport::create(['session_id' => $session->id, 'user_account_id' => $mod->id, 'incident_category' => 'emergency_flag', 'description' => 'Unknown original case', 'status' => 'open']);
        $this->artisan('emergencies:reconcile-resolved', ['--apply' => true])->expectsOutputToContain('Reconciled 0')->assertSuccessful();
        $this->assertSame('open', $ambiguous->fresh()->status);
        $this->assertSame('pending', $new->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'emergency_incident_reconciled']);
    }

    public function test_archived_cases_cannot_trigger_a_popup_even_when_status_still_says_open(): void
    {
        [$mod, $adviser, $session, $alert] = $this->fixture();
        $alert->forceFill(['archived_at' => now()])->save();
        $this->actingAs($mod)->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $this->getJson(route('notifications.feed'))->assertJsonPath('workflow.emergency_count', 0)->assertJsonPath('fresh.0.urgent', false);
        $this->get(route('moderator.emergency'))->assertViewHas('openIncidents', fn ($rows) => $rows->isEmpty());
    }
}
