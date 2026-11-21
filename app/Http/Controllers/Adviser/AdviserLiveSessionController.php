<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionCoachNote;
use App\Models\VoiceCall;
use App\Models\VoiceListen;
use App\Models\VoiceSignalPacket;
use App\Services\AdviserScope;
use App\Services\VoiceConfiguration;
use App\Services\VoiceSupervisionService;
use App\Services\SupportAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QA: "dapat pwede makita ng adviser ang live session". The supervising Adviser can watch an
 * active chat of one of their Helpers, read-only (Seeker shown by alias only), and send private
 * coaching notes that only the Helper sees. Access is limited to active sessions of Helpers the
 * Adviser supervises, and every view and note is audited (supervision consent covers review).
 */
class AdviserLiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        $adviser = $this->actor($request);
        $sessions = $this->liveQuery($adviser->id)->with(['helper', 'seeker', 'concern'])->orderBy('start_time')->get();

        return view('adviser.live-sessions', compact('sessions'));
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $adviser = $this->actor($request);
        $session = $this->liveQuery($adviser->id)->with(['helper', 'seeker', 'concern'])->find($id);
        if (! $session) {
            return redirect()->route('adviser.live.index')->with('status', 'That session is no longer live. Completed sessions are reviewed from Pending Evaluations.');
        }
        SupportAudit::record('adviser_live_session_viewed', $session, ['adviser_id' => $adviser->id]);

        return view('adviser.live-session', compact('session'));
    }

    /** Messages and coach notes after the given ids; also reports whether the session is still live. */
    public function feed(Request $request, int $id): JsonResponse
    {
        $adviser = $this->actor($request);
        $session = Session::with(['helper', 'seeker'])->findOrFail($id);
        abort_unless($session->helper?->adviser_id === $adviser->id, 403);
        $live = $session->isActive() && in_array($session->session_type, ['chat', 'voice'], true);
        $after = (int) $request->query('after', 0);
        $notesAfter = (int) $request->query('notes_after', 0);
        $messages = $live ? Message::where('session_id', $session->id)->where('id', '>', $after)->orderBy('id')->limit(200)->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'from' => $m->is_helper ? 'helper' : 'seeker',
                // Alias only for the Seeker; the Helper is shown by name to their own Adviser.
                'name' => $m->is_helper ? ($session->helper?->full_name ?? 'Helper') : ($session->seeker?->generated_alias ?? 'Seeker'),
                'text' => $m->message_text,
                'time' => ($m->sent_datetime ?? $m->created_at)?->copy()->timezone('Asia/Manila')->format('g:i A'),
            ]) : collect();
        $notes = SessionCoachNote::with('adviser')->where('session_id', $session->id)->where('id', '>', $notesAfter)->orderBy('id')->get()->map->toFeed();

        return response()->json(['live' => $live, 'status' => $session->status_label, 'messages' => $messages, 'notes' => $notes]);
    }

    public function storeNote(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $adviser = $this->actor($request);
        $data = $request->validate(['note' => 'required|string|min:2|max:600']);
        $session = $this->liveQuery($adviser->id)->findOrFail($id);
        $note = SessionCoachNote::create(['session_id' => $session->id, 'adviser_id' => $adviser->id, 'helper_id' => $session->helper_id, 'note' => trim($data['note'])]);
        SupportAudit::record('adviser_coach_note_sent', $session, ['adviser_id' => $adviser->id, 'note_id' => $note->id]);
        if ($session->helper?->user_account_id) {
            Notification::create(['user_account_id' => $session->helper->user_account_id, 'notification_type' => 'session',
                'title' => 'Coach note from your Adviser', 'message' => 'Your Adviser sent a private note for your current session. Only you can see it.',
                'link' => route('helper.chat.show', $session->id, false)]);
        }

        return $request->expectsJson()
            ? response()->json(['note' => $note->load('adviser')->toFeed()], 201)
            : back()->with('success', 'Coach note sent to the Helper.');
    }

    private function actor(Request $request)
    {
        abort_unless($request->user()?->role === 'adviser' && $request->user()?->is_active, 403);

        return app(AdviserScope::class)->actor();
    }

    private function liveQuery(int $adviserId)
    {
        return Session::where('session_status', Session::STATUS_ACTIVE)->whereIn('session_type', ['chat', 'voice'])
            ->where('is_test_record', false)
            ->whereHas('helper', fn ($q) => $q->where('adviser_id', $adviserId));
    }

    // ── Live voice supervision (receive-only; nothing is recorded) ────────────────────────────────

    public function listenStart(Request $request, int $id): JsonResponse
    {
        $this->actor($request);
        $data = $request->validate(['purpose' => ['required', 'string', \Illuminate\Validation\Rule::in(VoiceSupervisionService::PURPOSES)], 'reason' => 'required|string|min:10|max:500'], [
            'purpose.required' => 'Select a purpose.', 'reason.min' => 'Explain your reason in at least 10 characters.']);
        $session = Session::with('helper')->findOrFail($id);
        $listen = app(VoiceSupervisionService::class)->start($request->user(), $session, $data['purpose'], $data['reason']);
        $credential = app(VoiceConfiguration::class)->credential();
        abort_unless($credential, 503, 'Voice is unavailable right now.');

        return response()->json(['listen_id' => $listen->id, 'call_id' => $listen->call_id,
            'iceServers' => $credential->payload, 'iceTransportPolicy' => 'relay'], 201)->header('Cache-Control', 'no-store');
    }

    /** Signals the call participants sent to this Adviser, and whether listening can continue. */
    public function listenState(Request $request, int $id, int $listen): JsonResponse
    {
        [$session, $record] = $this->ownListen($request, $id, $listen);
        $call = VoiceCall::find($record->call_id);
        if (! $record->ended_at && (! $call || ! in_array($call->status, ['connecting', 'connected'], true) || ! $session->isActive())) {
            app(VoiceSupervisionService::class)->stop($record, 'call_ended');
        }
        $after = (int) $request->query('after', 0);
        $packets = $record->fresh()->ended_at ? collect() : VoiceSignalPacket::where('call_id', $record->call_id)->where('recipient_id', $request->user()->id)
            ->where('expires_at', '>', now())->where('id', '>', $after)->orderBy('id')->limit(100)->get();

        return response()->json(['active' => ! $record->fresh()->ended_at, 'call_status' => $call?->status,
            'signals' => $packets->map(fn ($p) => array_merge($p->payload, ['signal_id' => $p->id]))->values(),
            'cursor' => $packets->isNotEmpty() ? $packets->last()->id : $after])->header('Cache-Control', 'no-store');
    }

    /** The Adviser's answer (or ICE candidate) back to one participant's audio offer. */
    public function listenSignal(Request $request, int $id, int $listen): JsonResponse
    {
        [$session, $record] = $this->ownListen($request, $id, $listen);
        abort_if($record->ended_at, 409, 'Listening has ended.');
        $data = $request->validate(['type' => 'required|in:answer,candidate', 'to' => 'required|in:seeker,helper',
            'sdp' => 'required_if:type,answer|nullable|string|max:7000',
            'candidate' => 'required_if:type,candidate|nullable|array:candidate,sdpMid,sdpMLineIndex,usernameFragment',
            'candidate.candidate' => 'required_if:type,candidate|string|max:1500',
            'candidate.sdpMid' => 'nullable|string|max:50', 'candidate.sdpMLineIndex' => 'nullable|integer|min:0|max:10', 'candidate.usernameFragment' => 'nullable|string|max:100']);
        \App\Http\Controllers\VoiceCallController::assertRelayAudio($data);
        abort_if($data['type'] === 'answer' && preg_match('/^a=(?:sendrecv|sendonly)\b/m', $data['sdp']), 422, 'The Adviser can only listen.');
        $recipient = $data['to'] === 'helper' ? $session->helper?->user_account_id : $session->seeker?->user_account_id;
        abort_unless($recipient, 409);
        $payload = ['call_id' => $record->call_id, 'type' => $data['type'], 'listener_id' => $record->id];
        $payload[$data['type'] === 'candidate' ? 'candidate' : 'sdp'] = $data[$data['type'] === 'candidate' ? 'candidate' : 'sdp'];
        if (isset($payload['sdp'])) $payload['sdp'] = rtrim($payload['sdp'], "\r\n")."\r\n";
        $packet = VoiceSignalPacket::create(['call_id' => $record->call_id, 'recipient_id' => $recipient, 'payload' => $payload, 'expires_at' => now()->addMinutes(2)]);
        try { event(new \App\Events\VoiceSignal($session->id, $recipient, array_merge($payload, ['signal_id' => $packet->id]))); }
        catch (\Throwable $e) { /* participants also poll for signals */ }

        return response()->json(['sent' => true])->header('Cache-Control', 'no-store');
    }

    public function listenStop(Request $request, int $id, int $listen): JsonResponse
    {
        [, $record] = $this->ownListen($request, $id, $listen);
        app(VoiceSupervisionService::class)->stop($record);

        return response()->json(['stopped' => true]);
    }

    /** @return array{0: Session, 1: VoiceListen} */
    private function ownListen(Request $request, int $id, int $listen): array
    {
        $adviser = $this->actor($request);
        $session = Session::with(['helper', 'seeker'])->findOrFail($id);
        abort_unless((int) $session->helper?->adviser_id === (int) $adviser->id, 403);
        $record = VoiceListen::where('session_id', $session->id)->where('adviser_user_id', $request->user()->id)->findOrFail($listen);

        return [$session, $record];
    }
}
