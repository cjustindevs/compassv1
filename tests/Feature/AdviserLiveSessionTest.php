<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Message;
use App\Models\Session;
use App\Models\SessionCoachNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** QA round 2: Adviser watches a live session read-only and coaches the Helper privately. */
class AdviserLiveSessionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'active'): array
    {
        $au = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        $a = Adviser::create(['user_account_id' => $au->id, 'first_name' => 'Live', 'last_name' => 'Adviser', 'email' => $au->email]);
        $hu = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $h = Helper::create(['user_account_id' => $hu->id, 'adviser_id' => $a->id, 'first_name' => 'Live', 'last_name' => 'Helper', 'email' => $hu->email]);
        $su = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $su->id, 'generated_alias' => 'QuietOwl'.$su->id, 'pseudo_id' => 'PS-LIVE-'.$su->id]);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $h->id, 'session_status' => $status, 'session_type' => 'chat',
            'start_time' => now()->subMinutes(5), 'helper_accepted_at' => now()->subMinutes(5), 'created_date' => now()]);
        Message::create(['session_id' => $session->id, 'sender_id' => $su->id, 'sender' => 'seeker', 'message_text' => 'I feel stressed about exams.', 'sent_datetime' => now()]);

        return [$au, $hu, $su, $session, $seeker];
    }

    public function test_supervising_adviser_watches_live_messages_with_the_seeker_alias_only(): void
    {
        [$au, , $su, $session, $seeker] = $this->fixture();

        $this->actingAs($au)->get(route('adviser.live.index'))->assertOk()->assertSee('Watch live')->assertSee($seeker->generated_alias);
        $this->get(route('adviser.live.show', $session->id))->assertOk()->assertSee('Coach the Helper privately');
        $this->getJson(route('adviser.live.feed', $session->id))->assertOk()
            ->assertJsonPath('live', true)
            ->assertJsonPath('messages.0.text', 'I feel stressed about exams.')
            ->assertJsonPath('messages.0.name', $seeker->generated_alias)
            ->assertJsonMissing(['name' => $su->name]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'adviser_live_session_viewed']);
    }

    public function test_coach_notes_reach_only_the_helper(): void
    {
        [$au, $hu, $su, $session] = $this->fixture();

        $this->actingAs($au)->postJson(route('adviser.live.notes', $session->id), ['note' => 'Reflect their feelings first.'])->assertCreated();
        $this->assertDatabaseHas('notifications', ['user_account_id' => $hu->id, 'title' => 'Coach note from your Adviser']);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $su->id, 'title' => 'Coach note from your Adviser']);

        $this->actingAs($hu)->getJson(route('helper.chat.coach-notes', $session->id))->assertOk()->assertJsonPath('notes.0.note', 'Reflect their feelings first.');
        $this->assertNotNull(SessionCoachNote::first()->read_at);

        // The Seeker's chat feed never carries coach notes.
        $this->actingAs($su)->getJson(route('chat.messages', $session->id))->assertDontSee('Reflect their feelings first.');
    }

    public function test_other_advisers_and_ended_sessions_are_not_watchable(): void
    {
        [, , , $session] = $this->fixture();
        $other = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $other->email]);
        $this->actingAs($other)->get(route('adviser.live.show', $session->id))->assertRedirect(route('adviser.live.index'));
        $this->getJson(route('adviser.live.feed', $session->id))->assertForbidden();
        $this->postJson(route('adviser.live.notes', $session->id), ['note' => 'Not mine.'])->assertNotFound();

        [$au, , , $ended] = $this->fixture('completed');
        $this->actingAs($au)->get(route('adviser.live.show', $ended->id))->assertRedirect(route('adviser.live.index'));
        $this->getJson(route('adviser.live.feed', $ended->id))->assertOk()->assertJsonPath('live', false)->assertJsonCount(0, 'messages');
    }
}
