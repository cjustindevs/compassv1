<?php

namespace Tests\Feature;

use App\Models\{Adviser, AuditLog, EmergencyAlert, HelpSeeker, Helper, Notification, Session, User};
use App\Services\{EmergencyEscalationService, EmergencyNotificationPresentation, EmergencyReviewReminders, EmergencyStaffNotifications, NotificationPopupDelivery};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmergencyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
        User::factory()->count(3)->create(['role' => 'seeker']); // Profile IDs must not be treated as account IDs.
    }

    private function adviser(string $name, bool $active = true): User
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => $active]);
        Adviser::create(['user_account_id' => $user->id, 'first_name' => $name, 'last_name' => 'Reviewer', 'email' => $user->email]);
        return $user;
    }

    private function supportCase(User $supervisor, ?User $reviewer = null): Session
    {
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'adviser_id' => $supervisor->adviser->id,
            'first_name' => 'Gab', 'last_name' => 'Helper', 'email' => $helperUser->email]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'OwnershipFixture'.$seekerUser->id]);
        return Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id,
            'review_adviser_id' => $reviewer?->adviser->id, 'risk_level' => 'high', 'session_type' => 'chat',
            'session_status' => 'active', 'helper_accepted_at' => now(), 'start_time' => now(), 'created_date' => now()]);
    }

    private function alert(Session $session, User $owner): EmergencyAlert
    {
        return EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id,
            'adviser_id' => $owner->adviser->id, 'status' => 'notified', 'trigger_reason' => 'Private fixture narrative.', 'triggered_at' => now()]);
    }

    public function test_new_case_prefers_helper_supervisor_and_keeps_separate_screening_reviewer(): void
    {
        $supervisor = $this->adviser('Camille'); $reviewer = $this->adviser('Carl');
        $session = $this->supportCase($supervisor, $reviewer);
        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $session->seeker, ['preserve_classification' => true]);
        $this->assertSame($supervisor->adviser->id, $alert->adviser_id);
        $this->assertSame($reviewer->adviser->id, $session->fresh()->review_adviser_id);
        $this->assertDatabaseHas('notifications', ['emergency_alert_id' => $alert->id, 'user_account_id' => $supervisor->id, 'title' => 'Emergency assigned to you']);
        $this->assertDatabaseHas('notifications', ['emergency_alert_id' => $alert->id, 'user_account_id' => $reviewer->id, 'title' => 'Shared emergency alert']);
        $this->assertTrue($alert->adviser_notified);
        $this->assertNull($alert->acknowledged_at);
    }

    public function test_existing_coordinator_supervisor_and_shared_recipient_have_distinct_labels_and_access(): void
    {
        $owner = $this->adviser('Camille'); $supervisor = $this->adviser('James'); $other = $this->adviser('Carl');
        $session = $this->supportCase($supervisor); $alert = $this->alert($session, $owner);
        app(EmergencyStaffNotifications::class)->deliver($alert);
        foreach ([[$owner, 'Emergency assigned to you'], [$supervisor, 'Emergency supervision update'], [$other, 'Shared emergency alert']] as [$user, $title]) {
            $notice = Notification::where('emergency_alert_id', $alert->id)->where('user_account_id', $user->id)->sole();
            $this->assertSame($title, $notice->title);
            $this->assertStringContainsString('Coordinator: Camille Reviewer', $notice->message);
            $popup = $this->actingAs($user)->getJson(route('emergency-notice.next'))->assertOk();
            $this->assertSame($title, $popup->json('notice.title'));
            $this->get(route('adviser.notifications'))->assertOk()->assertSee($title)->assertSee('Coordinator: Camille Reviewer');
            $this->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('recent.0.title', $title);
        }
        $this->get(route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id]))->assertOk()
            ->assertSee('Shared coordination view')->assertDontSee('Open authorized Adviser review');
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertDontSee('Private fixture narrative.');
        $this->assertSame($owner->adviser->id, $alert->fresh()->adviser_id);
        $this->actingAs($owner)->get(route('adviser.session.show', $session))->assertOk();
    }

    public function test_reconciliation_deduplicates_each_case_without_losing_moderator_alerts(): void
    {
        $owner = $this->adviser('Camille'); $moderator = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $first = $this->alert($this->supportCase($owner), $owner);
        $second = $this->alert($this->supportCase($owner), $owner);
        $service = app(EmergencyStaffNotifications::class);
        $service->reconcileOpen(); $count = Notification::count(); $service->reconcileOpen();
        $this->assertSame($count, Notification::count());
        $this->assertSame(2, Notification::where('user_account_id', $moderator->id)->count());
        $this->assertSame(2, Notification::where('user_account_id', $owner->id)->count());
    }

    public function test_inactive_coordinator_fallback_is_recorded_and_does_not_rewrite_acknowledgment(): void
    {
        $old = $this->adviser('Unavailable', false); $supervisor = $this->adviser('James');
        $alert = $this->alert($this->supportCase($supervisor, $old), $old);
        $at = now()->subMinutes(10); $alert->forceFill(['acknowledged_at' => $at, 'acknowledged_by' => $old->id])->save();
        app(EmergencyStaffNotifications::class)->deliver($alert);
        $this->assertSame($supervisor->adviser->id, $alert->fresh()->adviser_id);
        $this->assertTrue($alert->fresh()->acknowledged_at->equalTo($at));
        $this->assertDatabaseHas('audit_logs', ['action' => 'emergency_coordinator_transferred', 'target_id' => $alert->id]);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $old->id]);
    }

    public function test_handoff_notifies_new_owner_once_preserves_history_and_quiets_old_assignment(): void
    {
        $owner = $this->adviser('Camille'); $next = $this->adviser('James'); $other = $this->adviser('Carl');
        $alert = $this->alert($this->supportCase($owner, $owner), $owner);
        $at = now()->subMinutes(20); $alert->forceFill(['acknowledged_at' => $at, 'acknowledged_by' => $owner->id])->save();
        app(EmergencyStaffNotifications::class)->deliver($alert);
        $oldNotice = Notification::where('user_account_id', $owner->id)->where('title', 'Emergency assigned to you')->sole();
        $payload = ['adviser_id' => $next->adviser->id, 'expected_adviser_id' => $owner->adviser->id, 'handoff_reason' => 'Coordinator handoff for continued coverage.'];
        $this->actingAs($other)->post(route('adviser.emergencies.coordinator', $alert), $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('adviser.emergencies.coordinator', $alert), $payload)->assertRedirect();
        $this->assertSame($next->adviser->id, $alert->fresh()->adviser_id);
        $this->assertTrue($alert->fresh()->acknowledged_at->equalTo($at));
        $this->assertSame($owner->id, $alert->fresh()->acknowledged_by);
        $this->assertFalse(app(NotificationPopupDelivery::class)->actionable($oldNotice, $owner));
        $copy = app(EmergencyNotificationPresentation::class)->present($oldNotice, $owner);
        $this->assertStringContainsString('Current coordinator: James Reviewer', $copy['message']);
        $this->assertSame('Emergency assigned to you', $oldNotice->fresh()->title);
        $this->assertDatabaseHas('emergency_review_actions', ['emergency_alert_id' => $alert->id, 'action' => 'coordinator_changed']);
        $count = Notification::count(); app(EmergencyStaffNotifications::class)->reconcileOpen();
        $this->assertSame($count, Notification::count());
        $this->actingAs($next)->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Private fixture narrative.');
        $this->get(route('adviser.session.show', $alert->session_id))->assertOk();
        $this->actingAs($owner)->post(route('adviser.emergencies.coordinator', $alert), array_replace($payload, ['adviser_id' => $other->adviser->id]))->assertStatus(409);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAs($next)->post(route('adviser.emergencies.coordinator', $alert), array_replace($payload, ['adviser_id' => $owner->adviser->id, 'expected_adviser_id' => $next->adviser->id]))->assertStatus(409);
    }

    public function test_follow_up_targets_coordinator_supervisor_and_moderator_instead_of_other_advisers(): void
    {
        $owner = $this->adviser('Camille'); $supervisor = $this->adviser('James'); $other = $this->adviser('Carl');
        $mod = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $alert = $this->alert($this->supportCase($supervisor), $owner);
        $this->travel(6)->minutes(); config(['emergency.reminder_minutes' => 5]);
        app(EmergencyReviewReminders::class)->run();
        foreach ([$owner, $supervisor, $mod] as $user) $this->assertDatabaseHas('notifications', ['user_account_id' => $user->id, 'title' => 'Emergency acknowledgment pending']);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $other->id, 'title' => 'Emergency acknowledgment pending']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $other->id, 'title' => 'Shared emergency alert']);
    }

    public function test_handoff_rejects_inactive_targets_without_changing_the_recorded_owner(): void
    {
        $owner = $this->adviser('Camille'); $inactive = $this->adviser('Unavailable', false);
        $alert = $this->alert($this->supportCase($owner), $owner);
        $this->actingAs($owner)->post(route('adviser.emergencies.coordinator', $alert), ['adviser_id' => $inactive->adviser->id,
            'expected_adviser_id' => $owner->adviser->id, 'handoff_reason' => 'Attempt to transfer to an inactive account.'])->assertNotFound();
        $this->assertSame($owner->adviser->id, $alert->fresh()->adviser_id);
        $this->assertDatabaseMissing('audit_logs', ['target_type' => 'emergency_alerts', 'target_id' => $alert->id, 'action' => 'emergency_coordinator_transferred']);
    }

    public function test_previous_coordinator_without_supervision_no_longer_receives_actionable_follow_up(): void
    {
        $owner = $this->adviser('Camille'); $supervisor = $this->adviser('James'); $next = $this->adviser('Carl');
        $alert = $this->alert($this->supportCase($supervisor), $owner);
        $notice = Notification::create(['emergency_alert_id' => $alert->id, 'user_account_id' => $owner->id,
            'title' => 'Emergency acknowledgment pending', 'message' => 'Coordinator: Camille Reviewer. Review the case.',
            'notification_type' => 'emergency', 'popup_event_key' => 'emergency:alert:'.$alert->id,
            'link' => route('adviser.emergencies.show', $alert->id, false)]);
        $this->actingAs($owner)->post(route('adviser.emergencies.coordinator', $alert), ['adviser_id' => $next->adviser->id,
            'expected_adviser_id' => $owner->adviser->id, 'handoff_reason' => 'Coverage transferred to the next coordinator.'])->assertRedirect();
        $this->assertFalse(app(NotificationPopupDelivery::class)->actionable($notice, $owner));
        $this->assertSame('Emergency coordinator updated', app(EmergencyNotificationPresentation::class)->present($notice, $owner)['title']);
    }

    public function test_fresh_report_during_coordinator_recovery_is_not_replayed_by_reconciliation(): void
    {
        $old = $this->adviser('Unavailable', false); $next = $this->adviser('James');
        $alert = $this->alert($this->supportCase($next), $old);
        app(EmergencyStaffNotifications::class)->deliver($alert, true);
        $count = Notification::count();
        app(EmergencyStaffNotifications::class)->reconcileOpen();
        $this->assertSame($count, Notification::count());
        $this->assertDatabaseMissing('emergency_review_actions', ['emergency_alert_id' => $alert->id]);
        $audit = AuditLog::where('target_type', 'emergency_alerts')->where('target_id', $alert->id)->where('action', 'emergency_coordinator_transferred')->sole();
        $this->assertSame('automatic', $audit->metadata['assignment_mode']);
    }
}
