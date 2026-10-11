<?php
namespace App\Http\Controllers;
use App\Models\{Session, ScreeningResponse};
use App\Services\{SupportAudit, RiskClassificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
class ScreeningReviewController extends Controller {
    public function index(Request $request) {
        abort_unless($request->user()->role==='adviser' && $request->user()->adviser,403);
        $sessions=Session::with(['seeker','helper','concern','report','screeningResponses'])->withCount('messages')
            ->pendingScreeningReviewForAdviser((int) $request->user()->adviser->id)
            ->get();
        return view('adviser.screenings',compact('sessions'));
    }
    public function conversation(Request $request, Session $session): View {
        $adviser=$request->user()->adviser;
        abort_unless($request->user()->role==='adviser' && $request->user()->is_active && $adviser,403);
        abort_unless((int)$session->review_adviser_id===(int)$adviser->id || (int)$session->helper?->adviser_id===(int)$adviser->id,403);
        abort_unless(app(\App\Services\AdviserTranscriptAccess::class)->allowed($session),403,'Conversation access requires purpose and current authorization.');
        $session->load(['seeker','helper','concern']);
        $messages=$session->messages()->orderBy('sent_datetime','asc')->orderBy('id','asc')->limit(500)->get();
        SupportAudit::record('screening_conversation_viewed',$session,['reviewer'=>$adviser->id,'purpose'=>'screening_review']);
        return view('adviser.screening-conversation',compact('session','messages'));
    }
    public function review(Request $request, Session $session) {
        abort_unless($request->user()->role==='adviser' && $request->user()->is_active && $request->user()->adviser
            && (int)$session->review_adviser_id===(int)$request->user()->adviser->id,403);
        $request->validate(['review_action'=>'sometimes|required|in:approve,record',
            'use_clarified_safety_answer'=>'nullable|boolean','use_clarified_answers'=>'nullable|boolean']);
        // Explicit buttons map to the existing outcome; older clients stay compatible.
        if ($request->has('review_action')) $request->merge(['allow_peer_support'=>$request->input('review_action') === 'approve']);
        $data=$request->validate(['risk_level'=>'required|in:low,moderate,high,emergency','reason'=>'required|string|min:10|max:1000','evidence_source'=>'required|string|max:255','allow_peer_support'=>'required|boolean']);
        $helperRequest=$session->report?->reassessment_requested_at && !$session->report?->reassessment_reviewed_at;
        if (!in_array($session->workflow_state,['adviser_review_required','emergency_escalated'],true) && !$session->permitsEmergencySupport() && !$helperRequest) {
            return back()->with('error','This request is no longer awaiting a screening review, so the reassessment was not recorded.');
        }
        DB::transaction(function () use ($request,$session,$data) {
            $session=Session::lockForUpdate()->findOrFail($session->id);
            abort_unless((int)$session->review_adviser_id===(int)$request->user()->adviser->id,403);
            $helperRequest=$session->report?->reassessment_requested_at && !$session->report?->reassessment_reviewed_at;
            abort_unless(in_array($session->workflow_state,['adviser_review_required','emergency_escalated'],true) || $session->permitsEmergencySupport() || $helperRequest,409);
            abort_if(!$helperRequest && $session->screeningResponses()->where('review_status','reviewed')->exists(),409,'This screening has already been reviewed.');
            if($helperRequest) abort_unless((int)$session->helper?->adviser_id === (int)$request->user()->adviser->id,403);
            $original=$session->screeningResponses()->oldest('id')->first();
            $answers = $original?->responses ?? [];
            $rule = 'classification_confirmed';
            $version = $original?->instrument_version ?? 'existing-classification-review';
            if ($request->boolean('use_clarified_answers')) {
                if ($request->boolean('use_clarified_safety_answer')) throw ValidationException::withMessages(['clarified_safety_answer'=>'Choose one clarification method for this review.']);
                if ($original && !in_array($original->instrument_version, [\App\Services\CompactScreening::VERSION, \App\Services\CompactScreening::FORM_VERSION, \App\Services\CompactScreening::SINGLE_VERSION], true)) {
                    throw ValidationException::withMessages(['use_clarified_answers'=>'Clarify the original instrument; do not replace it with a different questionnaire.']);
                }
                $rules = [];
                foreach (\App\Services\CompactScreening::rules() as $field=>$validation) $rules['answers.'.$field] = $validation;
                $answers = $request->validate($rules)['answers'];
                $result = app(\App\Services\CompactScreening::class)->classify($answers);
                if ($result['risk_level'] !== $data['risk_level']) throw ValidationException::withMessages(['risk_level'=>'Selected classification does not match the clarified answers.']);
                $rule = $result['rule_code'];
                $version = \App\Services\CompactScreening::VERSION;
            } elseif ($request->boolean('use_clarified_safety_answer')) {
                if ($original?->instrument_version !== \App\Services\CompactScreening::SINGLE_VERSION) {
                    throw ValidationException::withMessages(['clarified_safety_answer'=>'This clarification is only available for the single-question intake.']);
                }
                $clarification = $request->validate(['clarified_safety_answer'=>'required|in:yes,no']);
                $answers = ['suicidal_thoughts'=>$clarification['clarified_safety_answer']];
                $result = app(\App\Services\CompactScreening::class)->classifySingle($answers);
                if ($result['risk_level'] !== $data['risk_level']) throw ValidationException::withMessages(['risk_level'=>'Select the classification shown for the clarified safety answer.']);
                $rule = $result['rule_code'];
            } else {
                if ($data['risk_level'] !== $session->risk_level) {
                    throw ValidationException::withMessages(['risk_level'=>$original?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION
                        ? 'Clarify the original safety answer before assigning or changing its classification. Unasked questions cannot be assumed.'
                        : 'A changed classification requires complete, documented clarification answers.']);
                }
            }
            if ($data['risk_level'] === 'emergency' && $data['allow_peer_support']) throw ValidationException::withMessages(['allow_peer_support'=>'Emergency routing cannot be approved for ordinary peer support.']);
            if (mb_strlen(trim($data['reason'])) < 10) throw ValidationException::withMessages(['reason'=>'Provide a documented review justification of at least 10 characters.']);
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
            \App\Models\Notification::create(['user_account_id'=>$session->seeker->user_account_id,
                'title'=>'Screening reviewed','message'=>'Your adviser has reviewed your screening. Open your request to see the next step.',
                'notification_type'=>'system','link'=>'/seeker/requests']);
            if ($session->queue_request_id) \App\Models\QueueRequest::whereKey($session->queue_request_id)
                ->where('request_status','waiting')->update(['priority_level'=>$data['risk_level']]);

            if ($helperRequest) {
                $session->report->update(['reassessment_reviewed_at'=>now()]);
                $session->update(['requires_adviser_review'=>!$data['allow_peer_support'],'peer_support_approved_at'=>$data['allow_peer_support']?now():null]);
                if ($data['risk_level']==='emergency' && !$session->isCompleted()) app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$session->seeker,['reason'=>$data['reason']]);
                return;
            }
            if ($data['risk_level']==='emergency') {
                app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$session->seeker,['screening_id'=>$screening->id,'rule_code'=>'adviser_reassessment','reason'=>$data['reason']]);
            } elseif ($data['allow_peer_support']) {
                $concernAlreadySelected = $original?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION
                    && \App\Models\ConcernCategory::active()->whereKey($session->concern_id)->exists();
                $session->update(['requires_adviser_review'=>false,'peer_support_approved_at'=>now(),'session_status'=>'screening_completed',
                    'workflow_state'=>$concernAlreadySelected ? 'session_preferences_required' : 'concern_required']);
            }
        });
        return back()->with('success',$data['allow_peer_support']
            ? 'Peer support approved. The Seeker can continue through the existing request steps and matching. Original responses were retained.'
            : 'Review recorded without peer-support approval; original responses were retained.');
    }
}
