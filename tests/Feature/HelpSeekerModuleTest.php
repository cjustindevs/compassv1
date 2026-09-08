<?php

namespace Tests\Feature;

use App\Models\ConcernCategory;
use App\Models\HelpSeeker;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpSeekerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeker_can_complete_chat_only_screening_preferences_and_enter_queue(): void
    {
        [$user] = $this->seekerUser();
        $concern = ConcernCategory::firstOrCreate(['concern_name' => 'Academic Stress']);

        $this->actingAs($user)->post(route('request.screening.process'), [
            'concern_id' => $concern->id,
            'description' => 'I am overwhelmed by school requirements this week.',
            'current_suicide_plan' => 0, 'suicidal_thoughts' => 0, 'severe_distress' => 0, 'recurring_distress' => 0, 'difficulty_coping' => 0,
        ])->assertRedirect(route('request.preferences'));

        $this->actingAs($user)->post(route('request.preferences.process'), [
            'support_mode' => 'chat',
            'preferred_language' => 'Tagalog',
            'additional_notes' => 'Please use simple language.',
        ])->assertRedirect(route('request.matching'));

        $session = Session::where('seeker_id', $user->helpSeeker->id)->firstOrFail();

        $this->assertSame('chat', $session->session_type);
        $this->assertSame(Session::STATUS_PREFERENCES_SET, $session->session_status);
        $this->assertSame('Tagalog', $user->fresh()->preferred_language);
        $this->assertSame('chat', $user->fresh()->preferred_communication_mode);

        $this->assertDatabaseHas('queue_requests', [
            'seeker_id' => $user->helpSeeker->id,
            'request_status' => 'waiting',
            'preferred_session_type' => 'chat',
            'queue_position' => 1,
            'estimated_wait' => 8,
        ]);
    }

    public function test_preferences_reject_unsupported_support_modes(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'session_type' => 'chat',
            'session_status' => Session::STATUS_SCREENING_COMPLETED,
            'risk_level' => 'low',
            'created_date' => now(),
            'completion_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->from(route('request.preferences'))
            ->post(route('request.preferences.process'), [
                'support_mode' => 'video',
                'preferred_language' => 'English',
            ])
            ->assertRedirect(route('request.preferences'))
            ->assertSessionHasErrors('support_mode');
    }

    public function test_voice_preferences_are_rejected(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'session_type' => 'chat',
            'session_status' => Session::STATUS_SCREENING_COMPLETED,
            'risk_level' => 'low',
            'created_date' => now(),
            'completion_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->from(route('request.preferences'))
            ->post(route('request.preferences.process'), [
                'support_mode' => 'voice',
                'preferred_language' => 'English',
            ])
            ->assertRedirect(route('request.preferences'))
            ->assertSessionHasErrors('support_mode');
    }

    public function test_voice_preferences_are_rejected_even_with_consent(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'session_type' => 'chat',
            'session_status' => Session::STATUS_SCREENING_COMPLETED,
            'risk_level' => 'low',
            'created_date' => now(),
            'completion_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->post(route('request.preferences.process'), [
                'support_mode' => 'voice',
                'preferred_language' => 'English',
                'voice_consent' => '1',
            ])
            ->assertSessionHasErrors('support_mode');
        $this->assertDatabaseCount('queue_requests', 0);
        $this->assertSame('chat', $session->fresh()->session_type);
    }

    public function test_matching_page_shows_queue_position_and_estimated_wait(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'session_type' => 'chat',
            'session_status' => Session::STATUS_WAITING,
            'risk_level' => 'moderate',
            'created_date' => now(),
            'completion_status' => 'pending',
        ]);

        QueueRequest::create([
            'seeker_id' => $seeker->id,
            'request_status' => 'waiting',
            'priority_level' => 'moderate',
            'preferred_session_type' => 'chat',
            'queue_position' => 2,
            'estimated_wait' => 10,
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->get(route('request.matching'))
            ->assertOk()
            ->assertSee('Queue position: #2')
            ->assertSee('Estimated wait: 10 min');
    }

    public function test_chat_page_uses_persisted_session_start_time_for_timer(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $this->helperId(),
            'session_type' => 'chat',
            'session_status' => Session::STATUS_ACTIVE,
            'risk_level' => 'low',
            'start_time' => now()->subMinutes(7),
            'created_date' => now(),
            'completion_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->get(route('session.chat'))
            ->assertOk()
            ->assertSee('data-started-at="' . $session->fresh()->start_time->timestamp . '"', false);
    }

    public function test_completed_session_evaluation_accepts_ten_point_scores(): void
    {
        [$user, $seeker] = $this->seekerUser();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $this->helperId(),
            'session_type' => 'chat',
            'session_status' => Session::STATUS_COMPLETED,
            'risk_level' => 'low',
            'start_time' => now()->subMinutes(20),
            'end_time' => now(),
            'duration' => 20,
            'created_date' => now(),
            'completion_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->withSession(['session_id' => $session->id])
            ->post(route('session.evaluation.process'), [
                'session_id' => $session->id,
                'helpfulness_score' => 10, 'comfort_score' => 10, 'feeling_after_score' => 10,
                'understood_score' => 10, 'reuse_score' => 10,
                'comments' => 'Thank you.',
            ])
            ->assertRedirect(route('session.thank-you'));

        $this->assertDatabaseHas('help_seeker_evaluations', [
            'session_id' => $session->id,
            'overall_score' => 10,
            'comments' => 'Thank you.',
        ]);
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'session_status' => Session::STATUS_EVALUATED,
        ]);
    }

    private function seekerUser(): array
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'Seeker' . $user->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
            'account_created' => now(),
        ]);

        return [$user, $seeker];
    }

    private function helperId(): int
    {
        $helperUser = User::factory()->create(['role' => 'helper']);

        return \App\Models\Helper::create([
            'user_account_id' => $helperUser->id,
            'first_name' => 'Peer',
            'last_name' => 'Helper',
            'email' => $helperUser->email,
            'status' => 'busy',
            'competency_level' => 2,
            'max_concurrent_sessions' => 2,
        ])->id;
    }
}
