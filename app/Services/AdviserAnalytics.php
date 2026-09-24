<?php
namespace App\Services;
use App\Models\{Session,Helper,HelperSchedule,HelperCompetencyHistory,Referral,HelpSeekerEvaluation,Message};
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
        'period'=>'Activity date is end_time, otherwise start_time, otherwise submitted_at, otherwise legacy created_date/created_at. Completed/evaluated are completed; cancelled/abandoned are excluded from completed counts.',
    ];
    public function filters(Request $request): array {
        $data=$request->validate(['period'=>'nullable|in:weekly,monthly,quarterly,yearly','from'=>'nullable|required_with:to|date','to'=>'nullable|required_with:from|date|after_or_equal:from','helper_id'=>'nullable|integer','concern_id'=>'nullable|integer|exists:concern_categories,id','referral_status'=>'nullable|in:pending_adviser,pending_consent,pending_professional,no_professional_available,accepted,in_progress,completed,closed,declined','competency_metric'=>'nullable|in:overall_score,active_listening_score,empathy_score,respect_score,ethical_practices_score,referral_accuracy_score','format'=>'nullable|in:pdf,csv']);
        $adviser=app(AdviserScope::class)->actor();
        if(!empty($data['helper_id'])) abort_unless(Helper::whereKey($data['helper_id'])->where('adviser_id',$adviser->id)->exists(),403);
        $days=['weekly'=>7,'monthly'=>30,'quarterly'=>90,'yearly'=>365][$data['period'] ?? 'monthly'];
        $start=!empty($data['from']) ? Carbon::parse($data['from'],'Asia/Manila') : now('Asia/Manila')->subDays($days)->startOfDay();
        $end=!empty($data['to']) ? Carbon::parse($data['to'],'Asia/Manila') : now('Asia/Manila');
        if(!empty($data['to']) && strlen($data['to'])===10) $end->endOfDay();
        return array_merge($data,['period'=>$data['period'] ?? 'monthly','competency_metric'=>$data['competency_metric'] ?? 'overall_score','start'=>$start->utc(),'end'=>$end->utc()]);
    }
    public function scoped() {
        $id=app(AdviserScope::class)->actor()->id;
        return Session::query()->where(fn($q)=>$q->whereHas('helper',fn($h)=>$h->where('adviser_id',$id))->orWhere('review_adviser_id',$id)->orWhereHas('referrals',fn($r)=>$r->where('adviser_id',$id)->whereNotIn('status',['closed','declined','completed'])));
    }
    public function sessions(array $f) {
        return $this->scoped()->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(end_time,start_time,submitted_at,created_date,created_at)'),[$f['start'],$f['end']])
            ->when($f['helper_id'] ?? null,fn($q,$id)=>$q->where('helper_id',$id))
            ->when($f['concern_id'] ?? null,fn($q,$id)=>$q->where('concern_id',$id))
            ->when($f['referral_status'] ?? null,fn($q,$status)=>$q->whereHas('referrals',fn($r)=>$r->where('status',$status)));
    }
    public static function rating(?float $score): ?float { return $score!==null && $score>=1 && $score<=10 ? round(1+($score-1)*4/9,2) : null; }
    public function report(array $f): array {
        $rows=$this->sessions($f)->with(['queue','helper','referrals','report'])->orderBy('id')->get();
        $firstReplies=Message::whereIn('session_id',$rows->pluck('id'))->whereNotNull('sent_datetime')->select('session_id','sender_id')->selectRaw('MIN(sent_datetime) AS first_reply')->groupBy('session_id','sender_id')->get()->keyBy(fn($m)=>$m->session_id.':'.$m->sender_id);
        $wait=[]; $response=[]; $duration=[];
        foreach($rows as $row) {
            $queued=$row->queue?->queued_at; $requested=$row->submitted_at ?? $row->created_date;
            if($queued && $row->start_time && $row->start_time->gte(Carbon::parse($queued))) $wait[]=Carbon::parse($queued)->diffInSeconds($row->start_time)/60;
            $reply=$firstReplies->get($row->id.':'.$row->helper?->user_account_id)?->first_reply;
            if($requested && $reply && Carbon::parse($reply)->gte($requested)) $response[]=$requested->diffInSeconds(Carbon::parse($reply))/60;
            if(in_array($row->session_status,['completed','evaluated']) && $row->start_time && $row->end_time && $row->end_time->gte($row->start_time)) $duration[]=$row->start_time->diffInSeconds($row->end_time)/60;
        }
        $completed=$rows->whereIn('session_status',['completed','evaluated']); $started=$rows->whereNotNull('start_time');
        $referrals=$rows->flatMap->referrals; $reviewed=$referrals->whereNotNull('reviewed_at');
        $ratings=HelpSeekerEvaluation::whereIn('session_id',$completed->pluck('id'))->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(submitted_at,created_at)'),[$f['start'],$f['end']])->get();
        $average=fn($values)=>count($values) ? round(array_sum($values)/count($values),2) : null;
        $ratio=fn($n,$d)=>$d ? round(100*$n/$d,2) : null;
        $helperQuery=Helper::where('adviser_id',app(AdviserScope::class)->actor()->id)->when($f['helper_id'] ?? null,fn($q,$id)=>$q->whereKey($id));
        $helperIds=(clone $helperQuery)->pluck('id');
        $evaluations=HelperCompetencyHistory::whereIn('helper_id',$helperIds)->whereBetween('evaluation_date',[$f['start'],$f['end']])
            ->when(($f['concern_id'] ?? null) || ($f['referral_status'] ?? null),fn($q)=>$q->whereIn('report_id',$rows->pluck('report.id')->filter()))->orderBy('evaluation_date')->get();
        $hours=0;
        foreach(HelperSchedule::whereIn('helper_id',$helperIds)->where('is_active',true)->whereBetween('date',[$f['start']->copy()->timezone('Asia/Manila')->toDateString(),$f['end']->copy()->timezone('Asia/Manila')->toDateString()])->get() as $shift) {
            // Date-only duty schedules carry no times; planned coverage falls
            // back to the approved 6:00 PM - 11:00 PM operating window.
            $startTime = $shift->shift_start ?? HelperSchedule::OPERATING_START;
            $endTime = $shift->shift_end ?? HelperSchedule::OPERATING_END;
            $a=Carbon::parse($shift->date->toDateString().' '.$startTime,'Asia/Manila')->utc()->max($f['start']);
            $b=Carbon::parse($shift->date->toDateString().' '.$endTime,'Asia/Manila')->utc()->min($f['end']);
            if($b->gt($a)) $hours+=$a->diffInSeconds($b)/3600;
        }
        $metrics=['total'=>$rows->count(),'completed'=>$completed->count(),'cancelled'=>$rows->whereIn('session_status',['cancelled','abandoned'])->count(),'active'=>$rows->where('session_status','active')->count(),
            'completion_rate'=>$ratio($completed->whereNotNull('start_time')->count(),$started->count()),
            'referral_rate'=>$ratio($completed->filter(fn($r)=>$r->referrals->isNotEmpty() || $r->report?->referral_recommended)->count(),$completed->count()),
            'response_minutes'=>$average($response),'waiting_minutes'=>$average($wait),'duration_minutes'=>$average($duration),
            'satisfaction'=>$average($ratings->map(fn($r)=>self::rating($r->overall_score))->filter(fn($r)=>$r!==null)->all()),
            'referral_approval_rate'=>$ratio($reviewed->whereNotNull('approved_at')->count(),$reviewed->count()),'duty_hours'=>round($hours,2)];
        $trends=$rows->groupBy(fn($r)=>($r->end_time ?? $r->start_time ?? $r->submitted_at ?? $r->created_date ?? $r->created_at)->copy()->timezone('Asia/Manila')->format('Y-m'))->sortKeys()->map(fn($group,$month)=>['month'=>$month,'sessions'=>$group->count(),'completed'=>$group->whereIn('session_status',['completed','evaluated'])->count()])->values();
        $metric=$f['competency_metric'] ?? 'overall_score';
        $competency=$evaluations->groupBy(fn($e)=>$e->evaluation_date->copy()->timezone('Asia/Manila')->format('Y-m'))->sortKeys()->map(fn($group,$month)=>['month'=>$month,'score'=>round($group->avg(fn($e)=>$metric==='overall_score' ? $e->normalized_score : $e->$metric),2)])->values();
        return compact('metrics','trends','competency')+['helpers'=>$helperQuery->get(),'definitions'=>self::DEFINITIONS,'filters'=>$f];
    }
}
