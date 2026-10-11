<?php

namespace Tests\Feature;

use App\Models\{Adviser, HelpSeeker, Notification, ScreeningResponse, Session, User};
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
            ->assertSee('Review screening and record decision')->assertSee('Select reassessed risk')
            ->assertDontSee('No immediate danger cues were recorded')->assertSee('Original concern');
        $this->post(route('adviser.screenings.review', $case), ['_review_session'=>$case->id,'risk_level'=>'low','reason'=>'Retry this decision'])
            ->assertSessionHasErrors(['evidence_source','allow_peer_support']);
        $response = $this->get(route('adviser.screenings'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'class="screening-review-expand"  open '));
        $this->assertSame(1, substr_count($response->getContent(), '>Retry this decision</textarea>'));
        $this->assertSame('adviser_review_required', $case->fresh()->workflow_state);
    }
}
