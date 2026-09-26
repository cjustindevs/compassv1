<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\ConsentRecord;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Message;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $user = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Adviser', 'email' => $user->email]);
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'adviser_id' => $adviser->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $helperUser->email]);
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'PrivateSeeker'.$seekerUser->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_type' => 'chat', 'session_status' => 'completed', 'risk_level' => 'low', 'created_date' => now()]);
        SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Submitted summary']);
        Message::create(['session_id' => $session->id, 'sender_id' => $seekerUser->id, 'sender' => 'seeker', 'message_text' => 'PRIVATE CONVERSATION SENTINEL', 'sent_datetime' => now(), 'is_transcript' => true]);

        return [$user, $session];
    }

    private function consent(Session $session, string $decision = 'accepted'): void
    {
        ConsentRecord::create(['seeker_id' => $session->seeker_id, 'session_id' => $session->id, 'purpose' => 'transcription', 'document_type' => 'informed_consent', 'version' => ConsentService::VERSION, 'decision' => $decision, 'consent_given' => $decision === 'accepted', 'withdrawn' => $decision === 'withdrawn', 'consent_date' => now()]);
    }

    public function test_documentation_and_transcript_list_do_not_disclose_chat(): void
    {
        [$user,$session] = $this->records();
        $this->actingAs($user)->get(route('adviser.session.show', $session->id))->assertOk()->assertSee('Submitted summary')->assertDontSee('PRIVATE CONVERSATION SENTINEL');
        $this->get(route('adviser.transcripts'))->assertOk()->assertDontSee('PRIVATE CONVERSATION SENTINEL');
        $this->get(route('chat.transcript', $session->id))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_documentation_viewed', 'target_id' => $session->id]);
    }

    public function test_supervision_review_does_not_require_per_session_transcription_consent(): void
    {
        [$user,$session] = $this->records();
        $url = route('adviser.transcript.access', $session->id);
        $data = ['purpose' => 'competency_assessment', 'reason' => 'Review a documented competency concern.'];
        // No transcription consent record is created: adviser supervision review
        // is covered by the seeker's general consent captured at the start of the flow.
        $this->actingAs($user)->post($url, $data)->assertOk()->assertSee('PRIVATE CONVERSATION SENTINEL');
        $this->post($url, ['purpose' => 'anything', 'reason' => 'A sufficiently long reason'])->assertUnprocessable();
        $this->get(route('chat.transcript', $session->id))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'transcript_access_authorized', 'target_id' => $session->id]);
        // A transcription-purpose withdrawal no longer revokes supervision review.
        $this->consent($session, 'withdrawn');
        $this->get(route('chat.transcript', $session->id))->assertOk();
    }

    public function test_missing_profile_and_unrelated_adviser_cannot_access_records(): void
    {
        [$user,$session] = $this->records();
        $missing = User::factory()->create(['role' => 'adviser']);
        $this->actingAs($missing)->get(route('adviser.dashboard'))->assertForbidden();
        $this->get(route('adviser.session.show', $session->id))->assertForbidden();
        Adviser::create(['user_account_id' => $missing->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $missing->email]);
        $this->actingAs($missing->fresh())->get(route('adviser.session.show', $session->id))->assertForbidden();
        foreach (['helper', 'seeker', 'moderator', 'professional', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('adviser.session.show', $session->id))->assertForbidden();
        }
    }

    public function test_grants_expire_and_do_not_authorize_a_different_adviser(): void
    {
        [$user,$session] = $this->records();
        $this->consent($session);
        $this->actingAs($user)->post(route('adviser.transcript.access', $session->id), ['purpose' => 'referral_review', 'reason' => 'Review supporting referral evidence.'])->assertOk();
        $this->travel(11)->minutes();
        $this->get(route('chat.transcript', $session->id))->assertForbidden();
        $this->travelBack();
        $session->helper->update(['adviser_id' => null]);
        $this->get(route('chat.transcript', $session->id))->assertForbidden();
    }

    public function test_referral_review_rejects_consent_impersonation_and_terminal_repeats(): void
    {
        [$user,$session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $user->adviser->id, 'priority_level' => 'low', 'referral_reason' => 'Review support options', 'status' => 'pending_adviser']);
        $url = route('adviser.referral.approve', $referral->id);
        $this->actingAs($user)->post($url, ['review_notes' => 'Appropriate for referral review', 'consent_obtained' => true])->assertSessionHasErrors('consent_obtained');
        $this->post($url, ['review_notes' => 'Appropriate for referral review'])->assertSessionHas('success');
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'status' => 'pending_consent', 'professional_id' => null, 'help_seeker_consent' => false]);
        $this->post(route('adviser.referral.reject', $referral->id), ['rejection_reason' => 'Overwrite prior decision'])->assertRedirect()->assertSessionHasErrors('rejection_reason');
        $this->assertDatabaseHas('audit_logs', ['action' => 'referral_status_changed', 'target_id' => $referral->id]);
    }

    public function test_assignment_capacity_is_checked_before_any_transfer(): void
    {
        [$user,$session] = $this->records();
        $nextUser = User::factory()->create(['role' => 'adviser']);
        $target = Adviser::create(['user_account_id' => $nextUser->id, 'first_name' => 'Target', 'last_name' => 'Adviser', 'email' => $nextUser->email]);
        for ($i = 0; $i < 15; $i++) {
            $helperUser = User::factory()->create(['role' => 'helper']);
            Helper::create(['user_account_id' => $helperUser->id, 'adviser_id' => $target->id, 'first_name' => 'Capacity', 'last_name' => (string) $i, 'email' => $helperUser->email]);
        }
        $this->actingAs($user)->post(route('adviser.helpers.reassign'), ['helper_ids' => [$session->helper_id], 'adviser_id' => $target->id, 'reason' => 'Coverage change'])->assertSessionHasErrors('adviser_id');
        $this->assertSame($user->adviser->id, $session->helper->fresh()->adviser_id);
        $this->assertDatabaseMissing('adviser_helper_assignments', ['helper_id' => $session->helper_id, 'adviser_id' => $target->id]);
    }
    public function test_live_conversation_channel_is_participant_only(): void
    {
        [$user, $completed] = $this->records();
        $session = Session::create(['seeker_id'=>$completed->seeker_id,'helper_id'=>$completed->helper_id,'session_type'=>'chat','session_status'=>'active','risk_level'=>'low','helper_accepted_at'=>now(),'start_time'=>now()]);
        $channels = \Illuminate\Support\Facades\Broadcast::getChannels();
        $authorize = $channels['session.{sessionId}'];
        $this->assertFalse($authorize($user, $session->id));
        $this->assertTrue($authorize($session->helper->user, $session->id));
        $this->assertTrue($authorize($session->seeker->user, $session->id));
    }
    public function test_report_filter_cannot_widen_an_unrelated_helper_selection(): void
    {
        [$user] = $this->records();
        [, $otherSession] = $this->records();
        $this->actingAs($user)->get(route('adviser.reports',['helper_id'=>$otherSession->helper_id]))->assertForbidden();
        $this->get(route('adviser.reports.export',['helper_id'=>$otherSession->helper_id,'format'=>'csv']))->assertForbidden();
    }
}
