<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\ProfessionalNote;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $professionalUser;

    protected PsychologyProfessional $professional;

    protected User $adviserUser;

    protected Referral $pendingReferral;

    protected function setUp(): void
    {
        parent::setUp();

        // Professional (Dr. Maria Santos)
        $this->professionalUser = User::create([
            'name' => 'Dr. Maria Santos',
            'email' => 'maria@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'professional',
            'email_verified_at' => now(),
        ]);

        $this->professional = PsychologyProfessional::create([
            'user_account_id' => $this->professionalUser->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@compass.edu.ph',
            'specialization' => 'Clinical Psychology',
            'license_number' => 'PSY-2024-001',
            'is_available' => true,
        ]);

        // Adviser
        $adviserUser = User::create([
            'name' => 'Dr. Elena Cruz',
            'email' => 'elena@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'adviser',
            'email_verified_at' => now(),
        ]);

        $this->adviserUser = $adviserUser;

        $adviser = Adviser::create([
            'user_account_id' => $adviserUser->id,
            'first_name' => 'Elena',
            'last_name' => 'Cruz',
            'email' => 'elena@compass.edu.ph',
        ]);

        // Helper
        $helperUser = User::create([
            'name' => 'Helper One',
            'email' => 'helper1@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'helper',
            'email_verified_at' => now(),
        ]);

        $helper = Helper::create([
            'user_account_id' => $helperUser->id,
            'adviser_id' => $adviser->id,
            'first_name' => 'Helper',
            'last_name' => 'One',
            'email' => 'helper1@compass.edu.ph',
        ]);

        // Seeker
        $seekerUser = User::create([
            'name' => 'Seeker One',
            'email' => 'seeker1@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'seeker',
            'email_verified_at' => now(),
        ]);

        $seeker = HelpSeeker::create([
            'user_account_id' => $seekerUser->id,
            'generated_alias' => 'TestAlias42',
        ]);

        // Session
        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_type' => 'chat',
            'session_status' => 'completed',
            'risk_level' => 'high',
            'completion_status' => 'completed',
            'created_date' => now()->subDays(5),
        ]);

        // Pending professional referral
        $this->pendingReferral = Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'professional_id' => $this->professional->id,
            'priority_level' => Referral::PRIORITY_HIGH,
            'help_seeker_consent' => true,
            'identity_disclosed' => false,
            'referral_reason' => 'Persistent anxiety affecting daily functioning.',
            'referral_date' => now()->subDay(),
            'status' => Referral::STATUS_PENDING_PROFESSIONAL,
        ]);

        // Completed referral (owned by the professional)
        Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'professional_id' => $this->professional->id,
            'priority_level' => Referral::PRIORITY_LOW,
            'help_seeker_consent' => true,
            'identity_disclosed' => true,
            'referral_reason' => 'Career counseling following academic stress.',
            'referral_date' => now()->subDays(10),
            'closed_date' => now()->subDays(2),
            'status' => Referral::STATUS_COMPLETED,
        ]);
    }

    public function test_professional_dashboard_renders_with_database_stats(): void
    {
        $response = $this->actingAs($this->professionalUser)->get(route('professional.dashboard'));

        $response->assertOk();
        $response->assertSee('Professional Dashboard');
        $response->assertSee('Pending Referrals');
        $response->assertSee('TestAlias42');
    }

    public function test_dashboard_stats_endpoint_returns_counts(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->getJson(route('professional.dashboard.stats'));

        $response->assertOk()
            ->assertJson([
                'pending' => 1,
                'active' => 0,
                'completed' => 1,
            ]);
    }

    public function test_referrals_page_shows_pipeline(): void
    {
        $response = $this->actingAs($this->professionalUser)->get(route('professional.referrals'));

        $response->assertOk();
        $response->assertSee('Referral Management');
        $response->assertSee('Pending');
        $response->assertSee('TestAlias42');
    }

    public function test_referral_detail_renders_for_owned_referral(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->get(route('professional.referral.show', $this->pendingReferral->id));

        $response->assertOk();
        $response->assertSee('Referral #' . $this->pendingReferral->id);
        $response->assertSee('Accept Referral');
    }

    public function test_professional_cannot_view_another_professionals_referral(): void
    {
        $otherUser = User::create([
            'name' => 'Dr. Juan Dela Cruz',
            'email' => 'juan@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'professional',
            'email_verified_at' => now(),
        ]);

        PsychologyProfessional::create([
            'user_account_id' => $otherUser->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@compass.edu.ph',
        ]);

        $this->actingAs($otherUser)
            ->get(route('professional.referral.show', $this->pendingReferral->id))
            ->assertNotFound();
    }

    public function test_accept_referral_creates_notification_and_updates_status(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.referral.accept', $this->pendingReferral->id));

        $response->assertRedirect(route('professional.referrals'));

        $this->assertDatabaseHas('referrals', [
            'id' => $this->pendingReferral->id,
            'status' => Referral::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $this->adviserUser->id,
            'notification_type' => 'referral',
        ]);
    }

    public function test_decline_referral_requires_reason_and_notifies_adviser(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.referral.decline', $this->pendingReferral->id), [
                'decline_reason' => 'Case is outside my area of specialization.',
            ]);

        $response->assertRedirect(route('professional.referrals'));

        $this->assertDatabaseHas('referrals', [
            'id' => $this->pendingReferral->id,
            'status' => Referral::STATUS_DECLINED,
            'decline_reason' => 'Case is outside my area of specialization.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $this->adviserUser->id,
            'title' => 'Referral Declined',
        ]);
    }

    public function test_decline_referral_validation_requires_reason(): void
    {
        $this->actingAs($this->professionalUser)
            ->post(route('professional.referral.decline', $this->pendingReferral->id), [])
            ->assertSessionHasErrors('decline_reason');
    }

    public function test_start_case_moves_accepted_referral_to_in_progress(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_ACCEPTED]);

        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.referral.start', $this->pendingReferral->id));

        $response->assertRedirect(route('professional.cases'));

        $this->assertDatabaseHas('referrals', [
            'id' => $this->pendingReferral->id,
            'status' => Referral::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_cases_page_lists_active_cases(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->professionalUser)->get(route('professional.cases'));

        $response->assertOk();
        $response->assertSee('Active Cases');
        $response->assertSee('TestAlias42');
        $response->assertSee('#'.$this->pendingReferral->id);
    }

    public function test_case_detail_renders_only_for_active_case(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->professionalUser)
            ->get(route('professional.cases.show', $this->pendingReferral->id));

        $response->assertOk();
        $response->assertSee('Add Intervention Note');
        $response->assertSee('Intervention History');
    }

    public function test_case_detail_hidden_for_pending_referral(): void
    {
        $this->actingAs($this->professionalUser)
            ->get(route('professional.cases.show', $this->pendingReferral->id))
            ->assertNotFound();
    }

    public function test_add_intervention_notes_stores_professional_note(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.cases.notes', $this->pendingReferral->id), [
                'intervention_type' => 'individual_therapy',
                'notes' => 'Conducted CBT session focused on anxiety management.',
                'follow_up_plan' => 'Monitor coping strategies weekly.',
                'follow_up_date' => now()->addWeek()->toDateString(),
            ]);

        $response->assertRedirect(route('professional.cases.show', $this->pendingReferral->id));

        $this->assertDatabaseHas('professional_notes', [
            'referral_id' => $this->pendingReferral->id,
            'professional_id' => $this->professional->id,
            'intervention_type' => 'individual_therapy',
        ]);

        $this->assertSame(1, ProfessionalNote::where('referral_id', $this->pendingReferral->id)->count());
    }

    public function test_add_notes_auto_starts_accepted_case(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_ACCEPTED]);

        $this->actingAs($this->professionalUser)
            ->post(route('professional.cases.notes', $this->pendingReferral->id), [
                'intervention_type' => 'initial_assessment',
                'notes' => 'Initial assessment completed.',
            ]);

        $this->assertDatabaseHas('referrals', [
            'id' => $this->pendingReferral->id,
            'status' => Referral::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_update_case_status_to_completed_sets_closed_date(): void
    {
        $this->pendingReferral->update(['status' => Referral::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.cases.status', $this->pendingReferral->id), [
                'status' => Referral::STATUS_COMPLETED,
            ]);

        $response->assertRedirect(route('professional.cases.show', $this->pendingReferral->id));

        $this->assertDatabaseHas('referrals', [
            'id' => $this->pendingReferral->id,
            'status' => Referral::STATUS_COMPLETED,
        ]);

        $this->assertNotNull($this->pendingReferral->fresh()->closed_date);
    }

    public function test_reports_page_renders_metrics(): void
    {
        $response = $this->actingAs($this->professionalUser)->get(route('professional.reports'));

        $response->assertOk();
        $response->assertSee('Performance Reports');
        $response->assertSee('Total Referrals');
        $response->assertSee('Acceptance Rate');
    }

    public function test_report_export_returns_csv(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->get(route('professional.reports.export', ['period' => 'yearly']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Referral ID', $response->streamedContent());
    }

    public function test_profile_page_renders_professional_info(): void
    {
        $response = $this->actingAs($this->professionalUser)->get(route('professional.profile'));

        $response->assertOk();
        $response->assertSee('Dr. Maria Santos');
        $response->assertSee('PSY-2024-001');
    }

    public function test_profile_update_persists_changes(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->put(route('professional.profile.update'), [
                'name' => 'Dr. Maria Santos II',
                'email' => 'maria@compass.edu.ph',
                'specialization' => 'Trauma-Informed Therapy',
                'license_number' => 'PSY-2024-100',
                'phone' => '+63 917 555 9999',
            ]);

        $response->assertRedirect(route('professional.profile'));

        $this->assertDatabaseHas('psychology_professionals', [
            'id' => $this->professional->id,
            'specialization' => 'Trauma-Informed Therapy',
            'license_number' => 'PSY-2024-100',
            'phone' => '+63 917 555 9999',
        ]);
    }

    public function test_availability_update_persists(): void
    {
        $response = $this->actingAs($this->professionalUser)
            ->post(route('professional.profile.availability'), [
                'is_available' => 0,
            ]);

        $response->assertRedirect();

        $this->assertFalse((bool) $this->professional->fresh()->is_available);
    }

    public function test_settings_page_renders(): void
    {
        $response = $this->actingAs($this->professionalUser)->get(route('professional.settings'));

        $response->assertOk();
        $response->assertSee('Availability');
    }

    public function test_non_professional_is_blocked_from_professional_pages(): void
    {
        $seeker = User::create([
            'name' => 'Blocked Seeker',
            'email' => 'blocked@compass.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'seeker',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($seeker)
            ->get(route('professional.dashboard'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('professional.dashboard'))->assertRedirect(route('login'));
    }
}