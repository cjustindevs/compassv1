<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ModeratorActivity
{
    public function query(): Builder
    {
        $audit = DB::table('audit_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_account_id')
            ->where(function ($q) {
                $q->whereIn('l.action', ['request_submitted', 'request_expired', 'request_cancelled', 'queue_entry_created', 'match_recommended', 'match_override', 'helper_assigned', 'helper_accepted', 'helper_declined', 'session_started', 'session_completed', 'session_scheduled', 'queue_priority_changed', 'record_archived', 'record_restored', 'report_exported', 'replacement_offer_declined', 'replacement_offer_expired', 'connection_handoff_completed', 'helper_reconnected'])
                    ->orWhere(fn ($q) => $q->where('l.action', 'like', 'emergency_%')->whereIn('l.target_type', ['emergency_alerts', 'incident_reports', 'counseling_sessions']))
                    ->orWhere(fn ($q) => $q->where('u.role', 'moderator')->whereIn('l.target_type', ['counseling_sessions', 'queue_requests', 'emergency_alerts', 'incident_reports', 'session_reconnections', 'helpers']));
            })->selectRaw("l.id, 'audit' as source, l.created_at as occurred_at, l.action,
                CASE WHEN l.target_type IN ('emergency_alerts','incident_reports') THEN 'emergencies' WHEN l.target_type = 'queue_requests' THEN 'queue' WHEN l.target_type = 'counseling_sessions' THEN 'cases' ELSE 'actions' END as type,
                l.target_type as record_type, l.target_id as record_id,
                CASE WHEN u.role = 'seeker' THEN 'Help Seeker' ELSE COALESCE(u.name,'System') END as actor,
                COALESCE(l.outcome,'recorded') as status");
        // Legacy business timestamps remain visible when no corresponding audit exists.
        $queue = DB::table('queue_requests as q')->whereNotExists(fn ($a) => $a->selectRaw('1')->from('audit_logs')->where('target_type', 'queue_requests')->whereColumn('target_id', 'q.id'))
            ->selectRaw("q.id, 'queue' as source, COALESCE(q.matched_date,q.request_date) as occurred_at,
                CASE WHEN q.matched_date IS NULL THEN 'queue_entry_created' ELSE 'helper_matched' END as action,
                'queue' as type, 'queue_requests' as record_type, q.id as record_id, 'System' as actor, q.request_status as status");
        $cases = app(ModeratorEmergencyCases::class)->query(true);
        $emergency = DB::query()->fromSub($cases, 'e')->whereNotExists(fn ($a) => $a->selectRaw('1')->from('audit_logs')->whereColumn('target_type', 'e.source')->whereColumn('target_id', 'e.id'))
            ->selectRaw("e.id, 'emergency' as source, COALESCE(e.resolved_at,e.acknowledged_at,e.triggered_at) as occurred_at,
                CASE WHEN e.resolved_at IS NOT NULL THEN 'emergency_resolved' WHEN e.acknowledged_at IS NOT NULL THEN 'emergency_acknowledged' ELSE 'emergency_recorded' END as action,
                'emergencies' as type, e.source as record_type, e.id as record_id, 'System' as actor, e.status");

        return DB::query()->fromSub($audit->unionAll($queue)->unionAll($emergency), 'operational_activity');
    }
}
