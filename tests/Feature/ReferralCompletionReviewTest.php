<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\ReferralAppointment;
use App\Models\ReferralCompletionReview;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReferralCompletionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
    }

    private function fixture(): array
    {
        $au = User::factory()->create(['role' => 'adviser']);
        $a = Adviser::create(['user_account_id' => $au->id, 'first_name' => 'Completion', 'last_name' => 'Reviewer', 'email' => $au->email]);
        $hu = User::factory()->create(['role' => 'helper']);
        $h = Helper::create(['user_account_id' => $hu->id, 'adviser_id' => $a->id, 'first_name' => 'Assigned', 'last_name' => 'Helper', 'email' => $hu->email]);
        $su = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $su->id, 'generated_alias' => 'PrivateAlias'.$su->id, 'pseudo_id' => 'PS-COMP-'.$su->id]);
        $pu = User::factory()->create(['role' => 'professional']);
        $p = PsychologyProfessional::create(['user_account_id' => $pu->id, 'first_name' => 'Assigned', 'last_name' => 'Professional', 'email' => $pu->email, 'is_available' => true]);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $h->id, 'review_adviser_id' => $a->id, 'session_status' => 'active', 'session_type' => 'chat', 'start_time' => now(), 'helper_accepted_at' => now(), 'created_date' => now()]);
        $r = Referral::create(['session_id' => $session->id, 'helper_id' => $h->id, 'adviser_id' => $a->id, 'professional_id' => $p->id, 'status' => 'in_progress', 'approved_at' => now(), 'help_seeker_consent' => true, 'consent_obtained_at' => now(), 'priority_level' => 'low', 'referral_reason' => 'Synthetic recommendation.', 'referral_date' => now()]);

        return [$au, $pu, $r, $hu, $su];
    }

    private function submit(User $professional, Referral $r, array $data = []): ReferralCompletionReview
    {
        $this->actingAs($professional)->post(route('referrals.completion.submit', $r), array_replace(['outcome' => 'Private synthetic professional outcome.', 'follow_up_required' => false], $data))->assertRedirect()->assertSessionHasNoErrors();

        return $r->completionReviews()->latest('id')->firstOrFail();
    }

    private function confirm(User $adviser, ReferralCompletionReview $report, array $data = []): void
    {
        $this->actingAs($adviser)->post(route('referrals.completion.review', $report), array_replace(['decision' => 'confirmed', 'review_notes' => 'Reviewed documented work and support disposition.'], $data))->assertRedirect()->assertSessionHasNoErrors();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_professional_submission_waits_for_adviser_and_emergency_is_separate(bool $emergency): void
    {
        [$adviser,$professional,$r,$helper,$seeker] = $this->fixture();
        if ($emergency) {
            $alert = EmergencyAlert::create(['session_id' => $r->session_id, 'seeker_id' => $r->session->seeker_id, 'adviser_id' => $r->adviser_id, 'referral_id' => $r->id, 'trigger_reason' => 'Synthetic emergency']);
        }
        $report = $this->submit($professional, $r);
        $this->assertSame('in_progress', $r->fresh()->status);
        $this->assertNull($r->fresh()->completed_at);
        $this->assertNotSame($report->summary, $report->getRawOriginal('summary'));
        $this->assertDatabaseHas('notifications', ['user_account_id' => $adviser->id, 'title' => 'Referral completion review requested']);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $seeker->id, 'title' => 'Referral support completed']);
        $this->get(route('professional.referral.show', $r))->assertOk()->assertSee('Awaiting Adviser resolution')->assertDontSee('id="completionReportDialog"', false);
        $this->actingAs($adviser)->get(route('adviser.referral.show', $r->case_reference))->assertOk()->assertSee('Completion review pending')->assertSee($report->summary);
        $this->get(route('adviser.referrals'))->assertOk()->assertSee('Review completion #'.$r->id);
        $this->confirm($adviser, $report);
        $this->assertSame('completed', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->completed_at);
        $this->assertSame('confirmed', $report->fresh()->review_status);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $professional->id, 'title' => 'Referral completion confirmed']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $seeker->id, 'title' => 'Referral support completed']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->id, 'title' => 'Referral support completed']);
        $this->assertStringNotContainsString('Private synthetic', $r->fresh()->outcome);
        $this->assertSame('active', $r->session->fresh()->session_status);
        if ($emergency) {
            $this->assertNotContains($alert->fresh()->status, ['resolved', 'closed']);
            $this->post(route('adviser.emergencies.resolve', $alert), ['resolution_notes' => 'Separate emergency coordination and follow-up documented.'])->assertSessionHasNoErrors();
            $this->assertSame('resolved', $alert->fresh()->status);
        }
    }

    public function test_resolution_first_does_not_complete_the_professional_referral(): void
    {
        [$a,$p,$r] = $this->fixture();
        $alert = EmergencyAlert::create(['session_id' => $r->session_id, 'seeker_id' => $r->session->seeker_id, 'adviser_id' => $r->adviser_id, 'referral_id' => $r->id, 'trigger_reason' => 'Synthetic emergency']);
        $this->actingAs($a)->post(route('adviser.emergencies.resolve', $alert), ['resolution_notes' => 'Emergency response documented separately.'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $r->fresh()->status);
        $this->confirm($a, $this->submit($p, $r));
        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_follow_up_resubmission_preserves_the_original_and_rejects_stale_decisions(): void
    {
        [$a,$p,$r] = $this->fixture();
        $first = $this->submit($p, $r);
        $this->actingAs($a)->post(route('referrals.completion.review', $first), ['decision' => 'follow_up_requested', 'review_notes' => 'Please document the follow-up support arrangement.'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $r->fresh()->status);
        $this->actingAs($p)->get(route('professional.referral.show', $r))->assertOk()->assertSee('Follow-up requested')->assertSee('Submit updated completion report');
        $second = $this->submit($p, $r, ['outcome' => 'Updated synthetic outcome with follow-up arranged.']);
        $this->assertDatabaseCount('referral_completion_reviews', 2);
        $this->assertSame('Private synthetic professional outcome.', $first->fresh()->summary);
        $this->actingAs($a)->post(route('referrals.completion.review', $first), ['decision' => 'confirmed', 'review_notes' => 'Trying to confirm an outdated report.'])->assertSessionHasErrors('completion');
        $this->confirm($a, $second);
    }

    public function test_duplicate_submission_and_confirmation_do_not_duplicate_notifications(): void
    {
        [$a,$p,$r] = $this->fixture();
        $first = $this->submit($p, $r);
        $count = Notification::count();
        $this->submit($p, $r);
        $this->assertSame($count, Notification::count());
        $this->assertDatabaseCount('referral_completion_reviews', 1);
        $this->confirm($a, $first);
        $count = Notification::count();
        $this->actingAs($a)->post(route('referrals.completion.review', $first), ['decision' => 'confirmed', 'review_notes' => 'Repeat confirmation is not another completion.'])->assertSessionHasErrors('completion');
        $this->assertSame($count, Notification::count());
    }

    public function test_outstanding_appointments_and_follow_up_need_documented_disposition(): void
    {
        [$a,$p,$r] = $this->fixture();
        $appointment = ReferralAppointment::create(['referral_id' => $r->id, 'professional_id' => $r->professional_id, 'created_by' => $p->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'meeting_details' => 'Synthetic instructions.', 'status' => 'scheduled']);
        $report = $this->submit($p, $r, ['follow_up_required' => true, 'follow_up_notes' => 'Continuing support to be coordinated.']);
        $this->actingAs($a)->post(route('referrals.completion.review', $report), ['decision' => 'confirmed', 'review_notes' => 'Reviewed the completion request.'])->assertSessionHasErrors('appointments_reason');
        $this->post(route('referrals.completion.review', $report), ['decision' => 'confirmed', 'review_notes' => 'Reviewed the completion request.', 'appointments_action' => 'completed', 'appointments_reason' => 'Cannot claim a future appointment was completed.'])->assertSessionHasErrors('appointments_action');
        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->confirm($a, $report, ['appointments_action' => 'cancelled', 'appointments_reason' => 'Future appointment cancelled; follow-up transferred to the documented service.']);
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertNotNull($report->fresh()->appointment_disposition_notes);
        $this->assertNotSame($report->fresh()->appointment_disposition_notes, $report->fresh()->getRawOriginal('appointment_disposition_notes'));
    }

    public function test_roles_validation_and_withdrawal_block_unauthorized_completion(): void
    {
        [$a,$p,$r,$helper,$seeker] = $this->fixture();
        $payload = ['outcome' => 'Synthetic completion summary.', 'follow_up_required' => false];
        $this->actingAs($helper)->post(route('referrals.completion.submit', $r), $payload)->assertForbidden();
        $other = User::factory()->create(['role' => 'professional']);
        PsychologyProfessional::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Professional', 'email' => $other->email]);
        $this->actingAs($other)->post(route('referrals.completion.submit', $r), $payload)->assertForbidden();
        $this->actingAs($p)->post(route('referrals.completion.submit', $r), ['outcome' => '          ', 'follow_up_required' => false])->assertSessionHasErrors('outcome');
        $this->post(route('referrals.completion.submit', $r), ['outcome' => 'Synthetic valid summary.', 'follow_up_required' => true])->assertSessionHasErrors('follow_up_notes');
        $report = $this->submit($p, $r);
        $otherA = User::factory()->create(['role' => 'adviser']);
        Adviser::create(['user_account_id' => $otherA->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $otherA->email]);
        $this->actingAs($otherA)->post(route('referrals.completion.review', $report), ['decision' => 'confirmed', 'review_notes' => 'An unrelated reviewer cannot finalize this.'])->assertForbidden();
        $this->actingAs($seeker)->post(route('seeker.privacy.decision'), ['purpose' => 'referral', 'decision' => 'withdrawn', 'referral_id' => $r->id])->assertSessionHasNoErrors();
        $this->actingAs($a)->post(route('referrals.completion.review', $report), ['decision' => 'confirmed', 'review_notes' => 'Withdrawal must not be replaced by completion.'])->assertSessionHasErrors('completion');
        $this->assertSame('closed', $r->fresh()->status);
        $this->assertSame('pending', $report->fresh()->review_status);
    }

    public function test_legacy_outcome_endpoints_cannot_bypass_review_or_missing_summary(): void
    {
        [$a,$p,$r] = $this->fixture();
        $this->actingAs($p)->patchJson(route('referrals.outcome', $r), ['status' => 'completed', 'follow_up_required' => false])->assertUnprocessable();
        $this->patchJson(route('referrals.outcome', $r), ['status' => 'closed', 'outcome' => 'Legacy API completion report.', 'follow_up_required' => false])->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertDatabaseCount('referral_completion_reviews', 1);
        $this->assertNull($r->fresh()->closed_date);
        $this->confirm($a, $r->completionReviews()->sole());
    }

    public function test_pending_review_blocks_note_and_appointment_changes_until_follow_up(): void
    {
        [$a,$p,$r] = $this->fixture();
        $report = $this->submit($p, $r);
        $this->actingAs($p)->postJson(route('professional.cases.notes', $r->case_reference), ['intervention_type' => 'follow_up', 'notes' => 'Must not change submitted work.'])->assertStatus(409);
        $this->postJson(route('professional.referral.appointment', $r), ['starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'), 'meeting_format' => 'in_person', 'meeting_details' => 'Synthetic appointment.'])->assertStatus(409);
        $this->actingAs($a)->post(route('referrals.completion.review', $report), ['decision' => 'follow_up_requested', 'review_notes' => 'Please provide a new follow-up note.'])->assertSessionHasNoErrors();
        $this->actingAs($p)->post(route('professional.cases.notes', $r->case_reference), ['intervention_type' => 'follow_up', 'notes' => 'New follow-up work documented.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('professional_notes', 1);
    }

    public function test_inactive_reviewer_reassignment_exposes_only_operational_information(): void
    {
        [$a,$p,$r] = $this->fixture();
        $a->update(['is_active' => false]);
        $mod = User::factory()->create(['role' => 'moderator']);
        $report = $this->submit($p, $r);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $mod->id, 'title' => 'Completion reviewer assignment needed']);
        $this->actingAs($mod)->get(route('moderator.referrals.unassigned'))->assertOk()->assertSee('Referral #'.$r->id)->assertDontSee($report->summary);
        $new = User::factory()->create(['role' => 'adviser']);
        $newA = Adviser::create(['user_account_id' => $new->id, 'first_name' => 'New', 'last_name' => 'Reviewer', 'email' => $new->email]);
        $this->post(route('referrals.completion.assign', $r), ['adviser_id' => $newA->id])->assertSessionHasNoErrors();
        $this->assertSame($newA->id, $r->fresh()->adviser_id);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $new->id, 'title' => 'Referral completion review requested']);
        $this->confirm($new, $report);
    }

    public function test_different_emergency_reviewer_gets_its_own_authorized_link(): void
    {
        [$a,$p,$r] = $this->fixture();
        $other = User::factory()->create(['role' => 'adviser']);
        $otherA = Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Emergency', 'last_name' => 'Reviewer', 'email' => $other->email]);
        $alert = EmergencyAlert::create(['session_id' => $r->session_id, 'seeker_id' => $r->session->seeker_id, 'adviser_id' => $otherA->id, 'referral_id' => $r->id, 'trigger_reason' => 'Synthetic emergency']);
        $report = $this->submit($p, $r);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $other->id, 'link' => '/adviser/emergencies/'.$alert->id, 'title' => 'Professional completion submitted']);
        $this->actingAs($other)->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Professional completion report:')->assertDontSee($report->summary);
        $this->post(route('referrals.completion.review', $report), ['decision' => 'confirmed', 'review_notes' => 'Emergency reviewer is not automatically referral reviewer.'])->assertForbidden();
    }

    public function test_embedded_identity_uses_one_container_and_no_nested_dialog_or_bell(): void
    {
        [$a,$p,$r,$helper,$seeker] = $this->fixture();
        $html = $this->actingAs($seeker)->get(route('identity.form', [$r, 'embedded' => 1]))->assertOk()->assertSee('Identity Disclosure Consent')->assertDontSee('<dialog', false)->assertDontSee('live-notifications.js')->getContent();
        $doc = new \DOMDocument;
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $this->assertCount(1,$xpath->query('//*[@data-identity-container]'));
        $this->assertCount(2,$xpath->query('//*[@data-identity-close]'));
        $this->assertCount(1,$xpath->query('//button[@data-identity-submit]'));
        $this->assertStringContainsString('min-height:0;max-height:none;overflow-y:auto',$html);
        $this->actingAs($p)->get(route('professional.referral.show',$r))->assertOk()->assertSee('professional-referral.css')->assertSee('Submit completion for Adviser review');
    }
}
