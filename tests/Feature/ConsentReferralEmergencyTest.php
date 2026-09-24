<?php

namespace Tests\Feature;

use App\Events\EmergencyTriggered;
use App\Events\ModeratorAlert;
use App\Events\ReferralConsentRequested;
use App\Events\ReferralConsentUpdated;
use App\Models\Adviser;
use App\Models\ConsentRecord;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\IncidentReport;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\HelperReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Seeker' . $user->id, 'age' => 20, 'gender' => 'male']);
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

    public function test_request_consent_creates_consent_requested_referral_and_is_idempotent(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'The seeker may benefit from professional support for anxiety management.',
        ])->assertSessionHasNoErrors();

        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);
        $this->assertFalse($referral->help_seeker_consent);
        $this->assertFalse($referral->identity_disclosed);
        $this->assertSame('The seeker may benefit from professional support for anxiety management.', $referral->referral_reason);

        Event::assertDispatched(ReferralConsentRequested::class, function ($event) use ($referral) {
            return $event->referral->is($referral)
                && $event->broadcastOn()->name === 'private-session.' . $referral->session_id
                && $event->broadcastAs() === 'ReferralConsentRequested'
                && $event->broadcastWith()['referral_id'] === $referral->id;
        });

        $this->assertNotNull($referral->consent_requested_at);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'Updated summary should not create a second referral.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Referral::where('session_id', $session->id)->count(), 'Re-request must reuse the open referral.');
        $this->assertDatabaseCount('consent_records', 2);
    }

    public function test_referral_consent_request_survives_a_broadcast_exception(): void
    {
        \Illuminate\Support\Facades\Broadcast::extend('failing-broadcaster', fn () => new class implements \Illuminate\Contracts\Broadcasting\Broadcaster {
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
                throw new \Illuminate\Broadcasting\BroadcastException('Pusher error: cURL error 7: Failed to connect to localhost port 8080');
            }
        });
        config(['broadcasting.default' => 'failing-broadcaster']);

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'The seeker may benefit from professional support for anxiety management.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('referrals', ['session_id' => $session->id, 'status' => Referral::STATUS_CONSENT_REQUESTED]);
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
            ->assertJsonPath('referral.status', Referral::STATUS_CONSENT_REQUESTED)
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
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);
        $this->assertNull($referral->approved_at);
    }

    public function test_accept_submit_adviser_approval_forwards_to_professional_without_reconsent(): void
    {
        Event::fake();

        [$helperUser, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $professional = $this->professional();

        $this->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Summary.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();

        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => Referral::STATUS_CONSENT_REQUESTED, 'referral_id' => $referral->id]);

        $referral->refresh();
        $this->assertTrue($referral->help_seeker_consent);
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);
        $this->assertNotNull($referral->consent_obtained_at);
        $this->assertDatabaseHas('consent_records', [
            'seeker_id' => $seekerUser->helpSeeker->id,
            'purpose' => 'referral',
            'scope' => 'referral',
            'decision' => 'accepted',
            'consent_given' => true,
            'referral_id' => $referral->id,
        ]);

        Event::assertDispatched(ReferralConsentUpdated::class, function ($event) use ($referral) {
            return $event->referral->is($referral)
                && $event->accepted === true
                && $event->broadcastOn()->name === 'private-session.' . $referral->session_id
                && $event->broadcastWith()['help_seeker_consent'] === true;
        });

        $this->actingAs($helperUser)->post(route('helper.session.referral', ['id' => $session->id]), [
            'referral_id' => $referral->id,
            'referral_reason' => 'Ongoing severe anxiety affecting study and sleep.',
            'priority_level' => 'high',
        ])->assertStatus(302);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertSame('Ongoing severe anxiety affecting study and sleep.', $referral->referral_reason);

        $adviser = $helper->adviser;
        $this->actingAs($adviser->user)->postJson(route('referrals.review', $referral), [
            'approved' => true,
            'notes' => 'Approved based on helper observations.',
        ])->assertOk()->assertJson(['success' => true, 'status' => Referral::STATUS_PENDING_PROFESSIONAL]);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertNotNull($referral->approved_at);
        $this->assertTrue($referral->help_seeker_consent);
        $this->assertSame($professional->id, $referral->professional_id);
        $this->assertNotNull($referral->professional_notified_at);

        Event::assertDispatched(ReferralConsentUpdated::class);
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
        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $first), ['accepted' => false])->assertOk();

        $this->actingAs($helperUser)->post(route('helper.session.referral.consent', ['id' => $session->id]), ['summary' => 'Second request after decline.'])->assertSessionHasNoErrors();
        $second = Referral::where('session_id', $session->id)->where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $second->status);
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
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);
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
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);
        $this->assertSame(Referral::PRIORITY_EMERGENCY, $referral->priority_level);
        $this->assertFalse($referral->help_seeker_consent);
        $this->assertFalse($referral->identity_disclosed);

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->count());
        $this->assertDatabaseHas('emergency_alerts', ['session_id' => $session->id, 'referral_id' => $referral->id]);

        Event::assertDispatched(ModeratorAlert::class, function ($event) use ($session, $moderatorUser) {
            return $event->broadcastOn()->name === 'private-moderator.' . $moderatorUser->id
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

    public function test_emergency_consent_accept_forwards_directly_to_adviser_then_professional(): void
    {
        Event::fake();

        [, $helper] = $this->readyHelper();
        $seekerUser = $this->seekerUser();
        $session = $this->activeSession($helper, $seekerUser);
        $professional = $this->professional();

        $this->post(route('helper.session.emergency', ['id' => $session->id]), ['description' => 'Immediate self-harm thoughts.']);
        $referral = Referral::where('session_id', $session->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->status);

        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => Referral::STATUS_PENDING_ADVISER, 'referral_id' => $referral->id]);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertTrue($referral->help_seeker_consent);
        $this->assertDatabaseHas('consent_records', ['purpose' => 'referral', 'scope' => 'referral', 'decision' => 'accepted', 'referral_id' => $referral->id]);

        $adviser = $helper->adviser;
        $this->actingAs($adviser->user)->postJson(route('referrals.review', $referral), [
            'approved' => true,
            'notes' => 'Emergency referral approved.',
        ])->assertOk()->assertJson(['success' => true, 'status' => Referral::STATUS_PENDING_PROFESSIONAL]);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_PROFESSIONAL, $referral->status);
        $this->assertSame($professional->id, $referral->professional_id);
        $this->assertTrue($referral->help_seeker_consent);
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

        $this->actingAs($seekerUser)->postJson(route('referrals.consent-request', $referral), ['accepted' => false])->assertOk();
        $this->assertSame(Referral::STATUS_CLOSED, $referral->fresh()->status);

        $this->assertSame(1, IncidentReport::where('session_id', $session->id)->where('incident_category', 'emergency_flag')->where('status', 'open')->count(), 'Emergency incident persists even when referral consent is declined.');
        $this->assertDatabaseHas('emergency_alerts', ['session_id' => $session->id, 'referral_id' => $referral->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referral_consent_declined_safety_protocol']);
    }
}