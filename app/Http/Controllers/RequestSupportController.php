<?php

namespace App\Http\Controllers;

use App\Events\NewCaseAssigned;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\Message;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestSupportController extends Controller
{
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
            'urgency' => 'required|in:low,medium,high',
            'safety_check' => 'required|in:yes,no,prefer_not_to_say'
        ]);

        $helpSeeker = Auth::user()->helpSeeker;

        if (!$helpSeeker) {
            return back()->withErrors(['concern_id' => 'Your help seeker profile could not be found. Please complete your registration first.']);
        }

        $riskLevel = $this->calculateRisk($validated);

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
            'support_mode' => 'required|in:chat,voice,both',
            'preferred_language' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:500'
        ]);

        $session = $this->currentPendingSession();

        if (!$session) {
            return redirect()->route('request.screening')
                ->with('error', 'Your request could not be found. Please start over.');
        }

        // Update the counseling session with the chosen support mode
        $session->update([
            'session_type' => $validated['support_mode'] === 'voice' ? 'voice' : 'chat',
            'session_status' => Session::STATUS_PREFERENCES_SET,
        ]);

        // Place the seeker in the queue (one active waiting request per seeker)
        QueueRequest::where('seeker_id', $session->seeker_id)
            ->where('request_status', 'waiting')
            ->delete();

        QueueRequest::create([
            'seeker_id' => $session->seeker_id,
            'request_date' => now(),
            'request_status' => 'waiting',
            'priority_level' => session('risk_level', 'low'),
            'preferred_session_type' => $validated['support_mode'] === 'voice' ? 'voice' : 'chat',
        ]);

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
                ]);
                $availableHelper->update(['status' => 'busy']);

                QueueRequest::where('seeker_id', $session->seeker_id)
                    ->where('request_status', 'waiting')
                    ->update([
                        'request_status' => 'assigned',
                        'assigned_helper_id' => $availableHelper->id,
                        'matched_date' => now(),
                    ]);

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
                try {
                    broadcast(new NewCaseAssigned($session, $availableHelper->user_account_id));
                } catch (\Throwable $e) {
                    report($e);
                }
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

        return view('request.matching', compact('availableHelper', 'resources', 'session', 'availableHelperCount'));
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

            $session->update([
                'helper_id' => null,
                'session_status' => 'waiting',
                'scheduled_start' => null,
            ]);

            QueueRequest::where('seeker_id', $session->seeker_id)
                ->where('request_status', 'assigned')
                ->update([
                    'request_status' => 'waiting',
                    'assigned_helper_id' => null,
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
        $session = $this->currentPendingSession();

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
        $this->startSession(['voice_recording_consent' => true, 'session_type' => 'voice']);

        return redirect()->route('session.voice');
    }

    /**
     * Decline voice recording, fall back to chat
     */
    public function declineVoiceConsent()
    {
        $this->startSession(['voice_recording_consent' => false, 'session_type' => 'chat']);

        return redirect()->route('session.chat');
    }

    /**
     * Mark the matched session as active and seed the helper's greeting
     */
    private function startSession(array $attributes = []): void
    {
        $session = $this->currentPendingSession();

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
                'sent_datetime' => now(),
            ]);
        }

        session([
            'helper_id' => $session->helper_id,
            'voice_consent' => $attributes['voice_recording_consent'] ?? false,
        ]);
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
    private function calculateRisk($data)
    {
        if ($data['safety_check'] === 'yes') {
            return 'emergency';
        }

        if ($data['urgency'] === 'high') {
            return 'high';
        }

        if ($data['urgency'] === 'medium') {
            return 'moderate';
        }

        return 'low';
    }
}