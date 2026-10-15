<?php

namespace App\Console\Commands;

use App\Models\EmergencyAlert;
use App\Models\IncidentReport;
use App\Services\SupportAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileResolvedEmergencyIncidents extends Command
{
    protected $signature = 'emergencies:reconcile-resolved {--apply : Repair confirmed stale incident mirrors}';

    protected $description = 'Preview or repair open incident mirrors of resolved emergencies, preserving case history';

    public function handle(): int
    {
        $count = 0;
        EmergencyAlert::whereIn('status', ['resolved', 'closed'])->orderBy('id')->eachById(function ($row) use (&$count) {
            DB::transaction(function () use ($row, &$count) {
                // Serialize with new reports/resolution, which lock session before alert.
                if ($row->session_id) {
                    DB::table('counseling_sessions')->where('id', $row->session_id)->lockForUpdate()->first();
                }
                $alert = EmergencyAlert::lockForUpdate()->findOrFail($row->id);
                if (! in_array($alert->status, ['resolved', 'closed'], true)) {
                    return;
                }
                $noOpen = $alert->session_id
                    && EmergencyAlert::where('session_id', $alert->session_id)->latest('id')->value('id') === $alert->id
                    && ! EmergencyAlert::where('session_id', $alert->session_id)->whereNotIn('status', ['resolved', 'closed'])->exists();
                $incidents = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])
                    ->where(function ($query) use ($alert, $noOpen) {
                        $query->where('emergency_alert_id', $alert->id);
                        if ($noOpen) {
                            $query->orWhere(fn ($q) => $q->where('session_id', $alert->session_id)->whereNull('emergency_alert_id')->whereIn('incident_category', ['emergency_flag', 'classification_emergency']));
                        }
                    })->lockForUpdate()->get();
                $count += $incidents->count();
                if (! $this->option('apply')) {
                    return;
                }
                foreach ($incidents as $incident) {
                    $incident->forceFill(['status' => 'resolved', 'resolved_at' => $incident->resolved_at ?? $alert->resolved_at ?? now(),
                        'resolution_summary' => $incident->resolution_summary ?: $alert->resolution_notes])->save();
                    SupportAudit::record('emergency_incident_reconciled', $incident, ['emergency_alert_id' => $alert->id]);
                }
            }, 3);
        });
        $this->info(($this->option('apply') ? 'Reconciled' : 'Would reconcile').' '.$count.' confirmed stale emergency incident(s).');

        return self::SUCCESS;
    }
}
