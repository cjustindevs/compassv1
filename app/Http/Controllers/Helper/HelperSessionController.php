<?php

namespace App\Http\Controllers\Helper;

use App\Events\EmergencyTriggered;
use App\Events\ModeratorAlert;
use App\Events\ReferralRecommended;
use App\Events\SessionEnded;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Helper;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\ScreeningResponse;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\HelperMatchingService;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HelperSessionController extends Controller
{
    use BroadcastsSafely;

    public function __construct(
        protected HelperChatController $chat,
    ) {}

    public function preSessionAssessment(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'seeker.screeningResponses'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        if ($session->session_status !== Session::STATUS_HELPER_ASSIGNED) {
            return redirect()->route('helper.dashboard')
                ->with('error', 'This session is no longer pending.');
        }

        $screeningSummary = $this->getScreeningSummary($session->seeker);
        $matchingDetails = app(HelperMatchingService::class)->getMatchingDetails($helper);
        $timeRemaining = $session->pre_session_brief_expires_at
            ? max(0, now()->diffInSeconds($session->pre_session_brief_expires_at, false))
            : Helper::PRE_SESSION_BRIEF_MINUTES * 60;

        return view('helper.pre-session-assessment', compact('session', 'screeningSummary', 'matchingDetails', 'timeRemaining'));
    }

    public function startSessionFromPreAssessment(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::where('helper_id', $helper->id)->findOrFail($id);

        if ($session->session_status !== Session::STATUS_HELPER_ASSIGNED) {
            return redirect()->route('helper.dashboard')
                ->with('error', 'This session is no longer pending.');
        }

        try {
            $session = app(\App\Services\SeekerWorkflowService::class)->start(Auth::user(), $session);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return redirect()->route('helper.session.pre-assessment', $id)->with('error', $e->getMessage());
        }

        return redirect()->route('helper.session.chat', $session->id)
            ->with('success', 'Session started. You can now communicate with the help seeker.');
    }

    private function getScreeningSummary($seeker): array
    {
        $screening = ScreeningResponse::where('seeker_id', $seeker->id)
            ->latest('classified_at')
            ->first();

        if (! $screening) {
            return [
                'concern_category' => 'Not specified',
                'summary' => 'No screening data available.',
                'indicators' => [],
                'is_emergency' => false,
            ];
        }

        $responses = is_array($screening->responses) ? $screening->responses : json_decode($screening->responses, true);
        $summary = $this->createScreeningSummary($responses ?: []);

        return [
            'concern_category' => $responses['concern_category'] ?? 'Not specified',
            'summary' => $summary['text'],
            'indicators' => $summary['indicators'],
            'is_emergency' => $screening->risk_level === 'emergency',
        ];
    }

    private function createScreeningSummary(array $responses): array
    {
        $indicators = [];
        $summaryParts = [];

        foreach ([
            'severe_distress' => 'reports severe emotional distress',
            'recurring_distress' => 'experiencing recurring emotional distress',
            'difficulty_coping' => 'reports difficulty coping',
        ] as $key => $text) {
            if (($responses[$key] ?? false) === true) {
                $indicators[] = $key;
                $summaryParts[] = $text;
            }
        }

        $safetyIndicators = [];
        foreach ([
            'current_suicide_plan' => 'reports current suicidal thoughts with plan',
            'suicidal_thoughts' => 'reports suicidal thoughts',
            'recent_self_harm' => 'has recent self-harm history',
        ] as $key => $text) {
            if (($responses[$key] ?? false) === true) {
                $indicators[] = $key;
                $safetyIndicators[] = $text;
            }
        }

        $functionalImpacts = [];
        foreach ([
            'sleep_affected' => 'sleep affected',
            'concentration_affected' => 'concentration affected',
            'attendance_affected' => 'attendance affected',
            'relationships_affected' => 'relationships affected',
        ] as $key => $text) {
            if (($responses[$key] ?? false) === true) {
                $functionalImpacts[] = $text;
            }
        }

        $text = '';
        if ($safetyIndicators !== []) {
            $text .= 'Safety concern: ' . implode('; ', $safetyIndicators) . '. ';
        }
        if ($summaryParts !== []) {
            $text .= 'The seeker ' . implode('; ', $summaryParts) . '. ';
        }
        if ($functionalImpacts !== []) {
            $text .= 'Affected areas: ' . implode(', ', $functionalImpacts) . '. ';
        }

        return [
            'text' => $text !== '' ? $text : 'Seeker completed screening. No major concerns identified.',
            'indicators' => $indicators,
            'safety_indicators' => $safetyIndicators,
            'functional_impacts' => $functionalImpacts,
        ];
    }

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
        abort(503,'Voice service is unavailable.');
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
        abort(503,'Voice service is unavailable.');
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
        abort(503,'Voice service is unavailable.');
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
        $revisions = $report ? DB::table('session_report_revisions')->where('report_id', $report->id)->latest('id')->get() : collect();

        $skills = collect(\App\Services\HelperReadinessService::SKILLS)
            ->mapWithKeys(fn (string $skill) => [$skill => ucwords(str_replace('_', ' ', $skill))])
            ->all();

        return view('helper.notes', compact('session', 'report', 'skills', 'revisions'));
    }

    /**
     * Store the session report (documentation) in the database.
     */
    public function storeNotes(Request $request, int $id)
    {
        $validated = $request->validate([
            'help_seeker_condition' => 'nullable|string|max:500',
            'session_summary' => 'required|string|max:2000',
            'observations' => 'required|string|max:2000',
            'actions_taken' => 'required|string|max:2000',
            'risk_level_assessed' => 'nullable|in:low,moderate,high,emergency',
            'session_result' => 'required|in:stable,needs_follow_up,needs_referral',
            'follow_up_plan' => ['nullable', 'string', 'max:2000', \Illuminate\Validation\Rule::requiredIf(in_array($request->input('session_result'), ['needs_follow_up', 'needs_referral'], true))],
            'personal_reflection' => 'nullable|string|max:2000',
            'skills_applied' => 'nullable|array',
            'skills_applied.*' => ['string', 'max:50', \Illuminate\Validation\Rule::in(\App\Services\HelperReadinessService::SKILLS)],
            'correction_reason' => 'nullable|string|max:1000',
        ]);

        $session = Session::where('helper_id', Auth::user()->helper->id)->findOrFail($id);

        $hasReflection = filled($validated['personal_reflection'] ?? null) || ! empty($validated['skills_applied'] ?? []);
        app(\App\Services\HelperDocumentationService::class)->save(Auth::user(), $session, $validated, $hasReflection ? 'both' : 'summary');

        return back()->with('success', 'Session notes saved successfully.');
    }

    /**
     * Store the helper's personal reflection and applied skills. The reflection
     * is private between the helper and their adviser.
     */
    public function storeReflection(Request $request, int $id)
    {
        $validated = $request->validate([
            'personal_reflection' => 'required|string|max:2000',
            'skills_applied' => 'required|array|min:1',
            'skills_applied.*' => ['string', 'max:50', \Illuminate\Validation\Rule::in(\App\Services\HelperReadinessService::SKILLS)],
            'correction_reason' => 'nullable|string|max:1000',
        ]);

        $session = Session::where('helper_id', Auth::user()->helper->id)->findOrFail($id);

        app(\App\Services\HelperDocumentationService::class)->save(Auth::user(), $session, $validated, 'reflection');

        return back()->with('success', 'Personal reflection saved.');
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

        $incident = IncidentReport::where('session_id', $session->id)
            ->where('incident_category', 'emergency_flag')
            ->where('status', 'open')
            ->lockForUpdate()
            ->first();

        if ($incident) {
            $incident->forceFill([
                'description' => $validated['description'],
                'immediate_action' => $validated['immediate_action'] ?? $incident->immediate_action,
            ])->save();
        } else {
            $incident = IncidentReport::create([
                'session_id' => $session->id,
                'user_account_id' => $session->seeker?->user_account_id,
                'incident_category' => 'emergency_flag',
                'description' => $validated['description'],
                'immediate_action' => $validated['immediate_action'] ?? null,
                'risk_level' => 'emergency',
                'status' => 'open',
            ]);
        }

        app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$session->seeker,['reason'=>'Helper reported immediate safety concern','preserve_classification'=>true]);

        $this->notifyStaff(
            ['moderator', 'adviser'],
            ' Emergency flagged',
            'Emergency flagged in session #' . $session->id . ' by ' . $helper->full_name . '. ' . $validated['description'],
            'emergency',
            '/moderator/dashboard'
        );

        // Real-time alert for advisers
        foreach (User::where('role', 'adviser')->pluck('id') as $adviserUserId) {
            $this->broadcastSafely(new EmergencyTriggered($session, $incident, $adviserUserId));
        }

        // Real-time minimal alert for moderators (no clinical/identity data).
        foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
            $this->broadcastSafely(new ModeratorAlert(
                $moderatorUserId,
                'emergency',
                'Emergency flagged',
                'Emergency flagged in session #' . $session->id,
                '/moderator/emergency',
                [
                    'session_id' => $session->id,
                    'alert_id' => $incident->id,
                    'risk_level' => 'emergency',
                    'occurred_at' => now()->toIso8601String(),
                ]
            ));
        }

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Your session has been escalated',
                'message' => 'A support coordinator has been notified about your session and will reach out.',
                'notification_type' => 'emergency',
                'type_icon' => 'fa-life-ring',
                'link' => '/session/chat',
            ]);
        }

        return back()->with('success', 'Emergency flagged. A support coordinator has been notified. Incident #' . $incident->id . ' recorded.');
    }

    /**
     * Request seeker consent for a professional referral (consent-first flow).
     * Creates the referral in the 'consent_requested' state so the seeker can
     * review and either accept or decline before the full referral is submitted.
     */
    public function requestReferralConsent(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        abort_unless($session->isActive() || $session->isCompleted(), 409);

        $validated = $request->validate([
            'summary' => 'required|string|max:500',
        ]);

        $referral = app(\App\Services\ReferralManagementService::class)->requestConsent($session, ['summary' => $validated['summary']]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Referral consent requested. The seeker will be prompted to review it.');
        }

        return response()->json(['success' => true, 'referral_id' => $referral->id, 'status' => $referral->status]);
    }

    /**
     * Submit the full referral once the seeker has accepted referral consent.
     */
    public function recommendReferral(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'referral_id' => 'required|exists:referrals,id',
            'referral_reason' => 'required|string|max:1000',
            'priority_level' => 'required|in:low,moderate,high,emergency',
        ]);

        abort_unless($session->isActive() || $session->isCompleted(), 409);

        $referral = Referral::where('id', $validated['referral_id'])
            ->where('session_id', $session->id)
            ->where('helper_id', $helper->id)
            ->firstOrFail();

        $referral = app(\App\Services\ReferralManagementService::class)->submitAfterConsent($referral, [
            'referral_reason' => $validated['referral_reason'],
            'priority_level' => $validated['priority_level'],
        ]);

        $this->notifyStaff(
            ['adviser'],
            ' New referral request',
            'Referral #' . $referral->id . ' (' . $validated['priority_level'] . ' priority) for session #' . $session->id . ' from ' . $helper->full_name . '.',
            'referral',
            '/adviser/dashboard'
        );

        // Real-time alert for advisers
        foreach (User::where('role', 'adviser')->pluck('id') as $adviserUserId) {
            $this->broadcastSafely(new ReferralRecommended($referral, $adviserUserId));
        }

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'A referral was recommended for you',
                'message' => 'Your helper has recommended connecting you with a professional. An adviser will follow up.',
                'notification_type' => 'referral',
                'type_icon' => 'fa-clipboard-list',
                'link' => '/session/chat',
            ]);
        }

        return back()->with('success', 'Referral recommended. An adviser will review it shortly. Referral #' . $referral->id . ' recorded.');
    }

    public function clarifyReferral(Request $request, int $id)
    {
        $data = $request->validate(['response'=>'required|string|min:10|max:2000']);
        app(\App\Services\ReferralManagementService::class)->clarify(\App\Models\Referral::findOrFail($id), $data['response'], true);
        return back()->with('success','Your clarification was sent to the Adviser.');
    }

    public function referralStatus(int $id)
    {
        $helper = Auth::user()->helper;

        $referral = Referral::with(['session.seeker', 'adviser', 'professional'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        return view('helper.referral-status', compact('referral'));
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
                'type_icon' => $type === 'emergency' ? 'fa-triangle-exclamation' : 'fa-clipboard-list',
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

        app(\App\Services\SessionDurationService::class)->expire($session);

        if (! $session->isCompleted()) {
            if (!app(\App\Services\SessionDurationService::class)->complete($session)) return redirect()->route('helper.session.notes',$id);

            // Tell the seeker (and anyone watching the room) the session has ended.
            $this->broadcastSafely(new SessionEnded($session, 'helper'));

            // Let every moderator know in real time so their live session stats refresh.
            foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
                $this->broadcastSafely(new ModeratorAlert($moderatorUserId, 'session', 'Session ended', 'Session #' . $session->id . ' has been completed.', '/moderator/sessions'));
            }

            if ($session->seeker) {
                Notification::create([
                    'user_account_id' => $session->seeker->user_account_id,
                    'title' => 'Session completed',
                    'message' => 'Your session has been completed. Please share your feedback to help us improve.',
                    'notification_type' => 'evaluation',
                    'type_icon' => 'fa-pen-to-square',
                    'link' => '/session/evaluation',
                ]);
            }
        }

        return redirect()
            ->route('helper.session.notes', ['id' => $session->id])
            ->with('success', 'Session completed. Please complete your documentation.');
    }
}
