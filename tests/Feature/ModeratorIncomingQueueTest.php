<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\User;
use App\Services\HelperReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the Moderator "Incoming Queue" page.
 *
 * Guards the four defects reported against /moderator/queue:
 *  1. raw Blade/HTML source leaking into the Waiting Seekers rows,
 *  2. the assign/remove controls, and
 *  3. negative average / per-row waiting durations.
 */
class ModeratorIncomingQueueTest extends TestCase
{
    use RefreshDatabase;
    use SeekerWorkflowFixtures;

    private function moderator(): User
    {
        $user = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        Moderator::create([
            'user_account_id' => $user->id,
            'first_name' => 'Mara',
            'last_name' => 'Moderator',
            'email' => $user->email,
        ]);

        return $user->fresh();
    }

    private function eligibleHelper(): Helper
    {
        // Inside the approved duty window so the readiness check is valid.
        $this->travelTo(now('Asia/Manila')->setTime(19, 0)->utc());

        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create([
            'user_account_id' => $user->id,
            'first_name' => 'Helen',
            'last_name' => 'Duty',
            'email' => $user->email,
            'status' => 'available',
            'availability' => 'available',
            'competency_level' => 3,
            'competency_risk_level' => 3,
            'max_concurrent_sessions' => 2,
        ]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::updateOrCreate(
            ['helper_id' => $helper->id, 'date' => now('Asia/Manila')->startOfDay()],
            ['shift_start' => '18:00:00', 'shift_end' => '23:00:00', 'created_by' => $user->id, 'is_active' => true]
        );
        ReadinessCheck::create([
            'helper_id' => $helper->id,
            'assessment_date' => now(),
            'valid_until' => now()->addHours(3),
            'assessment_result' => 'ready',
            'emotionally_ready' => true,
            'willing_to_listen' => true,
            'stress_level' => 'low',
            'availability_status' => 'available',
            'skills_confirmed' => HelperReadinessService::SKILLS,
            'is_active' => true,
        ]);

        return $helper->fresh();
    }

    /**
     * @return array{0: QueueRequest, 1: Session, 2: HelpSeeker, 3: User}
     */
    private function waitingRequest(string $priority = 'low', ?string $alias = null): array
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => $alias ?? ('Seeker'.$user->id),
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);
        $this->consentFixture($user);
        $queue = QueueRequest::create([
            'seeker_id' => $seeker->id,
            'request_status' => 'waiting',
            'priority_level' => $priority,
            'preferred_session_type' => 'chat',
            'request_date' => now()->subMinutes(7),
        ]);
        $session = Session::create([
            'seeker_id' => $seeker->id,
            'queue_request_id' => $queue->id,
            'session_status' => Session::STATUS_WAITING,
            'session_type' => 'chat',
            'risk_level' => 'low',
            'submitted_at' => now(),
            'created_date' => now(),
        ]);

