<?php

namespace Tests\Feature;

use App\Events\SessionEnded;
use App\Models\HelpSeeker;
use App\Models\Helper;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SessionDurationTest extends TestCase
{
    use RefreshDatabase;

    private function room(): array
    {
        $this->travelTo(now()->startOfSecond());
        Event::fake([SessionEnded::class]);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $profile = HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'CalmFox42']);
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'first_name' => 'Peer', 'last_name' => 'Helper', 'email' => $helperUser->email, 'status' => 'busy']);
        \App\Models\ReadinessCheck::create([
            'helper_id' => $helper->id, 'assessment_result' => 'ready',
            'emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'low',
            'assessment_date' => now(), 'valid_until' => now()->addHours(4),
        ]);
        $session = Session::create(['seeker_id' => $profile->id, 'helper_id' => $helper->id, 'session_status' => 'active', 'start_time' => now()->subMinutes(90)->addSecond()]);
        return [$seeker, $helperUser, $session];
    }

    public function test_status_closes_at_exact_deadline_without_scheduler_and_is_idempotent(): void
    {
        [$seeker, $helper, $session] = $this->room();
        $this->actingAs($seeker)->getJson(route('chat.status', $session->id))
            ->assertOk()->assertJsonPath('session.ended', false)->assertJsonPath('session.remaining_seconds', 1);
        $this->travel(1)->seconds();
        $this->getJson(route('chat.status', $session->id))->assertOk()
            ->assertJsonPath('session.ended', true)->assertJsonPath('session.remaining_seconds', 0);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'duration' => 90, 'auto_completed' => true]);
        $endedAt = $session->fresh()->end_time->toIso8601String();
        $this->travel(10)->minutes();
        $this->actingAs($helper)->getJson(route('chat.status', $session->id))->assertJsonPath('session.ended', true);
        $this->assertSame($endedAt, $session->fresh()->end_time->toIso8601String());
        Event::assertDispatchedTimes(SessionEnded::class, 1);
    }

    public function test_message_polling_expires_session_and_late_messages_are_rejected(): void
    {
        [$seeker, $helper, $session] = $this->room();
        $this->travel(1)->seconds();
        $this->actingAs($seeker)->getJson(route('chat.messages', $session->id))->assertJsonPath('session.ended', true);
        $this->postJson(route('chat.send'), ['session_id' => $session->id, 'message' => 'late'])->assertStatus(409);
        $this->actingAs($helper)->postJson(route('helper.session.chat.send', $session->id), ['message' => 'late'])->assertStatus(409);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_late_end_actions_cannot_overwrite_expiry_time(): void
    {
        [$seeker, $helper, $session] = $this->room();
        $this->travel(20)->minutes();
        $this->actingAs($seeker)->withSession(['session_id' => $session->id])->post(route('session.end'))->assertRedirect();
        $this->assertSame(90, $session->fresh()->duration);
        $endedAt = $session->fresh()->end_time->toIso8601String();
        $this->actingAs($helper)->post(route('helper.session.end', $session->id))->assertRedirect();
        $this->assertSame($endedAt, $session->fresh()->end_time->toIso8601String());
    }

    public function test_nonparticipant_cannot_trigger_expiry(): void
    {
        [, , $session] = $this->room();
        $this->travel(1)->minutes();
        $this->actingAs(User::factory()->create(['role' => 'seeker']))->getJson(route('chat.status', $session->id))->assertForbidden();
        $this->assertSame('active', $session->fresh()->session_status);
    }
}
