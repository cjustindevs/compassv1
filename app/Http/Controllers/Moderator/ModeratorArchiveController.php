<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\IncidentReport;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Services\ModeratorEmergencyCases;
use App\Services\SupportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModeratorArchiveController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()?->is_active && $request->user()->role === 'moderator', 403);
        $data = $request->validate(['record_type' => 'required|in:emergency_alerts,incident_reports,counseling_sessions,queue_requests', 'record_id' => 'required|integer', 'restore' => 'nullable|boolean']);
        $class = ['emergency_alerts' => EmergencyAlert::class, 'incident_reports' => IncidentReport::class, 'counseling_sessions' => Session::class, 'queue_requests' => QueueRequest::class][$data['record_type']];
        DB::transaction(function () use ($class, $data, $request) {
            $record = $class::whereKey($data['record_id'])->lockForUpdate()->firstOrFail();
            $status = $record instanceof Session ? $record->session_status : ($record instanceof QueueRequest ? $record->request_status : $record->status);
            $terminal = $record instanceof Session ? ['completed', 'evaluated', 'cancelled', 'no_show'] : ($record instanceof QueueRequest ? ['completed', 'expired', 'cancelled'] : ['resolved', 'closed', 'cancelled']);
            if (! in_array($status, $terminal, true)) {
                throw ValidationException::withMessages(['archive' => 'Only completed, resolved, closed, cancelled or expired records may be archived.']);
            }
            if ($record instanceof Session && app(ModeratorEmergencyCases::class)->active()->where('session_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['archive' => 'This case still has an open emergency review.']);
            }
            $restore = $request->boolean('restore');
            if ((bool) $record->archived_at === ! $restore) {
                return;
            }
            $record->forceFill(['archived_at' => $restore ? null : now(), 'archived_by' => $restore ? null : $request->user()->id])->save();
            SupportAudit::record($restore ? 'record_restored' : 'record_archived', $record, ['purpose' => 'operational_history']);
        });

        return back()->with('success', $request->boolean('restore') ? 'Record restored to historical lists.' : 'Record archived. History has been preserved.');
    }
}
