<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Persist a message for the given session and broadcast it to the channel.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:counseling_sessions,id',
            'message' => 'required|string|max:1000',
        ]);

        $session = Session::findOrFail($request->session_id);
        $user = Auth::user();

        $isSeeker = $this->isSeeker($session, $user);
        $isHelper = $this->isHelper($session, $user);

        if (! $isSeeker && ! $isHelper) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'session_id' => $session->id,
            'sender_id' => $user->id,
            'sender' => $isSeeker ? 'seeker' : 'helper',
            'message_text' => trim($request->message),
            'sent_datetime' => now(),
            'is_reviewed' => false,
        ]);

        // The helper's first message moves an assigned session into active.
        if ($isHelper && $session->session_status === 'helper_assigned') {
            $session->update([
                'session_status' => 'active',
                'start_time' => $session->start_time ?? now(),
            ]);
        }

        // Broadcast the message (sync, no queue worker needed). If the
        // websocket server is briefly unreachable, the message is still saved.
        try {
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            report($e);
        }

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
            ]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    private function isSeeker(Session $session, $user): bool
    {
        $seekerId = $user->helpSeeker?->id;

        return $seekerId && (int) $session->seeker_id === (int) $seekerId;
    }

    private function isHelper(Session $session, $user): bool
    {
        $helperId = $user->helper?->id;

        return $helperId && (int) $session->helper_id === (int) $helperId;
    }
}
