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
use App\Services\HelperEligibilityService;
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

        $queueItems->each(function (QueueRequest $queue) {
            $session = Session::with('concern')->where('queue_request_id', $queue->id)->latest('id')->first();
            $queue->setRelation('supportSession', $session);
            $queue->concern_name = $session?->concern?->concern_name ?? 'General Concern';
            $queue->wait_minutes = (int) floor($queue->request_date->diffInSeconds(now()) / 60);
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

        $availableHelpers = Helper::with(['user', 'adviser.user', 'currentReadiness', 'schedule', 'latestCompetency', 'helperSpecialties'])
            ->withCount('activeSessions as active_sessions_count')
            ->orderBy('competency_level', 'desc')->get();
        // Helper state always derives from HelperEligibilityService so the
        // moderator sees exactly the same reasons the matching engine applies.
        $eligibility = app(HelperEligibilityService::class);
        $availableHelpers->each(function (Helper $helper) use ($eligibility) {
            $status = $eligibility->status($helper);
            $helper->assignment_reason = $status['assignable'] ? null : $status['label'];
            $helper->assignment_detail = $status['reason'];
            $helper->assignable = $status['assignable'];
            $helper->remaining_capacity = $helper->getRemainingCapacity();
        });
        // Eligible, ready helpers appear first so moderators can assign in one glance.
        $availableHelpers = $availableHelpers->sortBy(fn (Helper $helper) => $helper->assignment_reason !== null)->values();

        return view('moderator.queue', compact('queueItems', 'stats', 'availableHelpers'));
    }

    public function assign(Request $request): RedirectResponse
    {
        return $this->assignHelper($request, false);
    }

    public function reassign(Request $request): RedirectResponse
    {
        return $this->assignHelper($request, true);
    }

    /**
     * Set (or change) the session appointment time for a request that already
     * has a helper assigned but has not yet started. The helper's acceptance
     * window follows the newly scheduled start.
     */
    public function schedule(Request $request): RedirectResponse
    {
        $data = $request->validate(['queue_id' => 'required|exists:queue_requests,id', 'scheduled_at' => 'required|date']);
        $queue = QueueRequest::findOrFail($data['queue_id']);
        $session = Session::where('queue_request_id', $queue->id)
            ->whereIn('session_status', [Session::STATUS_HELPER_ASSIGNED, Session::STATUS_WAITING])
            ->latest('id')->first();
        abort_unless($session, 422, 'Only an assigned request awaiting helper acceptance can be scheduled.');
        $scheduledStart = \Illuminate\Support\Carbon::parse($data['scheduled_at'], config('app.schedule_timezone'))->utc();
        abort_unless($scheduledStart->isFuture(), 422, 'The scheduled appointment must be in the future.');
        \Illuminate\Support\Facades\DB::transaction(function () use ($session, $queue, $scheduledStart) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->helper_accepted_at || !in_array($session->session_status, [Session::STATUS_HELPER_ASSIGNED, Session::STATUS_WAITING], true)) {
                abort(422, 'The scheduled session can no longer be changed.');
            }
            $session->update(['scheduled_start' => $scheduledStart, 'pre_session_brief_expires_at' => $scheduledStart]);
            $queue->update(['scheduled_date' => $scheduledStart]);
            \App\Services\SupportAudit::record('session_scheduled', $session, ['scheduled_start' => $scheduledStart->toDateTimeString()]);
        });
        return back()->with('success', 'Session scheduled for ' . $scheduledStart->setTimezone(config('app.schedule_timezone'))->format('M d, h:i A') . ' (' . $scheduledStart->setTimezone(config('app.schedule_timezone'))->timezoneName . ').');
    }

    private function assignHelper(Request $request, bool $reassign): RedirectResponse
    {
        $data = $request->validate([
            'queue_id' => 'required|exists:queue_requests,id', 'helper_id' => 'required|exists:helpers,id',
            'emergency_override' => 'prohibited',
            'scheduled_at' => 'nullable|date',
        ]);
        $queue = QueueRequest::findOrFail($data['queue_id']);
        $scheduledStart = null;
        if (!empty($data['scheduled_at'])) {
            $scheduledStart = \Illuminate\Support\Carbon::parse($data['scheduled_at'], config('app.schedule_timezone'))->utc();
            abort_unless($scheduledStart->isFuture(), 422, 'The scheduled appointment must be in the future.');
        }
        $result = app(\App\Services\HelperMatchingService::class)->manualAssign(
            $queue, (int) $data['helper_id'], false, $reassign, $reassign ? 'manual_reassign' : 'manual', $scheduledStart
        );
        return redirect()->route('moderator.queue')->with(
            $result instanceof Session ? 'success' : 'error',
            $result instanceof Session
                ? ($reassign ? 'Helper reassigned successfully.' : 'Helper assigned successfully.')
                : (is_string($result) ? $result : 'Assignment could not be completed. Check the request status, helper readiness, schedule, capacity, and risk competency.')
        );
    }

    /**
     * Remove a request from the queue entirely (moderator action).
     * Returns JSON so the queue UI can update live without a full reload.
     */
    public function removeFromQueue(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->role === 'moderator' && $request->user()->is_active, 403);
        $message = app(\App\Services\ModeratorQueueRemoval::class)->remove($id);
        $this->broadcastSafely(new QueueUpdated(Auth::id()));
        return response()->json(['success' => true, 'message' => $message]);
    }

    public function priority(Request $request, QueueRequest $queue): RedirectResponse
    {
        $data = $request->validate(['priority_level' => 'required|in:low,moderate,high,emergency']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($queue, $data) {
            $queue = QueueRequest::whereKey($queue->id)->lockForUpdate()->firstOrFail();
            abort_unless($queue->request_status === 'waiting', 422, 'Only waiting requests can be reprioritized.');
            $levels = ['low' => 0, 'moderate' => 1, 'high' => 2, 'emergency' => 3];
            if ($levels[$data['priority_level']] < $levels[$queue->priority_level]) {
                throw \Illuminate\Validation\ValidationException::withMessages(['priority_level' => 'Queue priority can be raised here; risk review is required before lowering it.']);
            }
            $previous = $queue->priority_level;
            $queue->update(['priority_level' => $data['priority_level']]);
            \App\Models\AuditLog::create(['user_account_id' => Auth::id(), 'module' => 'moderator',
                'action' => 'queue_priority_changed', 'description' => json_encode(['queue_id' => $queue->id, 'from' => $previous, 'to' => $data['priority_level']])]);
        });
        app(\App\Services\HelperMatchingService::class)->processQueueRequest($queue->fresh());
        $this->broadcastSafely(new QueueUpdated(Auth::id()));
        return back()->with('success', 'Queue priority updated.');
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
