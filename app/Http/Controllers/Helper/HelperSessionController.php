<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperSessionController extends Controller
{
    public function __construct(
        protected HelperChatController $chat,
    ) {}

    /**
     * Route: helper.session.chat → delegate to the chat controller.
     */
    public function chat(int $id)
    {
        return $this->chat->show($id);
    }

    /**
     * Route: helper.session.chat.send → delegate to the chat controller.
     */
    public function sendMessage(Request $request, int $id)
    {
        return $this->chat->send($request, $id);
    }

    /**
     * Show the voice call room for a session.
     */
    public function voice(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $callLog = $session->callLog;

        $elapsed = $session->start_time
            ? sprintf('%d:%02d', floor($session->start_time->diffInSeconds(now()) / 60), $session->start_time->diffInSeconds(now()) % 60)
            : '0:00';

        return view('helper.voice', compact('session', 'callLog', 'elapsed'));
    }

    /**
     * Mark the voice call as started (records the timestamp in the session).
     */
    public function startVoice(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::where('helper_id', $helper->id)->findOrFail($id);

        session(['voice_call_started_at_' . $session->id => now()->timestamp]);

        return response()->json(['ok' => true, 'started_at' => now()->timestamp]);
    }

    /**
     * End the voice call and persist a call log in the database.
     */
    public function endVoice(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $startedTs = session('voice_call_started_at_' . $session->id);
        $callStart = $startedTs
            ? now()->createFromTimestamp((int) $startedTs)
            : ($session->start_time ?? now());
        $callEnd = now();
        $durationSeconds = max(1, (int) $callStart->diffInSeconds($callEnd));

        CallLog::updateOrCreate(
            ['session_id' => $session->id],
            [
                'recording_consent' => $session->voice_recording_consent,
                'call_start' => $callStart,
                'call_end' => $callEnd,
                'duration' => $durationSeconds,
                'review_status' => 'pending',
            ]
        );

        session()->forget('voice_call_started_at_' . $session->id);

        return redirect()
            ->route('helper.session.notes', ['id' => $session->id])
            ->with('success', 'Call ended. Add your session notes below.');
    }

    /**
     * Show the session documentation (notes) page.
     */
    public function notes(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'concern', 'report'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $report = $session->report;

        $skills = [
            'active_listening' => 'Active Listening',
            'empathy' => 'Empathy',
            'crisis_intervention' => 'Crisis Intervention',
            'problem_solving' => 'Problem Solving',
            'validation' => 'Validation',
            'referral' => 'Referral',
        ];

        return view('helper.notes', compact('session', 'report', 'skills'));
    }

    /**
     * Store the session report (documentation) in the database.
     */
    public function storeNotes(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'help_seeker_condition' => 'nullable|string|max:500',
            'session_summary' => 'required|string|max:2000',
            'personal_reflection' => 'nullable|string|max:2000',
            'skills_applied' => 'nullable|array',
            'skills_applied.*' => 'string|max:50',
            'referral_recommended' => 'nullable|boolean',
        ]);

        SessionReport::updateOrCreate(
            ['session_id' => $session->id],
            [
                'help_seeker_condition' => $validated['help_seeker_condition'] ?? null,
                'session_summary' => $validated['session_summary'],
                'personal_reflection' => $validated['personal_reflection'] ?? null,
                'skills_applied' => $validated['skills_applied'] ?? [],
                'referral_recommended' => $request->boolean('referral_recommended'),
            ]
        );

        return back()->with('success', 'Session notes saved successfully.');
    }

    /**
     * End the session: mark completed, compute duration, free the helper, notify the seeker.
     */
    public function end(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        if ($session->session_status !== 'completed') {
            $now = now();
            $duration = $session->start_time
                ? max(1, (int) $session->start_time->diffInMinutes($now))
                : 0;

            $session->update([
                'session_status' => 'completed',
                'completion_status' => 'completed',
                'end_time' => $now,
                'duration' => $duration,
            ]);

            Helper::where('id', $helper->id)->update(['status' => 'available']);

            if ($session->seeker) {
                Notification::create([
                    'user_account_id' => $session->seeker->user_account_id,
                    'title' => 'Session completed',
                    'message' => 'Your session has been completed. Please share your feedback to help us improve.',
                    'notification_type' => 'evaluation',
                    'type_icon' => '📝',
                    'link' => '/session/evaluation',
                ]);
            }
        }

        return redirect()
            ->route('helper.session.notes', ['id' => $session->id])
            ->with('success', 'Session completed. Please complete your documentation.');
    }
}