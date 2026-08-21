<?php

namespace Tests\Feature;

use App\Events\CaseAccepted;
use App\Events\CaseDeclined;
use App\Events\NewCaseAssigned;
use App\Events\NewHelperAssigned;
use App\Events\SessionEnded;
use App\Models\Helper;
use App\Models\HelpSeekerEvaluation;
use App\Models\Message;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HelperModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $helperUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\HelperModuleSeeder::class);

        $this->helperUser = User::where('role', 'helper')->firstOrFail();
    }

    public function test_helper_dashboard_renders_with_database_stats(): void
    {
        $response = $this->actingAs($this->helperUser)->get(route('helper.dashboard'));

        $response->assertOk();
        $response->assertSee('Total Sessions');
    }

    public function test_non_helper_is_blocked_from_helper_pages(): void
    {
        $seeker = User::where('role', 'seeker')->firstOrFail();

        $this->actingAs($seeker)
            ->get(route('helper.dashboard'))
            ->assertForbidden();
    }

    public function test_cases_page_lists_assigned_cases_from_database(): void
    {
        $response = $this->actingAs($this->helperUser)->get(route('helper.cases'));

        $response->assertOk();
        $response->assertSee('SilentRiver21');
        $response->assertSee('All Cases');
    }

    public function test_readiness_page_renders_breathing_exercise_ui(): void
    {
        $response = $this->actingAs($this->helperUser)
            ->get(route('helper.readiness'));

        $response->assertOk();
        $response->assertSee('Start Breathing Exercise');
        $response->assertSee('exercise-modal');
        $response->assertSee('begin-lesson-btn');
        $response->assertSee('continue-btn');
        $response->assertSee('breath-circle');
    }

    public function test_readiness_submission_requires_the_breathing_exercise(): void
    {
        $countBefore = \App\Models\ReadinessCheck::count();

        $this->actingAs($this->helperUser)
            ->from(route('helper.readiness'))
            ->post(route('helper.readiness.store'), [
                'emotionally_ready' => 1,
                'willing_to_listen' => 1,
                'stress_level' => 'low',
                'availability_status' => 'available',
            ])
            ->assertSessionHasErrors('exercise_completed')
            ->assertRedirect(route('helper.readiness'));

        $this->assertSame($countBefore, \App\Models\ReadinessCheck::count());
    }

    public function test_readiness_submission_after_exercise_is_stored_with_helper_id_and_exercise_status(): void
    {
        $this->actingAs($this->helperUser)
            ->from(route('helper.readiness'))
            ->post(route('helper.readiness.store'), [
                'emotionally_ready' => 1,
                'willing_to_listen' => 1,
                'stress_level' => 'low',
                'availability_status' => 'available',
                'exercise_completed' => 'completed',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('helper.dashboard'));

        $this->assertDatabaseHas('readiness_checks', [
            'helper_id' => $this->helperUser->helper->id,
            'assessment_result' => 'ready',
            'breathing_exercise' => 'completed',
        ]);
    }

    public function test_readiness_submission_allows_skipping_the_exercise(): void
    {
        $this->actingAs($this->helperUser)
            ->from(route('helper.readiness'))
            ->post(route('helper.readiness.store'), [
                'emotionally_ready' => 0,
                'willing_to_listen' => 0,
                'stress_level' => 'high',
                'availability_status' => 'not_ready',
                'exercise_completed' => 'skipped',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('helper.dashboard'));

        $this->assertDatabaseHas('readiness_checks', [
            'helper_id' => $this->helperUser->helper->id,
            'assessment_result' => 'not_ready',
            'breathing_exercise' => 'skipped',
        ]);
    }

    public function test_readiness_submission_marks_helper_available_when_ready(): void
    {
        $this->actingAs($this->helperUser)
            ->post(route('helper.readiness.store'), [
                'emotionally_ready' => 1,
                'willing_to_listen' => 1,
                'stress_level' => 'low',
                'availability_status' => 'available',
                'exercise_completed' => 'completed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);
    }

    public function test_readiness_submission_marks_helper_offline_when_not_ready(): void
    {
        $this->actingAs($this->helperUser)
            ->post(route('helper.readiness.store'), [
                'emotionally_ready' => 0,
                'willing_to_listen' => 0,
                'stress_level' => 'high',
                'availability_status' => 'not_ready',
                'exercise_completed' => 'completed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'offline',
        ]);
    }

    public function test_decline_reassigns_to_the_next_available_helper_and_broadcasts_to_seeker(): void
    {
        Event::fake([CaseDeclined::class, NewCaseAssigned::class, NewHelperAssigned::class]);

        // A second, ready, available helper so the queue has someone to hand the case to.
        $backupUser = User::create([
            'name' => 'Backup Helper',
            'email' => 'backup@example.com',
            'password' => bcrypt('password'),
            'role' => 'helper',
            'email_verified_at' => now(),
        ]);

        $backupHelper = Helper::create([
            'user_account_id' => $backupUser->id,
            'first_name' => 'Backup',
            'last_name' => 'Helper',
            'email' => 'backup@example.com',
            'status' => 'available',
            'competency_level' => 3,
            'max_concurrent_sessions' => 2,
        ]);

        \App\Models\ReadinessCheck::create([
            'helper_id' => $backupHelper->id,
            'assessment_date' => now()->subDay(),
            'availability_status' => 'available',
            'assessment_result' => 'ready',
            'emotionally_ready' => true,
            'willing_to_listen' => true,
            'stress_level' => 'low',
        ]);

        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'helper_assigned')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.cases.decline', ['id' => $session->id]))
            ->assertRedirect(route('helper.cases'));

        $session->refresh();

        // The case was handed straight to the backup helper, still pending their acceptance.
        $this->assertSame($backupHelper->id, $session->helper_id);
        $this->assertSame('helper_assigned', $session->session_status);

        $this->assertDatabaseHas('helpers', [
            'id' => $backupHelper->id,
            'status' => 'busy',
        ]);

        // The declining helper was freed.
        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);

        // The new helper got their assignment event...
        Event::assertDispatched(NewCaseAssigned::class, function (NewCaseAssigned $event) use ($session, $backupHelper) {
            return $event->session->id === $session->id
                && $event->userId === $backupHelper->user_account_id;
        });

        // ...and the seeker was told a new helper is on the case.
        Event::assertDispatched(NewHelperAssigned::class, function (NewHelperAssigned $event) use ($session) {
            return $event->session->id === $session->id
                && $event->userId === $session->seeker->user_account_id
                && $event->helperName === 'Backup Helper';
        });

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $backupHelper->user_account_id,
            'notification_type' => 'assignment',
        ]);
    }

    public function test_readiness_history_renders(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.readiness.history'))
            ->assertOk();
    }

    public function test_chat_page_renders_messages_from_database(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $response = $this->actingAs($this->helperUser)
            ->get(route('helper.session.chat', ['id' => $session->id]));

        $response->assertOk();
        $response->assertSee('Live Chat');
    }

    public function test_chat_index_route_never_returns_404(): void
    {
        $response = $this->actingAs($this->helperUser)
            ->get(route('helper.chat'));

        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_legacy_session_chat_url_redirects_instead_of_404(): void
    {
        $this->actingAs($this->helperUser)
            ->get('/helper/session/chat')
            ->assertRedirect(route('helper.chat'));
    }

    public function test_chat_show_route_renders_chat_interface(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->get(route('helper.chat.show', ['id' => $session->id]))
            ->assertOk();
    }

    public function test_accept_case_broadcasts_case_accepted_event(): void
    {
        Event::fake([CaseAccepted::class]);

        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'helper_assigned')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.cases.accept', ['id' => $session->id]))
            ->assertRedirect();

        Event::assertDispatched(CaseAccepted::class, function (CaseAccepted $event) use ($session) {
            return $event->session->id === $session->id;
        });

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => 'active',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $session->seeker->user_account_id,
            'title' => 'Your session has started',
        ]);
    }

    public function test_decline_case_broadcasts_case_declined_and_returns_to_queue(): void
    {
        Event::fake([CaseDeclined::class, NewCaseAssigned::class]);

        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'helper_assigned')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.cases.decline', ['id' => $session->id]))
            ->assertRedirect(route('helper.cases'));

        Event::assertDispatched(CaseDeclined::class, function (CaseDeclined $event) use ($session) {
            return $event->session->id === $session->id;
        });

        // Released back to the queue, helper is free again
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'helper_id' => null,
            'session_status' => 'waiting',
        ]);

        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);
    }

    public function test_matching_assigns_available_helper_and_broadcasts_new_case_assigned(): void
    {
        Event::fake([NewCaseAssigned::class]);

        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'preferences_set',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);

        session([
            'screening_data' => ['concern_id' => $session->concern_id, 'description' => 'Test request.', 'urgency' => 'low', 'safety_check' => 'no'],
            'preferences_data' => ['support_mode' => 'chat', 'preferred_language' => 'English', 'additional_notes' => null],
            'risk_level' => 'low',
            'session_id' => $session->id,
        ]);

        $this->actingAs($seekerUser)
            ->get(route('request.matching'));

        Event::assertDispatched(NewCaseAssigned::class, function (NewCaseAssigned $event) use ($session) {
            return $event->session->id === $session->id;
        });

        $session->refresh();
        $this->assertNotNull($session->helper_id);
        $this->assertSame('helper_assigned', $session->session_status);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $session->helper->user_account_id,
            'notification_type' => 'assignment',
        ]);
    }

    public function test_matching_skips_helpers_without_a_ready_readiness_check(): void
    {
        // A helper who is "available" but has NEVER passed a readiness check
        // must not be matchable — only the seeded ready helper can be picked.
        $unreadyUser = User::create([
            'name' => 'Unready Helper',
            'email' => 'unready@example.com',
            'password' => bcrypt('password'),
            'role' => 'helper',
            'email_verified_at' => now(),
        ]);
        $unreadyHelper = Helper::create([
            'user_account_id' => $unreadyUser->id,
            'first_name' => 'Unready',
            'last_name' => 'Helper',
            'email' => 'unready@example.com',
            'status' => 'available',
            'competency_level' => 2,
            'max_concurrent_sessions' => 2,
        ]);

        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'preferences_set',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);

        session([
            'screening_data' => ['concern_id' => $session->concern_id, 'description' => 'Test request.', 'urgency' => 'low', 'safety_check' => 'no'],
            'preferences_data' => ['support_mode' => 'chat', 'preferred_language' => 'English', 'additional_notes' => null],
            'risk_level' => 'low',
            'session_id' => $session->id,
        ]);

        $this->actingAs($seekerUser)
            ->get(route('request.matching'));

        $session->refresh();
        $this->assertNotNull($session->helper_id);
        $this->assertNotSame($unreadyHelper->id, $session->helper_id);
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'helper_id' => $this->helperUser->helper->id,
        ]);
    }

    public function test_seeker_resumes_pending_request_after_login(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        // A pending request exists in the database, but the PHP session
        // has no flash data — exactly what a logged-out seeker sees.
        Session::create([
            'seeker_id' => $seeker->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'preferences_set',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);

        $this->actingAs($seekerUser)
            ->get(route('request.screening'))
            ->assertRedirect(route('request.matching'));
    }

    public function test_matching_page_shows_waiting_for_acceptance_state(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $this->helperUser->helper->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'helper_assigned',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);

        session(['session_id' => $session->id]);

        $response = $this->actingAs($seekerUser)
            ->get(route('request.matching'));

        $response->assertOk();
        $response->assertSee('Waiting for helper to accept');
        $response->assertSee($this->helperUser->helper->full_name);
    }

    public function test_matching_page_shows_active_session_state(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $this->helperUser->helper->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'active',
            'completion_status' => 'pending',
            'created_date' => now(),
            'start_time' => now(),
        ]);

        session(['session_id' => $session->id]);

        $response = $this->actingAs($seekerUser)
            ->get(route('request.matching'));

        $response->assertOk();
        $response->assertSee('Session started!');
        $response->assertSee('Go to Chat');
    }

    public function test_seeker_chat_page_renders_premium_chat_interface(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $this->helperUser->helper->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'active',
            'completion_status' => 'pending',
            'created_date' => now(),
            'start_time' => now(),
        ]);

        session(['session_id' => $session->id]);

        $response = $this->actingAs($seekerUser)
            ->get(route('session.chat'));

        $response->assertOk();
        $response->assertSee('Chat with');
        $response->assertSee('id="chatMessages"', false);
        $response->assertSee('id="sendButton"', false);
        $response->assertSee('id="typingIndicator"', false);
    }

    public function test_helper_can_send_a_message_and_it_is_stored(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->postJson(route('helper.session.chat.send', ['id' => $session->id]), [
                'message' => 'A message from the helper test.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'session_id' => $session->id,
            'sender' => 'helper',
            'message_text' => 'A message from the helper test.',
        ]);
    }

    public function test_voice_page_and_call_log_are_persisted(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->get(route('helper.session.voice', ['id' => $session->id]))
            ->assertOk();

        $this->actingAs($this->helperUser)
            ->post(route('helper.session.voice.start', ['id' => $session->id]))
            ->assertOk();

        $this->actingAs($this->helperUser)
            ->post(route('helper.session.voice.end', ['id' => $session->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('call_logs', ['session_id' => $session->id]);
    }

    public function test_session_notes_can_be_stored_in_database(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.session.notes.store', ['id' => $session->id]), [
                'help_seeker_condition' => 'Anxious but cooperative.',
                'session_summary' => 'We discussed grounding techniques.',
                'personal_reflection' => 'Went well.',
                'skills_applied' => ['active_listening', 'validation'],
                'referral_recommended' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('session_reports', [
            'session_id' => $session->id,
            'session_summary' => 'We discussed grounding techniques.',
        ]);
    }

    public function test_end_session_completes_the_session(): void
    {
        Event::fake([SessionEnded::class]);

        $session = Session::with('seeker')
            ->where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.session.end', ['id' => $session->id]))
            ->assertRedirect(route('helper.session.notes', ['id' => $session->id]));

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => 'completed',
            'completion_status' => 'completed',
        ]);

        // The seeker must be told the session ended so they can evaluate.
        Event::assertDispatched(SessionEnded::class, function (SessionEnded $event) use ($session) {
            return $event->session->id === $session->id && $event->endedBy === 'helper';
        });

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => $session->seeker->user_account_id,
            'notification_type' => 'evaluation',
        ]);

        // The helper must be freed so they can take new cases.
        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);
    }

    public function test_seeker_ending_session_broadcasts_to_helper_and_frees_helper(): void
    {
        Event::fake([SessionEnded::class]);

        $session = Session::with('seeker')
            ->where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $seekerUser = User::where('role', 'seeker')->firstOrFail();

        session(['session_id' => $session->id]);

        $this->actingAs($seekerUser)
            ->post(route('session.end'))
            ->assertRedirect(route('session.evaluation'));

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => 'completed',
            'completion_status' => 'completed',
        ]);

        Event::assertDispatched(SessionEnded::class, function (SessionEnded $event) use ($session) {
            return $event->session->id === $session->id && $event->endedBy === 'seeker';
        });

        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);

        // The seeker lands on the evaluation page with the session still in scope.
        $this->actingAs($seekerUser)
            ->get(route('session.evaluation'))
            ->assertOk()
            ->assertSee('Post-Session Evaluation');
    }

    public function test_stale_pending_requests_are_cancelled_on_dashboard(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $seeker = $seekerUser->helpSeeker;

        // A pending request abandoned >24h ago — the phantom "pending request".
        $stale = Session::create([
            'seeker_id' => $seeker->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'waiting',
            'completion_status' => 'pending',
            'created_date' => now()->subDays(2),
        ]);

        $this->actingAs($seekerUser)
            ->get(route('seeker.dashboard'))
            ->assertOk();

        $stale->refresh();
        $this->assertSame('cancelled', $stale->session_status);
        $this->assertSame('cancelled', $stale->completion_status);

        // The dashboard must no longer show the stale request as pending.
        $this->assertDatabaseMissing('counseling_sessions', [
            'id' => $stale->id,
            'session_status' => 'waiting',
        ]);
    }

    public function test_seeker_chat_redirects_to_evaluation_when_session_already_ended(): void
    {
        $session = Session::with('seeker')
            ->where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $session->update([
            'session_status' => 'completed',
            'completion_status' => 'completed',
            'end_time' => now(),
        ]);

        $seekerUser = User::where('role', 'seeker')->firstOrFail();

        session(['session_id' => $session->id]);

        $this->actingAs($seekerUser)
            ->get(route('session.chat'))
            ->assertRedirect(route('session.evaluation'));
    }

    public function test_helper_chat_redirects_to_notes_when_session_already_ended(): void
    {
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $session->update([
            'session_status' => 'completed',
            'completion_status' => 'completed',
            'end_time' => now(),
        ]);

        $this->actingAs($this->helperUser)
            ->get(route('helper.session.chat', ['id' => $session->id]))
            ->assertRedirect(route('helper.session.notes', ['id' => $session->id]));
    }

    public function test_evaluation_completion_marks_session_completed_and_frees_helper(): void
    {
        Event::fake([SessionEnded::class]);

        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $session = $this->makeActiveSession();

        $this->actingAs($seekerUser)
            ->post(route('session.evaluation.process'), $this->validEvaluationPayload())
            ->assertRedirect(route('session.thank-you'));

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => 'evaluated',
            'completion_status' => 'completed',
        ]);

        $this->assertDatabaseHas('help_seeker_evaluations', [
            'session_id' => $session->id,
            'overall_score' => 5,
        ]);
    }

    public function test_calendar_renders(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.calendar'))
            ->assertOk();
    }

    public function test_competency_page_renders_saved_evaluations(): void
    {
        $response = $this->actingAs($this->helperUser)
            ->get(route('helper.competency'));

        $response->assertOk();
        $response->assertSee('Skill Breakdown');
    }

    public function test_resources_page_renders_from_database(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.resources'))
            ->assertOk();
    }

    public function test_notifications_page_renders_and_can_mark_read(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.notifications'))
            ->assertOk();

        $unread = \App\Models\Notification::where('user_account_id', $this->helperUser->id)
            ->where('status', 'unread')
            ->first();

        $this->actingAs($this->helperUser)
            ->post(route('helper.notifications.read', ['id' => $unread->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'id' => $unread->id,
            'status' => 'read',
        ]);
    }

    public function test_profile_page_renders_and_updates(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.profile'))
            ->assertOk();

        $this->actingAs($this->helperUser)
            ->put(route('helper.profile.update'), [
                'first_name' => 'Helper',
                'last_name' => 'Updated',
                'bio' => 'A short bio.',
                'phone' => '09170000000',
                'preferred_language' => 'Filipino',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'last_name' => 'Updated',
            'bio' => 'A short bio.',
        ]);
    }

    public function test_settings_page_renders_and_updates(): void
    {
        $this->actingAs($this->helperUser)
            ->get(route('helper.settings'))
            ->assertOk();

        $this->actingAs($this->helperUser)
            ->put(route('helper.settings.update'), [
                'dark_mode' => 1,
                'font_size' => 'large',
                'email_notifications' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->helperUser->id,
            'dark_mode' => true,
        ]);
    }

    public function test_sidebar_partials_render(): void
    {
        $view = $this->actingAs($this->helperUser)
            ->view('layouts.partials.helper-sidebar');

        $view->assertSee('COMPASS');
        $view->assertSee('Assigned Cases');
        $view->assertSee('Logout');
    }

    // ─────────────────────────────────────────────────────────────
    // POST-SESSION EVALUATION FLOW
    // ─────────────────────────────────────────────────────────────

    private function makeActiveSession(): Session
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $helper = $this->helperUser->helper;

        $session = Session::create([
            'seeker_id' => $seekerUser->helpSeeker->id,
            'helper_id' => $helper->id,
            'concern_id' => \App\Models\ConcernCategory::first()->id,
            'risk_level' => 'low',
            'session_type' => 'chat',
            'session_status' => 'active',
            'completion_status' => 'pending',
            'start_time' => now()->subMinutes(12),
            'created_date' => now(),
        ]);

        $helper->update(['status' => 'busy']);

        return $session;
    }

    private function validEvaluationPayload(): array
    {
        return [
            'helpfulness' => 'very_helpful',
            'comfort' => 'comfortable',
            'feeling' => 'better',
            'understood' => 'yes',
            'reuse' => 'yes',
            'rating' => 5,
            'highlights' => ['active_listening', 'advice_quality'],
            'comments' => 'The session really helped me calm down.',
        ];
    }

    public function test_evaluation_submission_redirects_to_thank_you_and_completes_the_session(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $session = $this->makeActiveSession();

        $this->actingAs($seekerUser)
            ->post(route('session.evaluation.process'), $this->validEvaluationPayload())
            ->assertRedirect(route('session.thank-you'));

        $this->assertDatabaseHas('help_seeker_evaluations', [
            'session_id' => $session->id,
            'overall_score' => 5,
        ]);

        // The session must be marked evaluated so the dashboard stops
        // showing the "End & Evaluate" card (this was the evaluation loop).
        $session->refresh();
        $this->assertSame('evaluated', $session->session_status);
        $this->assertSame('completed', $session->completion_status);

        // The helper must be freed so they can take new cases.
        $this->assertDatabaseHas('helpers', [
            'id' => $this->helperUser->helper->id,
            'status' => 'available',
        ]);
    }

    public function test_evaluation_page_resumes_an_active_session_from_the_database(): void
    {
        // Simulate a seeker who opened the evaluation link after a refresh
        // (no session_id in the PHP session) — the controller must fall back
        // to the latest active session in the database.
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $this->makeActiveSession();

        $this->actingAs($seekerUser)
            ->get(route('session.evaluation'))
            ->assertOk()
            ->assertSee('Post-Session Evaluation')
            ->assertSee('Submit Feedback');
    }

    public function test_thank_you_page_renders(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();

        $this->actingAs($seekerUser)
            ->get(route('session.thank-you'))
            ->assertOk()
            ->assertSee('Thank You')
            ->assertSee('Go to Dashboard');
    }

    public function test_already_evaluated_session_redirects_to_thank_you_page(): void
    {
        $seekerUser = User::where('role', 'seeker')->firstOrFail();
        $session = $this->makeActiveSession();

        \App\Models\HelpSeekerEvaluation::create([
            'session_id' => $session->id,
            'overall_score' => 4,
            'helpfulness_score' => 4,
            'comfort_score' => 4,
            'feeling_after_score' => 4,
            'understood_score' => 1,
            'reuse_score' => 1,
        ]);

        $this->actingAs($seekerUser)
            ->from(route('session.evaluation'))
            ->get(route('session.evaluation'))
            ->assertRedirect(route('session.thank-you'));
    }

    // ─────────────────────────────────────────────────────────────
    // EMERGENCY FLAG + REFERRAL
    // ─────────────────────────────────────────────────────────────

    public function test_helper_can_flag_an_emergency(): void
    {
        $session = $this->makeActiveSession();

        $this->actingAs($this->helperUser)
            ->from(route('helper.session.chat', ['id' => $session->id]))
            ->post(route('helper.session.emergency', ['id' => $session->id]), [
                'description' => 'Seeker expressed immediate risk of self-harm.',
                'immediate_action' => 'Kept the seeker talking and asked about their location.',
            ])
            ->assertRedirect(route('helper.session.chat', ['id' => $session->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incident_reports', [
            'session_id' => $session->id,
            'risk_level' => 'emergency',
            'incident_category' => 'emergency_flag',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'risk_level' => 'emergency',
        ]);

        // The adviser must be notified so they can follow up.
        $this->assertDatabaseHas('notifications', [
            'user_account_id' => User::where('role', 'adviser')->first()->id,
            'notification_type' => 'emergency',
        ]);
    }

    public function test_helper_can_recommend_a_referral(): void
    {
        $session = $this->makeActiveSession();

        $this->actingAs($this->helperUser)
            ->from(route('helper.session.chat', ['id' => $session->id]))
            ->post(route('helper.session.referral', ['id' => $session->id]), [
                'referral_reason' => 'Seeker may benefit from professional counseling for grief.',
                'priority_level' => 'moderate',
                'help_seeker_consent' => 1,
            ])
            ->assertRedirect(route('helper.session.chat', ['id' => $session->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('referrals', [
            'session_id' => $session->id,
            'helper_id' => $this->helperUser->helper->id,
            'priority_level' => 'moderate',
            'status' => 'pending_adviser',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_account_id' => User::where('role', 'adviser')->first()->id,
            'notification_type' => 'referral',
        ]);
    }
}
