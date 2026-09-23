<?php
namespace App\Observers;
use App\Models\Session;
use App\Services\SupportAudit;
use Illuminate\Support\Facades\DB;
class SessionWorkflowObserver {
    public function saving(Session $session): void {
        if ($session->exists && $session->isDirty('session_status') && in_array($session->getOriginal('session_status'),['completed','evaluated','cancelled','no_show'])
            && !in_array($session->session_status,['completed','evaluated','cancelled','no_show'])) abort(409,'Closed requests cannot be reopened.');
        if (!$session->isDirty('workflow_state') && $session->isDirty('session_status')) {
            $session->workflow_state = match ($session->session_status) {
                'screening_completed'=>'concern_required', 'preferences_set','waiting'=>'queued',
                'helper_assigned'=>'helper_pending_acceptance', 'active'=>'session_active',
                'completed'=>'evaluation_pending', 'evaluated'=>'closed','cancelled','no_show'=>'closed',
                'emergency'=>'emergency_escalated', 'pending_review'=>'adviser_review_required', default=>'screening_required',
            };
        }
    }
    public function saved(Session $session): void {
        if ($session->wasChanged('workflow_state') || $session->wasRecentlyCreated) {
            DB::table('request_status_events')->insert(['session_id'=>$session->id,'actor_id'=>auth()->id(),
                'from_state'=>$session->getOriginal('workflow_state'),'to_state'=>$session->workflow_state ?? 'screening_required','occurred_at'=>now()]);
            SupportAudit::record('request_status_changed',$session,['from'=>$session->getOriginal('workflow_state'),'to'=>$session->workflow_state]);
        }
    }
}
