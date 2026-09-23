<?php
namespace App\Http\Controllers;
use App\Models\{Session, ScreeningResponse};
use App\Services\{SupportAudit, RiskClassificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class ScreeningReviewController extends Controller {
    public function index(Request $request) {
        abort_unless($request->user()->role==='adviser' && $request->user()->adviser,403);
        $sessions=Session::with(['seeker','helper','concern','report','screeningResponses'])->withCount('messages')
            ->where('review_adviser_id',$request->user()->adviser->id)
            ->where('requires_adviser_review',true)
            ->whereNot('session_status',Session::STATUS_CANCELLED)
            ->where(function ($query) {
                $query->where('workflow_state','adviser_review_required')
                    ->orWhereHas('report', fn ($r) => $r->whereNotNull('reassessment_requested_at')->whereNull('reassessment_reviewed_at'));
            })
            ->get();
        return view('adviser.screenings',compact('sessions'));
    }
    public function conversation(Request $request, Session $session): View {
        $adviser=$request->user()->adviser;
        abort_unless($request->user()->role==='adviser' && $request->user()->is_active && $adviser,403);
        abort_unless((int)$session->review_adviser_id===(int)$adviser->id || (int)$session->helper?->adviser_id===(int)$adviser->id,403);
        abort_unless(app(\App\Services\AdviserTranscriptAccess::class)->allowed($session),403,'Conversation access requires purpose, consent and current authorization.');
        $session->load(['seeker','helper','concern']);
        $messages=$session->messages()->orderBy('sent_datetime','asc')->orderBy('id','asc')->limit(500)->get();
        SupportAudit::record('screening_conversation_viewed',$session,['reviewer'=>$adviser->id,'purpose'=>'screening_review']);
        return view('adviser.screening-conversation',compact('session','messages'));
    }
    public function review(Request $request, Session $session) {
        abort_unless($request->user()->role==='adviser' && $request->user()->is_active && $request->user()->adviser
            && (int)$session->review_adviser_id===(int)$request->user()->adviser->id,403);
        $data=$request->validate(['risk_level'=>'required|in:low,moderate,high,emergency','reason'=>'required|string|max:1000','evidence_source'=>'required|string|max:255','allow_peer_support'=>'required|boolean']);
        $helperRequest=$session->report?->reassessment_requested_at && !$session->report?->reassessment_reviewed_at;
        if (($session->workflow_state ?? '')!=='adviser_review_required' && !$helperRequest) {
            return back()->with('error','This request is no longer awaiting a screening review, so the reassessment was not recorded.');
        }
        DB::transaction(function () use ($request,$session,$data) {
            $session=Session::lockForUpdate()->findOrFail($session->id);
            abort_unless((int)$session->review_adviser_id===(int)$request->user()->adviser->id,403);
            $helperRequest=$session->report?->reassessment_requested_at && !$session->report?->reassessment_reviewed_at;
            if($helperRequest) abort_unless((int)$session->helper?->adviser_id === (int)$request->user()->adviser->id,403);
            $original=$session->screeningResponses()->oldest('id')->first();
            $answers = $original?->responses ?? [];
            $rule = 'classification_confirmed';
            $version = $original?->instrument_version ?? 'existing-classification-review';
            if ($request->boolean('use_clarified_answers')) {
                abort_unless(!$original || $original->instrument_version === \App\Services\CompactScreening::VERSION,422,'Clarify the original instrument; do not replace it with a different questionnaire.');
                $rules = [];
                foreach (\App\Services\CompactScreening::rules() as $field=>$validation) $rules['answers.'.$field] = $validation;
                $answers = $request->validate($rules)['answers'];
                $result = app(\App\Services\CompactScreening::class)->classify($answers);
                abort_unless($result['risk_level'] === $data['risk_level'],422,'Selected classification does not match the clarified answers.');
                $rule = $result['rule_code'];
                $version = \App\Services\CompactScreening::VERSION;
            } else {
                abort_unless($data['risk_level'] === $session->risk_level,422,'A changed classification requires complete, documented clarification answers.');
            }
            abort_if($data['risk_level'] === 'emergency' && $data['allow_peer_support'],422,'Emergency routing cannot be approved for ordinary peer support.');
            abort_unless(mb_strlen(trim($data['reason'])) >= 10,422,'Provide a documented review justification.');
            $screening=ScreeningResponse::create(['seeker_id'=>$session->seeker_id,'session_id'=>$session->id,
                'responses'=>$answers, 'risk_level'=>$data['risk_level'],'priority'=>app(RiskClassificationService::class)->getQueuePriority($data['risk_level']),
                'action'=>'adviser_reassessment','reason'=>$data['reason'],'classified_at'=>now(),'classified_by'=>'adviser',
                'is_complete'=>true,'instrument_version'=>$version,'rule_code'=>$rule,
                'review_status'=>'reviewed','actor_id'=>$request->user()->id,'evidence_source'=>$data['evidence_source']]);
            $session->update(['risk_level'=>$data['risk_level'],'risk_updated_at'=>now(),'risk_update_reason'=>$data['reason'],'risk_updated_by'=>$request->user()->id]);
            if ($data['risk_level']==='high' && !\App\Models\Referral::where('session_id',$session->id)->exists()) {
                $referral=\App\Models\Referral::create(['session_id'=>$session->id,'helper_id'=>$session->helper_id,'adviser_id'=>$request->user()->adviser->id,'priority_level'=>'high','help_seeker_consent'=>false,'referral_reason'=>'Adviser reassessment requires professional referral review.','referral_date'=>now(),'status'=>'pending_adviser']);
                SupportAudit::record('referral_proposed',$referral);
            }
            SupportAudit::record('risk_reassessed',$screening,['original_screening_id'=>$original?->id,'evidence_source'=>$data['evidence_source']]);
            if ($helperRequest) {
                $session->report->update(['reassessment_reviewed_at'=>now()]);
                $session->update(['requires_adviser_review'=>!$data['allow_peer_support'],'peer_support_approved_at'=>$data['allow_peer_support']?now():null]);
                if ($data['risk_level']==='emergency' && !$session->isCompleted()) app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$session->seeker,['reason'=>$data['reason']]);
                return;
            }
            if ($data['risk_level']==='emergency') {
                app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$session->seeker,['screening_id'=>$screening->id,'rule_code'=>'adviser_reassessment','reason'=>$data['reason']]);
            } elseif ($data['allow_peer_support']) {
                $session->update(['requires_adviser_review'=>false,'peer_support_approved_at'=>now(),'session_status'=>'screening_completed','workflow_state'=>'concern_required']);
            }
        });
        return back()->with('success','Review recorded; original responses were retained.');
    }
}
