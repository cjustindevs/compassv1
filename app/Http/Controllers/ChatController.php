<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Session;
use App\Services\ChatTranscriptionService;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    use BroadcastsSafely;

    public function __construct(protected ChatTranscriptionService $transcriptionService) {}

    /**
     * Persist a message for the given session and broadcast it to the channel.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:counseling_sessions,id',
            'message' => 'required|string|max:1000',
            'is_voice' => 'nullable|boolean',
            'audio_url' => 'nullable|string|max:2048',
        ]);

        $session = Session::findOrFail($request->session_id);
        $user = Auth::user();
        \Illuminate\Support\Facades\Gate::authorize('participate',$session);

        $isSeeker = $this->isSeeker($session, $user);
        $isHelper = $this->isHelper($session, $user);

        if (! $isSeeker && ! $isHelper) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        app(\App\Services\SessionDurationService::class)->expire($session);

        if ($session->isCompleted()) {
            return response()->json(['error' => 'This session has already ended.'], 409);
        }

        if (! $session->isActive() || !$session->helper_accepted_at) {
            return response()->json(['error' => 'This session is not ready for chat.'], 409);
        }

        abort_if($request->boolean('is_voice') || $request->filled('audio_url') || $session->session_type !== 'chat',422,'Voice media is unavailable.');
        $message = Message::create([
            'session_id' => $session->id,
            'sender_id' => $user->id,
            'sender' => $isSeeker ? 'seeker' : 'helper',
            'message_text' => trim($request->message),
            'sent_datetime' => now(),
            'is_reviewed' => false,
            'is_transcript' => $session->session_type === 'chat' && ! $request->boolean('is_voice'),
            'transcript' => $session->session_type === 'chat' && ! $request->boolean('is_voice') ? trim($request->message) : null,
            'transcript_generated_at' => $session->session_type === 'chat' && ! $request->boolean('is_voice') ? now() : null,
        ]);

        // Broadcast the message (sync, no queue worker needed). If the
        // websocket server is briefly unreachable, the message is still saved.
        $this->broadcastSafely(new MessageSent($message));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'message' => $message->message_text,
                'sender_id' => $message->sender_id,
                'sender' => $message->sender_role,
                'sender_role' => $message->sender_role,
                'sender_name' => $user->displayName(),
                'sent_datetime' => $message->time_formatted,
                'sent_datetime_iso' => ($message->sent_datetime ?? now())->toIso8601String(),
                'is_transcript' => (bool) $message->is_transcript,
                'audio_url' => $message->audio_url,
            ],
        ]);
    }

    /**
     * Return all messages for a session the current user belongs to.
     */
    public function getMessages($sessionId)
    {
        $session = Session::findOrFail($sessionId);
        $user = Auth::user();

        if (! $this->isSeeker($session, $user) && ! $this->isHelper($session, $user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        abort_unless($session->helper_accepted_at && in_array($session->session_status,['active','completed','evaluated']),409);
        $state = app(\App\Services\SessionDurationService::class)->state($session);

        if ($this->isHelper($session,$user) && !$session->isActive()) return response()->json(['success'=>true,'messages'=>[],'session'=>$state]);
        $messages = Message::where('session_id', $sessionId)
            ->orderBy('sent_datetime', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'message' => $message->message_text,
                'sender_id' => $message->sender_id,
                'sender' => $message->sender_role,
                'sender_role' => $message->sender_role,
                'sender_name' => $message->senderName(),
                'sent_datetime' => $message->time_formatted,
                'sent_datetime_iso' => ($message->sent_datetime ?? now())->toIso8601String(),
                'is_transcript' => (bool) $message->is_transcript,
                'audio_url' => $message->audio_url,
            ]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'session' => $state,
        ]);
    }

    public function expire(int $sessionId) {
        $session=Session::findOrFail($sessionId);
        \Illuminate\Support\Facades\Gate::authorize('participate',$session);
        app(\App\Services\SessionDurationService::class)->expire($session);
        return response()->json(['session'=>app(\App\Services\SessionDurationService::class)->state($session)]);
    }

    public function status(int $sessionId)
    {
        $session = Session::findOrFail($sessionId);
        abort_unless($this->isSeeker($session, Auth::user()) || $this->isHelper($session, Auth::user()), 403);

        return response()->json([
            'success' => true,
            'session' => app(\App\Services\SessionDurationService::class)->state($session),
        ])->header('Cache-Control', 'no-store');
    }

    private function isSeeker(Session $session, $user): bool
    {
        $seekerId = $user->helpSeeker?->id;

        return $user->is_active && $user->role==='seeker' && $seekerId && (int) $session->seeker_id === (int) $seekerId;
    }

    private function isHelper(Session $session, $user): bool
    {
        $helperId = $user->helper?->id;

        return $user->is_active && $user->role==='helper' && $helperId && (int) $session->helper_id === (int) $helperId;
    }

    public function getTranscript(int $sessionId)
    {
        $user = Auth::user();
        $session = Session::with(['seeker', 'helper.user'])->findOrFail($sessionId);
        $transcript = $this->transcriptionService->getTranscriptForUser($session, $user->id, $user->role);

        if (! $transcript) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this transcript.'], 403);
        }

        return response()->json(['success' => true, 'transcript' => $transcript]);
    }

    public function downloadTranscript(int $sessionId)
    {
        $user = Auth::user();
        $session = Session::with(['seeker', 'helper.user'])->findOrFail($sessionId);
        $transcript = $this->transcriptionService->getTranscriptForUser($session, $user->id, $user->role);

        if (! $transcript) {
            abort(403, 'You do not have access to this transcript.');
        }

        $lines = [
            'COMPASS Session Transcript',
            'Session: ' . $transcript['reference_number'],
            'Seeker: ' . $transcript['seeker_alias'],
            'Helper: ' . $transcript['helper_name'],
            'Generated: ' . $transcript['generated_at'],
            '',
        ];

        foreach ($transcript['messages'] as $message) {
            $lines[] = '[' . $message['timestamp'] . '] ' . $message['sender'] . ': ' . $message['message'];
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="transcript-' . $session->id . '.txt"',
        ]);
    }

    public function typing(Request $request)
    {
        $data=$request->validate(['session_id'=>'required|integer']);
        \Illuminate\Support\Facades\Gate::authorize('participate', Session::findOrFail($data['session_id']));
        return response()->json(['success' => true]);
    }
}
