<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\StaffMail;
use App\Models\User;
use App\Services\EmergencyEscalationService;
use App\Services\IdentityVaultService;
use App\Services\ReferralManagementService;
use App\Services\StaffMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatWorkflowRepairsTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Manila'));
        config(['app.enforce_duty_hours' => false, 'app.relax_duty_hours' => false]);
    }

    private function room(): array
    {
        $adviserUser = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $adviserUser->id, 'first_name' => 'Reviewer', 'last_name' => 'Staff', 'email' => $adviserUser->email]);
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'first_name' => 'Support', 'last_name' => 'Helper', 'email' => $helperUser->email, 'adviser_id' => $adviser->id]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'PrivateOtter55']);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'risk_level' => 'low', 'session_type' => 'chat', 'session_status' => 'active', 'helper_accepted_at' => now(), 'start_time' => now(), 'created_date' => now()]);

        $this->conversationFixture($session);
        return [$helperUser, $adviserUser, $seekerUser, $session];
    }

    public function test_account_retry_uses_one_account_profile_email_and_receipt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['creation_token' => (string) Str::uuid(), 'first_name' => 'New', 'last_name' => 'Staff', 'email' => 'FreshStaff@gmail.com', 'role' => 'helper', 'account_status' => 'active', 'password' => 'StrongPass!2026', 'password_confirmation' => 'StrongPass!2026'];
        $this->actingAs($admin)->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();
        $account = User::where('email', 'freshstaff@gmail.com')->firstOrFail();
        $this->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('helpers', 1);
        $this->assertDatabaseCount('staff_mail_outbox', 1);
        $this->assertDatabaseCount('account_creation_receipts', 1);
        $this->assertSame(1, User::where('email', 'freshstaff@gmail.com')->count());
        $this->assertTrue(Hash::check('StrongPass!2026', $account->fresh()->password));
        $data['last_name'] = 'Different';
        $this->post(route('admin.users.store'), $data)->assertSessionHasErrors('email');
        $this->get(route('admin.users'))->assertOk()->assertSee('Email queued')->assertSee('reactivate-user-dialog', false);
    }

    public function test_orphan_profile_conflict_does_not_create_a_new_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create(['role' => 'helper', 'email' => 'old@example.com']);
        Helper::create(['user_account_id' => $existing->id, 'email' => 'linked@example.com', 'first_name' => 'Existing', 'last_name' => 'Profile']);
        $this->actingAs($admin)->post(route('admin.users.store'), ['first_name' => 'Another', 'last_name' => 'Person', 'email' => 'linked@example.com', 'role' => 'helper', 'account_status' => 'active', 'password' => 'StrongPass!2026', 'password_confirmation' => 'StrongPass!2026'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'linked@example.com']);
        $this->assertDatabaseCount('helpers', 1);
    }

    public function test_moderator_login_clears_a_stale_schedule_destination(): void
    {
        $user = User::factory()->create(['role' => 'moderator']);
        $this->withSession(['url.intended' => route('moderator.schedules')])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('moderator.dashboard'));
        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_staff_mail_records_acceptance_and_reserved_addresses_are_not_sent(): void
    {
        $user = User::factory()->create(['role' => 'helper', 'email' => 'staff@gmail.com']);
        config(['otp.delivery_driver' => 'resend', 'services.resend.key' => 'test-only', 'mail.from.address' => 'sender@example.com', 'mail.from.name' => 'COMPASS']);
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'provider-test-id'], 200)]);
        app(StaffMailService::class)->enqueue($user, 'account-created-'.$user->id, 'Created', 'Account created');
        app(StaffMailService::class)->deliver();
        $this->assertSame('provider-test-id', StaffMail::first()->provider_message_id);
        $local = User::factory()->create(['role' => 'helper', 'email' => 'test@compass.local']);
        app(StaffMailService::class)->enqueue($local, 'account-created-'.$local->id, 'Created', 'Account created');
        app(StaffMailService::class)->deliver();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('staff_mail_outbox', ['user_id' => $local->id, 'failure_code' => 'non_deliverable_address', 'attempts' => 5]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.users.welcome-email.retry', $user), ['confirm_email_retry' => 1])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('staff_mail_outbox', 2);
        $user->update(['email' => 'corrected@gmail.com']);
        $this->get(route('admin.users'))->assertOk()->assertSee('Account email address changed');
        $this->post(route('admin.users.welcome-email.retry', $user), ['confirm_email_retry' => 1])->assertSessionHasNoErrors();
        $new = StaffMail::latest('id')->first();
        $this->assertNull($new->sent_at);
        $this->assertSame('corrected@gmail.com', $new->payload['email']);
        $this->assertDatabaseCount('staff_mail_outbox', 3);
    }

    public function test_today_duty_is_distinct_and_status_endpoint_reflects_cancellation(): void
    {
        [$helper] = $this->room();
        $duty = HelperSchedule::create(['helper_id' => $helper->helper->id, 'date' => '2026-10-15', 'shift_start' => '00:00', 'shift_end' => '04:05', 'created_by' => $helper->id, 'is_active' => true]);
        HelperSchedule::create(['helper_id' => $helper->helper->id, 'date' => '2026-10-16', 'created_by' => $helper->id, 'is_active' => true]);
        $this->actingAs($helper)->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('duties.today.0.status', 'Duty today')->assertJsonCount(1, 'duties.upcoming');
        $duty->update(['is_active' => false]);
        $this->getJson(route('helper.readiness.status'))->assertJsonCount(0, 'duties.today');
    }

    public function test_chat_report_notifies_again_after_intake_but_double_click_is_idempotent(): void
    {
        [$helper,$adviser,$seeker,$session] = $this->room();
        $mod = User::factory()->create(['role' => 'moderator']);
        $existing = app(EmergencyEscalationService::class)->escalateEmergency($session, $session->seeker, ['preserve_classification' => true]);
        $existing->forceFill(['acknowledged_at' => now()->subMinute(), 'acknowledged_by' => $adviser->id])->save();
        $before = Notification::where('user_account_id', $adviser->id)->count();
        $this->actingAs($helper)->postJson(route('helper.session.emergency', $session->id), ['description' => 'A distinct chat incident.', 'immediate_action' => 'Stayed connected.'])->assertOk();
        $this->assertDatabaseCount('emergency_alerts', 1);
        $this->assertDatabaseHas('incident_reports', ['emergency_alert_id' => $existing->id, 'description' => 'A distinct chat incident.']);
        $this->assertGreaterThan($before, Notification::where('user_account_id', $adviser->id)->count());
        $count = Notification::count();
        $this->postJson(route('helper.session.emergency', $session->id), ['description' => 'Repeated click'])->assertOk();
        $this->assertSame($count, Notification::count());
        $this->getJson(route('session.referral-prompt', $session))->assertJsonPath('emergency.active', true)->assertJsonPath('emergency.chat_reported', true)->assertJsonPath('emergency.acknowledged', false);
        $this->actingAs($adviser)->get(route('adviser.emergencies.show', $existing))->assertOk()->assertSee('A distinct chat incident.')->assertSee('Professional handoff');
        $this->getJson(route('notifications.feed'))->assertJsonPath('workflow.emergency_count', 1);
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice.url', route('adviser.emergencies.show', $existing, false));
        $this->post(route('adviser.emergencies.action', $existing), ['action' => 'instruction', 'notes' => 'New chat report reviewed and instructions recorded.'])->assertSessionHasNoErrors();
        $this->actingAs($helper)->getJson(route('session.referral-prompt', $session))->assertJsonPath('emergency.acknowledged', true);
        $this->actingAs($mod)->get(route('moderator.emergency'))->assertOk()->assertSee('PrivateOtter55');
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertSame('low', $session->fresh()->risk_level);
    }

    public function test_new_report_after_resolution_creates_new_alert_and_blocks_other_helper(): void
    {
        [$helper,$adviser,$seeker,$session] = $this->room();
        $this->actingAs($helper)->postJson(route('helper.session.emergency', $session->id), ['description' => 'First incident'])->assertOk();
        $alert = EmergencyAlert::firstOrFail();
        $this->actingAs($adviser)->post(route('adviser.emergencies.resolve', $alert), ['resolution_notes' => 'Response documented and follow-up coordinated.'])->assertSessionHasNoErrors();
        $this->actingAs($helper)->postJson(route('helper.session.emergency', $session->id), ['description' => 'Second distinct incident'])->assertOk();
        $this->assertDatabaseCount('emergency_alerts', 2);
        $other = User::factory()->create(['role' => 'helper']);
        Helper::create(['user_account_id' => $other->id, 'email' => $other->email, 'first_name' => 'Other', 'last_name' => 'Staff']);
        $this->actingAs($other)->postJson(route('helper.session.emergency', $session->id), ['description' => 'Forbidden report'])->assertNotFound();
    }

    public function test_legacy_unlinked_chat_incident_gets_an_alert_without_replacing_the_report(): void
    {
        [$helper,$adviser,$seeker,$session] = $this->room();
        $incident = IncidentReport::create(['session_id' => $session->id, 'user_account_id' => $seeker->id, 'incident_category' => 'emergency_flag',
            'description' => 'Original legacy chat report.', 'risk_level' => 'emergency', 'status' => 'open']);
        $this->actingAs($helper)->postJson(route('helper.session.emergency', $session->id), ['description' => 'Retry from the chat'])->assertOk();
        $this->assertDatabaseCount('emergency_alerts', 1);
        $this->assertDatabaseCount('incident_reports', 1);
        $this->assertSame('Original legacy chat report.', $incident->fresh()->description);
        $this->assertNotNull($incident->fresh()->emergency_alert_id);
        $count = Notification::count();
        $this->postJson(route('helper.session.emergency', $session->id), ['description' => 'Retry again'])->assertOk();
        $this->assertSame($count, Notification::count());
    }

    public function test_emergency_referral_is_linked_and_handoff_waits_for_consent_and_identity(): void
    {
        [$helper,$adviser,$seeker,$session] = $this->room();
        $this->actingAs($helper)->postJson(route('helper.session.emergency', $session->id), ['description' => 'During-chat concern'])->assertOk();
        $alert = EmergencyAlert::firstOrFail();
        $this->actingAs($adviser)->post(route('adviser.emergencies.referral-request', $alert), ['notes' => 'Please document an urgent professional recommendation.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referrals', 0);
        $this->actingAs($helper)->postJson(route('helper.session.referral.consent', $session), ['summary' => 'Professional follow-up recommended'])->assertOk();
        $referral = Referral::firstOrFail();
        $this->assertSame($referral->id, $alert->fresh()->referral_id);
        $this->assertSame('emergency', $referral->priority_level);
        $this->actingAs($seeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertStatus(409);
        $this->actingAs($adviser)->post(route('adviser.referral.approve', $referral), ['review_notes' => 'Approved for professional review.'])->assertSessionHasNoErrors();
        $this->actingAs($seeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        $this->assertFalse($alert->fresh()->professional_referred);
        $this->assertNull($referral->fresh()->professional_id);
        $proUser = User::factory()->create(['role' => 'professional']);
        $pro = PsychologyProfessional::create(['user_account_id' => $proUser->id, 'email' => $proUser->email, 'first_name' => 'Assigned', 'last_name' => 'Professional', 'is_available' => true]);
        $this->mock(IdentityVaultService::class, fn ($mock) => $mock->shouldReceive('hasCurrentSubmission')->andReturn(true));
        $this->actingAs($adviser);
        app(ReferralManagementService::class)->forwardToProfessional($referral->fresh());
        $this->assertSame($pro->id, $referral->fresh()->professional_id);
        $this->assertTrue($alert->fresh()->professional_referred);
        $this->assertNotContains($alert->fresh()->status, ['resolved', 'closed']);
        $this->actingAs($proUser)->get(route('professional.referral.show', $referral))->assertOk()->assertSee('Linked emergency case');
        $other = User::factory()->create(['role' => 'professional']);
        PsychologyProfessional::create(['user_account_id' => $other->id, 'email' => $other->email, 'first_name' => 'Other', 'last_name' => 'Staff']);
        $this->actingAs($other)->get(route('professional.referral.show', $referral))->assertNotFound();
    }
}
