<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\IdentityVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdviserProfessionalActionUiTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
    }

    protected function tearDown(): void
    {
        DB::purge('identity_vault');
        parent::tearDown();
    }

    private function records(): array
    {
        $adviserUser = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $adviserUser->id, 'first_name' => 'Action', 'last_name' => 'Reviewer', 'email' => $adviserUser->email]);
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'adviser_id' => $adviser->id, 'first_name' => 'Assigned', 'last_name' => 'Helper', 'email' => $helperUser->email]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'TestAlias'.$seekerUser->id]);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'review_adviser_id' => $adviser->id, 'session_status' => 'active', 'session_type' => 'chat', 'start_time' => now(), 'helper_accepted_at' => now(), 'created_date' => now()]);
        $this->actingAs($adviserUser);

        $this->conversationFixture($session);
        return [$adviserUser, $helperUser, $session];
    }

    public function test_referral_clarification_and_history_remain_inside_the_detail_layout(): void
    {
        [$adviser,$helper,$session] = $this->records();
        $referral = Referral::create(['session_id'=>$session->id,'helper_id'=>$session->helper_id,'adviser_id'=>$adviser->adviser->id,
            'status'=>'pending_adviser','priority_level'=>'low','referral_reason'=>'Review this request.','referral_date'=>now()]);
        $referral->forceFill(['clarification_requested_at'=>now(),'clarification_question'=>'Please clarify the recommendation.',
            'recommendation_form'=>['session_summary'=>'Session summary for review']])->save();
        $page = $this->get(route('adviser.referral.show',$referral->case_reference))->assertOk();
        $document = new \DOMDocument; @$document->loadHTML($page->getContent()); $xpath = new \DOMXPath($document);
        $this->assertSame(1,$xpath->query('//section[@aria-label="Referral clarification"]/ancestor::div[contains(@class,"referral-detail-page")]')->length);
        $this->assertSame(1,$xpath->query('//section[@aria-label="Referral recommendation"]/ancestor::div[contains(@class,"referral-detail-page")]')->length);
        $this->assertSame(1,$xpath->query('//h3[normalize-space(.)="Version history"]/ancestor::div[contains(@class,"referral-detail-page")]')->length);
    }

    public function test_emergency_page_offers_real_referral_request_and_shows_waiting_state_after_submit(): void
    {
        [$adviser,$helper,$session] = $this->records();
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $adviser->adviser->id, 'trigger_reason' => 'Synthetic test emergency']);
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()
            ->assertSee('href="#emergencyProfessionalHandoff"', false)->assertSee('Request Helper recommendation')
            ->assertSee(route('adviser.emergencies.referral-request', $alert), false);
        $this->post(route('adviser.emergencies.referral-request', $alert), ['notes' => 'Please document an urgent professional recommendation.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->id, 'title' => 'Urgent referral recommendation requested']);
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Waiting for Helper recommendation')
            ->assertDontSee('action="'.route('adviser.emergencies.referral-request', $alert).'"', false);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_pre_session_emergency_explains_why_the_action_is_not_ready(): void
    {
        [$adviser,$helper,$session] = $this->records();
        $session->update(['helper_id' => null, 'session_status' => 'waiting', 'helper_accepted_at' => null, 'start_time' => null]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $adviser->adviser->id, 'trigger_reason' => 'Synthetic intake alert']);
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Waiting for Helper connection')
            ->assertSee('continues priority queuing')->assertSee('disabled>Request Helper recommendation', false)
            ->assertDontSee('action="'.route('adviser.emergencies.referral-request', $alert).'"', false);
        $this->post(route('adviser.emergencies.referral-request', $alert), ['notes' => 'Premature request before Helper connection.'])->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_referral_next_step_tracks_review_consent_contact_assignment_release_and_handoff(): void
    {
        [$adviser,$helper,$session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $adviser->adviser->id, 'status' => 'pending_adviser', 'priority_level' => 'high', 'referral_reason' => 'Synthetic referral recommendation', 'referral_date' => now(), 'help_seeker_consent' => false]);
        $proUser = User::factory()->create(['role' => 'professional']);
        $pro = PsychologyProfessional::create(['user_account_id' => $proUser->id, 'first_name' => 'Available', 'last_name' => 'Professional', 'email' => $proUser->email, 'is_available' => true]);
        $identityReady = false;
        $this->mock(IdentityVaultService::class, function ($mock) use (&$identityReady) {
            $mock->shouldReceive('hasCurrentSubmission')->andReturnUsing(function () use (&$identityReady) {
                return $identityReady;
            });
        });
        $page = route('adviser.referral.show', $referral->case_reference);
        $this->get($page)->assertOk()->assertSee('Review and approve referral')->assertDontSee('href="#professionalAssignment"', false);
        $referral->forceFill(['clarification_requested_at' => now()])->save();
        $this->get($page)->assertOk()->assertSee('Waiting for Helper revision')->assertDontSee('Review and approve referral');
        $referral->forceFill(['clarification_received_at' => now(), 'approved_at' => now(), 'reviewed_at' => now(), 'status' => 'pending_consent'])->save();
        $this->get($page)->assertOk()->assertSee('Waiting for Seeker referral consent')->assertDontSee('id="professionalAssignment"', false);
        $referral->update(['help_seeker_consent' => true, 'consent_obtained_at' => now(), 'status' => 'pending_professional']);
        $this->get($page)->assertOk()->assertSee('Waiting for Seeker contact details')->assertDontSee('href="#professionalAssignment"', false);
        $identityReady = true;
        $pro->update(['is_available' => false]);
        $this->get($page)->assertOk()->assertSee('No available Professional.')
            ->assertDontSee('href="#professionalAssignment"', false);
        $pro->update(['is_available' => true]);
        $this->get($page)->assertOk()->assertSee('Your action: assign and notify a Professional.')
            ->assertSee('href="#professionalAssignment"', false)->assertSee('id="professionalAssignment"', false)->assertSee('Assign and notify Professional');
        $referral->update(['professional_id' => $pro->id, 'professional_notified_at' => now()]);
        $this->get($page)->assertOk()->assertSee('Review identity release')->assertSee('id="professionalIdentityRelease"', false)
            ->assertSee('<details open><summary>Identity release permissions', false);
        $referral->update(['identity_disclosed' => true]);
        $this->get($page)->assertOk()->assertSee('Professional handoff recorded.');
        $referral->update(['status' => 'closed']);
        $this->get($page)->assertOk()->assertSee('No further Professional assignment or identity release is available.')
            ->assertDontSee('id="professionalIdentityRelease"', false);
    }

    public function test_emergency_link_avoids_sending_a_different_emergency_reviewer_to_a_forbidden_referral(): void
    {
        [$owner,$helper,$session] = $this->records();
        $otherUser = User::factory()->create(['role' => 'adviser']);
        $other = Adviser::create(['user_account_id' => $otherUser->id, 'first_name' => 'Referral', 'last_name' => 'Reviewer', 'email' => $otherUser->email]);
        $session->helper->update(['adviser_id' => $other->id]);
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $other->id, 'status' => 'pending_adviser', 'priority_level' => 'high', 'referral_reason' => 'Synthetic referral', 'referral_date' => now()]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $owner->adviser->id, 'referral_id' => $referral->id, 'trigger_reason' => 'Synthetic emergency']);
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Referral reviewer: Referral Reviewer')
            ->assertDontSee('Continue Professional referral');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_visible_actions_complete_normal_and_emergency_referrals_with_real_consent_and_identity_storage(bool $emergency): void
    {
        config(['database.connections.identity_vault' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'identity_vault.key' => 'base64:'.base64_encode(str_repeat('v', 32))]);
        DB::purge('identity_vault');
        $this->artisan('migrate', ['--database' => 'identity_vault', '--path' => 'database/migrations/identity_vault', '--force' => true])->assertSuccessful();
        [$adviser, $helper, $session] = $this->records();
        $seeker = $session->seeker->user;
        $session->seeker->update(['pseudo_id' => 'PS-ACTION-'.$seeker->id]);
        $professionalUser = User::factory()->create(['role' => 'professional']);
        $professional = PsychologyProfessional::create(['user_account_id' => $professionalUser->id, 'first_name' => 'Available', 'last_name' => 'Professional', 'email' => $professionalUser->email, 'is_available' => true]);
        $unselectedUser = User::factory()->create(['role' => 'professional']);
        PsychologyProfessional::create(['user_account_id' => $unselectedUser->id, 'first_name' => 'Other', 'last_name' => 'Professional', 'email' => $unselectedUser->email, 'is_available' => true]);

        if ($emergency) {
            $this->actingAs($helper)->postJson(route('helper.session.emergency', $session), ['description' => 'Synthetic during-chat emergency for workflow testing.'])->assertOk();
            $alert = EmergencyAlert::sole();
            $this->actingAs($adviser)->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Request Helper recommendation');
            $this->post(route('adviser.emergencies.referral-request', $alert), ['notes' => 'Please submit an urgent recommendation for this test case.'])->assertSessionHasNoErrors();
        }

        $this->actingAs($helper)->postJson(route('helper.session.referral.consent', $session), ['summary' => 'Synthetic recommendation for professional coordination.'])->assertOk();
        $referral = Referral::sole();
        $assignmentUrl = $emergency ? route('adviser.emergencies.professional', $alert) : route('adviser.referral.assign', $referral);
        $page = route('adviser.referral.show', $referral->case_reference);
        $prompt = route('session.referral-prompt', $session);
        $this->actingAs($seeker)->get(route('session.chat'))->assertOk()->assertSee('chatIdentityOpen', false)
            ->assertSee('chat-referral.js')->assertSee('chatIdentityDialog', false);
        $this->getJson($prompt)->assertOk()->assertJsonPath('referral.needs_identity_disclosure', false)
            ->assertJsonPath('referral.can_provide_identity', false)->assertJsonPath('referral.identity_url', null);
        $this->actingAs($adviser)->get($page)->assertOk()->assertSee('Review and approve referral');
        $this->post(route('adviser.referral.approve', $referral), ['review_notes' => 'Reviewed the test recommendation for professional support.'])->assertSessionHasNoErrors();
        $this->get($page)->assertOk()->assertSee('Waiting for Seeker referral consent');
        $this->post($assignmentUrl, ['professional_id' => $professional->id, 'reason' => 'This test professional is available.'])->assertSessionHasErrors('professional_id');
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $professionalUser->id]);

        $this->actingAs($seeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        $this->getJson($prompt)->assertOk()->assertJsonPath('referral.needs_identity_disclosure', true)
            ->assertJsonPath('referral.identity_url', route('identity.form', [$referral, 'embedded' => 1]));
        $this->get(route('identity.form', [$referral, 'embedded' => 1]))->assertOk()
            ->assertSee('Identity Disclosure Consent')->assertSee('name="identity_disclosure"', false)
            ->assertSee('identity-details-saved');
        $this->actingAs($helper)->getJson($prompt)->assertOk()->assertJsonPath('referral.needs_identity_disclosure', false)
            ->assertJsonPath('referral.identity_url', null);
        $this->actingAs($adviser)->get($page)->assertOk()->assertSee('Waiting for Seeker contact details');
        $this->post($assignmentUrl, ['professional_id' => $professional->id, 'reason' => 'This test professional is available.'])->assertSessionHasErrors('professional_id');
        $this->assertNull($referral->fresh()->professional_id);
        $this->actingAs($seeker)->postJson(route('identity.store', $referral), ['identity_disclosure' => true, 'real_name' => 'Synthetic Person', 'phone_number' => '+639170000000'])->assertOk();
        $this->getJson($prompt)->assertOk()->assertJsonPath('referral.has_identity', true)
            ->assertJsonPath('referral.needs_identity_disclosure', false)->assertDontSee('Synthetic Person');
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertFalse($referral->fresh()->identity_disclosed);
        $stored = DB::connection('identity_vault')->table('idv_identities')->first();
        $this->assertNotSame('Synthetic Person', $stored->real_name);

        $this->actingAs($adviser)->get($page)->assertOk()->assertSee('Choose Professional')
            ->assertSee('data-confirm="Assign and notify Professional?"', false);
        if ($emergency) {
            $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Raise to Professional')
                ->assertSee('name="professional_id"', false)->assertSee($professional->full_name)->assertSee('Other Professional');
        }
        $this->post($assignmentUrl, ['professional_id' => $professional->id, 'reason' => 'This test professional is available.'])->assertSessionHasNoErrors();
        $this->assertSame($professional->id, $referral->fresh()->professional_id);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $professionalUser->id, 'notification_type' => $emergency ? 'emergency' : 'referral', 'link' => '/professional/referral/'.$referral->id]);
        $count = Notification::where('user_account_id', $professionalUser->id)->count();
        $this->post($assignmentUrl, ['professional_id' => $professional->id, 'reason' => 'Repeated assignment click.'])->assertSessionHasErrors('professional_id');
        $this->assertSame($count, Notification::where('user_account_id', $professionalUser->id)->count());
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $unselectedUser->id]);
        $this->get($page)->assertOk()->assertSee('Review identity release');
        $this->actingAs($professionalUser)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()
            ->assertJsonPath('fresh.0.link', '/professional/referral/'.$referral->id);
        $notice = $this->getJson(route('emergency-notice.next'))->assertOk();
        if ($emergency) {
            $notice->assertJsonPath('notice.url', '/professional/referral/'.$referral->id);
            $this->get($notice->json('notice.url'))->assertOk()->assertSee('Linked emergency case')->assertSee('staffEmergencyNotice', false);
            $this->postJson(route('emergency-notice.dismiss'), ['reference' => $notice->json('notice.reference')])->assertNoContent();
            $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
            $this->actingAs($unselectedUser)->postJson(route('emergency-notice.dismiss'), ['reference' => $notice->json('notice.reference')])->assertNotFound();
            $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        } else {
            $notice->assertJsonPath('notice', null);
        }
        $this->actingAs($professionalUser)->get(route('identity.show', $referral))->assertForbidden();
        $this->actingAs($adviser)->post(route('identity.release', $referral), ['fields' => ['real_name', 'phone_number'], 'reason' => 'Necessary test contact details for approved referral coordination.'])->assertSessionHasNoErrors();
        $this->get($page)->assertOk()->assertSee('Professional handoff recorded.');
        $this->actingAs($professionalUser)->get(route('identity.show', $referral))->assertOk()->assertSee('Synthetic Person');
        $this->post(route('professional.referral.accept', $referral), [
            'starts_at' => now('Asia/Manila')->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now('Asia/Manila')->addDay()->addHour()->format('Y-m-d\TH:i'),
            'meeting_format' => 'in_person', 'meeting_details' => 'Synthetic test appointment location.',
        ])->assertSessionHasNoErrors();
        $appointment = $referral->appointments()->firstOrFail();
        $this->actingAs($seeker)->post(route('seeker.appointment.respond', $appointment), ['decision' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $appointment->fresh()->seeker_response);
        $this->assertSame('active', $session->fresh()->session_status);
        if ($emergency) {
            $this->assertSame($referral->id, $alert->fresh()->referral_id);
            $this->assertTrue($alert->fresh()->professional_referred);
            $this->assertNotContains($alert->fresh()->status, ['resolved', 'closed']);
        }
    }

    public function test_emergency_handoff_rejects_ineligible_recipients_unrelated_reviewers_and_closed_cases(): void
    {
        [$adviser, $helper, $session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $adviser->adviser->id,
            'status' => 'pending_professional', 'priority_level' => 'emergency', 'approved_at' => now(), 'help_seeker_consent' => true, 'referral_reason' => 'Synthetic emergency recommendation.', 'referral_date' => now()]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $adviser->adviser->id, 'referral_id' => $referral->id, 'trigger_reason' => 'Synthetic emergency']);
        $this->mock(IdentityVaultService::class, fn ($mock) => $mock->shouldReceive('hasCurrentSubmission')->andReturn(true));
        $proUser = User::factory()->create(['role' => 'professional']);
        $pro = PsychologyProfessional::create(['user_account_id' => $proUser->id, 'first_name' => 'Selected', 'last_name' => 'Professional', 'email' => $proUser->email, 'is_available' => false]);
        $url = route('adviser.emergencies.professional', $alert);
        $payload = ['professional_id' => $pro->id, 'reason' => 'Choose this Professional for the emergency referral.'];
        $this->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('No active, available Professional.')
            ->assertDontSee('<option value="'.$pro->id.'">', false);
        $this->post($url, $payload)->assertSessionHasErrors('professional_id');
        $pro->update(['is_available' => true]);
        $proUser->update(['is_active' => false]);
        $this->post($url, $payload)->assertSessionHasErrors('professional_id');
        $proUser->update(['is_active' => true, 'role' => 'helper']);
        $this->post($url, $payload)->assertSessionHasErrors('professional_id');
        $proUser->update(['role' => 'professional']);
        $other = User::factory()->create(['role' => 'adviser']);
        Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Unrelated', 'last_name' => 'Reviewer', 'email' => $other->email]);
        $this->actingAs($other)->post($url, $payload)->assertForbidden();
        $this->actingAs($helper)->post($url, $payload)->assertForbidden();
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAs($adviser)->post($url, $payload)->assertSessionHasErrors('professional_id');
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $proUser->id]);
        $this->assertSame(0, DB::table('emergency_review_actions')->where('action', 'professional_raised')->count());
    }

    public function test_professional_popup_ignores_reassigned_revoked_and_resolved_emergency_referrals(): void
    {
        [$adviser, $helper, $session] = $this->records();
        $professionalUser = User::factory()->create(['role' => 'professional']);
        $professional = PsychologyProfessional::create(['user_account_id' => $professionalUser->id, 'first_name' => 'Recipient', 'last_name' => 'Professional', 'email' => $professionalUser->email, 'is_available' => true]);
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $adviser->adviser->id,
            'professional_id' => $professional->id, 'status' => 'pending_professional', 'priority_level' => 'emergency', 'approved_at' => now(), 'help_seeker_consent' => true, 'referral_reason' => 'Synthetic emergency referral.', 'referral_date' => now()]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id, 'adviser_id' => $adviser->adviser->id, 'referral_id' => $referral->id, 'trigger_reason' => 'Private emergency narrative']);
        Notification::create(['user_account_id' => $professionalUser->id, 'notification_type' => 'emergency', 'title' => 'Urgent referral', 'message' => 'Generic assignment notice', 'link' => '/professional/referral/'.$referral->id]);
        $popup = route('emergency-notice.next');
        $this->actingAs($professionalUser)->getJson($popup)->assertOk()->assertJsonPath('notice.url', '/professional/referral/'.$referral->id)->assertDontSee('Private emergency narrative');
        $referral->update(['professional_id' => null]);
        $this->getJson($popup)->assertJsonPath('notice', null);
        $referral->update(['professional_id' => $professional->id, 'help_seeker_consent' => false]);
        $this->getJson($popup)->assertJsonPath('notice', null);
        $referral->update(['help_seeker_consent' => true]);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->getJson($popup)->assertJsonPath('notice', null);
        $professionalUser->update(['is_active' => false]);
        $this->actingAs($professionalUser->fresh())->getJson($popup)->assertForbidden();
    }
}