        return [$queue, $session, $seeker, $user];
    }

    public function test_waiting_rows_do_not_leak_html_source(): void
    {
        $this->actingAs($this->moderator());
        [$queue] = $this->waitingRequest();

        $response = $this->get(route('moderator.queue'))->assertOk();
        $html = $response->getContent();

        // The orphaned attribute fragment that used to render as visible text.
        $this->assertStringNotContainsString('name="scheduled_at"', substr($html, 0, strpos($html, 'Recently Matched')));
        $this->assertStringNotContainsString('id }}"', $html);
        $this->assertStringNotContainsString('Optional appointment time', $html);
        $this->assertStringNotContainsString('class="assign-select"\n', $html);

        // The row still renders the real controls.
        $this->assertStringContainsString('name="helper_id"', $html);
        $this->assertStringContainsString('value="'.$queue->id.'"', $html);
    }

    public function test_waiting_row_shows_only_the_approved_columns(): void
    {
        $this->actingAs($this->moderator());
        [$queue] = $this->waitingRequest('high', 'Seeker-Very-Long-Pseudonymous-Alias-Identifier');

        $html = $this->get(route('moderator.queue'))->assertOk()->getContent();

        $this->assertStringContainsString('P2 · High', $html);
        $this->assertStringContainsString('Seeker-Very-Long-Pseudonymous-Alias-Identifier', $html);
        $this->assertStringContainsString('7 min', $html, 'The wait chip should show the elapsed minutes.');
        $this->assertStringContainsString('Chat', $html, 'The communication preference is shown.');
        $this->assertStringContainsString(route('moderator.queue.assign'), $html);
        $this->assertStringContainsString(route('moderator.queue.remove', $queue->id, false), $html);
    }

    public function test_queue_page_never_renders_a_negative_waiting_duration(): void
    {
        $this->actingAs($this->moderator());
        $this->waitingRequest();

        $html = $this->get(route('moderator.queue'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/-\d+m\s*-\d+s/', $html, 'Average wait must not render with a negative sign.');
        $this->assertDoesNotMatchRegularExpression('/-\d+ min/', $html, 'Per-row wait must not be negative.');
    }

    public function test_stats_endpoint_never_reports_a_negative_average_wait(): void
    {
        $this->actingAs($this->moderator());

        // A matched_date that precedes request_date cannot describe a real wait
        // (legacy rows written before the database session was pinned to UTC).
        [$queue] = $this->waitingRequest();
        $queue->update(['request_status' => 'assigned', 'matched_date' => $queue->request_date->copy()->subMinutes(30)]);

        $this->getJson(route('moderator.queue.stats'))
            ->assertOk()
            ->assertJsonPath('avg_wait', '0m 0s')
            ->assertJsonPath('waiting', 0)
            ->assertJsonPath('assigned', 1);
    }

    public function test_stats_endpoint_reports_every_rendered_metric(): void
    {
        $this->actingAs($this->moderator());
        $this->waitingRequest();
        $this->waitingRequest();

        $this->getJson(route('moderator.queue.stats'))
            ->assertOk()
            ->assertJsonStructure([
                'waiting', 'assigned', 'avg_wait', 'avg_holding', 'unserved', 'queue_size', 'oldest_wait',
            ]);
    }

    public function test_stats_agree_with_the_rendered_page(): void
    {
        $this->actingAs($this->moderator());
        $this->waitingRequest();
        $this->waitingRequest('emergency');

        $stats = $this->getJson(route('moderator.queue.stats'))->assertOk()->json();
        $this->assertSame(2, $stats['waiting']);
        $this->assertSame(0, $stats['assigned']);
        $this->assertSame(2, $stats['queue_size']);

        $html = $this->get(route('moderator.queue'))->assertOk()->getContent();
        $this->assertStringContainsString('id="statWaiting">2<', $html);
        $this->assertStringContainsString('id="waitingHeading">2 waiting<', $html);
    }

    public function test_assign_requires_a_selected_helper(): void
    {
        $this->actingAs($this->moderator());
        [$queue] = $this->waitingRequest();

        $this->post(route('moderator.queue.assign'), ['queue_id' => $queue->id])
            ->assertSessionHasErrors('helper_id');

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting']);
    }

    public function test_assign_rejects_an_appointment_time(): void
    {
        $this->actingAs($this->moderator());
        $helper = $this->eligibleHelper();
        [$queue, $session] = $this->waitingRequest();

        $this->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id,
            'helper_id' => $helper->id,
            'scheduled_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('scheduled_at');

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting']);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'session_status' => Session::STATUS_WAITING]);
    }

    public function test_assign_creates_an_awaiting_acceptance_assignment(): void
    {
        $this->actingAs($this->moderator());
        $helper = $this->eligibleHelper();
        [$queue, $session] = $this->waitingRequest();

        $this->post(route('moderator.queue.assign'), ['queue_id' => $queue->id, 'helper_id' => $helper->id])
            ->assertRedirect(route('moderator.queue'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('queue_requests', [
            'id' => $queue->id, 'request_status' => 'assigned', 'assigned_helper_id' => $helper->id,
        ]);
        // Acceptance is required before the session activates.
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id, 'helper_id' => $helper->id,
            'session_status' => Session::STATUS_HELPER_ASSIGNED, 'match_status' => 'awaiting_acceptance',
        ]);
        $this->assertNull($session->fresh()->helper_accepted_at);
    }

    public function test_assign_refuses_a_duplicate_assignment(): void
    {
        $this->actingAs($this->moderator());
        $helper = $this->eligibleHelper();
        [$queue] = $this->waitingRequest();

        $this->post(route('moderator.queue.assign'), ['queue_id' => $queue->id, 'helper_id' => $helper->id])
            ->assertSessionHas('success');

        // The request has left the waiting state, so a second assign must fail.
        $this->post(route('moderator.queue.assign'), ['queue_id' => $queue->id, 'helper_id' => $helper->id])
            ->assertSessionHas('error');

        $this->assertSame(1, QueueRequest::where('assigned_helper_id', $helper->id)->count());
    }

    public function test_remove_button_target_cancels_a_waiting_request(): void
    {
        $this->actingAs($this->moderator());
        [$queue, $session, , $seekerUser] = $this->waitingRequest();

        $this->deleteJson(route('moderator.queue.remove', $queue->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'cancelled']);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'session_status' => 'cancelled']);
        // The seeker is notified; the account and history are preserved.
        $this->assertDatabaseHas('notifications', ['user_account_id' => $seekerUser->id]);
        $this->assertDatabaseHas('help_seekers', ['id' => $queue->seeker_id]);
    }

    public function test_remove_button_target_requeues_an_unaccepted_assignment(): void
    {
        $this->actingAs($this->moderator());
        $helper = $this->eligibleHelper();
        [$queue, $session] = $this->waitingRequest();

        $this->post(route('moderator.queue.assign'), ['queue_id' => $queue->id, 'helper_id' => $helper->id]);

        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('queue_requests', [
            'id' => $queue->id, 'request_status' => 'waiting', 'assigned_helper_id' => null,
        ]);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'session_status' => Session::STATUS_WAITING]);
        $this->assertNull($session->fresh()->helper_id);
    }

    public function test_remove_is_forbidden_for_a_seeker(): void
    {
        [, , , $seekerUser] = $this->waitingRequest();
        $this->actingAs($seekerUser);
        $queue = QueueRequest::latest('id')->first();

        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertForbidden();
        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting']);
    }

    public function test_emergency_requests_are_ordered_first_and_keep_their_class(): void
    {
        $this->actingAs($this->moderator());
        $this->waitingRequest('low', 'Seeker-Low-Order');
        $this->waitingRequest('emergency', 'Seeker-Emergency-Order');

        $html = $this->get(route('moderator.queue'))->assertOk()->getContent();

        $this->assertStringContainsString('P1 · Emergency', $html);
        $this->assertStringContainsString('P4 · Low', $html);
        $this->assertLessThan(
            strpos($html, 'Seeker-Low-Order'),
            strpos($html, 'Seeker-Emergency-Order'),
            'The emergency request must be listed before the low-priority one.'
        );
    }
}
