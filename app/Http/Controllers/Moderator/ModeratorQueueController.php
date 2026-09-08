<?php

namespace App\Http\Controllers\Moderator;

use App\Events\ModeratorAlert;
use App\Events\NewCaseAssigned;
use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModeratorQueueController extends Controller
{
    use BroadcastsSafely;

    public function index()
    {
        $queueItems = QueueRequest::with(['seeker', 'assignedHelper'])
            ->whereIn('request_status', ['waiting', 'assigned'])
            ->orderByRaw("CASE priority_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderBy('request_date')
            ->get();

        $seekerIds = $queueItems->pluck('seeker_id')->filter()->unique()->values()->all();
        $latestSessions = Session::whereIn('seeker_id', $seekerIds)
            ->with('concern:id,concern_name')
            ->latest('created_date')
            ->get()
            ->unique('seeker_id')
            ->keyBy('seeker_id');

        $queueItems->each(function (QueueRequest $queue) use ($latestSessions) {
            $session = $latestSessions->get($queue->seeker_id);
            $queue->concern_name = $session?->concern?->concern_name ?? 'General Concern';
            $queue->wait_minutes = (int) floor(now()->diffInSeconds($queue->request_date) / 60);
        });

        $stats = [
            'waiting' => $queueItems->where('request_status', 'waiting')->count(),
            'assigned' => $queueItems->where('request_status', 'assigned')->count(),
            'avg_wait' => $this->getAverageWaitTime(),
            'unserved' => $queueItems->where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
            'queue_size' => $queueItems->count(),
            'avg_holding' => $this->getAverageHoldingTime(),
        ];

        $availableHelpers = Helper::where('status', 'available')
            ->whereHas('latestReadiness', fn ($query) => $query->ready())
            ->with('latestCompetency')
            ->withCount('activeSessions as active_sessions_count')
            ->orderBy('competency_level', 'desc')
            ->get()
            ->filter(fn (Helper $helper) => $helper->canAcceptSessions());

        return view('moderator.queue', compact('queueItems', 'stats', 'availableHelpers'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_requests,id',
            'helper_id' => 'required|exists:helpers,id',
        ]);

        $queue = QueueRequest::findOrFail($request->queue_id);
        $helper = Helper::findOrFail($request->helper_id);

        $override = $request->boolean('emergency_override')
            && in_array($queue->priority_level, ['emergency', 'high'], true);

        if (! $override && ! $helper->canAcceptSessions()) {
            return redirect()->route('moderator.queue')
                ->with('error', $helper->full_name . ' must be available, under capacity, and have a current ready assessment before assignment.');
        }

        if ($override && ! $helper->hasCapacity()) {
            return redirect()->route('moderator.queue')
                ->with('error', $helper->full_name . ' is already at their session capacity and cannot be assigned right now.');
        }

        $queue->update([
            'assigned_helper_id' => $helper->id,
            'request_status' => 'assigned',
            'queue_position' => null,
            'matched_date' => now(),
        ]);

        $helper->update(['status' => 'busy']);

        // Link the helper to the seeker's pending session (or create one).
        $session = Session::where('seeker_id', $queue->seeker_id)
            ->whereIn('session_status', Session::PENDING_STATUSES)
            ->latest('created_date')
            ->first();

        if (! $session) {
            $session = Session::create([
                'seeker_id' => $queue->seeker_id,
                'helper_id' => $helper->id,
                'moderator_id' => Auth::user()->moderator?->id,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'session_type' => $queue->preferred_session_type ?? 'chat',
                'risk_level' => $queue->priority_level ?? 'low',
                'created_date' => now(),
            ]);
        } else {
            $session->update([
                'helper_id' => $helper->id,
                'moderator_id' => Auth::user()->moderator?->id,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'session_type' => $queue->preferred_session_type ?? $session->session_type,
                'risk_level' => $queue->priority_level ?? $session->risk_level,
            ]);
        }

        Notification::create([
            'user_account_id' => $helper->user_account_id,
            'title' => 'New Case Assigned',
            'message' => 'You have been assigned to support ' . ($session->seeker?->generated_alias ?? 'a seeker') . ' (' . ucfirst($session->risk_level) . ' risk).',
            'notification_type' => 'assignment',
            'type_icon' => '📋',
            'link' => '/helper/cases',
            'status' => 'unread',
        ]);

        $this->broadcastSafely(new NewCaseAssigned($session, $helper->user_account_id));
        $this->broadcastSafely(new ModeratorAlert(Auth::id(), 'assignment', 'Helper assigned', ($session->seeker?->generated_alias ?? 'A seeker') . ' was matched with ' . $helper->full_name, '/moderator/queue'));

        // Real-time: keep the moderator's queue view in sync (private channel).
        $this->broadcastSafely(new QueueUpdated(Auth::id()));

        return redirect()->route('moderator.queue')
            ->with('success', $helper->full_name . ' assigned to ' . ($session->seeker?->generated_alias ?? 'the seeker') . ' successfully!');
    }

    public function reassign(Request $request): RedirectResponse
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_requests,id',
            'helper_id' => 'required|exists:helpers,id',
        ]);

        $queue = QueueRequest::findOrFail($request->queue_id);
        $helper = Helper::findOrFail($request->helper_id);

        $override = $request->boolean('emergency_override')
            && in_array($queue->priority_level, ['emergency', 'high'], true);

        if (! $override && ! $helper->canAcceptSessions()) {
            return redirect()->route('moderator.queue')
                ->with('error', $helper->full_name . ' must be available, under capacity, and have a current ready assessment before reassignment.');
        }

        if ($override && ! $helper->hasCapacity()) {
            return redirect()->route('moderator.queue')
                ->with('error', $helper->full_name . ' is already at their session capacity and cannot be reassigned right now.');
        }

        $queue->update([
            'assigned_helper_id' => $helper->id,
            'matched_date' => now(),
        ]);

        $session = Session::where('seeker_id', $queue->seeker_id)
            ->where('session_status', Session::STATUS_HELPER_ASSIGNED)
            ->latest('created_date')
            ->first();

        $oldHelperId = $session?->helper_id;

        if ($session) {
            $session->update(['helper_id' => $helper->id]);
        }

        $helper->update(['status' => 'busy']);

        // Release the previous helper back to the available pool if they are
        // no longer connected to this session.
        if ($oldHelperId && $oldHelperId !== $helper->id) {
            Helper::where('id', $oldHelperId)->update(['status' => 'available']);
        }

        $this->broadcastSafely(new QueueUpdated(Auth::id()));

        return redirect()->route('moderator.queue')
            ->with('success', 'Helper reassigned to ' . $helper->full_name . '.');
    }

    /**
     * Remove a request from the queue entirely (moderator action).
     * Returns JSON so the queue UI can update live without a full reload.
     */
    public function removeFromQueue(Request $request): JsonResponse
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_requests,id',
        ]);

        $queue = QueueRequest::findOrFail($request->queue_id);
        $queue->delete();

        $this->broadcastSafely(new QueueUpdated(Auth::id()));

        return response()->json([
            'success' => true,
            'message' => 'Request removed from the queue.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $queueItems = QueueRequest::whereIn('request_status', ['waiting', 'assigned'])->get();

        return response()->json([
            'waiting' => $queueItems->where('request_status', 'waiting')->count(),
            'assigned' => $queueItems->where('request_status', 'assigned')->count(),
            'avg_wait' => $this->getAverageWaitTime(),
            'unserved' => $queueItems->where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
            'queue_size' => $queueItems->count(),
        ]);
    }

    private function getAverageWaitTime(): string
    {
        $avg = QueueRequest::where('request_status', 'assigned')
            ->whereNotNull('matched_date')
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('matched_date', 'request_date') . ') as avg_wait')
            ->first();

        if ($avg && $avg->avg_wait) {
            $minutes = floor($avg->avg_wait / 60);
            $seconds = floor($avg->avg_wait % 60);

            return $minutes . 'm ' . $seconds . 's';
        }

        return '0m 0s';
    }

    private function getAverageHoldingTime(): string
    {
        $avg = QueueRequest::where('request_status', 'assigned')
            ->whereNotNull('matched_date')
            ->whereNotNull('scheduled_date')
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('scheduled_date', 'matched_date') . ') as avg_hold')
            ->first();

        if ($avg && $avg->avg_hold) {
            $seconds = floor($avg->avg_hold);

            return $seconds . 's';
        }

        return '0s';
    }
}
