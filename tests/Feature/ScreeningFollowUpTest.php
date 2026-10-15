<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\ConcernCategory;
use App\Models\HelpSeeker;
use App\Models\ScreeningResponse;
use App\Models\Session;
use App\Models\User;
use App\Services\CompactScreening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class ScreeningFollowUpTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    private function adviser(): User
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Assigned', 'last_name' => 'Adviser', 'email' => $user->email]);

        return $user;
    }

    private function fixture(): array
    {
        $adviser = $this->adviser();
        $seeker = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'PrivateAlias'.$seeker->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $this->consentFixture($seeker);
        $concern = ConcernCategory::firstOrCreate(['concern_name' => 'Stress'], ['is_active' => true]);
        $case = Session::create(['seeker_id' => $seeker->helpSeeker->id, 'concern_id' => $concern->id,
            'session_type' => 'chat', 'session_status' => 'pending_review', 'workflow_state' => 'adviser_review_required',
            'risk_level' => null, 'requires_adviser_review' => true, 'review_adviser_id' => $adviser->adviser->id, 'created_date' => now()]);
        $original = ScreeningResponse::create(['session_id' => $case->id, 'seeker_id' => $case->seeker_id,
            'responses' => ['suicidal_thoughts' => 'prefer_not_to_say', 'description' => 'I need support with school.'],
            'instrument_version' => CompactScreening::SINGLE_VERSION, 'review_status' => 'adviser_review_required',
            'risk_level' => null, 'priority' => 2, 'action' => 'adviser_review_required', 'evidence_source' => 'seeker_responses']);

        return [$adviser, $seeker, $case, $original];
    }

    private function send(User $adviser, Session $case, string $note = 'Please review this question when you are comfortable.'): void
    {
        $this->actingAs($adviser)->post(route('adviser.screenings.follow-up', $case), ['follow_up_note' => $note])
            ->assertRedirect(route('adviser.screenings').'#screening-'.$case->id)->assertSessionHasNoErrors();
    }

    private function answer(User $seeker, Session $case, ScreeningResponse $original, string $answer): void
    {
        $this->actingAs($seeker)->post(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => $answer])
            ->assertRedirect(route('request.matching'))->assertSessionHasNoErrors();
    }

    public function test_request_is_a_single_exchange_with_original_data_and_pending_queue_preserved(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $before = $original->responses;
        $this->actingAs($adviser)->get(route('adviser.screenings'))->assertOk()->assertSee('Send follow-up')
            ->assertDontSee('<select name="clarified_safety_answer"', false)->assertDontSee('<button type="submit" name="review_action"', false);
        $this->send($adviser, $case);
        $this->send($adviser, $case, 'This retry must not replace the note.');
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($adviser->id, $original->fresh()->follow_up_requested_by);
        $this->assertNotNull($original->fresh()->follow_up_requested_at);
        $this->assertStringNotContainsString('retry', $original->fresh()->follow_up_note);
        $this->assertSame($before, $original->fresh()->responses);
        $this->assertNull($case->fresh()->risk_level);
        $this->assertSame(1, Session::pendingScreeningReviewForAdviser($adviser->adviser->id)->count());
        $this->assertDatabaseCount('queue_requests', 0);
        $this->assertDatabaseCount('screening_responses', 1);
        $this->get(route('adviser.screenings'))->assertSee('Waiting for the Seeker')->assertDontSee('<button type="submit" name="review_action"', false);
        $this->actingAs($seeker)->get(route('request.matching'))->assertOk()->assertSee('Your Adviser')
            ->assertSee(CompactScreening::SINGLE_QUESTION)->assertSee('Send response')->assertSee('data-followup-form', false);
    }

    public function test_response_is_owned_validated_immutable_and_does_not_automatically_approve(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->actingAs($seeker)->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'no'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->send($adviser, $case);
        $this->actingAs($seeker)->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id + 50, 'follow_up_answer' => 'no'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->answer($seeker, $case, $original, 'no');
        $this->answer($seeker, $case, $original, 'no');
        $this->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'yes'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('no', $original->fresh()->follow_up_answer);
        $this->assertNotNull($original->fresh()->follow_up_answered_at);
        $this->assertNull($case->fresh()->peer_support_approved_at);
        $this->assertSame('adviser_review_required', $case->fresh()->workflow_state);
        $this->assertDatabaseCount('queue_requests', 0);
        $this->get(route('request.matching'))->assertSee('Response saved:')->assertDontSee('<input type="radio" name="follow_up_answer"', false);
        $this->actingAs($adviser)->getJson(route('adviser.notifications.unread-count', ['include_reviews' => 1]))
            ->assertOk()->assertJsonCount(1, 'reviews')->assertJsonPath('screening_count', 1);
    }

    public function test_approval_uses_the_saved_answer_and_keeps_existing_preferences_and_matching_steps(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->send($adviser, $case);
        $this->answer($seeker, $case, $original, 'no');
        $this->actingAs($adviser)->get(route('adviser.screenings'))->assertOk()->assertSee('Approve peer support')
            ->assertSee('Routing classification:')->assertDontSee('<select name="risk_level"', false)->assertDontSee('<input name="evidence_source"', false);
        $data = ['use_seeker_follow_up' => 1, 'review_action' => 'approve', 'reason' => 'Reviewed the saved follow-up response.'];
        $this->postJson(route('adviser.screenings.review', $case), array_replace($data, ['reason' => ' ']))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson(route('adviser.screenings.review', $case), array_replace($data, ['review_action' => 'record']))->assertUnprocessable()->assertJsonValidationErrors('review_action');
        $this->post(route('adviser.screenings.review', $case), $data + ['risk_level' => 'high', 'evidence_source' => 'Untrusted replacement source'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('low', $case->fresh()->risk_level);
        $this->assertSame('session_preferences_required', $case->fresh()->workflow_state);
        $this->assertFalse($case->fresh()->requires_adviser_review);
        $this->assertNotNull($case->fresh()->peer_support_approved_at);
        $review = ScreeningResponse::latest('id')->firstOrFail();
        $this->assertSame(['suicidal_thoughts' => 'no'], $review->responses);
        $this->assertSame('seeker_follow_up', $review->evidence_source);
        $this->assertSame('single_safety_no', $review->rule_code);
        $this->assertSame('prefer_not_to_say', $original->fresh()->responses['suicidal_thoughts']);
        $this->assertDatabaseCount('referrals', 0);
        $this->assertDatabaseCount('queue_requests', 0);
        $this->postJson(route('adviser.screenings.review', $case), $data)->assertRedirect();
        $this->assertDatabaseCount('screening_responses', 2);
        $this->actingAs($seeker)->get(route('request.matching'))->assertRedirect(route('request.preferences'));
        $this->get(route('seeker.requests.show', $case))->assertOk()->assertSee('Original safety answer')
            ->assertSee('Prefer not to say')->assertSee('Follow-up response')->assertDontSee('Reviewed the saved follow-up response.');
        $this->get(route('request.preferences'))->assertOk();
        $this->post(route('request.preferences.process'), ['support_mode' => 'chat', 'preferred_language' => 'English'])
            ->assertRedirect(route('request.matching'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('queue_requests', 1);
        $this->assertSame('waiting', $case->fresh()->queue->request_status);
    }

    public function test_yes_uses_existing_high_routing_and_referral_review_without_an_automatic_session(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->send($adviser, $case);
        $this->answer($seeker, $case, $original, 'yes');
        $this->assertNull($case->fresh()->risk_level);
        $this->actingAs($adviser)->post(route('adviser.screenings.review', $case), [
            'use_seeker_follow_up' => 1, 'review_action' => 'approve', 'reason' => 'Reviewed the disclosed safety answer under Adviser supervision.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('high', $case->fresh()->risk_level);
        $this->assertDatabaseHas('referrals', ['session_id' => $case->id, 'priority_level' => 'high', 'status' => 'pending_adviser', 'help_seeker_consent' => false]);
        $this->assertSame('screening_completed', $case->fresh()->session_status);
        $this->assertDatabaseCount('queue_requests', 0);
    }

    public function test_declining_or_not_answering_keeps_review_pending_and_cannot_be_bypassed(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->send($adviser, $case);
        $approve = ['use_seeker_follow_up' => 1, 'review_action' => 'approve', 'reason' => 'Review the saved Seeker response.'];
        $this->actingAs($adviser)->postJson(route('adviser.screenings.review', $case), $approve)->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->answer($seeker, $case, $original, 'prefer_not_to_say');
        $this->actingAs($adviser)->postJson(route('adviser.screenings.review', $case), $approve)->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->postJson(route('adviser.screenings.review', $case), ['review_action' => 'approve', 'use_clarified_safety_answer' => 1,
            'clarified_safety_answer' => 'no', 'risk_level' => 'low', 'reason' => 'Attempt to replace the saved response.', 'evidence_source' => 'External note'])
            ->assertUnprocessable()->assertJsonValidationErrors('clarified_safety_answer');
        $this->get(route('adviser.screenings'))->assertSee('still prefers not to say')->assertDontSee('<button type="submit" name="review_action"', false);
        $this->assertSame(1, Session::pendingScreeningReviewForAdviser($adviser->adviser->id)->count());
        $this->assertNull($case->fresh()->risk_level);
        $this->assertNull($case->fresh()->peer_support_approved_at);
        $this->assertDatabaseCount('screening_responses', 1);
        $this->assertDatabaseCount('queue_requests', 0);
    }

    public function test_only_the_assigned_active_adviser_and_owning_active_seeker_can_use_the_exchange(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $otherAdviser = $this->adviser();
        $this->actingAs($otherAdviser)->postJson(route('adviser.screenings.follow-up', $case), [])->assertForbidden();
        $adviser->update(['is_active' => false]);
        $this->actingAs($adviser)->postJson(route('adviser.screenings.follow-up', $case), [])->assertForbidden();
        $adviser->update(['is_active' => true]);
        $this->send($adviser, $case, '<script>privateNote()</script>');
        $other = User::factory()->create(['role' => 'seeker']);
        HelpSeeker::create(['user_account_id' => $other->id, 'generated_alias' => 'OtherSeeker', 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $this->actingAs($other)->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'no'])->assertForbidden();
        $this->get(route('request.matching'))->assertRedirect(route('request.screening'));
        $this->actingAs($seeker)->get(route('request.matching'))->assertOk()->assertSee('&lt;script&gt;privateNote()', false)
            ->assertDontSee('<script>privateNote()', false)->assertDontSee($adviser->email);
        $seeker->update(['is_active' => false]);
        $this->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'no'])->assertForbidden();
        $this->assertNull($original->fresh()->follow_up_answer);
        $this->assertDatabaseCount('notifications', 1);
        $helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $this->actingAs($helperUser)->postJson(route('adviser.screenings.follow-up', $case), [])->assertForbidden();
        $this->postJson(route('request.follow-up.respond', $case), ['screening_id' => $original->id, 'follow_up_answer' => 'no'])->assertForbidden();
    }

    public function test_follow_up_rejects_closed_legacy_and_already_answered_intakes_and_limits_note_length(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->actingAs($adviser)->postJson(route('adviser.screenings.follow-up', $case), ['follow_up_note' => str_repeat('a', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_note');
        $case->update(['session_status' => 'cancelled']);
        $this->postJson(route('adviser.screenings.follow-up', $case), [])->assertUnprocessable();
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->actingAs($adviser);
        $original->update(['instrument_version' => CompactScreening::FORM_VERSION]);
        $this->postJson(route('adviser.screenings.follow-up', $case), [])->assertUnprocessable();
        $original->update(['instrument_version' => CompactScreening::SINGLE_VERSION, 'responses' => ['suicidal_thoughts' => 'yes']]);
        $this->postJson(route('adviser.screenings.follow-up', $case), [])->assertUnprocessable();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_response_to_a_follow_up_cannot_reopen_a_request_closed_in_another_tab(): void
    {
        [$adviser,$seeker,$case,$original] = $this->fixture();
        $this->send($adviser,$case);
        $case->update(['session_status' => 'cancelled']);
        $this->actingAs($seeker)->postJson(route('request.follow-up.respond',$case),['screening_id' => $original->id, 'follow_up_answer' => 'no'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_answer');
        $this->assertNull($original->fresh()->follow_up_answer);
        $this->assertSame('cancelled',$case->fresh()->session_status);
        $this->assertDatabaseCount('notifications',1);
        $this->assertDatabaseCount('queue_requests',0);
    }
}
