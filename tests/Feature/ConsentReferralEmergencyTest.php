<?php

namespace Tests\Feature;

use App\Events\EmergencyTriggered;
use App\Events\ModeratorAlert;
use App\Events\ReferralConsentUpdated;
use App\Models\Adviser;
use App\Models\ConcernCategory;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\CompactScreening;
use App\Services\ConsentService;
use App\Services\HelperReadinessService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class ConsentReferralEmergencyTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-16 20:00', 'Asia/Manila')->utc());
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
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'shift_start' => '18:00', 'shift_end' => '23:00', 'created_by' => $user->id, 'is_active' => true]);
        $this->actingAs($user);
        app(HelperReadinessService::class)->submit($user, $this->readiness());

        return [$user, $helper->fresh()];
    }

    private function readiness(array $overrides = []): array
    {
        return array_replace(['emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'low', 'availability_status' => 'available', 'exercise_completed' => 'skipped', 'skills_confirmed' => HelperReadinessService::SKILLS], $overrides);
    }

    private function seekerUser(): User
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Seeker'.$user->id, 'age' => 20, 'gender' => 'male']);
        $this->consentFixture($user);

        return $user;
    }

    private function activeSession(Helper $helper, User $seekerUser): Session
    {
        $seeker = $seekerUser->helpSeeker;

        return Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'risk_level' => 'low',
            'session_status' => Session::STATUS_ACTIVE,
            'start_time' => now(),
            'helper_accepted_at' => now(),
            'submitted_at' => now(),
            'created_date' => now(),
        ]);
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

    public function test_recommendation_creates_pending_adviser_referral_and_is_idempotent(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'The seeker may benefit from professional support for anxiety management.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertFalse($referral->help_seeker_consent);
        $this->assertFalse($referral->identity_disclosed);
        $this->assertSame('The seeker may benefit from professional support for anxiety management.', $referral->referral_reason);

        $this->assertNull($referral->consent_requested_at);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'Updated summary should not create a second referral.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Referral::where('session_id', $session->id)->count(), 'Re-request must reuse the open referral.');
        $this->assertDatabaseCount('consent_records', 2);
    }

    public function test_referral_consent_request_survives_a_broadcast_exception(): void
    {
        Broadcast::extend('failing-broadcaster', fn () => new class implements Broadcaster
        {
            public function auth($request)
            {
                return [];
            }

            public function validAuthenticationResponse($request, $result)
            {
                return json_encode([]);
            }

            public function broadcast(array $channels, $event, array $payload)
            {
                throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to localhost port 8080');
            }
        });
        config(['broadcasting.default' => 'failing-broadcaster']);

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'The seeker may benefit from professional support for anxiety management.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('referrals', ['session_id' => $session->id, 'status' => Referral::STATUS_PENDING_ADVISER]);
        $this->assertFalse(Referral::where('session_id', $session->id)->firstOrFail()->identity_disclosed);
    }

    public function test_consent_prompt_is_visible_to_seeker_and_helper_but_hidden_from_others(): void
    {
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Professional support recommendation.']);

        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->actingAs($seekerUser)->getJson(route('session.referral-prompt', $session))
            ->assertOk()
            ->assertJsonPath('referral.id', $referral->id)
            ->assertJsonPath('referral.status', Referral::STATUS_PENDING_ADVISER)
            ->assertJsonPath('referral.help_seeker_consent', false)
            ->assertJsonPath('referral.summary', 'Professional support recommendation.')
            ->assertJsonPath('referral.consent_url', route('referrals.consent-request', $referral));

        $otherSeeker = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $otherSeeker->id, 'generated_alias' => 'OtherSeeker', 'age' => 22, 'gender' => 'female']);
        $this->actingAs($otherSeeker)->getJson(route('session.referral-prompt', $session))->assertForbidden();
    }

    public function test_helper_submission_is_blocked_until_seeker_accepts(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Summary.'])->assertSessionHasNoErrors();
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->post(route('helper.session.referral', ['id' => $session->id]), [
            'referral_id' => $referral->id,
            'referral_reason' => 'Ongoing severe anxiety affecting study and sleep.',
            'priority_level' => 'high',
        ])->assertStatus(409);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertNull($referral->approved_at);
    }

    public function test_adviser_review_precedes_consent_and_professional_assignment_waits_for_identity(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $this->professional();
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Professional support recommended.'])->assertSessionHasNoErrors();
        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertStatus(409);
        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        $this->assertTrue($referral->fresh()->help_seeker_consent);
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertDatabaseHas('consent_records', ['purpose' => 'referral', 'decision' => 'accepted', 'referral_id' => $referral->id]);
    }

    private function approve(Referral $referral, Helper $helper): void
    {
        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Reviewed supporting documentation.'])->assertOk();
    }

    public function test_session_reviewer_receives_referral_when_helper_has_no_supervisor(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $adviser = $helper->adviser;
        $helper->update(['adviser_id' => null]);
        $session = $this->activeSession($helper, $this->seekerUser());
        $session->update(['review_adviser_id' => $adviser->id]);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Reviewer should receive this referral.'])->assertSessionHasNoErrors();
        $referral = $session->referrals()->firstOrFail();
        $this->assertSame($adviser->id, $referral->adviser_id);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $adviser->user_account_id, 'title' => 'New referral request']);
        $this->actingAs($adviser->user)->get(route('adviser.referrals'))->assertOk()->assertSee('Reviewer should receive this referral.');
        // Legacy unassigned referrals remain visible only to the explicit session reviewer.
        $referral->update(['adviser_id' => null]);
        $this->get(route('adviser.referrals'))->assertOk()->assertSee('Reviewer should receive this referral.');
        $this->get(route('adviser.referral.show', $referral))->assertOk();
        $other = User::factory()->create(['role' => 'adviser']);
        Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $other->email]);
        $this->actingAs($other)->get(route('adviser.referrals'))->assertOk()->assertDontSee('Reviewer should receive this referral.');
        $this->get(route('adviser.referral.show', $referral))->assertForbidden();
    }

    public function test_recommendation_without_active_reviewer_is_preserved_and_escalated(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $helper->adviser->user->update(['is_active' => false]);
        $session = $this->activeSession($helper, $this->seekerUser());
        $moderator = User::factory()->create(['role' => 'moderator', 'is_active' => true]);

        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Needs an assigned reviewer.'])
            ->assertRedirect();

        // The recommendation must never be discarded just because no Adviser
        // can be resolved; it is parked for Moderator assignment instead.
        $referral = Referral::sole();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER_ASSIGNMENT, $referral->status);
        $this->assertNull($referral->adviser_id);
        $this->assertSame('Needs an assigned reviewer.', $referral->referral_reason);
        $this->assertNotNull($referral->priority_level);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $moderator->id,
            'notification_type' => 'referral',
            'link' => '/moderator/referrals/unassigned',
        ]);

        $this->actingAs($moderator)->get(route('moderator.referrals.unassigned'))->assertOk()->assertSee('Needs an assigned reviewer.');
    }

    public function test_adviser_browser_approval_redirect_and_review_scripts_work(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seeker = $this->seekerUser();
        $session = $this->activeSession($helper, $seeker);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Professional support recommended.'])->assertSessionHasNoErrors();
        $referral = $session->referrals()->firstOrFail();
        $this->actingAs($helper->adviser->user);
        $page = $this->get(route('adviser.referral.show', $referral->id))->assertOk();
        preg_match_all('~<script\\b[^>]*>(.*?)</script>~si', $page->getContent(), $scripts);
        foreach ($scripts[1] as $script) {
            $this->assertStringNotContainsString('</style>', $script);
            $syntax = new Process(['node', '--check']);
            $syntax->setInput($script)->run();
            $this->assertTrue($syntax->isSuccessful(), $syntax->getErrorOutput());
        }
        $this->post(route('adviser.referral.approve', $referral->id), ['review_notes' => 'Reviewed supporting documentation.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('adviser.referrals'));
        $this->get(route('adviser.referrals'))->assertOk()->assertSee('Referral approved.');
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->user_account_id, 'title' => 'Referral approved']);
        $this->from(route('adviser.referral.show', $referral->id))
            ->post(route('adviser.referral.approve', $referral->id), ['review_notes' => 'Duplicate submission.'])
            ->assertRedirect(route('adviser.referral.show', $referral->id))->assertSessionHasErrors('review_notes');
        $this->get(route('adviser.referral.show', $referral->id))->assertOk()->assertSee('This referral has already been reviewed.');
        $this->actingAs($seeker)->getJson(route('session.referral-prompt', $session))->assertOk()->assertJsonPath('referral.status', 'pending_consent');
    }

    public function test_status_poll_uses_referral_consent_not_identity_disclosure(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seeker = $this->seekerUser();
        $session = $this->activeSession($helper, $seeker);
        $this->post(route('helper.session.referral.consent', $session->id), ['summary' => 'Professional support recommended.']);
        $referral = $session->referrals()->firstOrFail();
        $this->approve($referral, $helper);
        $this->actingAs($seeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        app(ConsentService::class)->decide($seeker, 'identity_disclosure', 'declined', $session->id, $referral->id, 'referral');
        $this->getJson(route('session.referral-prompt', $session))->assertOk()->assertJsonPath('consent.decision', 'accepted');
        $this->withSession(['info' => 'Your referral status has changed.'])->get(route('seeker.referrals'))
            ->assertOk()->assertSee('workflowNotice')->assertSee('Your referral status has changed.');
    }

    public function test_seeker_decline_closes_referral_and_blocks_submission(): void
    {
        Event::fake();

        [$helperUser, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $this->professional();

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Summary.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => false])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => Referral::STATUS_CLOSED, 'referral_id' => $referral->id]);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_CLOSED, $referral->status);
        $this->assertFalse($referral->help_seeker_consent);
        $this->assertNotNull($referral->consent_declined_at);
        $this->assertNotNull($referral->closed_date);
        $this->assertDatabaseHas('consent_records', [
            'purpose' => 'referral',
            'scope' => 'referral',
            'decision' => 'declined',
            'consent_given' => false,
            'referral_id' => $referral->id,
        ]);

        Event::assertDispatched(ReferralConsentUpdated::class, fn ($event) => $event->accepted === false);

        $this->actingAs($helperUser)->post(route('helper.session.referral', ['id' => $session->id]), [
            'referral_id' => $referral->id,
            'referral_reason' => 'Should not be accepted.',
            'priority_level' => 'high',
        ])->assertStatus(409);
    }

    public function test_re_request_after_decline_creates_a_fresh_referral(): void
    {
        Event::fake();

        [$helperUser, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'First request.']);
        $first = Referral::where('session_id', $session->id)->firstOrFail();
        $this->approve($first, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $first), ['accepted' => false])->assertOk();

        $this->actingAs($helperUser)->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Second request after decline.'])->assertSessionHasNoErrors();
        $second = Referral::where('session_id', $session->id)->where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $second->status);
        $this->assertSame(2, Referral::where('session_id', $session->id)->count());

        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $first), ['accepted' => true])->assertStatus(409);
    }

    public function test_withdrawal_of_referral_consent_blocks_further_disclosure(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $seeker = $seekerUser->helpSeeker;
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Summary.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        $this->assertTrue(app(ConsentService::class)->valid($seeker, 'referral', $referral->id));

        $this->actingAs($seekerUser)->getJson(route('session.referral-prompt', $session))
            ->assertOk()
            ->assertJsonPath('consent.scope', 'referral');

        app(ConsentService::class)->decide($seekerUser, 'referral', 'withdrawn', $session->id, $referral->id, 'referral');

        $this->assertFalse(app(ConsentService::class)->valid($seeker, 'referral', $referral->id));
        $this->actingAs($helper->user)->getJson(route('session.referral-prompt', $session))
            ->assertOk()
            ->assertJsonPath('consent.decision', 'withdrawn')
            ->assertJsonPath('consent.withdrawn', true);
    }

    public function test_other_seeker_and_other_helper_cannot_decide_or_submit(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Summary.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $otherSeeker = $this->seekerUser();
        $this->actingAs($otherSeeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertForbidden();

        [$otherUser] = $this->readyHelper();
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->actingAs($otherUser)->post(route('helper.session.referral', ['id' => $session->id]), [
            'referral_id' => $referral->id,
            'referral_reason' => 'Nope.',
            'priority_level' => 'high',
        ])->assertNotFound();
    }

    public function test_emergency_flag_creates_consent_requested_referral_and_repeat_click_deduplicates(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $moderatorUser = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        Moderator::create(['user_account_id' => $moderatorUser->id, 'first_name' => 'M', 'last_name' => 'Oder', 'email' => 'mod@example.com']);

        $this->post(route('helper.session.emergency', ['id' => $session->id]), [
            'description' => 'Seeker expressed immediate self-harm thoughts.',
            'immediate_action' => 'Stayed on the call and offered hotlines.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertSame(Referral::PRIORITY_EMERGENCY, $referral->priority_level);
        $this->assertFalse($referral->help_seeker_consent);
        $this->assertFalse($referral->identity_disclosed);

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->count());
        $this->assertDatabaseHas('emergency_alerts', ['session_id' => $session->id, 'referral_id' => $referral->id]);

        Event::assertDispatched(ModeratorAlert::class, function ($event) use ($session, $moderatorUser) {
            return $event->broadcastOn()->name === 'private-moderator.'.$moderatorUser->id
                && $event->broadcastWith()['session_id'] === $session->id
                && $event->broadcastWith()['risk_level'] === 'emergency';
        });
        Event::assertDispatched(EmergencyTriggered::class);

        $this->post(route('helper.session.emergency', ['id' => $session->id]), [
            'description' => 'Updated description on a second click.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->count(), 'Repeat flag must reuse the open incident.');
        $this->assertSame(1, Referral::where('session_id', $session->id)->count(), 'Repeat flag must not create a second referral.');
        $this->assertSame(1, EmergencyAlert::where('session_id', $session->id)->count());
        $incident = IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->first();
        $this->assertSame('Updated description on a second click.', $incident->description);
    }

    public function test_emergency_referral_still_requires_review_before_ordinary_consent(): void
    {
        Event::fake();
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $this->post(route('helper.session.emergency', $session->id), ['description' => 'Immediate safety concern.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertStatus(409);
        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk();
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertDatabaseCount('emergency_alerts', 1);
    }

    public function test_emergency_flag_persists_and_notifies_while_consent_is_declined(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $moderatorUser = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        Moderator::create(['user_account_id' => $moderatorUser->id, 'first_name' => 'M', 'last_name' => 'Oder', 'email' => 'mod@example.com']);

        $this->post(route('helper.session.emergency', ['id' => $session->id]), ['description' => 'Immediate self-harm thoughts.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->assertGreaterThanOrEqual(2, Notification::where('notification_type', 'emergency')->count(), 'Adviser, seeker, or moderator must be notified for an emergency flag.');

        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => false])->assertOk();
        $this->assertSame(Referral::STATUS_CLOSED, $referral->fresh()->status);

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->count(), 'Emergency incident persists even when referral consent is declined.');
        $this->assertDatabaseHas('emergency_alerts', ['session_id' => $session->id, 'referral_id' => $referral->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referral_consent_declined_safety_protocol']);
    }

    private function pendingAdviserReferral(Session $session, Helper $helper, bool $consent = false): Referral
    {
        return Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $helper->adviser->id,
            'priority_level' => Referral::PRIORITY_HIGH,
            'help_seeker_consent' => $consent,
            'consent_obtained_at' => $consent ? now() : null,
            'identity_disclosed' => false,
            'referral_reason' => 'Needs professional support.',
            'referral_date' => now(),
            'status' => Referral::STATUS_PENDING_ADVISER,
        ]);
    }

    public function test_post_approval_consent_notification_leads_to_the_seeker_decision_page(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper);

        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved after review.'])->assertOk();
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->status);

        $seekerNotification = Notification::where('user_account_id', $seekerUser->id)->where('title', 'Referral consent requested')->latest('id')->first();
        $this->assertNotNull($seekerNotification);
        $this->assertStringContainsString('/seeker/referrals', $seekerNotification->link, 'Post-approval consent must link to the decision page, not an inaccessible identity URL.');
    }

    public function test_consent_accept_opens_the_identity_modal(): void
    {
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper);
        $this->approve($referral, $helper);
        $this->actingAs($seekerUser)->post(route('referrals.consent', $referral), ['consent_given' => 1])->assertRedirect(route('seeker.referrals'))->assertSessionHas('identity_referral_id', $referral->id);
        $this->get(route('seeker.referrals'))->assertOk()->assertSee('identity-dialog-'.$referral->id, false);
    }

    public function test_adviser_approve_then_seeker_consent_prompts_identity(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $this->professional();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper);

        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved after review.'])->assertOk();
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->status);

        $this->actingAs($seekerUser)->post(route('referrals.consent', $referral), ['consent_given' => 1])->assertRedirect(route('seeker.referrals'));
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertTrue($referral->canProvideIdentity());
        $this->assertDatabaseHas('notifications', ['user_account_id' => $seekerUser->id, 'title' => 'Referral approved — provide contact details']);

        $this->actingAs($seekerUser)->get(route('identity.form', $referral))->assertOk();
    }

    public function test_seeker_consent_decline_after_approval_redirects_to_self_help(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $this->professional();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper);

        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved.'])->assertOk();
        $referral->refresh();

        $this->actingAs($seekerUser)->post(route('referrals.consent', $referral), ['consent_given' => 0])->assertRedirect('/selfhelp');
        $referral->refresh();
        $this->assertSame(Referral::STATUS_CLOSED, $referral->status);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $seekerUser->id, 'title' => 'Referral declined', 'link' => '/selfhelp']);
    }

    public function test_adviser_request_revision_and_helper_revises_recommendation(): void
    {
        Event::fake();

        [$helperUser, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper);
        $adviser = $helper->adviser;

        $this->actingAs($adviser->user)->post(route('adviser.referral.request-info', $referral->id), [
            'info_request' => 'Please clarify the recommended professional support and revise the scope.',
        ])->assertSessionHasNoErrors();

        $referral->refresh();
        $this->assertNotNull($referral->clarification_requested_at);
        $this->assertNull($referral->clarification_received_at);

        $this->actingAs($adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Premature.'])->assertStatus(409);

        $this->actingAs($helperUser)->post(route('helper.referral.clarify', $referral->id), [
            'response' => 'The recommendation stays appropriate with a clearer professional scope.',
            'referral_reason' => 'Revised recommendation with a clearer professional scope.',
        ])->assertSessionHasNoErrors();

        $referral->refresh();
        $this->assertNotNull($referral->clarification_received_at);
        $this->assertSame('Revised recommendation with a clearer professional scope.', $referral->referral_reason);
        $this->assertDatabaseHas('supervision_record_versions', ['record_type' => 'referrals', 'reason' => 'Helper revised the referral recommendation']);

        $this->actingAs($adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved after revision.'])->assertOk();
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->fresh()->status);
    }

    public function test_classification_emergency_creates_open_incident_for_moderator_board(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'EmSeeker'.$user->id, 'age' => 20, 'gender' => 'male']);
        $this->consentFixture($user);
        $this->actingAs($user);

        $this->post(route('request.screening.process'), array_replace(array_fill_keys(CompactScreening::FIELDS, '0'), ['current_suicide_plan' => '1', 'concern_id' => ConcernCategory::firstOrCreate(['concern_name' => 'Health'])->id]))
            ->assertRedirect(route('request.matching'));

        $session = Session::where('seeker_id', $user->helpSeeker->id)->firstOrFail();
        $this->assertSame('emergency_escalated', $session->workflow_state);
        $this->assertDatabaseCount('emergency_alerts', 1);
        $this->assertDatabaseHas('incident_reports', [
            'session_id' => $session->id,
            'incident_category' => 'classification_emergency',
            'status' => 'open',
            'risk_level' => 'emergency',
        ]);

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'classification_emergency')->whereIn('status', ['open', 'under_review', 'escalated'])->count());
    }

    public function test_identity_can_be_stored_while_a_professional_assignment_is_pending(): void
    {
        Event::fake();

        // Intentionally no professional exists so assignment pauses.
        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $referral = $this->pendingAdviserReferral($session, $helper, true);

        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review', $referral), ['approved' => true, 'notes' => 'Approved.'])->assertOk();
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertTrue($referral->canProvideIdentity(), 'Identity must be collectible while awaiting a professional assignment.');

        $this->actingAs($seekerUser)->get(route('identity.form', $referral))->assertOk();
        $this->get(route('seeker.referrals'))->assertOk()->assertSee('Provide contact details for coordination')->assertDontSee('no longer an open case');
    }

    public function test_helper_no_response_escalates_once_and_a_message_clears_it(): void
    {
        Event::fake();

        [$helperUser, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        Moderator::create(['user_account_id' => User::factory()->create(['role' => 'moderator', 'is_active' => true])->id, 'first_name' => 'M', 'last_name' => 'Oder', 'email' => 'mod@example.com']);

        $session->forceFill([
            'start_time' => now()->subMinutes(30),
            'last_helper_message_at' => now()->subMinutes(6),
        ])->save();

        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $session->refresh();
        $this->assertNotNull($session->no_response_escalated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'helper_no_response_escalated', 'target_id' => $session->id]);
        $this->assertGreaterThanOrEqual(1, Notification::where('user_account_id', $helper->adviser->user_account_id)->where('title', 'Helper not responding')->count());

        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'helper_no_response_escalated')->count(), 'Escalation must be idempotent.');

        $this->actingAs($helperUser)->postJson(route('chat.send'), ['session_id' => $session->id, 'message' => 'I am here now.'])->assertOk();
        $session->refresh();
        $this->assertNotNull($session->last_helper_message_at);
        $this->assertNull($session->no_response_escalated_at, 'A helper message must clear the last escalation.');
    }
}
