<?php

namespace App\Http\Controllers;

use App\Models\Helper;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\User;
use App\Services\HelperEligibilityService;
use App\Services\HelperMatchingService;
use App\Services\ModeratorEmergencyCases;
use App\Services\SupportAudit;
use App\Services\QueueHelperOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Operational coordination only: never exposes transcripts or screening answers. */
class EmergencyCoordinationController extends Controller
{
    private function incident(string $source, int $id, bool $history = false): object
    {
        abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role, ['moderator', 'adviser'], true), 403);
        if (auth()->user()->role === 'adviser') app(\App\Services\AdviserScope::class)->actor();
        abort_unless(in_array($source, ['emergency_alerts', 'incident_reports'], true), 404);

        return app(ModeratorEmergencyCases::class)->query($history)->where('source', $source)->where('id', $id)->firstOrFail();
    }

    public function show(string $source, int $id)
    {
        $case = $this->incident($source, $id, true);
        $session = Session::find($case->session_id);
        $terminal = in_array($case->status, ModeratorEmergencyCases::TERMINAL, true) || $case->archived_at;
        $current = app(ModeratorEmergencyCases::class)->active()->where('source', $source)->where('id', $id)->exists();
        $canAssign = $current && ! $terminal && $session?->queue_request_id && ! $session->helper_id && $session->session_status === Session::STATUS_WAITING;
        $helperChoices = $canAssign ? app(QueueHelperOptions::class)->forSession($session, Helper::with('user', 'adviser.user')->orderBy('first_name')->get())
            : ['helpers'=>[], 'unavailable'=>[], 'reason'=>'No queued assignment is actionable. Ask the responsible Adviser to review the support request.'];
        $helpers = collect($helperChoices['helpers']);

        return response()->view('emergency-coordination', compact('case', 'terminal', 'current', 'canAssign', 'helpers', 'helperChoices'))->header('Cache-Control', 'private, no-store');
    }

    public function helperOptions(string $source, int $id)
    {
        $case = $this->incident($source, $id, true);
        $session = Session::find($case->session_id);
        $actionable = app(ModeratorEmergencyCases::class)->active()->where('source', $source)->where('id', $id)->exists()
            && $session?->queue_request_id && !$session->helper_id && $session->session_status === Session::STATUS_WAITING;
        $choices = $actionable ? app(QueueHelperOptions::class)->forSession($session, Helper::with('user', 'adviser.user')->get())
            : ['helpers'=>[], 'unavailable'=>[], 'reason'=>'The case changed or already has a Helper. Refresh to view the current assignment.'];

        return response()->json($choices)->header('Cache-Control', 'private, no-store');
    }

    public function assign(Request $request, string $source, int $id)
    {
        $case = $this->incident($source, $id);
        $data = $request->validate(['helper_id' => 'required|integer|exists:helpers,id']);
        $session = Session::findOrFail($case->session_id);
        abort_unless($session->queue_request_id, 409, 'This emergency has no pending support request. Coordinate with the Adviser.');
        DB::transaction(function () use ($source, $id, $session, $data) {
            // Same queue-first lock order as automatic matching.
            $queue = QueueRequest::lockForUpdate()->findOrFail($session->queue_request_id);
            DB::table($source)->where('id', $id)->lockForUpdate()->first();
            $case = $this->incident($source, $id);
            abort_if(in_array($case->status, ModeratorEmergencyCases::TERMINAL, true) || $case->archived_at, 409, 'This emergency has concluded.');
            $result = app(HelperMatchingService::class)->manualAssign($queue, (int) $data['helper_id'], false, false, 'emergency_coordination');
            if (! $result instanceof Session) {
                throw ValidationException::withMessages(['helper_id' => is_string($result) ? $result : 'The request changed. Refresh its assignment status.']);
            }
            SupportAudit::record('emergency_helper_assigned', $result, ['source' => $source, 'incident_id' => $id]);
        }, 3);

        return back()->with('success', 'Helper assigned. Awaiting Helper acceptance.');
    }

    public function escalate(string $source, int $id)
    {
        $this->incident($source, $id);
        DB::transaction(function () use ($source, $id) {
            DB::table($source)->where('id', $id)->lockForUpdate()->first();
            $case = $this->incident($source, $id);
            abort_if(in_array($case->status, ModeratorEmergencyCases::TERMINAL, true) || $case->archived_at, 409, 'This emergency has concluded.');
            $session = Session::findOrFail($case->session_id);
            abort_if($session->helper_id, 409, 'A Helper is already assigned. Refresh the emergency.');
            foreach (User::where('role', 'adviser')->where('is_active', true)->get() as $user) {
                Notification::withoutGlobalScopes()->firstOrCreate([
                    'user_account_id' => $user->id, 'title' => 'Emergency needs Adviser coordination',
                    'link' => route('adviser.emergency.coordinate', [$source, $id], false),
                ], ['notification_type' => 'emergency', 'emergency_alert_id' => $source === 'emergency_alerts' ? $id : null,
                    'message' => 'An unassigned emergency needs coordination. Review eligible Helpers and the Professional handoff.']);
            }
            SupportAudit::record('emergency_coordination_requested', $session, ['source' => $source, 'incident_id' => $id]);
        }, 3);

        return back()->with('success', 'Advisers notified to coordinate emergency support.');
    }
}
