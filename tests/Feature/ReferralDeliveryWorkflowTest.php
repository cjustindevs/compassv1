<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\ProfessionalNote;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\HelperReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the Helper -> Adviser referral delivery defect and the
 * downstream workflow that depended on it.
 *
 * A helper can only notice the need for professional support while documenting
 * a session, so recommendations must survive every concluded session state, not
 * just a live one, and must never be discarded for lack of an assigned Adviser.
 */
class ReferralDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-26 09:00', 'Asia/Manila')->utc());
    }

    private function readyHelper(): array
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create([
            'user_account_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Helper',
            'email' => $user->email,
            'status' => 'available',
            'availability' => 'available',
            'competency_level' => 3,
            'competency_risk_level' => 3,
        ]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create([
            'helper_id' => $helper->id,
            'date' => now('Asia/Manila')->toDateString(),
            'shift_start' => '18:00',
            'shift_end' => '23:00',
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);
        app(HelperReadinessService::class)->submit($user, [
            'emotionally_ready' => true,
            'willing_to_listen' => true,
            'stress_level' => 'low',
            'availability_status' => 'available',
            'exercise_completed' => 'skipped',
            'skills_confirmed' => HelperReadinessService::SKILLS,
        ]);

        return [$user, $helper->fresh()];
    }

    private function seekerUser(): User
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'Seeker'.$user->id,
            'pseudo_id' => 'PS-TEST-'.$user->id,
            'age' => 20,
            'gender' => 'male',
        ]);
        $this->consentFixture($user);

        return $user;
    }

    private function makeSession(Helper $helper, User $seekerUser, string $status = Session::STATUS_ACTIVE, ?string $riskLevel = 'low'): Session
    {
        return Session::create([
            'seeker_id' => $seekerUser->helpSeeker->id,
            'helper_id' => $helper->id,
            'risk_level' => $riskLevel,
            'session_status' => $status,
            'start_time' => now()->subHour(),
            'helper_accepted_at' => now()->subHour(),
            'submitted_at' => now(),
            'created_date' => now(),
        ]);
    }

    private function adviserUser(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'adviser', 'is_active' => true], $overrides));
        Adviser::create([
            'user_account_id' => $user->id,
            'first_name' => 'Adviser',
            'last_name' => 'Reviewer',
            'email' => $user->email,
        ]);

        return $user;
    }

    private function professional(): PsychologyProfessional
    {
        return PsychologyProfessional::create([
            'user_account_id' => User::factory()->create(['role' => 'professional', 'is_active' => true])->id,
            'first_name' => 'Professional',
            'last_name' => 'One',
            'email' => 'pro@example.com',
            'is_available' => true,
        ]);
    }

    public function test_helper_can_recommend_from_an_evaluated_session(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $adviser = $helper->adviser;
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'The seeker would benefit from professional anxiety support.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertSame($adviser->id, $referral->adviser_id);
        $this->assertSame($helper->id, $referral->helper_id);

        // The assigned Adviser must actually receive it.
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $adviser->user_account_id,
            'notification_type' => 'referral',
            'title' => 'New referral request',
        ]);
        $this->actingAs($adviser->user)
            ->get(route('adviser.referrals'))
            ->assertOk()
            ->assertSee('professional anxiety support');
    }

    public function test_helper_can_recommend_from_a_completed_session(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_COMPLETED);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'Follow-up with a professional was recommended.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Referral::STATUS_PENDING_ADVISER, Referral::where('session_id', $session->id)->firstOrFail()->status);
    }

    public function test_helper_cannot_recommend_from_a_cancelled_session(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_CANCELLED);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'This should never be accepted.',
        ])->assertStatus(409);

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_recommendation_never_stores_a_null_priority(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();

        // A session can legitimately carry no assessed risk level, and
        // referrals.priority_level is NOT NULL.
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED, null);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'No risk level was recorded for this session.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertNotNull($referral->priority_level);
        $this->assertSame(Referral::PRIORITY_LOW, $referral->priority_level);
    }

    public function test_repeat_recommendation_updates_the_row_and_renotifies(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $adviser = $helper->adviser;
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);

        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'First attempt.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Revised with more detail.']);

        // The submission must not be silently discarded as a duplicate.
        $this->assertSame(1, Referral::where('session_id', $session->id)->count());
        $this->assertSame('Revised with more detail.', $referral->fresh()->referral_reason);
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $adviser->user_account_id,
            'title' => 'New referral request',
        ]);
    }

    public function test_recommendation_cannot_roll_back_a_referral_that_progressed(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $this->professional();

        [$referral] = $this->referralAwaitingProfessional($helper, $seekerUser, 'Original recommendation.');
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->storeIdentity($seekerUser, $referral);

        $professional = $referral->fresh()->professional;
        $this->actingAs(User::find($professional->user_account_id))
            ->post(route('professional.referral.accept', $referral->id))
            ->assertRedirect();
        $this->assertSame(Referral::STATUS_ACCEPTED, $referral->fresh()->status);

        // A repeat recommendation must not drag an accepted case back to review.
        $this->actingAs($helper->user)->post(route('helper.session.referral.consent', $referral->session_id), [
            'summary' => 'Late duplicate submission.',
        ])->assertStatus(409);

        $this->assertSame(Referral::STATUS_ACCEPTED, $referral->fresh()->status);
        $this->assertSame(1, Referral::where('session_id', $referral->session_id)->count());
    }

    public function test_recommendation_conflicting_with_another_sessions_open_referral_is_rejected(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $first = $this->makeSession($helper, $seekerUser, Session::STATUS_EVALUATED);

        $this->post(route('helper.session.referral.consent', $first->id), ['summary' => 'First referral.']);

        $second = $this->makeSession($helper, $seekerUser, Session::STATUS_EVALUATED);

        $this->post(route('helper.session.referral.consent', $second->id), ['summary' => 'Second referral.'])
            ->assertStatus(422);

        $this->assertSame(1, Referral::count());
    }

    public function test_unassigned_referral_is_preserved_and_resolvable_by_a_moderator(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $helper->update(['adviser_id' => null]);
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);
        $moderator = User::factory()->create(['role' => 'moderator', 'is_active' => true]);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'Unassigned helper needs a supervisor.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER_ASSIGNMENT, $referral->status);
        $this->assertNull($referral->adviser_id);
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $moderator->id,
            'title' => 'Referral awaiting Adviser assignment',
        ]);

        $adviserUser = $this->adviserUser();
        $adviser = Adviser::where('user_account_id', $adviserUser->id)->firstOrFail();

        $this->actingAs($moderator)
            ->post(route('moderator.referrals.assign-adviser', $referral->id), ['adviser_id' => $adviser->id])
            ->assertRedirect();

        $referral->refresh();
        $this->assertSame($adviser->id, $referral->adviser_id);
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $adviserUser->id,
            'title' => 'New referral request',
        ]);

        // The referral is now actionable by the newly assigned Adviser.
        $this->actingAs($adviserUser)->get(route('adviser.referrals'))->assertOk()->assertSee('Unassigned helper');
    }

    public function test_an_administrator_can_open_and_action_the_unassigned_queue(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $helper->update(['adviser_id' => null]);
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->post(route('helper.session.referral.consent', $session->id), [
            'summary' => 'Unassigned helper needs a supervisor.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        // Administrators receive this queue notification, so the link must work.
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $admin->id,
            'title' => 'Referral awaiting Adviser assignment',
        ]);
        $this->actingAs($admin)->get(route('moderator.referrals.unassigned'))->assertOk();

        $adviserUser = $this->adviserUser();
        $adviser = Adviser::where('user_account_id', $adviserUser->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('moderator.referrals.assign-adviser', $referral->id), ['adviser_id' => $adviser->id])
            ->assertRedirect();

        $this->assertSame($adviser->id, $referral->fresh()->adviser_id);
    }

    public function test_a_helper_cannot_assign_an_adviser_to_an_unassigned_referral(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $helper->update(['adviser_id' => null]);
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Pending assignment.']);

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $adviser = Adviser::where('user_account_id', $this->adviserUser()->id)->firstOrFail();

        $this->post(route('moderator.referrals.assign-adviser', $referral->id), ['adviser_id' => $adviser->id])
            ->assertForbidden();

        $this->get(route('moderator.referrals.unassigned'))->assertForbidden();
    }

    public function test_scheduling_notifies_the_seeker_the_helper_and_the_adviser(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $this->professional();

        [$referral] = $this->referralAwaitingProfessional($helper, $seekerUser, 'Needs scheduled support.');
        $this->storeIdentity($seekerUser, $referral);
        $referral->refresh();

        $professionalUser = User::find($referral->professional->user_account_id);
        $this->actingAs($professionalUser)->post(route('professional.referral.accept', $referral->id));

        $this->actingAs($professionalUser)->post(route('professional.referral.appointment', $referral->id), [
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->addHour()->format('Y-m-d\TH:i'),
            'meeting_details' => 'Video call link to follow.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $seekerUser->id,
            'title' => 'Referral appointment updated',
        ]);
        // The helper coordinates with the seeker and must learn the new time.
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $helper->user_account_id,
            'title' => 'Referral appointment updated',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $helper->adviser->user_account_id,
            'title' => 'Referral appointment updated',
        ]);
    }

    public function test_adviser_cannot_request_clarification_when_no_helper_can_respond(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $session = $this->makeSession($helper, $this->seekerUser(), Session::STATUS_EVALUATED);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'No helper attached.']);

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        // A pre-created screening row can exist without a helper; asking that
        // referral for clarification would block it forever.
        $referral->update(['helper_id' => null]);

        $this->actingAs($helper->adviser->user)
            ->post(route('adviser.referral.request-info', $referral->id), ['info_request' => 'Please clarify the risk level.'])
            ->assertSessionHasErrors('info_request');

        $this->assertNull($referral->fresh()->clarification_requested_at);
    }

    /**
     * Drives a referral all the way to an assigned professional:
     * helper recommendation -> adviser approval -> seeker consent -> identity.
     */
    private function referralAwaitingProfessional(Helper $helper, User $seekerUser, string $summary): array
    {
        $session = $this->makeSession($helper, $seekerUser, Session::STATUS_EVALUATED);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => $summary]);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->actingAs($helper->adviser->user)
            ->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved for professional support.'])
            ->assertOk();
        $referral->refresh();

        $this->actingAs($seekerUser)
            ->post(route('referrals.consent', $referral), ['consent_given' => 1])
            ->assertRedirect(route('seeker.referrals'));
        $referral->refresh();

        return [$referral->fresh(), $session];
    }

    private function storeIdentity(User $seekerUser, Referral $referral): void
    {
        $this->actingAs($seekerUser)->post(route('identity.store', $referral), [
            'real_name' => 'Jane Seeker',
            'phone_number' => '+63 917 000 0000',
            'identity_disclosure' => 1,
        ])->assertOk();
    }

    public function test_seeker_is_notified_when_a_professional_accepts(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $this->professional();

        [$referral] = $this->referralAwaitingProfessional($helper, $seekerUser, 'Needs professional anxiety support.');
        $this->storeIdentity($seekerUser, $referral);
        $referral->refresh();

        $professional = $referral->professional;
        $this->assertNotNull($professional, 'Identity submission should forward the referral to a professional.');

        $this->actingAs(User::find($professional->user_account_id))
            ->post(route('professional.referral.accept', $referral->id))
            ->assertRedirect();

        $this->assertSame(Referral::STATUS_ACCEPTED, $referral->fresh()->status);

        // The seeker is the person the referral exists for and must be told.
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $seekerUser->id,
            'title' => 'A professional has accepted your referral',
        ]);
    }

    public function test_notes_cannot_be_added_to_a_concluded_case(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $this->professional();

        [$referral] = $this->referralAwaitingProfessional($helper, $seekerUser, 'Support needed.');
        $this->storeIdentity($seekerUser, $referral);
        $referral->refresh();

        $professionalUser = User::find($referral->professional->user_account_id);
        $this->actingAs($professionalUser)->post(route('professional.referral.accept', $referral->id));

        $payload = ['intervention_type' => 'session', 'notes' => 'Intervention note.'];
        $this->actingAs($professionalUser)->post(route('professional.cases.notes', $referral->id), $payload);
        $this->assertDatabaseHas('professional_notes', ['referral_id' => $referral->id]);

        $this->actingAs($professionalUser)->post(route('professional.cases.status', $referral->id), ['status' => Referral::STATUS_COMPLETED]);
        $this->assertSame(Referral::STATUS_COMPLETED, $referral->fresh()->status);

        // Concluded work must not accrue new clinical notes.
        $this->actingAs($professionalUser)->post(route('professional.cases.notes', $referral->id), $payload)
            ->assertStatus(409);

        $this->assertSame(1, ProfessionalNote::where('referral_id', $referral->id)->count());
    }

    public function test_identity_submission_fails_closed_when_the_seeker_has_no_pseudo_id(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $this->professional();

        [$referral] = $this->referralAwaitingProfessional($helper, $seekerUser, 'Needs professional support.');
        $seekerUser->helpSeeker->forceFill(['pseudo_id' => null])->save();

        // A legacy seeker without a vault key must surface a controlled
        // unavailable response, never a TypeError from a typed parameter.
        $this->actingAs($seekerUser)
            ->post(route('identity.store', $referral), [
                'real_name' => 'Jane Seeker',
                'phone_number' => '+63 917 000 0000',
                'identity_disclosure' => 1,
            ])
            ->assertStatus(503);

        // Nothing was written, so the referral cannot be forwarded onward.
        $this->assertNull($referral->fresh()->professional_id);
    }
}
