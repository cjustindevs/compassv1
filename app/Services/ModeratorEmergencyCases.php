<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One operational case per session; alert lifecycle is authoritative over its incident mirror. */
class ModeratorEmergencyCases
{
    public const TERMINAL = ['resolved', 'closed', 'cancelled', 'archived'];

    public function query(bool $history = false): Builder
    {
        $alerts = DB::table('emergency_alerts as e')->selectRaw("e.id, 'emergency_alerts' as source, e.session_id,
            e.status, COALESCE(e.risk_level,s.risk_level,'emergency') as risk_level,
            COALESCE(e.triggered_at,e.created_at) as triggered_at, e.acknowledged_at, e.resolved_at, e.archived_at,
            e.adviser_id, s.helper_id, hs.generated_alias as seeker_alias,
            e.adviser_notified_at, e.professional_referred_at, e.professional_referred_at as escalated_at,
            CASE WHEN e.professional_referred THEN 1 ELSE 0 END as professional_referred,
            CASE WHEN e.adviser_notified THEN 1 ELSE 0 END as adviser_notified,
            COALESCE(detector.role,'system') as detected_by,
            h.first_name as helper_first_name, h.last_name as helper_last_name,
            a.first_name as adviser_first_name, a.last_name as adviser_last_name")
            ->leftJoin('users as detector', 'detector.id', '=', 'e.triggered_by')
            ->leftJoin('counseling_sessions as s', 's.id', '=', 'e.session_id')
            ->leftJoin('help_seekers as hs', 'hs.id', '=', 'e.seeker_id')
            ->leftJoin('helpers as h', 'h.id', '=', 's.helper_id')
            ->leftJoin('advisers as a', 'a.id', '=', 'e.adviser_id')
            ->when(! $history, fn ($query) => $query->where(fn ($q) => $q->whereNull('e.session_id')->orWhereNotExists(fn ($newer) => $newer->selectRaw('1')->from('emergency_alerts as n')->whereColumn('n.session_id', 'e.session_id')->whereColumn('n.id', '>', 'e.id'))));
        $legacy = DB::table('incident_reports as i')->selectRaw("i.id, 'incident_reports' as source, i.session_id,
            i.status, COALESCE(i.risk_level,'emergency') as risk_level,
            COALESCE(i.reported_at,i.created_at) as triggered_at, i.reviewed_at as acknowledged_at, i.resolved_at, i.archived_at,
            s.review_adviser_id as adviser_id, s.helper_id, hs.generated_alias as seeker_alias,
            NULL as adviser_notified_at, NULL as professional_referred_at, i.escalated_at,
            0 as professional_referred, 0 as adviser_notified,
            COALESCE(detector.role,'system') as detected_by,
            h.first_name as helper_first_name, h.last_name as helper_last_name,
            a.first_name as adviser_first_name, a.last_name as adviser_last_name")
            ->leftJoin('users as detector', 'detector.id', '=', 'i.user_account_id')
            ->leftJoin('counseling_sessions as s', 's.id', '=', 'i.session_id')
            ->leftJoin('help_seekers as hs', 'hs.id', '=', 's.seeker_id')
            ->leftJoin('helpers as h', 'h.id', '=', 's.helper_id')
            ->leftJoin('advisers as a', 'a.id', '=', 's.review_adviser_id')
            ->where(fn ($q) => $q->whereIn('i.incident_category', ['emergency_flag', 'classification_emergency'])->orWhere('i.risk_level', 'emergency'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('emergency_alerts as e')->whereColumn('e.session_id', 'i.session_id'))
            ->when(! $history, fn ($query) => $query->where(fn ($q) => $q->whereNull('i.session_id')->orWhereNotExists(fn ($n) => $n->selectRaw('1')->from('incident_reports as n')->whereColumn('n.session_id', 'i.session_id')->whereColumn('n.id', '>', 'i.id')->where(fn ($category) => $category->whereIn('n.incident_category', ['emergency_flag', 'classification_emergency'])->orWhere('n.risk_level', 'emergency')))));

        return DB::query()->fromSub($alerts->unionAll($legacy), 'emergency_cases');
    }

    public function active(): Builder
    {
        return $this->query()->whereNotIn('status', self::TERMINAL)->whereNull('archived_at');
    }

    public function statusLabel(object $case): string
    {
        if ($case->archived_at || $case->status === 'archived') {
            return 'Archived';
        }
        if (in_array($case->status, self::TERMINAL, true)) {
            return ucfirst($case->status);
        }
        if (in_array($case->status, ['escalated', 'referred'], true)) {
            return 'Escalated';
        }

        return $case->acknowledged_at ? 'Responding' : 'Awaiting acknowledgment';
    }
}
