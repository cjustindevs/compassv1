<?php

namespace App\Http\Controllers\Moderator;

use App\Events\ModeratorAlert;
use App\Events\NewCaseAssigned;
use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModeratorQueueController extends Controller
{
    public function index()
    {
        $queueItems = QueueRequest::with(['seeker', 'assignedHelper'])
            ->whereIn('request_status', ['waiting', 'assigned'])
            ->orderByRaw("CASE priority_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderBy('request_date')
            ->get()
            ->map(function (QueueRequest $queue) {
                $session = Session::where('seeker_id', $queue->seeker_id)
                    ->latest('created_date')
                    ->first();

                $queue->concern_name = $session?->concern?->concern_name ?? 'General Concern';
                $queue->wait_minutes = (int) floor(now()->diffInSeconds($queue->request_date) / 60);

                return $queue;
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
            ->with('latestCompetency')
            ->orderBy('competency_level', 'desc')
            ->get();

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

        $queue->update([
            'assigned_helper_id' => $helper->id,
            'request_status' => 'assigned',
            'queue_position' => null,
            'matched_date' => now(),
        ]);

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

        NewCaseAssigned::dispatch($session, $helper->user_account_id);
        ModeratorAlert::dispatch(Auth::id(), 'assignment', 'Helper assigned', $session->seeker?->generated_alias ?? 'A seeker' . ' was matched with ' . $helper->full_name, '/moderator/queue');

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

        $queue->update([
            'assigned_helper_id' => $helper->id,
            'matched_date' => now(),
        ]);

        Session::where('seeker_id', $queue->seeker_id)
            ->where('session_status', Session::STATUS_HELPER_ASSIGNED)
            ->latest('created_date')
            ->first()
            ?->update(['helper_id' => $helper->id]);

        return redirect()->route('moderator.queue')
            ->with('success', 'Helper reassigned to ' . $helper->full_name . '.');
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
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (matched_date - request_date))) as avg_wait')
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
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (scheduled_date - matched_date))) as avg_hold')
            ->first();

        if ($avg && $avg->avg_hold) {
            $seconds = floor($avg->avg_hold);

            return $seconds . 's';
        }

        return '0s';
    }
}