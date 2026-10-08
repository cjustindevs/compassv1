<?php

namespace Tests\Feature;

use App\Models\{Helper, HelperSchedule, ReadinessCheck, Session, HelpSeeker, QueueRequest, User, Notification};
use App\Services\HelperMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelperQueueRetryTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-08 19:00', 'Asia/Manila')->utc());
        config(['app.relax_duty_hours' => false, 'app.enforce_duty_hours' => true]);
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $user->email, 'status' => 'offline', 'availability' => 'available', 'competency_level' => 3]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'is_active' => true, 'created_by' => $user->id]);
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHour(), 'is_active' => true, 'assessment_result' => 'ready']);
        return $helper->fresh();
    }

    private function waiting(): Session
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'TestSeeker'.$user->id]);
        $this->consentFixture($user->fresh());
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_date' => now(), 'queued_at' => now(), 'request_status' => 'waiting', 'priority_level' => 'low']);
        return Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => 'waiting', 'workflow_state' => 'queued', 'risk_level' => 'low', 'session_type' => 'chat', 'submitted_at' => now(), 'created_date' => now()]);
    }

    public function test_opening_eligible_helper_dashboard_matches_existing_queue_once(): void
    {
        $helper = $this->helper();
        $session = $this->waiting();
        $this->actingAs($helper->user)->get(route('helper.dashboard'))->assertOk();
        $this->assertSame($helper->id, $session->fresh()->helper_id);
        $this->assertSame('helper_pending_acceptance', $session->fresh()->workflow_state);
        $this->assertSame('assigned', $session->queue->fresh()->request_status);
        $count = Notification::where('title', 'New case assigned')->count();
        $this->get(route('helper.dashboard'))->assertOk();
        $this->assertSame($count, Notification::where('title', 'New case assigned')->count());
    }

    public function test_dashboard_does_not_assign_helper_with_expired_readiness(): void
    {
        $helper = $this->helper();
        $helper->readinessChecks()->update(['valid_until' => now()->subMinute()]);
        $session = $this->waiting();
        $this->actingAs($helper->user)->get(route('helper.dashboard'))->assertOk();
        $this->assertNull($session->fresh()->helper_id);
        $this->assertSame('waiting', $session->queue->fresh()->request_status);
    }

    public function test_queue_retry_releases_expired_offer_and_assigns_another_eligible_helper(): void
    {
        $old = $this->helper();
        $session = $this->waiting();
        app(HelperMatchingService::class)->processQueueRequest($session->queue);
        $session->refresh()->update(['pre_session_brief_expires_at' => now()->subMinute()]);
        $old->update(['availability' => 'unavailable']);
        $replacement = $this->helper();
        $this->actingAs($replacement->user)->get(route('helper.dashboard'))->assertOk();
        $this->assertSame($replacement->id, $session->fresh()->helper_id);
        $this->assertSame(1, $old->fresh()->non_response_count);
        $this->assertSame('assigned', $session->queue->fresh()->request_status);
    }

    public function test_one_failed_request_does_not_block_the_remaining_queue(): void
    {
        $helper = $this->helper();
        $failed = $this->waiting();
        $next = $this->waiting();
        $service = new class($failed->queue_request_id) extends HelperMatchingService {
            public function __construct(private int $failedQueueId) {}
            public function processQueueRequest(QueueRequest $queue, ?int $excludeHelperId = null): ?Session
            {
                if ($queue->id === $this->failedQueueId) throw new \RuntimeException('Test transaction failure');
                return parent::processQueueRequest($queue, $excludeHelperId);
            }
        };
        $service->matchWaitingRequests();
        $this->assertNull($failed->fresh()->helper_id);
        $this->assertSame('waiting', $failed->queue->fresh()->request_status);
        $this->assertSame($helper->id, $next->fresh()->helper_id);
        $this->assertSame('assigned', $next->queue->fresh()->request_status);
    }

    public function test_matching_tries_next_candidate_when_first_cannot_be_reserved(): void
    {
        $first = $this->helper();
        $second = $this->helper();
        $session = $this->waiting();
        $service = new class($first->id) extends HelperMatchingService {
            public function __construct(private int $unavailableId) {}
            public function manualAssign(QueueRequest $queue, int $helperId, bool $emergencyOverride = false, bool $reassign = false, string $method = 'manual', ?\Illuminate\Support\Carbon $scheduledStart = null): Session|string|null
            {
                if ($helperId === $this->unavailableId) return 'The helper is already assigned to an active session.';
                return parent::manualAssign($queue, $helperId, $emergencyOverride, $reassign, $method, $scheduledStart);
            }
        };
        $result = $service->processQueueRequest($session->queue);
        $this->assertInstanceOf(Session::class, $result);
        $this->assertSame($second->id, $session->fresh()->helper_id);
    }

    public function test_waiting_page_does_not_blame_unrelated_unverified_helper_and_position_updates(): void
    {
        $helper = $this->helper();
        $older = $this->waiting();
        app(HelperMatchingService::class)->processQueueRequest($older->queue);
        $other = $this->helper();
        $other->update(['verification_status' => 'pending', 'training_verified' => false]);
        $waiting = $this->waiting();
        $waiting->queue->update(['queue_position' => 8]);
        $this->actingAs($waiting->seeker->user)->get(route('request.matching'))
            ->assertOk()->assertSee('free session capacity')
            ->assertDontSee('No verified peer helper is on duty right now')
            ->assertViewHas('currentQueueRequest', fn ($queue) => $queue->queue_position === 1);
        $this->assertNull($waiting->fresh()->helper_id);
    }

    public function test_waiting_message_identifies_missing_seeker_consent(): void
    {
        $session = $this->waiting();
        $session->seeker->consentRecords()->delete();
        $this->assertStringContainsString('Privacy and consent', app(HelperMatchingService::class)->waitingMessage($session));
    }

    public function test_assigned_adviser_can_complete_review_and_matching_resumes_once(): void
    {
        $helper = $this->helper();
        $helper->update(['is_under_review' => true, 'review_reason' => 'Three missed offers', 'non_response_count' => 3]);
        $waiting = $this->waiting();
        app(HelperMatchingService::class)->matchWaitingRequests();
        $this->assertNull($waiting->fresh()->helper_id);
        $this->actingAs($helper->adviser->user);
        $this->get(route('adviser.helper.show', $helper->id))->assertOk()->assertSee('Complete Adviser review');
        $this->post(route('adviser.helper.review.complete', $helper->id), ['review_resolution' => 'Reviewed missed offers and agreed to respond promptly.'])->assertRedirect();
        $this->assertFalse($helper->fresh()->is_under_review);
        $this->assertSame(0, $helper->fresh()->non_response_count);
        $this->assertSame($helper->id, $waiting->fresh()->helper_id);
        $this->post(route('adviser.helper.review.complete', $helper->id), ['review_resolution' => 'Repeated submission should not create duplicate history.'])->assertRedirect();
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'helper_review_completed')->count());
        $this->assertSame(1, Notification::where('title', 'Adviser review completed')->count());
    }

    public function test_review_completion_cannot_bypass_authorization_or_readiness(): void
    {
        $helper = $this->helper();
        $helper->update(['is_under_review' => true, 'non_response_count' => 3]);
        $helper->readinessChecks()->update(['valid_until' => now()->subMinute()]);
        $waiting = $this->waiting();
        $url = route('adviser.helper.review.complete', $helper->id);
        $this->actingAs($helper->user)->post($url, ['review_resolution' => 'I cannot clear my own review.'])->assertForbidden();
        $otherHelper = $this->helper();
        $otherHelper->update(['availability' => 'unavailable']);
        $foreign = $otherHelper->adviser->user;
        $this->actingAs($foreign)->post($url, ['review_resolution' => 'Unrelated Adviser cannot clear review.'])->assertForbidden();
        $this->actingAs($helper->adviser->user)->post($url, [])->assertSessionHasErrors('review_resolution');
        $this->assertTrue($helper->fresh()->is_under_review);
        $this->post($url, ['review_resolution' => 'Review completed; a new readiness check is required.'])->assertRedirect();
        $this->assertNull($waiting->fresh()->helper_id);
        $this->assertSame('waiting', $waiting->queue->fresh()->request_status);
    }

    public function test_queue_retry_keeps_seeker_consent_requirement(): void
    {
        $helper = $this->helper();
        $session = $this->waiting();
        $session->seeker->consentRecords()->delete();
        $this->actingAs($helper->user)->get(route('helper.dashboard'))->assertOk();
        $this->assertNull($session->fresh()->helper_id);
        $this->assertSame('waiting', $session->queue->fresh()->request_status);
    }
}
