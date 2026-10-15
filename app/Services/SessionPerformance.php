<?php

namespace App\Services;

use App\Models\{EmergencyAlert, HelperCompetencyHistory, HelperSchedule, HelpSeekerEvaluation, IncidentReport, Message, Session};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/** Shared dashboard/report calculations. Reads recorded events; never changes workflow state. */
class SessionPerformance
{
    public const ACTIVITY_DATE = 'COALESCE(end_time,start_time,submitted_at,created_date,created_at)';

    public function cohort(Builder $query, AnalyticsPeriod $period): Builder
    {
        return $period->constrain($query, self::ACTIVITY_DATE);
    }

    public function summarize(Collection $rows, Collection $helperIds, AnalyticsPeriod $period, ?Collection $reportIds = null): array
    {
        $rows->loadMissing(['queue', 'helper', 'referrals', 'report']);
        $firstReplies = Message::whereIn('session_id', $rows->pluck('id'))->whereNotNull('sender_id')
            ->whereNotNull('sent_datetime')->where('sent_datetime', '<=', now())
            ->select('session_id', 'sender_id')->selectRaw('MIN(sent_datetime) AS first_reply')
            ->groupBy('session_id', 'sender_id')->get()->keyBy(fn ($m) => $m->session_id.':'.$m->sender_id);
        $wait = $response = $duration = [];
        foreach ($rows as $row) {
            $queued = $row->queue?->queued_at;
            $requested = $row->submitted_at ?? $row->created_date;
            if ($queued && $row->start_time && $row->start_time->lte(now()) && $row->start_time->gte($queued)) {
                $wait[] = $queued->diffInSeconds($row->start_time) / 60;
            }
            $reply = $firstReplies->get($row->id.':'.$row->helper?->user_account_id)?->first_reply;
            if ($requested && $reply && Carbon::parse($reply)->gte($requested)) {
                $response[] = $requested->diffInSeconds(Carbon::parse($reply)) / 60;
            }
            if (in_array($row->session_status, ['completed', 'evaluated'], true) && $row->start_time && $row->end_time && $row->end_time->lte(now()) && $row->end_time->gte($row->start_time)) {
                $duration[] = $row->start_time->diffInSeconds($row->end_time) / 60;
            }
        }
        $completed = $rows->whereIn('session_status', ['completed', 'evaluated']);
        $started = $rows->filter(fn ($s) => $s->start_time && $s->start_time->lte(now()));
        $reviewed = $rows->flatMap->referrals->filter(fn ($r) => $r->reviewed_at && $r->reviewed_at->lte(now()));
        $ratings = HelpSeekerEvaluation::whereIn('session_id', $completed->pluck('id'))
            ->where('created_at', '<=', now())->orderByDesc('id')->get()->unique('session_id')
            ->filter(fn ($r) => ($r->submitted_at ?? $r->created_at)->gte($period->start) && ($r->submitted_at ?? $r->created_at)->lt($period->end) && ($r->submitted_at ?? $r->created_at)->lte(now()))
            ->map(fn ($r) => AdviserAnalytics::rating($r->overall_score))->filter(fn ($r) => $r !== null)->values();
        $evaluations = HelperCompetencyHistory::whereIn('helper_id', $helperIds)
            ->where('evaluation_date', '>=', $period->start->utc())->where('evaluation_date', '<', $period->end->utc())->where('evaluation_date', '<=', now())
            ->when($reportIds !== null, fn ($q) => $q->whereIn('report_id', $reportIds))
            ->orderBy('evaluation_date')->orderBy('id')->get()->filter(fn ($e) => $e->normalized_score >= 1 && $e->normalized_score <= 5);
        $served = $started->pluck('helper_id')->filter()->unique()->count();
        $recommended = $completed->filter(fn ($s) => $s->referrals->isNotEmpty() || $s->report?->referral_recommended)->count();
        $required = $rows->filter(fn ($s) => $s->start_time && $s->end_time && $s->end_time->lte(now()) && in_array($s->session_status, ['completed', 'evaluated', 'cancelled', 'no_show'], true));
        $documented = $required->filter(fn ($s) => $s->documentation_status === 'submitted' && $s->report?->summary_submitted_at && $s->report?->reflection_submitted_at);
        $emergencyIds = EmergencyAlert::whereIn('session_id', $rows->pluck('id'))->where('triggered_at', '<=', now())->pluck('session_id')
            ->merge(IncidentReport::whereIn('session_id', $rows->pluck('id'))->where('risk_level', 'emergency')->where('created_at', '<=', now())->pluck('session_id'));
        $emergencies = $rows->filter(fn ($s) => $s->risk_level === 'emergency' || $emergencyIds->contains($s->id))->count();
        $metrics = [
            'total' => $rows->count(), 'completed' => $completed->count(), 'active' => $rows->where('session_status', 'active')->count(),
            'cancelled' => $rows->whereIn('session_status', ['cancelled', 'abandoned'])->count(),
            'completion_rate' => self::ratio($completed->intersect($started)->count(), $started->count()),
            'referral_rate' => self::ratio($recommended, $completed->count()),
            'referral_approval_rate' => self::ratio($reviewed->whereNotNull('approved_at')->count(), $reviewed->count()),
            'response_minutes' => self::average($response), 'waiting_minutes' => self::average($wait), 'duration_minutes' => self::average($duration),
            'satisfaction' => self::average($ratings->all()), 'competency' => self::average($evaluations->pluck('normalized_score')->all()),
            'workload' => $served ? round($completed->whereIn('helper_id', $started->pluck('helper_id')->filter())->count() / $served, 2) : null,
            'duty_hours' => $this->scheduledHours($helperIds, $period), 'documentation_pending' => $required->count() - $documented->count(),
            'documentation_rate' => self::ratio($documented->count(), $required->count()), 'emergency_rate' => self::ratio($emergencies, $rows->count()),
        ];
        $samples = ['response_minutes' => count($response), 'waiting_minutes' => count($wait), 'duration_minutes' => count($duration),
            'satisfaction' => $ratings->count(), 'competency' => $evaluations->count(), 'completion_rate' => $started->count(),
            'referral_rate' => $completed->count(), 'referral_approval_rate' => $reviewed->count(), 'workload' => $served,
            'documentation_rate' => $required->count(), 'emergency_rate' => $rows->count()];
        $growth = $evaluations->where('rubric_version', CompetencyRubric::VERSION)->groupBy('helper_id')->filter(fn ($group) => $group->count() >= 2)
            ->map(fn ($group) => $group->last()->normalized_score - $group->first()->normalized_score);

        return compact('metrics', 'samples', 'evaluations') + ['growth' => self::average($growth->all())];
    }

