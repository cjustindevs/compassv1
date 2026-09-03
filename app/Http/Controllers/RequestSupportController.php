<?php

namespace App\Http\Controllers;

use App\Events\NewCaseAssigned;
use App\Events\QueueUpdated;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\Message;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\User;
use App\Models\ScreeningResponse;
use App\Services\EmergencyEscalationService;
use App\Services\RiskClassificationService;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestSupportController extends Controller
{
    use BroadcastsSafely;

    public function __construct(
        private RiskClassificationService $riskClassification,
        private EmergencyEscalationService $emergencyEscalation,
    ) {}

    /**
     * Show the screening form (Step 1).
     * If the seeker already has an in-progress request in the database
     * (e.g. they logged out mid-flow), resume it instead of starting over.
     */
    public function screening()
    {
        $pending = $this->currentPendingSession();

        if ($pending) {
            if ($pending->session_status === Session::STATUS_SCREENING_COMPLETED) {
                return redirect()->route('request.preferences')
                    ->with('info', 'You have a pending request. Continue where you left off.');
            }

            return redirect()->route('request.matching')
                ->with('info', 'You have a pending request. Continue where you left off.');
        }

        $concerns = ConcernCategory::all();
        return view('request.screening', compact('concerns'));
    }

    /**
     * Process the screening form, persist the session, and determine risk
     */
    public function processScreening(Request $request)
    {
        $validated = $request->validate([
            'concern_id' => 'required|exists:concern_categories,id',
            'custom_concern' => 'nullable|string|max:255',
            'description' => 'required|string|max:200',
            'safety_check' => 'required|in:yes,no,prefer_not_to_say'
        ]);

        $helpSeeker = Auth::user()->helpSeeker;

        if (!$helpSeeker) {
            return back()->withErrors(['concern_id' => 'Your help seeker profile could not be found. Please complete your registration first.']);
        }

        $classification = $this->calculateRisk($validated);
        $riskLevel = $classification['risk_level'];

        // Persist the request in the counseling_sessions table
        $session = Session::create([
            'seeker_id' => $helpSeeker->id,
            'concern_id' => $validated['concern_id'],
            'risk_level' => $riskLevel,
            'session_type' => 'chat',
            'session_status' => 'screening_completed',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);

        ScreeningResponse::create([
            'seeker_id' => $helpSeeker->id,
            'session_id' => $session->id,
            'responses' => $this->normalizeScreeningResponses($validated),
            'risk_level' => $riskLevel,
            'priority' => $classification['priority'],
            'action' => $classification['action'],
            'reason' => $classification['reason'],
            'classified_at' => now(),
            'classified_by' => 'system',
        ]);

        $helpSeeker->update([
            'current_risk_level' => $riskLevel,
            'risk_last_updated' => now(),
        ]);

        if ($riskLevel === RiskClassificationService::RISK_EMERGENCY) {
            $this->emergencyEscalation->escalateEmergency($session, $helpSeeker, ['reason' => $classification['reason']]);
        }

        session([
            'screening_data' => $validated,
            'risk_level' => $riskLevel,
            'session_id' => $session->id,
        ]);

        return redirect()->route('request.preferences')
            ->with('risk_level', $riskLevel)
            ->with('success', 'Screening completed. Please set your session preferences.');
    }

    /**
     * Show the preferences form (Step 2)
     */
    public function preferences()
    {
        $session = $this->currentPendingSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'Please complete the screening first.');
        }

        if ($session->session_status !== Session::STATUS_SCREENING_COMPLETED) {
            return redirect()->route('request.matching')
                ->with('info', 'You already have a pending request. Continue where you left off.');
        }

        return view('request.preferences');
    }

    /**
     * Process the preferences form, persist them, and place the seeker in the queue
     */
    public function processPreferences(Request $request)
    {
        $validated = $request->validate([
            'support_mode' => 'required|in:chat,voice',
            'preferred_language' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:500',
            'voice_consent' => 'exclude_unless:support_mode,voice|required|accepted',
        ]);

        $session = $this->currentPendingSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'Your request could not be found. Please start over.');
        }

        // Update the counseling session with the chosen support mode
        $voiceConsent = $validated['support_mode'] === 'voice' && $request->boolean('voice_consent');

        $session->update([
            'session_type' => $validated['support_mode'],
            'session_status' => Session::STATUS_PREFERENCES_SET,
            'concern_category' => $session->concern?->concern_name,
            'voice_recording_consent' => $voiceConsent,
            'voice_consent_obtained' => $voiceConsent,
        ]);

        Auth::user()->update([
            'preferred_language' => $validated['preferred_language'],
            'preferred_communication_mode' => $validated['support_mode'],
        ]);

        // Place the seeker in the queue (one active waiting request per seeker)
        QueueRequest::where('seeker_id', $session->seeker_id)
            ->where('request_status', 'waiting')
            ->delete();

        $queuePosition = $this->nextQueuePosition($session->risk_level);

        QueueRequest::create([
            'seeker_id' => $session->seeker_id,
            'request_date' => now(),
            'request_status' => 'waiting',
            'priority_level' => $session->risk_level,
            'preferred_session_type' => $validated['support_mode'],
            'voice_consent' => $voiceConsent,
            'queue_position' => $queuePosition,
            'estimated_wait' => $this->estimatedWaitMinutes($queuePosition, $session->risk_level),
        ]);

        // Let every moderator know the queue changed so their live badge/stats refresh.
        foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
            try {
                $this->broadcastSafely(new QueueUpdated($moderatorUserId));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        session([
            'preferences_data' => $validated
        ]);

        return redirect()->route('request.matching')
            ->with('success', 'Preferences saved. Checking for available helpers...');
    }

    /**
     * Show the matching page — checks real helper availability from the DB
     */
    public function matching()
    {
        $session = $this->currentRequestSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'Please complete all steps first.');
        }

        session(['session_id' => $session->id]);
        $session->load('helper');
        $currentQueueRequest = QueueRequest::where('seeker_id', $session->seeker_id)
            ->whereIn('request_status', ['waiting', 'assigned'])
            ->latest('request_date')
            ->first();

        // If a helper was already assigned (e.g. page refresh), keep showing them
        $availableHelper = $session->helper;

        if (!$availableHelper) {
            $availableHelper = Helper::findAvailableForRisk($session->risk_level);

            if ($availableHelper) {
                // Assign the helper and mark the helper as busy
                $session->update([
                    'helper_id' => $availableHelper->id,
                    'session_status' => 'helper_assigned',
                    'scheduled_start' => now(),
                    'session_type' => $currentQueueRequest?->preferred_session_type ?? $session->session_type,
                    'match_method' => 'automatic',
                    'matched_by' => 'system',
                    'matching_details' => $availableHelper->matching_details,
                    'pre_session_brief_expires_at' => now()->addMinutes(Helper::PRE_SESSION_BRIEF_MINUTES),
                ]);
                $availableHelper->incrementShiftSessions();
                $availableHelper->update(['status' => 'busy']);

                QueueRequest::where('seeker_id', $session->seeker_id)
                    ->where('request_status', 'waiting')
                    ->update([
                        'request_status' => 'assigned',
                        'assigned_helper_id' => $availableHelper->id,
                        'queue_position' => null,
                        'estimated_wait' => null,
                        'matched_date' => now(),
                    ]);

                $currentQueueRequest = QueueRequest::where('seeker_id', $session->seeker_id)
                    ->where('request_status', 'assigned')
                    ->latest('matched_date')
                    ->first();

                // Notify the helper about the new assignment
                Notification::create([
                    'user_account_id' => $availableHelper->user_account_id,
                    'title' => 'New case assigned',
                    'message' => 'You have been assigned a new case. Please review and accept it.',
                    'notification_type' => 'assignment',
                    'type_icon' => '📋',
                    'link' => '/helper/cases',
                ]);

                // Real-time push to the helper's browser (badge + toast).
                // Never let a brief websocket outage break the seeker flow.
                $this->broadcastSafely(new NewCaseAssigned($session, $availableHelper->user_account_id));
            } else {
                // No helper online — keep the session waiting in the queue
                $session->update(['session_status' => 'waiting']);
            }
        }

        $resources = $this->getRecommendedResources();

        $availableHelperCount = Helper::available()
            ->ready()
            ->withCount('activeSessions as active_sessions_count')
            ->get()
            ->filter(fn (Helper $h) => $h->active_sessions_count < (int) $h->max_concurrent_sessions)
            ->count();

        return view('request.matching', compact('availableHelper', 'resources', 'session', 'availableHelperCount', 'currentQueueRequest'));
    }

    /**
     * Seeker declines the offered helper — helper is freed, seeker returns to the queue
     */
    public function declineHelper(Request $request)
    {
        $session = $this->currentPendingSession();

        if ($session && $session->helper_id) {
            Helper::where('id', $session->helper_id)
                ->where('status', 'busy')
                ->update(['status' => 'available']);

            $session->helper?->decrementShiftSessions();

            $queuePosition = $this->nextQueuePosition($session->risk_level);

            $session->update([
                'helper_id' => null,
                'session_status' => 'waiting',
                'scheduled_start' => null,
                'pre_session_brief_expires_at' => null,
            ]);

            QueueRequest::where('seeker_id', $session->seeker_id)
                ->where('request_status', 'assigned')
                ->update([
                    'request_status' => 'waiting',
                    'assigned_helper_id' => null,
                    'queue_position' => $queuePosition,
                    'estimated_wait' => $this->estimatedWaitMinutes($queuePosition, $session->risk_level),
                    'matched_date' => null,
                ]);
        }

        return redirect()->route('request.matching')
            ->with('info', 'You have been placed back in the queue.');
    }

    /**
     * Voice recording consent step after matching
     */
    public function voiceConsent()
    {
        $session = $this->currentRequestSession();

        if (!$session) {
            return redirect()->route('request.screening');
        }

        $session->load('helper');

        // If the seeker chose chat-only, skip consent and go straight to chat
        if ($session->session_type !== 'voice') {
            return redirect()->route('session.chat');
        }

        // Voice chosen but no helper was ever matched — go back to matching
        if (!$session->helper) {
            return redirect()->route('request.matching')
                ->with('error', 'No helper is matched to your request yet.');
        }

        return view('request.voice-consent');
    }

    /**
     * Accept voice recording (or proceed) and start the voice session
     */
    public function processVoiceConsent(Request $request)
    {
        $this->startSession([
            'voice_recording_consent' => true,
            'voice_consent_obtained' => true,
            'session_type' => 'voice',
        ]);

        return redirect()->route('session.voice');
    }

    /**
     * Decline voice recording, fall back to chat
     */
    public function declineVoiceConsent()
    {
        $this->startSession([
            'voice_recording_consent' => false,
            'voice_consent_obtained' => false,
            'session_type' => 'chat',
        ]);

        return redirect()->route('session.chat');
    }

    /**
     * Mark the matched session as active and seed the helper's greeting
     */
    private function startSession(array $attributes = []): void
    {
        $session = $this->currentRequestSession();

        if (!$session || !$session->helper) {
            return;
        }

        $session->update(array_merge([
            'session_status' => Session::STATUS_ACTIVE,
            'start_time' => now(),
        ], $attributes));

        $hasGreeting = Message::where('session_id', $session->id)
            ->where('sender', 'helper')
            ->exists();

        if (!$hasGreeting) {
            Message::create([
                'session_id' => $session->id,
                'sender' => 'helper',
                'message_text' => 'Hi there! Thank you for reaching out. I am here to listen — how are you feeling today?',
                'transcript' => 'Hi there! Thank you for reaching out. I am here to listen — how are you feeling today?',
                'is_transcript' => true,
                'transcript_generated_at' => now(),
                'sent_datetime' => now(),
            ]);
        }

        session([
            'helper_id' => $session->helper_id,
            'voice_consent' => $attributes['voice_consent_obtained'] ?? false,
        ]);

        QueueRequest::where('seeker_id', $session->seeker_id)
            ->whereIn('request_status', ['waiting', 'assigned'])
            ->latest('request_date')
            ->first()?->update(['voice_consent' => (bool) ($attributes['voice_consent_obtained'] ?? false)]);
    }

    /**
     * Get recommended self-help resources
     */
    private function getRecommendedResources()
    {
        return [
            [
                'title' => 'Guided Breathing Exercise',
                'description' => 'A short breathing exercise to calm your mind.',
                'duration' => '3 min',
                'icon' => '🧘',
                'link' => route('selfhelp')
            ],
            [
                'title' => 'Grounding Techniques',
                'description' => 'Simple techniques to bring you back to the present moment.',
                'duration' => '5 min',
                'icon' => '🌿',
                'link' => route('selfhelp')
            ],
            [
                'title' => 'Managing Anxiety',
                'description' => 'Tips and strategies for managing anxious thoughts.',
                'duration' => '8 min',
                'icon' => '📘',
                'link' => route('selfhelp')
            ]
        ];
    }

    private function nextQueuePosition(?string $riskLevel): int
    {
        return QueueRequest::where('request_status', 'waiting')
            ->where('priority_level', $riskLevel ?: RiskClassificationService::RISK_LOW)
            ->count() + 1;
    }

    private function estimatedWaitMinutes(int $queuePosition, ?string $riskLevel): int
    {
        $baseMinutes = match ($riskLevel) {
            RiskClassificationService::RISK_EMERGENCY => 1,
            RiskClassificationService::RISK_HIGH => 3,
            RiskClassificationService::RISK_MODERATE => 5,
            default => 8,
        };

        return max($baseMinutes, $baseMinutes + (($queuePosition - 1) * 5));
    }

    /**
     * Resolve the seeker's current in-progress request from the database.
     *
     * Prefers the request stored in the PHP session (fast path during the
     * active flow), then falls back to the latest pending session row so the
     * flow survives logout / login / browser closes. Never returns a session
     * that belongs to another seeker.
     */
    /**
     * Resolve the seeker's current request from the database.
     *
     * Fast path: the request stored in the PHP session (pending OR already
     * active — the helper may have accepted while the seeker was away).
     * Resume path: the latest pending session row, falling back to the latest
     * active session, so the flow survives logout / login / browser closes.
     * Never returns a session that belongs to another seeker.
     */
    private function currentRequestSession(): ?Session
    {
        $helpSeeker = Auth::user()->helpSeeker;

        if (!$helpSeeker) {
            return null;
        }

        // Kill stale requests (>24h, never accepted) so they cannot resurrect.
        Session::where('seeker_id', $helpSeeker->id)
            ->abandoned()
            ->markAbandoned();

        $sessionId = session('session_id');
        $session = $sessionId ? Session::find($sessionId) : null;

        if ($session && $session->seeker_id === $helpSeeker->id && ($session->isPending() || $session->isActive())) {
            return $session;
        }

        return Session::pendingForSeeker($helpSeeker->id)->first()
            ?? Session::where('seeker_id', $helpSeeker->id)
                ->where('session_status', Session::STATUS_ACTIVE)
                ->orderByDesc('start_time')
                ->first();
    }

    /**
     * The seeker's latest in-progress (pending) request, if any.
     */
    private function currentPendingSession(): ?Session
    {
        $helpSeeker = Auth::user()->helpSeeker;

        if (!$helpSeeker) {
            return null;
        }

        // Kill stale requests (>24h, never accepted) so they cannot resurrect.
        Session::where('seeker_id', $helpSeeker->id)
            ->abandoned()
            ->markAbandoned();

        $sessionId = session('session_id');
        $session = $sessionId ? Session::find($sessionId) : null;

        if ($session && $session->seeker_id === $helpSeeker->id && $session->isPending()) {
            return $session;
        }

        return Session::pendingForSeeker($helpSeeker->id)->first();
    }

    /**
     * Calculate risk classification based on answers
     */
    private function calculateRisk($data): array
    {
        return $this->riskClassification->classifyRisk($this->normalizeScreeningResponses($data));
    }

    private function normalizeScreeningResponses(array $data): array
    {
        $safetyConcern = ($data['safety_check'] ?? 'no') === 'yes';

        return [
            'current_suicide_plan' => $safetyConcern,
            'suicidal_thoughts' => $safetyConcern,
            'severe_distress' => false,
            'recurring_distress' => false,
            'difficulty_coping' => false,
            'access_to_means' => false,
            'ongoing_self_harm' => false,
            'recent_attempt_needs_assistance' => false,
            'immediate_threat_to_life' => $safetyConcern,
            'immediate_threat_to_others' => false,
        ];
    }
}
