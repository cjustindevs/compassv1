<?php

namespace App\Http\Controllers;

use App\Models\HelpSeekerEvaluation;
use App\Models\Helper;
use App\Models\Message;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Show the live chat session (messages come from the database)
     */
    public function chat()
    {
        $session = $this->activeSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'No active session found.');
        }

        if (!$session->helper) {
            return redirect()->route('request.matching')
                ->with('error', 'Your session is still waiting for a helper.');
        }

        $messages = Message::where('session_id', $session->id)
            ->orderBy('sent_datetime', 'asc')
            ->get();

        $helperName = $session->helper->full_name ?: 'Peer Helper';
        $seekerName = $session->seeker->generated_alias ?? Auth::user()->name ?? 'Seeker';

        return view('session.chat', compact('session', 'messages', 'helperName', 'seekerName'));
    }

    /**
     * Persist a chat message sent by the seeker
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $session = $this->activeSession();

        if (!$session) {
            return response()->json(['error' => 'No active session found.'], 404);
        }

        $message = Message::create([
            'session_id' => $session->id,
            'sender' => 'seeker',
            'message_text' => $request->message,
            'sent_datetime' => now(),
        ]);

        return response()->json([
            'id' => $message->id,
            'message' => $message->message_text,
            'sender' => 'seeker',
            'time' => $message->time_formatted,
        ]);
    }

    /**
     * Show the live voice session
     */
    public function voice()
    {
        $session = $this->activeSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'No active session found.');
        }

        if (!$session->helper) {
            return redirect()->route('request.matching')
                ->with('error', 'Your session is still waiting for a helper.');
        }

        $helperName = $session->helper->full_name ?: 'Peer Helper';
        $seekerName = $session->seeker->generated_alias ?? Auth::user()->name ?? 'Seeker';
        $consentGiven = session('voice_consent', true);

        return view('session.voice', compact('session', 'helperName', 'seekerName', 'consentGiven'));
    }

    /**
     * End an active session: persist duration/status and free the helper
     */
    public function endSession(Request $request)
    {
        $session = $this->activeSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'No active session found.');
        }

        $now = now();
        $durationMinutes = $session->start_time
            ? max(1, (int) $session->start_time->diffInMinutes($now))
            : 0;

        $session->update([
            'session_status' => 'completed',
            'completion_status' => 'completed',
            'end_time' => $now,
            'duration' => $durationMinutes,
        ]);

        // Free the helper for future matches (helper module not built yet)
        if ($session->helper_id) {
            Helper::where('id', $session->helper_id)
                ->where('status', 'busy')
                ->update(['status' => 'available']);
        }

        session([
            'session_id' => $session->id,
            'session_duration_minutes' => $durationMinutes,
            'session_ended_at' => $now,
        ]);

        return redirect()->route('session.evaluation');
    }

    /**
     * Show post-session evaluation
     */
    public function evaluation()
    {
        $session = $this->activeSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'No session found to evaluate.');
        }

        $sessionId = $session->id;
        $helperName = $session->helper->full_name ?? 'Peer Helper';
        $language = session('preferred_language', 'English');
        $duration = $session->duration
            ? $session->duration . 'm'
            : ($session->start_time && $session->end_time
                ? $session->start_time->format('h:i A') . ' - ' . $session->end_time->format('h:i A')
                : '—');

        return view('session.evaluation', compact(
            'sessionId',
            'helperName',
            'duration',
            'language'
        ));
    }

    /**
     * Process post-session evaluation and persist it
     */
    public function processEvaluation(Request $request)
    {
        $validated = $request->validate([
            'helpfulness' => 'required|in:very_helpful,helpful,neutral,not_helpful',
            'comfort' => 'required|in:very_comfortable,comfortable,slightly_comfortable,not_comfortable',
            'feeling' => 'required|in:better,slightly_better,same,worse',
            'understood' => 'required|in:yes,no',
            'reuse' => 'required|in:yes,no',
            'rating' => 'required|integer|min:1|max:5',
            'highlights' => 'nullable|array',
            'highlights.*' => 'string|in:response_time,energy,active_listening,advice_quality,session_length,privacy_safety,follow_up_resources',
            'comments' => 'nullable|string|max:500'
        ]);

        $session = $this->activeSession();

        if ($session) {
            HelpSeekerEvaluation::updateOrCreate(
                ['session_id' => $session->id],
                [
                    'helpfulness_score' => $this->mapHelpfulness($validated['helpfulness']),
                    'comfort_score' => $this->mapComfort($validated['comfort']),
                    'feeling_after_score' => $this->mapFeeling($validated['feeling']),
                    'understood_score' => $validated['understood'] === 'yes' ? 1 : 0,
                    'reuse_score' => $validated['reuse'] === 'yes' ? 1 : 0,
                    'overall_score' => $validated['rating'],
                    'comments' => $validated['comments'] ?? null,
                ]
            );
        }

        session([
            'evaluation_data' => $validated,
            'evaluation_completed' => true
        ]);

        session()->forget([
            'screening_data',
            'preferences_data',
            'risk_level',
            'helper_id',
            'session_id',
            'voice_consent'
        ]);

        return redirect()->route('seeker.dashboard')
            ->with('success', 'Thank you for your feedback! Your session has been recorded.');
    }

    /**
     * Show real session history from the database
     */
    public function history()
    {
        $helpSeeker = Auth::user()->helpSeeker;

        if (!$helpSeeker) {
            $sessions = [];
        } else {
            $sessions = Session::with(['helper', 'evaluation', 'concern'])
                ->where('seeker_id', $helpSeeker->id)
                ->orderByDesc('created_date')
                ->get()
                ->map(function (Session $session) {
                    $statusLabels = [
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'active' => 'Active',
                        'scheduled' => 'Scheduled',
                        'no_show' => 'No Show',
                        'screening_completed' => 'Screening Done',
                        'preferences_set' => 'Pending Helper',
                        'waiting' => 'In Queue',
                        'helper_assigned' => 'Helper Assigned',
                    ];

                    $mode = match ($session->session_type) {
                        'voice' => 'Voice',
                        default => 'Chat',
                    };

                    return [
                        'id' => '#' . str_pad((string) $session->id, 4, '0', STR_PAD_LEFT),
                        'date' => $session->created_date?->format('M d, Y'),
                        'helper' => $session->helper->full_name ?? '—',
                        'mode' => $mode,
                        'duration' => $session->duration ? $session->duration . 'm' : '—',
                        'rating' => $session->evaluation
                            ? (int) round((float) $session->evaluation->overall_score)
                            : 0,
                        'status' => $statusLabels[$session->session_status] ?? ucfirst($session->session_status),
                        'risk' => ucfirst($session->risk_level ?? 'Low'),
                    ];
                })
                ->all();
        }

        return view('session.history', compact('sessions'));
    }

    /**
     * Load the ongoing session for the current seeker
     */
    private function activeSession(): ?Session
    {
        $sessionId = session('session_id');

        if (!$sessionId) {
            return null;
        }

        return Session::with(['helper', 'seeker'])
            ->find($sessionId);
    }

    // Score mapping helpers
    private function mapHelpfulness($value)
    {
        $map = [
            'very_helpful' => 5,
            'helpful' => 4,
            'neutral' => 3,
            'not_helpful' => 1
        ];
        return $map[$value] ?? 3;
    }

    private function mapComfort($value)
    {
        $map = [
            'very_comfortable' => 5,
            'comfortable' => 4,
            'slightly_comfortable' => 2,
            'not_comfortable' => 1
        ];
        return $map[$value] ?? 3;
    }

    private function mapFeeling($value)
    {
        $map = [
            'better' => 5,
            'slightly_better' => 4,
            'same' => 3,
            'worse' => 1
        ];
        return $map[$value] ?? 3;
    }
}