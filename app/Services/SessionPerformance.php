<?php

namespace App\Services;

use App\Models\{EmergencyAlert, HelperSchedule, HelpSeekerEvaluation, IncidentReport, Message, Session};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/** Shared dashboard/report calculations. Reads recorded events; never changes workflow state. */
class SessionPerformance
{
    public const ACTIVITY_DATE = 'COALESCE(end_time,start_time,submitted_at,created_date,created_at)';

    public function cohort(Builder $query, AnalyticsPeriod $period): Builder
    {
        return $period->constrain($query->where('is_test_record', false)->whereNull('duplicate_of_id'), self::ACTIVITY_DATE);
    }

    public function summarize(Collection $rows, Collection $helperIds, AnalyticsPeriod $period, ?Collection $reportIds = null): array
    {
        $rows = $rows->reject(fn ($s) => $s->is_test_record || $s->duplicate_of_id)->unique('id')->values();
        $rows->loadMissing(['queue', 'helper', 'referrals', 'report']);
        $inPeriod = fn ($at) => $at && $at->gte($period->start) && $at->lt($period->end) && $at->lte(now());
        $messages = Message::whereIn('session_id', $rows->pluck('id'))->whereNotNull('sender_id')
            ->whereNotNull('sent_datetime')->where('sent_datetime', '<=', now())->whereNull('audio_url')
            ->whereRaw("TRIM(message_text) <> ''")->select(['session_id', 'sender_id', 'sent_datetime'])->orderBy('sent_datetime')->get()->groupBy('session_id');
        $calls = \App\Models\VoiceCall::whereIn('session_id', $rows->pluck('id'))->whereNotNull('connected_at')
            ->where('connected_at', '<=', now())->orderBy('connected_at')->get()->groupBy('session_id');
        $wait = $response = $duration = [];
        foreach ($rows as $row) {
            $queued = $row->queue?->queued_at;
            if ($queued && $inPeriod($row->start_time) && $row->start_time->gte($queued)) {
                $wait[] = $queued->diffInSeconds($row->start_time) / 60;
            }
            // Response begins at the actual session start, never at queue entry.
            if ($row->start_time && $row->helper && $inPeriod($row->start_time)) {
                $reply = ($messages[$row->id] ?? collect())->first(fn ($m) => (int) $m->sender_id === (int) $row->helper->user_account_id
                    && $m->sent_datetime->gte($row->start_time) && (!$row->end_time || $m->sent_datetime->lte($row->end_time)));
                $call = ($calls[$row->id] ?? collect())->first(fn ($c) => $c->connected_at->gte($row->start_time)
                    && (!$row->end_time || $c->connected_at->lte($row->end_time)));
                $times = collect([$reply?->sent_datetime, $call?->connected_at])->filter()->sortBy(fn ($at) => $at->timestamp);
                if ($times->isNotEmpty()) $response[] = $row->start_time->diffInSeconds($times->first()) / 60;
            }
            if (in_array($row->session_status, ['completed', 'evaluated'], true) && $row->start_time && $inPeriod($row->end_time) && $row->end_time->gte($row->start_time)) {
                $duration[] = $row->start_time->diffInSeconds($row->end_time) / 60;
            }
        }
        $completed = $rows->filter(fn ($s) => in_array($s->session_status, ['completed', 'evaluated'], true) && $s->start_time && $inPeriod($s->end_time) && $s->end_time->gte($s->start_time));
        $started = $rows->filter(fn ($s) => $inPeriod($s->start_time));
        $reviewed = $rows->flatMap->referrals->unique('id')->filter(fn ($r) => $inPeriod($r->reviewed_at));
        $concluded = $rows->filter(fn ($s) => in_array($s->session_status, ['completed', 'evaluated'], true)
            && $s->start_time && $s->end_time && $s->end_time->gte($s->start_time) && $s->end_time->lte(now()));
        $feedbackByCutoff = HelpSeekerEvaluation::whereIn('session_id', $concluded->pluck('id'))->where('created_at', '<=', now())
            ->orderByDesc('id')->get()->filter(fn ($r) => ($r->submitted_at ?? $r->created_at)->lte(now()))->unique('session_id');
        $feedback = $feedbackByCutoff->filter(fn ($r) => $inPeriod($r->submitted_at ?? $r->created_at));
        $ratings = $feedback->map(fn ($r) => AdviserAnalytics::rating($r->overall_score))->filter(fn ($r) => $r !== null)->values();
        $helpful = $feedback->filter(fn ($r) => in_array($r->answers['helpfulness_score'] ?? null, EvaluationInstrument::OPTIONS['helpfulness_score'], true));
        $evaluations = app(CompetencyEvidence::class)->evaluations($helperIds, $period, $reportIds)->sortBy('evaluation_date')->values();
        $activityRows = $rows->filter(fn ($s) => $inPeriod($s->end_time ?? $s->start_time ?? $s->submitted_at ?? $s->created_date ?? $s->created_at));
        $served = $started->merge($completed)->pluck('helper_id')->filter()->unique()->count();
        $recommended = $completed->filter(fn ($s) => $s->referrals->isNotEmpty() || $s->report?->referral_recommended)->count();
        $required = $rows->filter(fn ($s) => $s->start_time && $inPeriod($s->end_time) && $s->end_time->gte($s->start_time) && in_array($s->session_status, ['completed', 'evaluated', 'cancelled', 'no_show'], true));
        $documented = $required->filter(fn ($s) => $s->documentation_status === 'submitted' && $s->report?->summary_submitted_at && $s->report?->reflection_submitted_at && $s->report->summary_submitted_at->lte(now()) && $s->report->reflection_submitted_at->lte(now()));
        $emergencyIds = EmergencyAlert::whereIn('session_id', $rows->pluck('id'))->where('triggered_at', '<=', now())->pluck('session_id')
            ->merge(IncidentReport::whereIn('session_id', $rows->pluck('id'))->where('risk_level', 'emergency')->where('created_at', '<=', now())->pluck('session_id'));
        $emergencies = $activityRows->filter(fn ($s) => $s->risk_level === 'emergency' || $emergencyIds->contains($s->id))->count();
        $metrics = [
            'total' => $rows->filter(fn ($s) => $inPeriod($s->submitted_at ?? $s->created_date ?? $s->created_at))->count(), 'completed' => $completed->count(), 'active' => $rows->where('session_status', 'active')->count(),
            'cancelled' => $activityRows->whereIn('session_status', ['cancelled', 'abandoned'])->count(),
            'completion_rate' => self::ratio($started->whereIn('session_status', ['completed','evaluated'])->filter(fn ($s) => $s->end_time && $s->end_time->lte(now()) && $s->end_time->gte($s->start_time))->count(), $started->count()),
            'referral_rate' => self::ratio($recommended, $completed->count()),
            'referral_approval_rate' => self::ratio($reviewed->whereNotNull('approved_at')->count(), $reviewed->count()),
            'response_minutes' => self::average($response), 'waiting_minutes' => self::average($wait), 'duration_minutes' => self::average($duration),
            'satisfaction' => self::average($ratings->all()),
            'helpfulness_rate' => self::ratio($helpful->filter(fn ($r) => in_array($r->answers['helpfulness_score'], ['Helpful','Very Helpful'], true))->count(), $helpful->count()),
            'feedback_coverage' => self::ratio($completed->filter(fn ($s) => $feedbackByCutoff->contains('session_id', $s->id))->count(), $completed->count()), 'competency' => self::average($evaluations->pluck('normalized_score')->all()),
            'workload' => $served ? round($completed->count() / $served, 2) : null,
            'duty_hours' => $this->scheduledHours($helperIds, $period), 'documentation_pending' => $required->count() - $documented->count(),
            'documentation_rate' => self::ratio($documented->count(), $required->count()), 'emergency_rate' => self::ratio($emergencies, $activityRows->count()),
        ];
        $samples = ['response_minutes' => count($response), 'waiting_minutes' => count($wait), 'duration_minutes' => count($duration),
            'satisfaction' => $ratings->count(), 'helpfulness_rate' => $helpful->count(), 'feedback_coverage' => $completed->count(), 'competency' => $evaluations->count(), 'completion_rate' => $started->count(),
            'referral_rate' => $completed->count(), 'referral_approval_rate' => $reviewed->count(), 'workload' => $served,
            'documentation_rate' => $required->count(), 'emergency_rate' => $activityRows->count()];
        $growth = $evaluations->where('rubric_version', CompetencyRubric::VERSION)->groupBy('helper_id')->filter(fn ($group) => $group->count() >= 2 && $group->pluck('evaluation_date')->unique()->count() >= 2)
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

    public static function number(?float $value, string $unit = ''): string
    {
        if ($value === null) return '—';
        if ($unit === ' min') {
            $seconds = max(0, (int) round($value * 60));
            return $seconds < 60 ? $seconds.'s' : intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }
        return self::display($value, $unit);
    }

    public static function display(?float $value, string $unit = ''): string
    {
        return $value === null ? 'No data' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').$unit;
    }
}
