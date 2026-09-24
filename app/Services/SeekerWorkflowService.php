<?php
namespace App\Services;
use App\Models\{Adviser, HelpSeeker, Helper, Notification, QueueRequest, ScreeningResponse, Session, User};
use Illuminate\Support\Facades\{DB, Gate};
class SeekerWorkflowService {
    use \App\Traits\BroadcastsSafely;
    public function __construct(private ConsentService $consents, private RiskClassificationService $risk) {}
    public function current(User $user): ?Session {
        Gate::authorize('seeker-workflow');
        return Session::where('seeker_id',$user->helpSeeker->id)
            ->whereNotIn('session_status',['completed','evaluated','cancelled','no_show'])->latest('id')->first();
    }
    public function screen(User $user, array $answers, ?array $compactDetails = null): Session {
        $this->consents->requireGeneral($user);
        return DB::transaction(function () use ($user,$answers,$compactDetails) {
            HelpSeeker::whereKey($user->helpSeeker->id)->lockForUpdate()->firstOrFail();
            $existing = $this->current($user);
            abort_if($existing,409,'Finish or cancel your existing request first.');
            $review = false;
            try { $result = $compactDetails !== null ? app(CompactScreening::class)->classify($answers) : $this->risk->classifyRisk($answers); }
            catch (\RuntimeException $e) { $review = true; $result = ['risk_level'=>null,'rule_code'=>'review_unresolved','priority'=>2,'action'=>'adviser_review_required','reason'=>'Unresolved screening responses']; }
            $emergency = $result['risk_level'] === 'emergency';
            $review = !$emergency && ($review || ($compactDetails === null && !ScreeningInstrument::approved()) || $result['risk_level']==='high');
            $adviser = $review ? $this->pickReviewer() : null;
            $session = Session::create(['seeker_id'=>$user->helpSeeker->id,'risk_level'=>$result['risk_level'],
                'session_type'=>'chat','session_status'=>$emergency?'emergency':($review?'pending_review':'screening_completed'),
                'workflow_state'=>$emergency?'emergency_escalated':($review?'adviser_review_required':($compactDetails !== null ? 'session_preferences_required' : 'concern_required')),
                'requires_adviser_review'=>$review,'review_adviser_id'=>$adviser?->id,
                'requires_closer_monitoring'=>$result['risk_level']==='moderate','elevated_priority'=>$result['risk_level']==='moderate',
                'concern_id'=>$compactDetails['concern_id'] ?? null,'completion_status'=>'pending','created_date'=>now()]);
            $screening = ScreeningResponse::create(['seeker_id'=>$session->seeker_id,'session_id'=>$session->id,
                'responses'=>$answers + ($compactDetails !== null ? ['description'=>$compactDetails['description'] ?? null,'custom_concern'=>$compactDetails['custom_concern'] ?? null] : []),'risk_level'=>$result['risk_level'],'priority'=>$result['priority'],
                'action'=>$result['action'],'reason'=>$result['reason'],'classified_at'=>now(),'classified_by'=>'system',
                'is_active'=>true,'is_complete'=>!$review,'instrument_version'=>$compactDetails !== null ? CompactScreening::VERSION : ScreeningInstrument::VERSION,
                'rule_code'=>$result['rule_code'],'review_status'=>$review?'adviser_review_required':'complete',
                'actor_id'=>$user->id,'evidence_source'=>'seeker_responses']);
            if ($result['risk_level']==='high' && $adviser) {
                $referral=\App\Models\Referral::create(['session_id'=>$session->id,'helper_id'=>null,'adviser_id'=>$adviser->id,'priority_level'=>'high','help_seeker_consent'=>false,'referral_reason'=>'Preliminary screening requires urgent professional review.','referral_date'=>now(),'status'=>'pending_adviser']);
                SupportAudit::record('referral_proposed',$referral);
            }
            SupportAudit::record('screening_submitted',$screening,['instrument_version'=>$compactDetails !== null ? CompactScreening::VERSION : ScreeningInstrument::VERSION]);
            SupportAudit::record($review?'screening_review_required':'risk_classified',$screening,['rule_code'=>$result['rule_code']]);
            if ($emergency) app(EmergencyEscalationService::class)->escalateEmergency($session,$user->helpSeeker,['reason'=>$result['reason'],'screening_id'=>$screening->id,'rule_code'=>$result['rule_code']]);
            if ($review && $adviser) Notification::create(['user_account_id'=>$adviser->user_account_id,'title'=>'Screening review required',
                'message'=>'A preliminary screening needs your review before peer support.','notification_type'=>'system','link'=>'/adviser/screenings']);
            return $session;
        });
    }
    public function concern(User $user, array $data): Session {
        $this->consents->requireGeneral($user);
        return DB::transaction(function () use($user,$data) {
            $session = $this->current($user); abort_unless($session,409);
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update',$session); abort_unless($session->workflow_state==='concern_required',409);
            $session->update(['concern_id'=>$data['concern_id'],'concern_category'=>\App\Models\ConcernCategory::findOrFail($data['concern_id'])->concern_name,'workflow_state'=>'session_preferences_required']);
            SupportAudit::record('concern_selected',$session);
            // Optional narrative stays in the restricted screening record rather than queue metadata.
            $screening = $session->screeningResponses()->latest('id')->firstOrFail();
            if (!empty($data['description'])) ScreeningResponse::create(array_merge($screening->only(['seeker_id','session_id','risk_level','priority','action','reason','instrument_version','rule_code','review_status']),[
                'responses'=>['concern_description'=>$data['description']],'classified_at'=>now(),'classified_by'=>'seeker','is_complete'=>true,'actor_id'=>$user->id,'evidence_source'=>'concern_description']));
            return $session;
        });
    }
    public function submit(User $user, array $data): Session {
        $this->consents->requireGeneral($user);
        return DB::transaction(function () use ($user,$data) {
            HelpSeeker::whereKey($user->helpSeeker->id)->lockForUpdate()->firstOrFail();
            $session=$this->current($user); abort_unless($session,409);
            $session=Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update',$session);
            if ($session->submitted_at) return $session;
            abort_unless($session->workflow_state==='session_preferences_required' && $session->concern_id && !$session->requires_adviser_review && $session->risk_level!=='emergency',409,'Complete the required steps before submitting.');
            $session->update(['workflow_state'=>'ready_for_submission','session_type'=>'chat']);
            $user->update(['preferred_language'=>$data['preferred_language'],'preferred_communication_mode'=>'chat']);
            $session->update(['workflow_state'=>'submitted','submitted_at'=>now()]);
            $queue=QueueRequest::create(['seeker_id'=>$session->seeker_id,'request_date'=>now(),'queued_at'=>now(),
                'request_status'=>'waiting','priority_level'=>$session->risk_level,'preferred_session_type'=>'chat','voice_consent'=>false]);
            app(QueueManagementService::class)->prepareQueue($queue);
            $session->update(['queue_request_id'=>$queue->id,'session_status'=>'waiting','workflow_state'=>'queued']);
            SupportAudit::record('request_submitted',$session); SupportAudit::record('queue_entry_created',$queue);
            foreach (User::where('role','moderator')->where('is_active',true)->get() as $moderator) Notification::create([
                'user_account_id'=>$moderator->id,'title'=>'Support request waiting','message'=>'A new request is awaiting an eligible helper.','notification_type'=>'system','link'=>'/moderator/queue']);
            DB::afterCommit(fn()=>app(HelperMatchingService::class)->matchWaitingRequests());
            DB::afterCommit(fn()=>$this->broadcastQueueUpdate());
            return $session;
        });
    }
    public function cancel(User $user, Session $session, bool $expired = false): void {
        if (!$expired) Gate::authorize('update',$session);
        DB::transaction(function () use ($session,$expired) {
            $session=Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (in_array($session->session_status,['completed','evaluated','cancelled','no_show'])) return;
            abort_if($session->isActive() && $expired,409);
            $assignedHelper=$session->helper;
            $pendingRecommendation=$assignedHelper && $session->session_status==='helper_assigned' && !$session->helper_accepted_at;
            $session->update(['session_status'=>'cancelled','completion_status'=>'cancelled','workflow_state'=>'closed',
                $expired?'expired_at':'cancelled_at'=>now(),'end_time'=>$session->isActive()?now():null]);
            $session->queue?->update(['request_status'=>$expired?'expired':'cancelled',$expired?'expired_at':'cancelled_at'=>now()]);
            if ($assignedHelper) { $assignedHelper->syncSessionCounters(); if (!$assignedHelper->activeSessions()->exists() && $assignedHelper->status==='busy') $assignedHelper->update(['status'=>'available']); }
            if ($pendingRecommendation && $assignedHelper->user) Notification::create(['user_account_id'=>$assignedHelper->user->id,'title'=>'Request withdrawn','message'=>'A recommendation was withdrawn before you could accept it. You may receive another match.','notification_type'=>'system','link'=>'/helper/cases']);
            SupportAudit::record($expired?'request_expired':'request_cancelled',$session);
            Notification::create(['user_account_id'=>$session->seeker->user_account_id,'title'=>$expired?'Request expired':'Request cancelled',
                'message'=>'Your request has closed. Its history is retained. You can start a new request.','notification_type'=>'system','link'=>'/seeker/requests']);
            DB::afterCommit(fn()=>$this->broadcastQueueUpdate());
        });
    }
    public function accept(User $user, Session $session): Session {
        abort_unless($user->role==='helper' && $user->is_active && (int)$session->helper_id===(int)$user->helper?->id,403);
        return DB::transaction(function () use ($user,$session) {
            $helper=Helper::whereKey($session->helper_id)->lockForUpdate()->firstOrFail();
            $session=Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$session->helper_id===(int)$helper->id,403);
            if ($session->helper_accepted_at && in_array($session->session_status,['helper_assigned','active'])) return $session;
            abort_unless($session->isHelperAssigned(),409);
            abort_if($session->pre_session_brief_expires_at?->isPast(),409,'This recommendation expired. Await a new match.');
            app(HelperEligibilityService::class)->requireEligible($helper,$session);
            abort_unless(app(ConsentService::class)->valid($session->seeker,'privacy_policy') && app(ConsentService::class)->valid($session->seeker,'informed_consent'),409,'Current seeker consent is required.');
            $session->update(['helper_accepted_at'=>now(),'match_status'=>'accepted','workflow_state'=>'session_ready']);
            $session->queue?->update(['helper_accepted_at'=>now(),'assigned_at'=>now()]);
            if ($helper->non_response_count > 0) {
                $helper->update(['non_response_count' => 0]);
            }
            SupportAudit::record('helper_accepted',$session);
            Notification::create(['user_account_id'=>$session->seeker->user_account_id,'title'=>'Helper accepted your request','message'=>'Your helper is preparing the session. Chat opens when the session starts.','notification_type'=>'session','link'=>'/request/matching']);
            return $session;
        });
    }
    public function start(User $user,Session $session): Session {
        abort_unless($user->role==='helper' && $user->is_active && (int)$session->helper_id===(int)$user->helper?->id,403);
        return DB::transaction(function()use($user,$session){
            $helper=Helper::whereKey($user->helper->id)->lockForUpdate()->firstOrFail();
            $session=Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$session->helper_id===(int)$helper->id,403);
            if($session->isActive() && $session->helper_accepted_at)return $session;
            abort_unless($session->isHelperAssigned() && $session->helper_accepted_at,409,'Accept this recommendation before starting.');
            app(HelperEligibilityService::class)->requireEligible($helper,$session);
            abort_unless(app(ConsentService::class)->valid($session->seeker,'privacy_policy') && app(ConsentService::class)->valid($session->seeker,'informed_consent'),409);
            $session->update(['session_status'=>'active','start_time'=>now(),'match_status'=>'assigned','workflow_state'=>'session_active']);
            $helper->update(['status'=>'busy']);$helper->syncSessionCounters();
            SupportAudit::record('session_started',$session);
            Notification::create(['user_account_id'=>$session->seeker->user_account_id,'title'=>'Your session has started','message'=>'Your helper is ready. You can open chat.','notification_type'=>'session','link'=>'/session/chat']);
            DB::afterCommit(fn()=>$this->broadcastSafely(new \App\Events\SessionUpdated($session,$session->seeker->user_account_id)));
            return $session;
        });
    }
    /**
     * Assign a screening review to the active adviser with the fewest
     * unresolved pending reviews, then the fewest handled helpers, then oldest.
     */
    private function pickReviewer(): ?Adviser {
        return Adviser::whereHas('user',fn($q)=>$q->where('is_active',true))
            ->withCount(['reviewSessions as pending_review_count'=>fn($q)=>$q->where('requires_adviser_review',true)
                ->whereDoesntHave('screeningResponses',fn($r)=>$r->where('review_status','reviewed'))])
            ->withCount('helpers as handled_count')
            ->orderBy('pending_review_count')->orderBy('handled_count')->orderBy('id')->first();
    }
    /** Push fresh queue stats to every active moderator channel. */
    protected function broadcastQueueUpdate(): void {
        foreach (User::where('role','moderator')->where('is_active',true)->pluck('id') as $moderatorId) {
            $this->broadcastSafely(new \App\Events\QueueUpdated($moderatorId));
        }
    }
}
