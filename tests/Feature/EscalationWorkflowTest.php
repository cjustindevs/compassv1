<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Helper;
use App\Models\IncidentReport;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\EmergencyEscalationService;
use App\Services\IncidentReportService;
use App\Services\ReferralManagementService;
use App\Services\RiskClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EscalationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_emergency_stays_pending_and_duplicate_submission_is_idempotent(): void
    {
        Event::fake();
        [$seeker, $session, $helper, $adviser] = $this->sessionWithAssignedHelper();
        $session->update(['helper_id' => null, 'review_adviser_id' => null]);
        $adviser->user->update(['is_active' => false]);
        $service = app(EmergencyEscalationService::class);
        $alert = $service->escalateEmergency($session->fresh(), $seeker);
        $count = Notification::count();
        $again = $service->escalateEmergency($session->fresh(), $seeker);
        $this->assertSame($alert->id, $again->id);
        $this->assertSame($count, Notification::count());
        $this->assertSame('pending', $alert->status);
        $this->assertFalse($alert->adviser_notified);
        $this->assertSame('no_active_adviser', $alert->notification_result);
        $this->assertNull($alert->resolved_at);
        $this->assertDatabaseMissing('referrals', ['session_id' => $session->id]);
    }

    public function test_emergency_support_requires_open_alert_and_preserves_stale_request(): void
    {
        Event::fake();
        [$seeker, $session, $helper] = $this->sessionWithAssignedHelper();
        $session->update(['helper_id'=>null, 'created_date'=>now()->subDays(2)]);
        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $seeker);
        $session->refresh();
        $this->assertTrue($session->permitsEmergencySupport());
        $this->assertSame('waiting', $session->session_status);
        $this->assertSame('waiting', $session->queue->request_status);
        $this->assertSame('emergency', $session->queue->priority_level);
        Session::whereKey($session->id)->markAbandoned();
        $this->assertSame('waiting', $session->fresh()->session_status);
        $alert->update(['status'=>'resolved']);
        $this->assertFalse($session->permitsEmergencySupport());
    }

    public function test_emergency_can_receive_temporary_helper_without_clearing_review(): void
    {
        Event::fake();
        config(['app.relax_duty_hours'=>true]);
        [$seeker, $session, $helper] = $this->sessionWithAssignedHelper();
        $session->update(['helper_id'=>null]);
        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $seeker);
        \App\Models\ReadinessCheck::create(['helper_id'=>$helper->id, 'assessment_date'=>now(),
            'valid_until'=>now()->addHour(), 'assessment_result'=>'ready', 'is_active'=>true]);
        $this->mock(\App\Services\ConsentService::class, function ($mock) {
            $mock->shouldReceive('valid')->andReturn(true);
        });
        $session->refresh();
        $assigned = app(\App\Services\HelperMatchingService::class)->manualAssign($session->queue, $helper->id);
        $this->assertInstanceOf(Session::class, $assigned, is_string($assigned) ? $assigned : 'Assignment failed');
        $this->assertTrue((bool) $assigned->requires_adviser_review);
        $this->assertSame('emergency', $assigned->risk_level);
        $this->assertNull($alert->fresh()->resolved_at);
        app(\App\Services\HelperWorkflowMaintenance::class)->releaseRecommendation($assigned, 'declined');
        $assigned->refresh();
        $this->assertNull($assigned->helper_id);
        $retry = app(\App\Services\HelperMatchingService::class)->manualAssign($assigned->queue->fresh(), $helper->id);
        $this->assertIsString($retry);
        $this->assertStringContainsString('already declined', $retry);
        $this->assertNull($alert->fresh()->resolved_at);
    }

    public function test_reminders_are_opt_in_deduplicated_and_stop_after_acknowledgment(): void
    {
        Event::fake();
        [$seeker, $session] = $this->sessionWithAssignedHelper();
        $moderator = User::factory()->create(['role'=>'moderator','is_active'=>true]);
        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $seeker);
        $service = app(\App\Services\EmergencyReviewReminders::class);
        $this->travel(6)->minutes();
        config(['emergency.reminder_minutes'=>0]);
        $before = Notification::count();
        $service->run();
        $this->assertSame($before, Notification::count());
        config(['emergency.reminder_minutes'=>5]);
        $service->run();
        $this->assertDatabaseHas('notifications',['user_account_id'=>$moderator->id,'title'=>'Emergency acknowledgment pending']);
        $after = Notification::count();
        $this->assertGreaterThan($before, $after);
        $service->run();
        $this->assertSame($after, Notification::count());
        $alert->forceFill(['acknowledged_at'=>now()])->save();
        $this->travel(6)->minutes();
        $service->run();
        $this->assertSame($after, Notification::count());
    }

    public function test_risk_classification_identifies_all_levels(): void
    {
        $service = app(RiskClassificationService::class);
        $base = array_fill_keys(array_keys(\App\Services\ScreeningInstrument::QUESTIONS),'no');

        $this->assertSame('low', $service->classifyRisk($base)['risk_level']);
        $this->assertSame('moderate', $service->classifyRisk(array_merge($base, ['difficulty_coping' => 'yes']))['risk_level']);
        $this->assertSame('high', $service->classifyRisk(array_merge($base, ['severe_distress' => 'yes']))['risk_level']);
        $this->assertSame('emergency', $service->classifyRisk(array_merge($base, ['immediate_intent' => 'yes']))['risk_level']);
    }

    public function test_emergency_escalation_flags_case_without_automatically_releasing_identity(): void
    {
        Event::fake();

        [$seeker, $session] = $this->sessionWithAssignedHelper();
        PsychologyProfessional::create($this->profileData('professional', 'pro@example.com'));
        \Illuminate\Support\Facades\DB::table('identity_vault')->insert(['seeker_id' => $seeker->id, 'real_name' => 'Test Seeker']);

        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $seeker, ['reason' => 'Immediate threat']);

        $this->assertDatabaseHas('emergency_alerts', ['id' => $alert->id, 'status' => 'notified', 'professional_referred' => false]);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'risk_level' => 'emergency', 'escalation_required' => true]);
        $this->assertDatabaseHas('help_seekers', ['id' => $seeker->id, 'current_risk_level' => 'emergency', 'has_emergency' => true]);
        $this->assertDatabaseHas('identity_vault', ['seeker_id' => $seeker->id, 'emergency_override' => false]);
        $this->assertSame(Session::STATUS_ACTIVE, $session->fresh()->session_status);
        $this->assertDatabaseMissing('referrals', ['session_id' => $session->id]);
        $this->assertGreaterThanOrEqual(2, Notification::where('notification_type', 'emergency')->count());
    }

    public function test_referral_workflow_handles_adviser_review_consent_professional_acceptance_and_completion(): void
    {
        Event::fake();

        [$seeker, $session, $helper, $adviser] = $this->sessionWithAssignedHelper();
        $professional = PsychologyProfessional::create($this->profileData('professional', 'pro@example.com'));
        $service = app(ReferralManagementService::class);

        $referral = $service->createReferral($session, $seeker, ['reason' => 'Needs professional intervention', 'urgency' => 'high']);
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);

        $this->actingAs($adviser->user);
        $referral = $service->reviewReferral($referral, $adviser, ['approved' => true, 'notes' => 'Proceed']);
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->status);

        $this->actingAs($seeker->user);
        $referral = $service->processConsent($referral, true);
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertFalse($referral->identity_disclosed);

        $this->assertNull($referral->professional_id);
        // This service test supplies the vault gateway result; real encrypted storage is tested in IdentityVaultTest.
        $this->mock(\App\Services\IdentityVaultService::class, function ($mock) { $mock->shouldReceive('hasCurrentSubmission')->once()->andReturn(true); });
        $referral = $service->forwardToProfessional($referral);
        $this->actingAs($professional->user);
        $referral = $service->acceptReferral($referral, $professional, ['starts_at'=>now('Asia/Manila')->addDay()->format('Y-m-d\TH:i'),'ends_at'=>now('Asia/Manila')->addDay()->addHour()->format('Y-m-d\TH:i'),'meeting_format'=>'video','meeting_details'=>'Open your secure appointment page.']);
        $this->assertSame(Referral::STATUS_ACCEPTED, $referral->status);

        $referral = $service->updateReferralOutcome($referral, ['status' => Referral::STATUS_COMPLETED, 'outcome' => 'Care transferred']);
        $this->assertSame(Referral::STATUS_COMPLETED, $referral->status);
        $this->assertNotNull($referral->completed_at);
    }

    public function test_incident_reporting_review_escalation_and_resolution(): void
    {
        $reporter = User::factory()->create(['role' => 'helper']);
        $moderatorUser = User::factory()->create(['role' => 'moderator']);
        Moderator::create($this->profileData('moderator', 'mod@example.com') + ['user_account_id' => $moderatorUser->id]);
        $adviserUser = User::factory()->create(['role' => 'adviser']);
        Adviser::create($this->profileData('adviser', 'adv2@example.com') + ['user_account_id' => $adviserUser->id]);

        $this->actingAs($reporter);
        $service = app(IncidentReportService::class);

        $incident = $service->createIncidentReport([
            'category' => 'breach_of_confidentiality',
            'description' => 'Confidentiality concern reported.',
            'risk_level' => 'high',
        ]);

        $this->actingAs($moderatorUser);
        $incident = $service->reviewIncident($incident, ['comments' => 'Review started']);
        $this->assertSame('under_review', $incident->status);

        $this->actingAs($adviserUser);
        $incident = $service->escalateIncident($incident, ['escalated_to' => $adviserUser->id, 'reason' => 'Adviser review needed']);
        $this->assertSame('escalated', $incident->status);

        $incident = $service->resolveIncident($incident, ['resolution_summary' => 'Resolved', 'corrective_actions' => 'Coaching assigned']);
        $this->assertSame('resolved', $incident->status);
        $this->assertNotNull($incident->resolved_at);
    }

    private function sessionWithAssignedHelper(): array
    {
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'HS-001']);

        $adviserUser = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create($this->profileData('adviser', 'adv@example.com') + ['user_account_id' => $adviserUser->id]);

        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create($this->profileData('helper', 'helper@example.com') + [
            'user_account_id' => $helperUser->id,
            'adviser_id' => $adviser->id,
            'status' => 'available',
            'competency_level' => 4,
            'max_concurrent_sessions' => 2,
        ]);

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_status' => Session::STATUS_ACTIVE,
            'session_type' => 'chat',
            'risk_level' => 'moderate',
            'created_date' => now(),
        ]);

        return [$seeker, $session, $helper, $adviser];
    }

    private function profileData(string $role, string $email): array
    {
        return [
            'user_account_id' => User::factory()->create(['role' => $role, 'email' => $email])->id,
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $email,
        ];
    }
}
