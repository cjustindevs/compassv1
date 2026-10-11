<?php

namespace Tests\Feature;

use App\Models\{Adviser, ConcernCategory, Helper, HelpSeeker, Notification, ScreeningResponse, Session, SessionReport, User};
use App\Services\CompactScreening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserScreeningNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(): User
    {
        $user = User::factory()->create(['role'=>'adviser','is_active'=>true]);
        Adviser::create(['user_account_id'=>$user->id,'first_name'=>'Assigned','last_name'=>'Reviewer','email'=>$user->email]);
        return $user;
    }

    private function review(User $adviser, ?string $risk = null): Session
    {
        $owner = User::factory()->create(['role'=>'seeker','is_active'=>true]);
        $seeker = HelpSeeker::create(['user_account_id'=>$owner->id,'generated_alias'=>'PrivateAlias'.$owner->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        return Session::create(['seeker_id'=>$seeker->id,'session_type'=>'chat','session_status'=>'pending_review',
            'workflow_state'=>'adviser_review_required','requires_adviser_review'=>true,'risk_level'=>$risk,
            'review_adviser_id'=>$adviser->adviser->id,'created_date'=>now()]);
    }

    private function notice(User $owner, Session $session, array $attributes = []): Notification
    {
        return Notification::create(array_replace(['user_account_id'=>$owner->id,'title'=>'Screening review required',
            'message'=>'A preliminary screening needs your review before peer support.','notification_type'=>'system',
            'link'=>'/adviser/screenings#screening-'.$session->id,'status'=>'unread'], $attributes));
    }

    public function test_notice_feed_only_returns_unread_owned_pending_reviews_and_keeps_emergencies_separate(): void
    {
        $user = $this->adviser(); $other = $this->adviser();
        $case = $this->review($user);
        $visible = $this->notice($user, $case);
        $this->notice($user, $case, ['status'=>'read']);
        $this->notice($user, $case, ['archived_at'=>now()]);
        $foreign = $this->review($other);
        $this->notice($other, $foreign, ['message'=>'Private other-Adviser notification']);
        $this->notice($user, $foreign); // A stale assignment must not offer another Adviser's review.
        $completed = $this->review($user); $completed->update(['session_status'=>'cancelled']);
        $this->notice($user, $completed);
        $emergency = $this->review($user, 'emergency');
        $last = $this->notice($user, $emergency);
        $response = $this->actingAs($user)->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>1]))
            ->assertOk()->assertJsonCount(1, 'reviews')->assertJsonPath('reviews.0.id', $visible->id)
            ->assertJsonPath('screening_count', 2)->assertJsonPath('review_cursor', $last->id);
        $this->assertStringNotContainsString('Private other-Adviser notification', $response->getContent());
        $this->assertSame('unread', $visible->fresh()->status);
        $case->update(['requires_adviser_review'=>false]);
        $this->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>1]))
            ->assertOk()->assertJsonCount(0, 'reviews')->assertJsonPath('screening_count', 1);
        $this->getJson(route('adviser.notifications.unread-count'))->assertOk()->assertJsonStructure(['count']);
        $this->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>'invalid']))->assertUnprocessable();
    }

    public function test_review_feed_is_bounded_and_rejects_other_roles_and_inactive_advisers(): void
    {
        $user = $this->adviser(); $case = $this->review($user);
        for ($i=0; $i<12; $i++) $this->notice($user, $case);
        $this->actingAs($user)->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>1]))
            ->assertOk()->assertJsonCount(10, 'reviews')->assertJsonPath('screening_count', 1);
        $this->actingAs(User::factory()->create(['role'=>'helper']))
            ->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>1]))->assertForbidden();
        $user->update(['is_active'=>false]);
        $this->actingAs($user)->getJson(route('adviser.notifications.unread-count', ['include_reviews'=>1]))->assertForbidden();
    }

    public function test_single_unknown_intake_shows_actual_answer_and_opens_the_correct_failed_form(): void
    {
        $user = $this->adviser(); $case = $this->review($user); $another = $this->review($user);
        foreach ([$case, $another] as $session) {
            ScreeningResponse::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,
                'responses'=>['suicidal_thoughts'=>'prefer_not_to_say','description'=>'Original concern'],
                'instrument_version'=>CompactScreening::SINGLE_VERSION,'review_status'=>'adviser_review_required',
                'priority'=>2,'action'=>'adviser_review_required']);
        }
        $this->actingAs($user)->get(route('adviser.screenings'))->assertOk()->assertSee('Prefer not to say')
            ->assertSee('Review screening and record decision')->assertSee('Send follow-up')
            ->assertDontSee('No immediate danger cues were recorded')->assertSee('Original concern');
        $note = 'Retry this follow-up '.str_repeat('x',300);
        $this->post(route('adviser.screenings.follow-up', $case), ['_review_session'=>$case->id,'follow_up_note'=>$note])
            ->assertSessionHasErrors('follow_up_note');
        $response = $this->get(route('adviser.screenings'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'class="screening-review-expand"  open '));
        $this->assertSame(1, substr_count($response->getContent(), '>'.$note.'</textarea>'));
        $this->assertSame('adviser_review_required', $case->fresh()->workflow_state);
    }

    private function singleScreening(Session $case): ScreeningResponse
    {
        $concern = ConcernCategory::firstOrCreate(['concern_name'=>'Stress'], ['is_active'=>true]);
        $case->update(['concern_id'=>$concern->id]);
        return ScreeningResponse::create(['session_id'=>$case->id,'seeker_id'=>$case->seeker_id,
            'responses'=>['suicidal_thoughts'=>'prefer_not_to_say','description'=>'Original concern'],
            'instrument_version'=>CompactScreening::SINGLE_VERSION,'review_status'=>'adviser_review_required',
            'priority'=>2,'action'=>'adviser_review_required']);
    }

    public function test_changed_classification_redirects_to_the_form_with_errors_instead_of_a_bare_422(): void
    {
        $user=$this->adviser(); $case=$this->review($user); $original=$this->singleScreening($case);
        $data=['_review_session'=>$case->id,'review_action'=>'approve','risk_level'=>'low',
            'reason'=>'Reviewed the original response.','evidence_source'=>'Original screening'];
        $this->actingAs($user)->from(route('adviser.screenings'))->post(route('adviser.screenings.review',$case),$data)
            ->assertRedirect(route('adviser.screenings'))->assertSessionHasErrors('risk_level');
        $this->assertSame('adviser_review_required',$case->fresh()->workflow_state);
        $this->assertNull($case->fresh()->risk_level);
        $this->assertSame('prefer_not_to_say',$original->fresh()->responses['suicidal_thoughts']);
        $this->assertDatabaseCount('screening_responses',1);
        $this->assertDatabaseCount('queue_requests',0);
        $this->get(route('adviser.screenings'))->assertOk()->assertSee('Clarify the original safety answer before assigning')
            ->assertSee('Send follow-up')->assertDontSee('name="answers[current_suicide_plan]"',false);
        $this->postJson(route('adviser.screenings.review',$case),$data)->assertUnprocessable()->assertJsonValidationErrors('risk_level');
    }

    public function test_single_answer_approval_preserves_original_and_returns_seeker_to_preferences_without_starting_a_session(): void
    {
        $user=$this->adviser(); $case=$this->review($user); $original=$this->singleScreening($case);
        $this->actingAs($user)->from(route('adviser.screenings'))->post(route('adviser.screenings.review',$case),[
            '_review_session'=>$case->id,'review_action'=>'approve','use_clarified_safety_answer'=>1,'clarified_safety_answer'=>'no',
            'risk_level'=>'low','reason'=>'Obtained a clarified answer directly from the Seeker.','evidence_source'=>'Adviser follow-up with the Seeker',
        ])->assertRedirect(route('adviser.screenings'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('prefer_not_to_say',$original->fresh()->responses['suicidal_thoughts']);
        $clarified=ScreeningResponse::latest('id')->firstOrFail();
        $this->assertSame(['suicidal_thoughts'=>'no'],$clarified->responses);
        $this->assertSame(CompactScreening::SINGLE_VERSION,$clarified->instrument_version);
        $this->assertSame('single_safety_no',$clarified->rule_code);
        $this->assertSame('session_preferences_required',$case->fresh()->workflow_state);
        $this->assertNotNull($case->fresh()->peer_support_approved_at);
        $this->assertFalse($case->fresh()->requires_adviser_review);
        $this->assertDatabaseCount('queue_requests',0);
        $this->assertDatabaseMissing('counseling_sessions',['id'=>$case->id,'session_status'=>'active']);
        $this->actingAs($case->seeker->user)->get(route('request.matching'))->assertRedirect(route('request.preferences'));
    }

    public function test_single_clarification_is_validated_and_emergency_approval_is_still_blocked(): void
    {
        $user=$this->adviser(); $case=$this->review($user); $this->singleScreening($case);
        $data=['review_action'=>'approve','use_clarified_safety_answer'=>1,'clarified_safety_answer'=>'yes',
            'risk_level'=>'low','reason'=>'Follow-up obtained directly from the Seeker.','evidence_source'=>'Direct follow-up'];
        $this->actingAs($user)->postJson(route('adviser.screenings.review',$case),$data)
            ->assertUnprocessable()->assertJsonValidationErrors('risk_level');
        $this->postJson(route('adviser.screenings.review',$case),array_replace($data,['clarified_safety_answer'=>'prefer_not_to_say']))
            ->assertUnprocessable()->assertJsonValidationErrors('clarified_safety_answer');
        $this->postJson(route('adviser.screenings.review',$case),array_replace($data,['reason'=>'          ']))
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($this->adviser())->postJson(route('adviser.screenings.review',$case),$data)->assertForbidden();
        $emergency=$this->review($user,'emergency');
        $this->actingAs($user)->from(route('adviser.screenings'))->post(route('adviser.screenings.review',$emergency),[
            'review_action'=>'approve','risk_level'=>'emergency','reason'=>'Confirmed the current emergency classification.','evidence_source'=>'Existing emergency review',
        ])->assertRedirect(route('adviser.screenings'))->assertSessionHasErrors('allow_peer_support');
        $this->assertNull($emergency->fresh()->peer_support_approved_at);
        $this->assertDatabaseCount('screening_responses',1);
    }

    public function test_helper_reassessment_keeps_its_complete_follow_up_and_does_not_reopen_a_completed_single_intake_session(): void
    {
        $user=$this->adviser(); $case=$this->review($user,'low'); $original=$this->singleScreening($case);
        $original->update(['responses'=>['suicidal_thoughts'=>'no','description'=>'Original concern']]);
        $helperUser=User::factory()->create(['role'=>'helper','is_active'=>true]);
        $helper=Helper::create(['user_account_id'=>$helperUser->id,'adviser_id'=>$user->adviser->id,
            'first_name'=>'Assigned','last_name'=>'Helper','email'=>$helperUser->email]);
        $case->update(['helper_id'=>$helper->id,'session_status'=>'completed','workflow_state'=>'evaluation_pending']);
        $report=SessionReport::create(['session_id'=>$case->id,'session_summary'=>'Completed support session',
            'risk_level_assessed'=>'moderate','reassessment_requested_at'=>now()]);
        $this->actingAs($user)->get(route('adviser.screenings'))->assertOk()->assertSee('use_clarified_answers')
            ->assertDontSee('type="checkbox" name="use_clarified_safety_answer"',false);
        $this->post(route('adviser.screenings.review',$case),[
            'review_action'=>'approve','use_clarified_answers'=>1,
            'answers'=>array_replace(array_fill_keys(CompactScreening::FIELDS,0),['difficulty_coping'=>1]),
            'risk_level'=>'moderate','reason'=>'Reviewed Helper observations and clarified all support answers.',
            'evidence_source'=>'Helper session notes and Adviser follow-up',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed',$case->fresh()->session_status);
        $this->assertSame('moderate',$case->fresh()->risk_level);
        $this->assertNotNull($report->fresh()->reassessment_reviewed_at);
        $this->assertSame('no',$original->fresh()->responses['suicidal_thoughts']);
        $this->assertDatabaseCount('queue_requests',0);
    }
}
