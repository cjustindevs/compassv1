<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\ModeratorAlert;
use App\Events\SessionEnded;
use App\Models\Helper;
use App\Models\HelpSeekerEvaluation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\ChatTranscriptionService;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    use BroadcastsSafely;

    public function __construct(protected ChatTranscriptionService $transcriptionService) {}

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



        if ($session->isCompleted()) {
            return redirect()->route('session.evaluation')
                ->with('info', 'This session has already ended.');
        }

        if (!$session->helper || !$session->isActive() || !$session->helper_accepted_at) {
            return redirect()->route('request.matching')
                ->with('error', 'Your session is still waiting for a helper.');
        }

        $messages = Message::where('session_id', $session->id)
            ->orderBy('sent_datetime', 'asc')
            ->get();

        $helperName = $session->helper->public_alias ?: 'Peer Helper';
        $seekerName = $session->seeker->generated_alias ?? Auth::user()->name ?? 'Seeker';

        $report = $session->report;

        return view('session.chat', compact('session', 'messages', 'helperName', 'seekerName', 'report'));
    }

    /**
     * Record the seeker's condition check-in at session start. The value the
     * seeker selects is stored as the session report's seeker condition, so it
     * reflects the seeker's own answer rather than the helper's words.
     */
    public function checkIn(Request $request)
    {
        Gate::authorize('seeker-workflow');

        $options = [
            'coping_well' => 'Coping well',
            'mild_distress' => 'Mild distress',
            'moderate_distress' => 'Moderate distress',
            'severe_distress' => 'Severe distress',
            'prefer_not_to_say' => 'Prefer not to say',
        ];

        $validated = $request->validate([
            'condition' => ['required', Rule::in(array_keys($options))],
        ]);

        $session = $this->activeSession();
        if (! $session || ! $session->isActive()) {
            return response()->json(['error' => 'Only an active session can record a check-in.'], 409);
        }

        $report = SessionReport::firstOrNew(['session_id' => $session->id]);
        $report->help_seeker_condition = $options[$validated['condition']];
        $report->save();

        \App\Services\SupportAudit::record('seeker_checkin_recorded', $session, ['condition' => $validated['condition']]);

        return response()->json(['saved' => true, 'condition' => $report->help_seeker_condition]);
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

        app(\App\Services\SessionDurationService::class)->expire($session);

        if ($session->isCompleted()) {
            return response()->json(['error' => 'This session has already ended.'], 409);
        }

        if (! $session->isActive() || !$session->helper_accepted_at) {
            return response()->json(['error' => 'Please wait for the helper to start the session.'], 409);
        }

        $message = Message::create([
            'session_id' => $session->id,
            'sender_id' => Auth::id(),
            'sender' => 'seeker',
            'message_text' => $request->message,
            'transcript' => $request->message,
            'is_transcript' => true,
            'transcript_generated_at' => now(),
            'sent_datetime' => now(),
        ]);

        $this->broadcastSafely(new MessageSent($message));

        return response()->json([
            'id' => $message->id,
            'message' => $message->message_text,
            'sender' => 'seeker',
            'sender_role' => 'seeker',
            'sender_name' => $message->senderName(),
            'time' => $message->time_formatted,
        ]);
    }

    /**
     * Show the live voice session
     */
    public function voice() { abort(503, 'Voice calls, recording and automatic transcription are unavailable. Please use chat.'); }

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

        app(\App\Services\SessionDurationService::class)->expire($session);
        if ($session->isCompleted()) {
            return redirect()->route('session.evaluation');
        }

        abort_unless($session->isActive() && $session->helper_accepted_at,409,'Only an accepted active session can be completed.');
        $now = now();
        $durationMinutes = $session->start_time
            ? max(1, (int) $session->start_time->diffInMinutes($now))
            : 0;

        if (!app(\App\Services\SessionDurationService::class)->complete($session)) return redirect()->route('session.evaluation');
        // Tell the helper (and anyone watching the room) the session has ended.
        $this->broadcastSafely(new SessionEnded($session, 'seeker'));

        // Let every moderator know in real time so their live session stats refresh.
        foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
            $this->broadcastSafely(new ModeratorAlert($moderatorUserId, 'session', 'Session ended', 'Session #' . $session->id . ' has been completed.', '/moderator/sessions'));
        }

        // Free the helper for future matches and notify them
        if ($session->helper_id) {
            Helper::where('id', $session->helper_id)
                ->where('status', 'busy')
                ->update(['status' => 'available']);

            $helper = $session->helper;
            if ($helper && $helper->user_account_id) {
                Notification::create([
                    'user_account_id' => $helper->user_account_id,
                    'title' => 'Session completed',
                    'message' => 'The seeker has ended the session. Please complete your session notes.',
                    'notification_type' => 'session',
                    'type_icon' => 'fa-pen-to-square',
                    'link' => '/helper/session/' . $session->id . '/notes',
                ]);
            }
        }

        session([
            'session_id' => $session->id,
            'session_duration_minutes' => $durationMinutes,
            'session_ended_at' => $now,
        ]);

        return redirect()->route('session.evaluation');
    }

    /**
     * Show post-session evaluation.
     *
     * Resolves the session from the PHP session first, then falls back to the
     * latest completed session that still has no evaluation (e.g. the helper
     * ended the session and the seeker opened the notification link).
     * If the session was already evaluated, send the seeker to the thank-you page.
     */
    public function evaluation(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('seeker-workflow');
        $session = $request->filled('session_id') ? Session::where('seeker_id',$request->user()->helpSeeker->id)->findOrFail($request->integer('session_id')) : $this->activeSession();

        if (!$session) {
            $session = Session::with(['helper', 'seeker'])
                ->where('seeker_id', Auth::user()->helpSeeker?->id)
                ->whereIn('session_status', ['completed', 'evaluated'])
                ->whereDoesntHave('evaluation')
                ->orderByDesc('end_time')
                ->first();
        }

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'No session found to evaluate.');
        }

        if (! in_array($session->session_status, [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED])) {
            return redirect()->route('session.chat');
        }

        if ($session->evaluation) {
            return redirect()->route('session.thank-you');
        }

        session(['session_id' => $session->id]);

        $sessionId = $session->id;
        $helperName = $session->helper->public_alias ?? 'Peer Helper';
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
        \Illuminate\Support\Facades\Gate::authorize('seeker-workflow');
        $fields = array_keys(\App\Services\EvaluationInstrument::OPTIONS);
        $rules = \App\Services\EvaluationInstrument::rules();
        $validated = $request->validate($rules + ['session_id' => 'required|integer', 'comments' => 'nullable|string|max:500']);
        $session = Session::where('seeker_id', Auth::user()->helpSeeker?->id)->findOrFail($validated['session_id']);
        abort_unless(in_array($session->session_status, [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED]), 409, 'End the session before submitting feedback.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($session, $validated, $fields) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_if($session->evaluation()->exists(), 409, 'Feedback has already been submitted.');
            $answers = array_intersect_key($validated, array_flip($fields));
            $scores = \App\Services\EvaluationInstrument::scores($answers);
            HelpSeekerEvaluation::create($scores + [
                'answers'=>$answers,'instrument_version'=>'compass-v4-categorical-1','submitted_at'=>now(),
                'session_id' => $session->id,
                'overall_score' => round(array_sum($scores) / count($scores), 1),
                'comments' => $validated['comments'] ?? null,
            ]);
            \App\Services\SupportAudit::record('evaluation_submitted',$session);
            $session->update(['session_status' => Session::STATUS_EVALUATED, 'seeker_evaluation_submitted' => true]);
        });
        session(['evaluation_completed' => true]);
        session()->forget(['screening_data', 'preferences_data', 'risk_level', 'helper_id', 'session_id', 'voice_consent']);
        return redirect()->route('session.thank-you');
    }

    /**
     * Show the thank-you page after a completed evaluation.
     */
    public function thankYou()
    {
        return view('session.thank-you');
    }

    /**
     * Show real session history from the database
     */
    public function history()
    {
        \Illuminate\Support\Facades\Gate::authorize('seeker-workflow');
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
                        'evaluated' => 'Evaluated',
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
                        'helper' => $session->helper->public_alias ?? '—',
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
     * Load the ongoing session for the current seeker.
     *
     * Fast path: the session id stored in the PHP session. Fallback: the latest
     * active session row in the database so the flow survives refreshes and
     * browser closes. Never returns a session that belongs to another seeker.
     */
    private function activeSession(): ?Session
    {
        \Illuminate\Support\Facades\Gate::authorize('seeker-workflow');
        $sessionId = session('session_id');
        $session = $sessionId ? Session::with(['helper', 'seeker'])->find($sessionId) : null;

        if ($session && $session->seeker_id === Auth::user()->helpSeeker?->id) {
            return $session;
        }

        return Session::with(['helper', 'seeker'])
            ->where('seeker_id', Auth::user()->helpSeeker?->id)
            ->where('session_status', Session::STATUS_ACTIVE)
            ->orderByDesc('start_time')
            ->first();
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
