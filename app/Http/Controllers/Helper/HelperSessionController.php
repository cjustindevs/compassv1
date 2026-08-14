<?php

namespace App\Http\Controllers\Helper;

use App\Events\SessionEnded;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Helper;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
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
     * Flag a session as an emergency: escalate to moderators/advisers
     * and record an incident report.
     */
    public function flagEmergency(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'seeker.user'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'immediate_action' => 'nullable|string|max:500',
        ]);

        $incident = IncidentReport::create([
            'session_id' => $session->id,
            'user_account_id' => $session->seeker?->user_account_id,
            'incident_category' => 'emergency_flag',
            'description' => $validated['description'],
            'immediate_action' => $validated['immediate_action'] ?? null,
            'risk_level' => 'emergency',
            'status' => 'open',
        ]);

        $session->update(['risk_level' => 'emergency']);

        $this->notifyStaff(
            ['moderator', 'adviser'],
            '🚨 Emergency flagged',
            'Emergency flagged in session #' . $session->id . ' by ' . $helper->full_name . '. ' . $validated['description'],
            'emergency',
            '/moderator/dashboard'
        );

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Your session has been escalated',
                'message' => 'A support coordinator has been notified about your session and will reach out.',
                'notification_type' => 'emergency',
                'type_icon' => '🛟',
                'link' => '/session/chat',
            ]);
        }

        return back()->with('success', 'Emergency flagged. A support coordinator has been notified. Incident #' . $incident->id . ' recorded.');
    }

    /**
     * Recommend a professional referral for the seeker.
     */
    public function recommendReferral(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'referral_reason' => 'required|string|max:1000',
            'priority_level' => 'required|in:low,moderate,high,emergency',
        ]);

        $referral = Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'priority_level' => $validated['priority_level'],
            'help_seeker_consent' => $request->boolean('help_seeker_consent'),
            'identity_disclosed' => $request->boolean('identity_disclosed'),
            'referral_reason' => $validated['referral_reason'],
            'referral_date' => now(),
            'status' => 'pending_adviser',
        ]);

        $this->notifyStaff(
            ['adviser'],
            '📋 New referral request',
            'Referral #' . $referral->id . ' (' . $validated['priority_level'] . ' priority) for session #' . $session->id . ' from ' . $helper->full_name . '.',
            'referral',
            '/adviser/dashboard'
        );

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'A referral was recommended for you',
                'message' => 'Your helper has recommended connecting you with a professional. An adviser will follow up.',
                'notification_type' => 'referral',
                'type_icon' => '📋',
                'link' => '/session/chat',
            ]);
        }

        return back()->with('success', 'Referral recommended. An adviser will review it shortly. Referral #' . $referral->id . ' recorded.');
    }

    /**
     * Notify every user with one of the given roles.
     */
    private function notifyStaff(array $roles, string $title, string $message, string $type, string $link): void
    {
        $recipients = User::whereIn('role', $roles)->pluck('id');

        foreach ($recipients as $userId) {
            Notification::create([
                'user_account_id' => $userId,
                'title' => $title,
                'message' => $message,
                'notification_type' => $type,
                'type_icon' => $type === 'emergency' ? '🚨' : '📋',
                'link' => $link,
            ]);
        }
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

            // Tell the seeker (and anyone watching the room) the session has ended.
            try {
                broadcast(new SessionEnded($session, 'helper'));
            } catch (\Throwable $e) {
                report($e);
            }

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