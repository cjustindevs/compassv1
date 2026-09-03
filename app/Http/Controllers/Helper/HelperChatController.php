<?php

namespace App\Http\Controllers\Helper;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Session;
use App\Services\ChatTranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperChatController extends Controller
{
    public function __construct(protected ChatTranscriptionService $transcriptionService) {}

    /**
     * Live Chat entry point — jump to the most recent session, or show a list.
     */
    public function index()
    {
        $helper = Auth::user()->helper;

        $active = Session::with('seeker')
            ->where('helper_id', $helper->id)
            ->where('session_status', 'active')
            ->orderByDesc('start_time')
            ->first();

        if ($active) {
            return redirect()->route('helper.session.chat', ['id' => $active->id]);
        }

        $sessions = Session::with(['seeker', 'concern', 'messages'])
            ->where('helper_id', $helper->id)
            ->whereIn('session_status', ['helper_assigned', 'active'])
            ->orderByDesc('created_date')
            ->get();

        return view('helper.chat', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Show the chat room for a session (messages from the database).
     */
    public function show(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'seeker.user', 'messages'])
            ->where('helper_id', $helper->id)
            ->find($id);

        if (! $session) {
            return redirect()->route('helper.chat')
                ->with('info', 'That session is no longer available. Select an active session below.');
        }

        if ($session->isCompleted()) {
            return redirect()->route('helper.session.notes', ['id' => $session->id])
                ->with('info', 'This session has ended. Please complete your session notes.');
        }

        $messages = $session->messages
            ->sortBy('sent_datetime')
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'sender' => $m->is_helper ? 'helper' : 'seeker',
                'sender_role' => $m->is_helper ? 'helper' : 'seeker',
                'sender_id' => $m->sender_id,
                'sender_name' => $m->senderName(),
                'message' => $m->message_text,
                'time' => $m->time_formatted,
                'sent_datetime' => $m->time_formatted,
                'sent_datetime_iso' => ($m->sent_datetime ?? now())->toIso8601String(),
            ])
            ->values();

        $seekerName = $session->seeker->generated_alias ?? 'Seeker';

        return view('helper.chat.show', [
            'session' => $session,
            'activeSession' => $session,
            'messages' => $messages,
            'seekerName' => $seekerName,
        ]);
    }

    /**
     * JSON endpoint — returns the latest messages for live polling.
     */
    public function messages(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with('messages')
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $messages = $session->messages
            ->sortBy('sent_datetime')
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'sender' => $m->is_helper ? 'helper' : 'seeker',
                'sender_role' => $m->is_helper ? 'helper' : 'seeker',
                'sender_id' => $m->sender_id,
                'sender_name' => $m->senderName(),
                'message' => $m->message_text,
                'time' => $m->time_formatted,
                'sent_datetime' => $m->time_formatted,
                'sent_datetime_iso' => ($m->sent_datetime ?? now())->toIso8601String(),
            ])
            ->values();

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    /**
     * Store a helper message in the database.
     * Works via /helper/session/{id}/chat/send (id in URL) or
     * /helper/chat/send (session_id in the request body).
     */
    public function send(Request $request, ?int $id = null)
    {
        $id = $id ?? (int) $request->input('session_id');

        $helper = Auth::user()->helper;

        $session = Session::where('helper_id', $helper->id)->find($id);

        if (! $session) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['error' => 'Session not found.'], 404);
            }

            return redirect()->route('helper.chat')
                ->with('error', 'That session is no longer available.');
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = Message::create([
            'session_id' => $session->id,
            'sender_id' => Auth::id(),
            'sender' => 'helper',
            'message_text' => trim($request->message),
            'transcript' => trim($request->message),
            'is_transcript' => true,
            'transcript_generated_at' => now(),
            'sent_datetime' => now(),
            'is_reviewed' => false,
        ]);

        try {
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            report($e);
        }

        if ($session->session_status === 'helper_assigned') {
            $session->update([
                'session_status' => 'active',
                'start_time' => $session->start_time ?? now(),
            ]);
        }

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'id' => $message->id,
                'message' => [
                    'id' => $message->id,
                    'message' => $message->message_text,
                    'sender_id' => $message->sender_id,
                    'sender' => 'helper',
                    'sender_role' => 'helper',
                    'sender_name' => $message->senderName(),
                    'sent_datetime' => $message->time_formatted,
                    'sent_datetime_iso' => ($message->sent_datetime ?? now())->toIso8601String(),
                ],
            ]);
        }

        return redirect()->route('helper.session.chat', ['id' => $session->id]);
    }
}
