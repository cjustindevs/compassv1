<?php
namespace App\Services;
use App\Models\{Adviser,AdviserFeedback,Helper,Notification,TrainingRecommendation};
use Illuminate\Support\Facades\DB;
class AdviserSupervisionMaintenance {
    public function run(): void {
        Adviser::whereHas('user',fn($q)=>$q->where('role','adviser')->where('is_active',true))->eachById(function($adviser) {
            DB::transaction(function() use($adviser) {
                Adviser::whereKey($adviser->id)->lockForUpdate()->first();
                foreach(Helper::where('adviser_id',$adviser->id)->get() as $helper) {
                    if($helper->schedules()->whereDate('date',now('Asia/Manila')->toDateString())->where('is_active',true)->get()->contains(fn($shift)=>$shift->isOnDuty()) && $helper->getReadinessStatus() !== 'ready') $this->notify($adviser->user_account_id,'Readiness follow-up: '.$helper->public_alias,'A scheduled Helper needs a readiness check before assignment.','/adviser/helper/'.$helper->id);
                    if($helper->currentAssignedSessionsCount() >= Helper::MAX_SESSIONS_PER_SHIFT) $this->notify($adviser->user_account_id,'Workload limit: '.$helper->public_alias,'A supervised Helper reached the duty-session limit.','/adviser/helper/'.$helper->id);
                }
                TrainingRecommendation::whereHas('helper',fn($q)=>$q->where('adviser_id',$adviser->id))->whereIn('status',['assigned','in_progress'])->whereDate('due_date','<=',now('Asia/Manila')->toDateString())->eachById(fn($task)=>$this->notify($adviser->user_account_id,'Training follow-up #'.$task->id,'A training recommendation is due for follow-up.','/adviser/training'));
                AdviserFeedback::whereHas('report.session.helper',fn($q)=>$q->where('adviser_id',$adviser->id))->whereDate('follow_up_date','<=',now('Asia/Manila')->toDateString())->whereNull('acknowledged_at')->eachById(fn($feedback)=>$this->notify($adviser->user_account_id,'Feedback follow-up #'.$feedback->id,'A feedback acknowledgment remains outstanding.','/adviser/evaluate/'.$feedback->report_id));
            },3);
        });
    }
    private function notify(int $user,string $title,string $message,string $link): void {
        if(Notification::where('user_account_id',$user)->where('title',$title)->where('link',$link)->whereDate('created_at',today())->exists()) return;
        Notification::create(['user_account_id'=>$user,'title'=>$title,'message'=>$message,'link'=>$link,'notification_type'=>'system']);
    }
}
