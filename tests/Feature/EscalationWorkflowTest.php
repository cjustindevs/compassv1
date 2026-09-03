<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Helper;
use App\Models\IdentityVault;
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

    public function test_risk_classification_identifies_all_levels(): void
    {
        $service = app(RiskClassificationService::class);
        $base = [
            'current_suicide_plan' => false,
            'suicidal_thoughts' => false,
            'severe_distress' => false,
            'recurring_distress' => false,
            'difficulty_coping' => false,
        ];

        $this->assertSame('low', $service->classifyRisk($base)['risk_level']);
        $this->assertSame('moderate', $service->classifyRisk(array_merge($base, ['difficulty_coping' => true]))['risk_level']);
        $this->assertSame('high', $service->classifyRisk(array_merge($base, ['severe_distress' => true]))['risk_level']);
        $this->assertSame('emergency', $service->classifyRisk(array_merge($base, ['current_suicide_plan' => true, 'suicidal_thoughts' => true]))['risk_level']);
    }

    public function test_emergency_escalation_flags_case_notifies_staff_and_releases_identity(): void
    {
        Event::fake();

        [$seeker, $session] = $this->sessionWithAssignedHelper();
        PsychologyProfessional::create($this->profileData('professional', 'pro@example.com'));
        IdentityVault::create(['seeker_id' => $seeker->id, 'real_name' => 'Test Seeker']);

        $alert = app(EmergencyEscalationService::class)->escalateEmergency($session, $seeker, ['reason' => 'Immediate threat']);

        $this->assertDatabaseHas('emergency_alerts', ['id' => $alert->id, 'status' => 'referred', 'professional_referred' => true]);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'risk_level' => 'emergency', 'escalation_required' => true]);
        $this->assertDatabaseHas('help_seekers', ['id' => $seeker->id, 'current_risk_level' => 'emergency', 'has_emergency' => true]);
        $this->assertDatabaseHas('identity_vault', ['seeker_id' => $seeker->id, 'emergency_override' => true]);
        $this->assertDatabaseHas('referrals', ['session_id' => $session->id, 'priority_level' => 'emergency']);
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

        $referral = $service->reviewReferral($referral, $adviser, ['approved' => true, 'notes' => 'Proceed']);
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->status);

        $referral = $service->processConsent($referral, true);
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertTrue($referral->identity_disclosed);

        $referral = $service->acceptReferral($referral, $professional);
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
