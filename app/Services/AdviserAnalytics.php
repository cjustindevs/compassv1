<?php
namespace App\Services;
use App\Models\{Session,Helper,Referral};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class AdviserAnalytics {
    public const DEFINITIONS = [
        'completion_rate'=>'Completed sessions with a recorded start / sessions with a recorded start in the selected activity cohort × 100.',
        'referral_rate'=>'Completed sessions with a referral recommendation / completed sessions × 100.',
        'response_minutes'=>'Mean minutes from submitted_at (legacy created_date) to the assigned Helper’s first sent_datetime. Missing or negative intervals are excluded.',
        'waiting_minutes'=>'Mean minutes from queue_requests.queued_at to counseling_sessions.start_time. Ongoing waits and missing timestamps are excluded.',
        'satisfaction'=>'Valid post-session scores submitted in the selected period, converted from the stored 1–10 experience scale to 1–5 using 1 + (score − 1) × 4/9.',
        'duty_hours'=>'Scheduled active duty hours overlapping the selected Asia/Manila date/time range. This is planned coverage, not attendance.',
        'competency'=>'Finalized recorded evaluations in the selected period, normalized to 1-5. Current rubric growth requires comparable evaluations.',
        'workload'=>'Completed sessions per Helper with at least one started session in the selected activity cohort.',
        'documentation_rate'=>'Required ended sessions with both summary and reflection submitted / required ended sessions. This is not an on-time rate.',
        'emergency_rate'=>'Unique emergency-flagged sessions / all sessions in the same selected activity cohort. Mirrored alerts do not add requests.',
        'period'=>'Activity date is end_time, otherwise start_time, otherwise submitted_at, otherwise legacy created_date/created_at. Completed/evaluated are completed; cancelled/abandoned are excluded from completed counts.',
    ];
    public function filters(Request $request): array {
        $data=$request->validate(['case_status'=>'nullable|in:active,completed,evaluated,waiting,helper_assigned,pending_review,emergency,cancelled,no_show','period'=>'nullable|in:today,weekly,monthly,quarterly,yearly,semester,all,custom','from'=>'nullable|required_with:to|date','to'=>'nullable|required_with:from|date|after_or_equal:from','helper_id'=>'nullable|integer','concern_id'=>'nullable|integer|exists:concern_categories,id','referral_status'=>'nullable|in:'.implode(',',Referral::STATUSES),'competency_metric'=>'nullable|in:overall_score,active_listening_score,empathy_score,respect_score,ethical_practices_score,referral_accuracy_score','format'=>'nullable|in:pdf,csv','history'=>'nullable|in:current,archived,all']);
        $adviser=app(AdviserScope::class)->actor();
        if(!empty($data['helper_id'])) abort_unless(Helper::whereKey($data['helper_id'])->where('adviser_id',$adviser->id)->exists(),403);
        $period = AnalyticsPeriod::fromRequest($request);
        return array_merge($data, ['period'=>$data['period'] ?? 'monthly', 'competency_metric'=>$data['competency_metric'] ?? 'overall_score',
            'start'=>Carbon::instance($period->start->utc()), 'end'=>Carbon::instance($period->end->utc())]);
    }

    public function scoped(?int $adviserId = null) {
        $id=$adviserId ?? app(AdviserScope::class)->actor()->id;
        return Session::query()->where(fn($q)=>$q->whereHas('helper',fn($h)=>$h->where('adviser_id',$id))->orWhere('review_adviser_id',$id)->orWhereHas('referrals',fn($r)=>$r->where('adviser_id',$id)->whereNotIn('status',['closed','declined','completed'])));
    }
    public function sessions(array $f, ?int $adviserId = null) {
        return $this->scoped($adviserId)
            ->when(($f['history'] ?? 'current')==='current', fn($q)=>$q->whereNull('archived_at'))
            ->when(($f['history'] ?? '')==='archived', fn($q)=>$q->whereNotNull('archived_at'))
            ->whereRaw('COALESCE(end_time,start_time,submitted_at,created_date,created_at) >= ?', [$f['start']])
            ->whereRaw('COALESCE(end_time,start_time,submitted_at,created_date,created_at) < ?', [$f['end']])
            ->whereRaw('COALESCE(end_time,start_time,submitted_at,created_date,created_at) <= ?', [now()])
            ->when($f['case_status'] ?? null,fn($q,$status)=>$q->where('session_status',$status))
            ->when($f['helper_id'] ?? null,fn($q,$id)=>$q->where('helper_id',$id))
            ->when($f['concern_id'] ?? null,fn($q,$id)=>$q->where('concern_id',$id))
            ->when($f['referral_status'] ?? null,fn($q,$status)=>$q->whereHas('referrals',fn($r)=>$r->where('status',$status)));
    }
    public static function rating(?float $score): ?float { return $score!==null && $score>=1 && $score<=10 ? round(1+($score-1)*4/9,2) : null; }
    public function report(array $f, ?int $adviserId = null): array {
        $rows = $this->sessions($f, $adviserId)->with(['queue','helper','referrals','report'])->orderBy('id')->get();
        $helperQuery = Helper::where('adviser_id', $adviserId ?? app(AdviserScope::class)->actor()->id)
            ->when($f['helper_id'] ?? null, fn ($q, $id) => $q->whereKey($id));
        $period = new AnalyticsPeriod(\Carbon\CarbonImmutable::instance($f['start']), \Carbon\CarbonImmutable::instance($f['end']));
        $reportIds = (($f['case_status'] ?? null) || ($f['concern_id'] ?? null) || ($f['referral_status'] ?? null)) ? $rows->pluck('report.id')->filter() : null;
        $summary = app(SessionPerformance::class)->summarize($rows, (clone $helperQuery)->pluck('id'), $period, $reportIds);
        $metrics = $summary['metrics'];
        $trends = $rows->groupBy(fn ($r) => ($r->end_time ?? $r->start_time ?? $r->submitted_at ?? $r->created_date ?? $r->created_at)->copy()->timezone('Asia/Manila')->format('Y-m'))
            ->sortKeys()->map(fn ($group, $month) => ['month'=>$month, 'sessions'=>$group->count(), 'completed'=>$group->whereIn('session_status',['completed','evaluated'])->count()])->values();
        $metric = $f['competency_metric'] ?? 'overall_score';
        $competency = $summary['evaluations']->groupBy(fn ($e) => $e->evaluation_date->copy()->timezone('Asia/Manila')->format('Y-m'))->sortKeys()
            ->map(fn ($group, $month) => ['month'=>$month, 'score'=>round($group->avg(fn ($e) => $metric === 'overall_score' ? $e->normalized_score : $e->$metric), 2)])->values();
        return compact('metrics','trends','competency') + ['samples'=>$summary['samples'], 'helpers'=>$helperQuery->get(), 'definitions'=>self::DEFINITIONS, 'filters'=>$f];
    }
}
