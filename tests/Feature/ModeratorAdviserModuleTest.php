<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\CalendarEvent;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorAdviserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_assignment_requires_current_ready_helper_unless_emergency_override(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        [$seeker] = $this->seeker();

        $queue = QueueRequest::create([
            'seeker_id' => $seeker->id,
            'request_status' => 'waiting',
            'priority_level' => 'low',
            'preferred_session_type' => 'chat',
        ]);

        Session::create([
            'seeker_id' => $seeker->id,
            'session_status' => Session::STATUS_WAITING,
            'session_type' => 'chat',
            'risk_level' => 'low',
            'created_date' => now(),
        ]);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id,
            'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'));

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting']);

        $queue->update(['priority_level' => 'emergency']);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id,
            'helper_id' => $helper->id,
            'emergency_override' => '1',
        ])->assertRedirect(route('moderator.queue'));

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'assigned', 'assigned_helper_id' => $helper->id]);
    }

    public function test_moderator_can_create_duty_schedule_and_conflicts_are_rejected(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();

        $payload = [
            'helper_id' => $helper->id,
            'event_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'description' => 'Morning queue coverage',
        ];

        $this->actingAs($moderatorUser)->post(route('moderator.schedules.store'), $payload)
            ->assertRedirect(route('moderator.schedules', ['date' => $payload['event_date']]));

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Duty: ' . $helper->full_name,
            'event_type' => CalendarEvent::TYPE_MEETING,
        ]);

        $this->actingAs($moderatorUser)->from(route('moderator.schedules'))->post(route('moderator.schedules.store'), $payload)
            ->assertRedirect(route('moderator.schedules'))
            ->assertSessionHas('error');
    }

    public function test_adviser_session_supervision_loads_report_and_blocks_other_advisers(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('one@example.com');
        [$otherUser] = $this->adviserUser('two@example.com');
        $helper = $this->helper($adviser);
        [$seeker] = $this->seeker();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_status' => Session::STATUS_ACTIVE,
            'session_type' => 'chat',
            'risk_level' => 'high',
            'created_date' => now(),
        ]);
        SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Needs review']);

        $this->actingAs($adviserUser)->get(route('adviser.session.show', $session->id))->assertOk();
        $this->actingAs($otherUser)->get(route('adviser.session.show', $session->id))->assertForbidden();
    }

    public function test_adviser_referrals_are_scoped_and_other_adviser_cannot_manage_them(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('scope-one@example.com');
        [$otherUser, $otherAdviser] = $this->adviserUser('scope-two@example.com');
        $ownReferral = $this->referralFor($adviser, 'Own referral');
        $otherReferral = $this->referralFor($otherAdviser, 'Other referral');

        $this->actingAs($adviserUser)->get(route('adviser.referrals'))
            ->assertOk()
            ->assertSee('Own referral')
            ->assertDontSee('Other referral');

        $this->actingAs($otherUser)->post(route('adviser.referral.approve', $ownReferral->id), [])
            ->assertForbidden();

        $this->actingAs($adviserUser)->post(route('adviser.referral.reject', $ownReferral->id), [
            'rejection_reason' => 'Not clinically indicated yet.',
        ])->assertRedirect(route('adviser.referrals'));

        $this->assertDatabaseHas('referrals', [
            'id' => $ownReferral->id,
            'status' => Referral::STATUS_DECLINED,
            'decline_reason' => 'Not clinically indicated yet.',
        ]);
        $this->assertDatabaseHas('referrals', ['id' => $otherReferral->id, 'status' => Referral::STATUS_PENDING_ADVISER]);
    }

    public function test_adviser_emergency_resolution_is_scoped_to_supervised_helpers(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('emergency-one@example.com');
        [$otherUser] = $this->adviserUser('emergency-two@example.com');
        $referral = $this->referralFor($adviser, 'Emergency referral', 'emergency');

        $alert = EmergencyAlert::create([
            'seeker_id' => $referral->session->seeker_id,
            'session_id' => $referral->session_id,
            'adviser_id' => $adviser->id,
            'alert_type' => 'screening',
            'risk_level' => 'emergency',
            'triggered_at' => now(),
            'trigger_reason' => 'Immediate threat identified.',
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser)->post(route('adviser.emergencies.resolve', $alert->id), [
            'resolution_notes' => 'Resolved by wrong adviser.',
        ])->assertForbidden();

        $this->actingAs($adviserUser)->post(route('adviser.emergencies.resolve', $alert->id), [
            'resolution_notes' => 'Coordinated with crisis response and professional.',
        ])->assertRedirect(route('adviser.emergencies'));

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolution_notes' => 'Coordinated with crisis response and professional.',
        ]);
    }

    public function test_adviser_helper_reassignment_is_rejected(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('transfer-one@example.com');
        [, $newAdviser] = $this->adviserUser('transfer-two@example.com');
        $referral = $this->referralFor($adviser, 'Transfer referral');

        $this->actingAs($adviserUser)->post(route('adviser.helpers.reassign'), [
            'helper_ids' => [$referral->helper_id],
            'adviser_id' => $newAdviser->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('helpers', ['id' => $referral->helper_id, 'adviser_id' => $adviser->id]);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'adviser_id' => $adviser->id]);
    }

    private function moderatorUser(): array
    {
        $user = User::factory()->create(['role' => 'moderator']);
        $moderator = Moderator::create([
            'user_account_id' => $user->id,
            'first_name' => 'Mara',
            'last_name' => 'Moderator',
            'email' => $user->email,
        ]);

        return [$user, $moderator];
    }

    private function adviserUser(string $email): array
    {
        $user = User::factory()->create(['role' => 'adviser', 'email' => $email]);
        $adviser = Adviser::create([
            'user_account_id' => $user->id,
            'first_name' => 'Ada',
            'last_name' => 'Adviser',
            'email' => $email,
        ]);

        return [$user, $adviser];
    }

    private function helper(?Adviser $adviser = null): Helper
    {
        $user = User::factory()->create(['role' => 'helper']);

        return Helper::create([
            'user_account_id' => $user->id,
            'adviser_id' => $adviser?->id,
            'first_name' => 'Helen',
            'last_name' => 'Helper' . $user->id,
            'email' => $user->email,
            'status' => 'available',
            'competency_level' => 3,
            'max_concurrent_sessions' => 2,
        ]);
    }

    private function seeker(): array
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'Seeker' . $user->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);

        return [$seeker, $user];
    }

    private function referralFor(Adviser $adviser, string $reason, string $priority = 'high'): Referral
    {
        $helper = $this->helper($adviser);
        [$seeker] = $this->seeker();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_status' => Session::STATUS_ACTIVE,
            'session_type' => 'chat',
            'risk_level' => $priority,
            'created_date' => now(),
        ]);

        return Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'priority_level' => $priority,
            'referral_reason' => $reason,
            'status' => Referral::STATUS_PENDING_ADVISER,
        ]);
    }
}