    public function scheduledHours(Collection $helperIds, AnalyticsPeriod $period): float
    {
        $hours = 0;
        foreach (HelperSchedule::whereIn('helper_id', $helperIds)->where('is_active', true)
            ->whereDate('date', '>=', $period->start->timezone('Asia/Manila')->toDateString())
            ->whereDate('date', '<', $period->end->timezone('Asia/Manila')->toDateString())->get() as $shift) {
            $a = Carbon::parse($shift->date->toDateString().' '.($shift->shift_start ?? HelperSchedule::OPERATING_START), 'Asia/Manila')->utc()->max($period->start);
            $b = Carbon::parse($shift->date->toDateString().' '.($shift->shift_end ?? HelperSchedule::OPERATING_END), 'Asia/Manila')->utc()->min($period->end);
            if ($b->gt($a)) $hours += $a->diffInSeconds($b) / 3600;
        }
        return round($hours, 2);
    }

    public function operations(AnalyticsPeriod $period, ?Builder $query = null): array
    {
        $rows = $this->cohort($query ?? Session::query(), $period)->with('queue')->get();
        $wait = $rows->filter(fn ($s) => $s->queue?->queued_at && $s->start_time && $s->start_time->lte(now()) && $s->start_time->gte($s->queue->queued_at))
            ->map(fn ($s) => $s->queue->queued_at->diffInSeconds($s->start_time) / 60);
        $helpers = $rows->filter(fn ($s) => $s->start_time && $s->start_time->lte(now()))->pluck('helper_id')->filter()->unique();
        return ['waiting_minutes' => self::average($wait->all()), 'samples' => $wait->count(),
            'workload' => $helpers->count() ? round($rows->whereIn('session_status', ['completed', 'evaluated'])->whereIn('helper_id', $helpers)->count() / $helpers->count(), 2) : null,
            'helpers' => $helpers->count()];
    }

    public static function average(array $values): ?float
    {
        return count($values) ? round(array_sum($values) / count($values), 2) : null;
    }

    public static function ratio(int $numerator, int $denominator): ?float
    {
        return $denominator ? round(100 * $numerator / $denominator, 2) : null;
    }

    public static function display(?float $value, string $unit = ''): string
    {
        return $value === null ? 'No data' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').$unit;
    }
}
