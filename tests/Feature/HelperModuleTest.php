<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\Message;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $session = Session::where('helper_id', $this->helperUser->helper->id)
            ->where('session_status', 'active')
            ->firstOrFail();

        $this->actingAs($this->helperUser)
            ->post(route('helper.session.end', ['id' => $session->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => 'completed',
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
}
