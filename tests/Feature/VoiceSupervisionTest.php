<?php

namespace Tests\Feature;

use App\Events\{SessionEnded, VoiceSignal};
use App\Models\{Adviser, ConsentRecord, HelpSeeker, Helper, Session, User, VoiceListen, VoiceTurnCredential};
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** An Adviser listens live (receive-only) to a connected call of a Helper they supervise. */
class VoiceSupervisionTest extends TestCase
{
    use RefreshDatabase;

    private const OFFER = "v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=sendonly\r\n";
    private const LISTEN_ANSWER = "v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=recvonly\r\n";

    /** @return array{0: User, 1: User, 2: Session, 3: User} seeker, helper, session, adviser */
    private function room(bool $supervisionConsent = true): array
    {
        $this->withoutVite();
        config(['voice.enabled' => true, 'voice.metered_domain' => 'compass-test.metered.live', 'voice.metered_secret' => 'private-master-secret',
            'broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'public-app-key',
            'broadcasting.connections.reverb.secret' => 'private-app-secret', 'broadcasting.connections.reverb.app_id' => 'compass-test']);
        Event::fake([VoiceSignal::class, SessionEnded::class]);
        $adviserUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        $adviser = Adviser::create(['user_account_id' => $adviserUser->id, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => $adviserUser->email]);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $profile = HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'QuietOwl7']);
        $helperUser = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $helperUser->id, 'adviser_id' => $adviser->id, 'first_name' => 'Peer', 'last_name' => 'Helper', 'email' => $helperUser->email, 'status' => 'busy']);
        $session = Session::create(['seeker_id' => $profile->id, 'helper_id' => $helper->id, 'session_type' => 'voice', 'session_status' => 'active',
            'workflow_state' => 'session_active', 'helper_accepted_at' => now()->subMinute(), 'start_time' => now()->subMinute()]);
        $this->actingAs($seeker);
        $purposes = ['privacy_policy', 'informed_consent', 'voice_participation'];
        if ($supervisionConsent) $purposes[] = 'voice_supervision';
        foreach ($purposes as $purpose) app(ConsentService::class)->decide($seeker, $purpose, 'accepted', in_array($purpose, ['voice_participation', 'voice_supervision'], true) ? $session->id : null);
        VoiceTurnCredential::create(['payload' => [['urls' => 'turn:relay.metered.ca:443?transport=tcp', 'username' => 'temporary-user', 'credential' => 'temporary-password']],
            'ready_at' => now()->subMinute(), 'expires_at' => now()->addHours(2)]);

        return [$seeker, $helperUser, $session, $adviserUser];
    }

    private function connectedCall(User $seeker, User $helper, Session $session): string
    {
        $call = $this->actingAs($seeker)->postJson(route('voice.start', $session))->assertOk()->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action', $session), ['call_id' => $call, 'action' => 'accept'])->assertOk();
        $this->postJson(route('voice.action', $session), ['call_id' => $call, 'action' => 'connected'])->assertOk();
        $this->actingAs($seeker)->postJson(route('voice.action', $session), ['call_id' => $call, 'action' => 'connected'])->assertOk();

        return $call;
    }

    private function listen(User $adviser, Session $session)
    {
        return $this->actingAs($adviser)->postJson(route('adviser.live.listen.start', $session->id), ['purpose' => 'quality_assurance', 'reason' => 'Checking how the Helper handles a first call.']);
    }

    public function test_supervising_adviser_listens_receive_only_and_the_participants_are_told(): void
    {
        [$seeker, $helper, $session, $adviser] = $this->room();
        $this->listen($adviser, $session)->assertStatus(409);  // no call yet
        $call = $this->connectedCall($seeker, $helper, $session);

        $start = $this->listen($adviser, $session)->assertCreated()->assertJsonPath('iceTransportPolicy', 'relay');
        $listenId = $start->json('listen_id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'adviser_voice_listen_started', 'target_id' => $session->id]);

        // Both participants see the listener (and must connect to it).
        $this->actingAs($seeker)->getJson(route('voice.state', $session))->assertJsonPath('listeners.0.id', $listenId);
        $this->actingAs($helper)->getJson(route('voice.state', $session))->assertJsonPath('listeners.0.id', $listenId);

        // The Seeker offers a copy of their own audio; it reaches only the Adviser, tagged with who sent it.
        $this->actingAs($seeker)->postJson(route('voice.signal', $session), ['call_id' => $call, 'type' => 'offer', 'sdp' => self::OFFER, 'listener_id' => $listenId])->assertOk();
        $state = $this->actingAs($adviser)->getJson(route('adviser.live.listen.state', [$session->id, $listenId]))->assertOk()->assertJsonPath('active', true);
        $this->assertSame(['offer', 'seeker', $listenId], [$state->json('signals.0.type'), $state->json('signals.0.from'), $state->json('signals.0.listener_id')]);

        // The Adviser answers receive-only; anything that would send the Adviser's audio is refused.
        $this->postJson(route('adviser.live.listen.signal', [$session->id, $listenId]), ['type' => 'answer', 'to' => 'seeker', 'sdp' => self::LISTEN_ANSWER])->assertOk();
        $this->postJson(route('adviser.live.listen.signal', [$session->id, $listenId]), ['type' => 'answer', 'to' => 'seeker', 'sdp' => "v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=sendrecv\r\n"])->assertUnprocessable();
        $this->postJson(route('adviser.live.listen.signal', [$session->id, $listenId]), ['type' => 'candidate', 'to' => 'helper', 'candidate' => ['candidate' => 'candidate:1 1 udp 1 192.168.1.1 1234 typ host']])->assertUnprocessable();
        $seekerSignals = $this->actingAs($seeker)->getJson(route('voice.state', $session).'?call_id='.$call.'&after_signal_id=0')->json('signals');
        $this->assertSame([['answer', $listenId]], array_map(fn ($s) => [$s['type'], $s['listener_id']], $seekerSignals));

        // Participants cannot answer on the Adviser's behalf.
        $this->postJson(route('voice.signal', $session), ['call_id' => $call, 'type' => 'answer', 'sdp' => self::OFFER, 'listener_id' => $listenId])->assertForbidden();
    }

    public function test_listening_needs_the_seekers_supervision_consent_and_the_supervising_adviser(): void
    {
        [$seeker, $helper, $session, $adviser] = $this->room(false);
        $this->connectedCall($seeker, $helper, $session);
        $this->listen($adviser, $session)->assertStatus(409)->assertJsonPath('message', 'The Seeker has not agreed to live supervision for this session, so this call cannot be listened to.');

        $otherUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        Adviser::create(['user_account_id' => $otherUser->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $otherUser->email]);
        $this->listen($otherUser, $session)->assertStatus(409)->assertJsonPath('message', 'Only the Adviser who supervises this Helper can listen.');
        $this->actingAs($otherUser)->postJson(route('adviser.live.listen.start', $session->id), ['purpose' => 'quality_assurance', 'reason' => 'short'])->assertUnprocessable();
    }

    public function test_enabling_voice_in_chat_records_supervision_consent_too(): void
    {
        [$seeker, $helper, $session] = $this->room(false);
        ConsentRecord::where('purpose', 'voice_participation')->delete();
        $this->actingAs($seeker)->postJson(route('voice.consent', $session), ['voice_participation' => true])->assertOk();
        $this->assertDatabaseHas('consent_records', ['session_id' => $session->id, 'purpose' => 'voice_supervision', 'decision' => 'accepted']);
    }

    public function test_call_screen_carries_the_listening_notice_and_supervision_wording(): void
    {
        [$seeker, , $session] = $this->room();
        $session->update(['session_type' => 'chat']);
        $html = $this->actingAs($seeker)->get(route('session.chat'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<p class="voice-call-supervised" data-voice-supervised role="status" hidden>/', $html);
        $this->assertStringContainsString('A COMPASS Adviser is listening for supervision. Calls are not recorded.', $html);
        $this->assertStringContainsString('I agree to voice calls and live supervision.', $html);
        $this->assertStringNotContainsString('Audio is not recorded or transcribed. I can leave', $html);
    }

    public function test_listening_ends_with_the_call_and_stop_is_audited(): void
    {
        [$seeker, $helper, $session, $adviser] = $this->room();
        $call = $this->connectedCall($seeker, $helper, $session);
        $listenId = $this->listen($adviser, $session)->json('listen_id');
        $this->actingAs($seeker)->postJson(route('voice.action', $session), ['call_id' => $call, 'action' => 'leave'])->assertOk();
        $this->assertNotNull(VoiceListen::find($listenId)->ended_at);
        $this->actingAs($adviser)->getJson(route('adviser.live.listen.state', [$session->id, $listenId]))->assertJsonPath('active', false)->assertJsonPath('signals', []);
        $this->actingAs($seeker)->getJson(route('voice.state', $session))->assertJsonPath('listeners', []);

        $call2 = $this->connectedCall($seeker, $helper, $session);
        $listen2 = $this->listen($adviser, $session)->json('listen_id');
        $this->actingAs($adviser)->postJson(route('adviser.live.listen.stop', [$session->id, $listen2]))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'adviser_voice_listen_ended', 'target_id' => $session->id]);
        $this->actingAs($seeker)->postJson(route('voice.signal', $session), ['call_id' => $call2, 'type' => 'offer', 'sdp' => self::OFFER, 'listener_id' => $listen2])->assertStatus(409);
    }
}
