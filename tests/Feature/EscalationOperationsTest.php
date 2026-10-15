<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\HelperReadinessService;
use App\Services\UnassignedRequestReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class EscalationOperationsTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    private function setupCase(): array
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-15 20:00', 'Asia/Manila')->utc());
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'email' => $helperUser->email, 'first_name' => 'Ready', 'last_name' => 'Helper', 'competency_level' => 3, 'competency_risk_level' => 3, 'status' => 'available', 'availability' => 'available']);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'shift_start' => '18:00', 'shift_end' => '23:00', 'created_by' => $helperUser->id, 'is_active' => true]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'DemoSeeker']);
        $this->consentFixture($seekerUser);
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'emergency', 'request_date' => now()->subMinutes(16)]);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => 'waiting', 'risk_level' => 'emergency', 'requires_immediate_action' => true, 'submitted_at' => now(), 'created_date' => now()]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $seeker->id, 'adviser_id' => $helper->adviser_id, 'trigger_reason' => 'Private screening text']);
        $mod = User::factory()->create(['role' => 'moderator']);

        return [$mod, $helper, $queue, $session, $alert];
    }

    public function test_moderator_assigns_only_an_eligible_helper_and_cannot_duplicate_assignment(): void
    {
        [$mod, $helper, $queue, $session, $alert] = $this->setupCase();
        $helper->update(['competency_level' => 4, 'competency_risk_level' => 4]);
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHours(2), 'assessment_result' => 'ready', 'is_active' => true]);
        $route = ['emergency_alerts', $alert->id];
        $this->actingAs($mod)->get(route('moderator.emergency.coordinate', $route))->assertOk()->assertSee('Assign Helper')->assertSee($helper->full_name)->assertDontSee('Private screening text');
        $this->post(route('moderator.emergency.assign', $route), ['helper_id' => $helper->id])->assertSessionHasNoErrors();
        $this->assertSame($helper->id, $session->fresh()->helper_id);
        $this->assertSame('assigned', $queue->fresh()->request_status);
        $this->post(route('moderator.emergency.assign', $route), ['helper_id' => $helper->id])->assertSessionHasErrors('helper_id');
        $this->assertSame(1, $helper->activeSessions()->count());
        $this->actingAs($helper->user)->get(route('moderator.emergency.coordinate', $route))->assertForbidden();
    }

    public function test_no_helper_escalation_and_fifteen_minute_reminder_are_idempotent_and_completed_tab_is_separate(): void
    {
        [$mod, $helper, $queue, $session, $alert] = $this->setupCase();
        $route = ['emergency_alerts', $alert->id];
        $this->actingAs($mod)->get(route('moderator.emergency.coordinate', $route))->assertOk()->assertSee('Request Adviser Escalation');
        $this->post(route('moderator.emergency.escalation', $route))->assertSessionHasNoErrors();
        $count = Notification::count();
        $this->post(route('moderator.emergency.escalation', $route))->assertSessionHasNoErrors();
        $this->assertSame($count, Notification::count());
        app(UnassignedRequestReminders::class)->run();
        $count = Notification::count();
        app(UnassignedRequestReminders::class)->run();
        $this->assertSame($count, Notification::count());
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->get(route('moderator.emergency'))->assertOk()->assertSee('No active emergency cases');
        $this->get(route('moderator.emergency', ['tab' => 'completed']))->assertOk()->assertSee('Completed Emergency Cases')->assertSee('Emergency #'.$alert->id);
        $this->post(route('moderator.emergency.assign', $route), ['helper_id' => $helper->id])->assertConflict();
    }

    public function test_failed_readiness_cannot_be_replaced_before_thirty_minutes_even_across_refresh(): void
    {
        [$mod, $helper, $queue] = $this->setupCase();
        $queue->update(['request_status' => 'cancelled']);
        $data = ['readiness_form_version' => HelperReadinessService::GROUPED_VERSION, 'listener_skills_ready' => true, 'emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'high', 'availability_status' => 'available', 'exercise_completed' => 'skipped'];
        $this->actingAs($helper->user)->post(route('helper.readiness.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame('unavailable', $helper->fresh()->availability);
        $data['stress_level'] = 'low';
        $this->travel(29)->minutes();
        $this->post(route('helper.readiness.store'), $data)->assertSessionHasErrors('readiness');
        $this->get(route('helper.readiness', ['refresh' => 1]))->assertOk()->assertSee('Wait 30 minutes');
        $this->assertDatabaseCount('readiness_checks', 1);
        $this->travel(1)->minutes();
        $this->post(route('helper.readiness.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('readiness_checks', 2);
    }

    public function test_adviser_scheduling_shares_moderator_ui_but_cannot_edit_another_advisers_helper(): void
    {
        [$mod, $helper] = $this->setupCase();
        $this->actingAs($helper->adviser->user)->get(route('adviser.schedule'))->assertOk()->assertSee('Duty Scheduling')->assertSee('Support Sessions')->assertSee('Helper Workload')->assertSee($helper->full_name);
        $this->getJson(route('adviser.schedule.workload'))->assertOk()->assertJsonCount(1, 'helpers')->assertJsonPath('helpers.0.id', $helper->id);
        $other = User::factory()->create(['role' => 'helper']);
        $otherHelper = Helper::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Helper', 'email' => $other->email]);
        $this->verifiedHelperFixture($otherHelper);
        $this->post(route('adviser.schedule.update'), ['helper_id' => $otherHelper->id, 'event_date' => now()->addDay()->toDateString()])->assertNotFound();
    }

    public function test_professional_transfer_keeps_owner_until_acceptance_then_revokes_old_access(): void
    {
        [$mod, $helper, $queue, $session, $alert] = $this->setupCase();
        $professionals = collect(range(1, 3))->map(function ($number) {
            $user = User::factory()->create(['role' => 'professional']);

            return PsychologyProfessional::create(['user_account_id' => $user->id, 'first_name' => 'Professional', 'last_name' => (string) $number, 'email' => $user->email, 'is_available' => true]);
        });
        [$old, $next, $unrelated] = $professionals->all();
        $session->update(['helper_id' => $helper->id, 'session_status' => 'active', 'start_time' => now(), 'helper_accepted_at' => now()]);
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id, 'adviser_id' => $helper->adviser_id, 'professional_id' => $old->id,
            'status' => 'in_progress', 'approved_at' => now(), 'help_seeker_consent' => true, 'consent_obtained_at' => now(), 'identity_disclosed' => true,
            'priority_level' => 'emergency', 'referral_reason' => 'Synthetic support recommendation.', 'referral_date' => now()]);
        ConsentRecord::create(['seeker_id' => $session->seeker_id, 'referral_id' => $referral->id, 'document_type' => 'informed_consent', 'purpose' => 'referral', 'version' => ConsentService::VERSION, 'decision' => 'accepted', 'consent_given' => true, 'consent_date' => now()]);
        $this->actingAs($helper->adviser->user)->post(route('adviser.referral.transfer', $referral), ['professional_id' => $next->id, 'reason' => 'Original Professional is unavailable.'])->assertRedirect()->assertSessionHasNoErrors();
        $transfer = DB::table('referral_professional_transfers')->sole();
        $this->assertSame($old->id, $referral->fresh()->professional_id);
        $this->actingAs($unrelated->user)->get(route('professional.transfer.show', $transfer->id))->assertNotFound();
        $this->actingAs($next->user)->get(route('professional.transfer.show', $transfer->id))->assertOk()->assertDontSee('Synthetic support recommendation');
        $this->post(route('professional.transfer.respond', $transfer->id), ['decision' => 'accept'])->assertRedirect('/professional/referral/'.$referral->id);
        $this->assertSame($next->id, $referral->fresh()->professional_id);
        $this->assertFalse($referral->fresh()->identity_disclosed);
        $this->actingAs($old->user)->get(route('professional.referral.show', $referral->id))->assertNotFound();
        $this->actingAs($next->user)->post(route('professional.transfer.respond', $transfer->id), ['decision' => 'accept'])->assertConflict();
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->adviser->user_account_id, 'title' => 'Professional transfer accepted']);
    }

    public function test_duty_edit_refuses_a_stale_form_and_preserves_the_newer_change(): void
    {
        [$mod, $helper] = $this->setupCase();
        $shift = $helper->schedules()->firstOrFail();
        $oldUpdatedAt = $shift->updated_at->toISOString();
        $this->travel(1)->minutes();
        $shift->update(['duty_notes' => 'Newer saved instructions']);
        $this->actingAs($mod)->post(route('moderator.schedules.store'), ['helper_id' => $helper->id, 'shift_id' => $shift->id,
            'event_date' => now('Asia/Manila')->toDateString(), 'shift_start' => '18:00', 'shift_end' => '23:00',
            'expected_updated_at' => $oldUpdatedAt, 'description' => 'Stale instructions'])->assertSessionHasErrors('shift_id');
        $this->assertSame('Newer saved instructions', $shift->fresh()->duty_notes);
    }

    public function test_completed_emergency_history_remains_readable_when_the_session_has_a_new_incident(): void
    {
        [$mod, $helper, $queue, $session, $old] = $this->setupCase();
        $old->update(['status' => 'resolved', 'resolved_at' => now()]);
        $new = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $helper->adviser_id, 'trigger_reason' => 'New private report']);
        $this->actingAs($mod)->get(route('moderator.emergency', ['tab' => 'completed']))->assertOk()->assertSee('Emergency #'.$old->id)->assertDontSee('Emergency #'.$new->id);
        $this->get(route('moderator.emergency.coordinate', ['emergency_alerts', $old->id]))->assertOk()->assertSee('has concluded')->assertDontSee('New private report');
        $this->post(route('moderator.emergency.assign', ['emergency_alerts', $old->id]), ['helper_id' => $helper->id])->assertNotFound();
    }

    public function test_adviser_response_kpi_uses_recorded_actions_and_notification_importance_is_explicit(): void
    {
        [$mod, $helper, $queue, $session, $alert] = $this->setupCase();
        $alert->forceFill(['triggered_at' => now()->subMinutes(10), 'acknowledged_at' => now()->subMinutes(5)])->save();
        $user = $helper->adviser->user;
        $this->actingAs($user)->get(route('adviser.dashboard', ['period' => 'today']))->assertOk()
            ->assertViewHas('overview', fn ($overview) => collect($overview['cards'])->firstWhere('label', 'Average Adviser Response Time')['value'] === '5 min')
            ->assertSee('1 cases measured');
        Notification::create(['user_account_id' => $user->id, 'title' => 'Referral needs review', 'message' => 'Review requested', 'notification_type' => 'referral', 'link' => '/adviser/referrals']);
        Notification::create(['user_account_id' => $user->id, 'title' => 'Routine update', 'message' => 'Saved', 'notification_type' => 'update']);
        $data = $this->getJson(route('notifications.feed'))->assertOk()->json('fresh');
        $this->assertTrue(collect($data)->firstWhere('title', 'Referral needs review')['important']);
        $this->assertFalse(collect($data)->firstWhere('title', 'Routine update')['important']);
    }
}
