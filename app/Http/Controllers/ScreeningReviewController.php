<?php

namespace App\Http\Controllers;

use App\Models\ConcernCategory;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Referral;
use App\Models\ScreeningResponse;
use App\Models\Session;
use App\Services\AdviserTranscriptAccess;
use App\Services\CompactScreening;
use App\Services\EmergencyEscalationService;
use App\Services\RiskClassificationService;
use App\Services\ScreeningFollowUp;
use App\Services\SupportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScreeningReviewController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'adviser' && $request->user()->adviser, 403);
        $sessions = Session::with(['seeker', 'helper', 'concern', 'report', 'screeningResponses'])->withCount('messages')
            ->pendingScreeningReviewForAdviser((int) $request->user()->adviser->id)
            ->latest('created_date')->latest('id')->get();

        return view('adviser.screenings', compact('sessions'));
    }

    public function conversation(Request $request, Session $session): View
    {
        $adviser = $request->user()->adviser;
        abort_unless($request->user()->role === 'adviser' && $request->user()->is_active && $adviser, 403);
        abort_unless((int) $session->review_adviser_id === (int) $adviser->id || (int) $session->helper?->adviser_id === (int) $adviser->id, 403);
        abort_unless(app(AdviserTranscriptAccess::class)->allowed($session), 403, 'Conversation access requires purpose and current authorization.');
        $session->load(['seeker', 'helper', 'concern']);
        $messages = $session->messages()->orderBy('sent_datetime', 'asc')->orderBy('id', 'asc')->limit(500)->get();
        SupportAudit::record('screening_conversation_viewed', $session, ['reviewer' => $adviser->id, 'purpose' => 'screening_review']);

        return view('adviser.screening-conversation', compact('session', 'messages'));
    }

    public function review(Request $request, Session $session)
    {
        abort_unless($request->user()->role === 'adviser' && $request->user()->is_active && $request->user()->adviser
            && (int) $session->review_adviser_id === (int) $request->user()->adviser->id, 403);
        if ($request->input('review_action') === 'complete_intake') {
            $request->validate(['reason'=>'required|string|min:10|max:1000', 'answers'=>'prohibited', 'risk_level'=>'prohibited', 'clarified_safety_answer'=>'prohibited']);
            DB::transaction(function () use ($request, $session) {
                $case = Session::lockForUpdate()->findOrFail($session->id);
                abort_unless((int) $case->review_adviser_id === (int) $request->user()->adviser->id, 403);
                abort_unless($case->permitsParallelScreeningSupport() && !$case->report?->reassessment_requested_at, 409);
                if (!$case->requires_adviser_review) return;
                $original = $case->screeningResponses()->oldest('id')->firstOrFail();
                ScreeningResponse::create(['seeker_id'=>$case->seeker_id, 'session_id'=>$case->id,
                    'responses'=>$original->responses, 'risk_level'=>$case->risk_level, 'priority'=>$original->priority,
                    'action'=>'intake_review_completed', 'reason'=>$request->string('reason')->toString(), 'classified_at'=>now(),
                    'classified_by'=>'adviser', 'instrument_version'=>$original->instrument_version, 'rule_code'=>$original->rule_code,
                    'review_status'=>'reviewed', 'is_complete'=>true, 'actor_id'=>$request->user()->id, 'evidence_source'=>'original_intake_review']);
                $case->update(['requires_adviser_review'=>false]);
                SupportAudit::record('intake_review_completed', $case);
            });
            return back()->with('success', 'Screening review completed.');
        }
        $request->validate(['answers' => 'prohibited', 'clarified_safety_answer' => 'prohibited',
            'use_clarified_answers' => 'prohibited', 'use_clarified_safety_answer' => 'prohibited', 'review_action' => 'sometimes|required|in:approve,record',
            'use_seeker_follow_up' => 'nullable|boolean']);
        // Explicit buttons map to the existing outcome; older clients stay compatible.
        if ($request->has('review_action')) {
            $request->merge(['allow_peer_support' => $request->input('review_action') === 'approve']);
        }
        $storedFollowUp = $request->boolean('use_seeker_follow_up');
        if ($storedFollowUp) {
            $request->merge(['evidence_source' => 'seeker_follow_up']);
        }
        $data = $request->validate(['risk_level' => ($storedFollowUp ? 'nullable' : 'required').'|in:low,moderate,high,emergency', 'reason' => 'required|string|min:10|max:1000', 'evidence_source' => 'required|string|max:255', 'allow_peer_support' => 'required|boolean']);
        $helperRequest = $session->report?->reassessment_requested_at && ! $session->report?->reassessment_reviewed_at;
        if (!$helperRequest && $session->screeningResponses()->where('review_status', 'reviewed')->exists()) return back()->with('info', 'This screening has already been reviewed.');
        if (! in_array($session->workflow_state, ['adviser_review_required', 'emergency_escalated'], true) && ! $session->permitsEmergencySupport() && ! $session->permitsParallelScreeningSupport() && ! $helperRequest) {
            return back()->with('error', 'This request is no longer awaiting a screening review, so the reassessment was not recorded.');
        }
        DB::transaction(function () use ($request, $session, $data) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless((int) $session->review_adviser_id === (int) $request->user()->adviser->id, 403);
            $helperRequest = $session->report?->reassessment_requested_at && ! $session->report?->reassessment_reviewed_at;
            abort_unless(in_array($session->workflow_state, ['adviser_review_required', 'emergency_escalated'], true) || $session->permitsEmergencySupport() || $session->permitsParallelScreeningSupport() || $helperRequest, 409);
            abort_if(! $helperRequest && $session->screeningResponses()->where('review_status', 'reviewed')->exists(), 409, 'This screening has already been reviewed.');
            if ($helperRequest) {
                abort_unless((int) $session->helper?->adviser_id === (int) $request->user()->adviser->id, 403);
            }
            $original = $session->screeningResponses()->oldest('id')->first();
            $parallelSupport = $session->permitsParallelScreeningSupport();
            if (! $helperRequest && $original?->follow_up_requested_at && ! $request->boolean('use_seeker_follow_up')) {
                throw ValidationException::withMessages(['follow_up_answer' => 'Review the saved follow-up response before making a decision. Do not replace the Seeker answer.']);
            }
            $answers = $original?->responses ?? [];
            $rule = 'classification_confirmed';
            $version = $original?->instrument_version ?? 'existing-classification-review';
            if ($request->boolean('use_seeker_follow_up')) {
                if ($helperRequest || $original?->instrument_version !== CompactScreening::SINGLE_VERSION
                    || ($original->responses['suicidal_thoughts'] ?? null) !== 'prefer_not_to_say'
                    || ! $original->follow_up_requested_at || ! $original->follow_up_answered_at
                    || ! in_array($original->follow_up_answer, ['yes', 'no'], true)) {
                    throw ValidationException::withMessages(['follow_up_answer' => 'A Yes or No follow-up response from the Seeker is needed before approval. The request remains pending.']);
                }
                if ($request->boolean('use_clarified_answers') || $request->boolean('use_clarified_safety_answer')) {
                    throw ValidationException::withMessages(['follow_up_answer' => 'Use the saved Seeker response without supplying replacement answers.']);
                }
                if (! $data['allow_peer_support']) {
                    throw ValidationException::withMessages(['review_action' => 'Keep this follow-up pending until a final support decision can be recorded.']);
                }
                $answers = ['suicidal_thoughts' => $original->follow_up_answer];
                $result = app(CompactScreening::class)->classifySingle($answers);
                // Always derive classification from the saved response, never form input.
                $data['risk_level'] = $result['risk_level'];
                $rule = $result['rule_code'];
            } else {
                if ($data['risk_level'] !== $session->risk_level) {
                    throw ValidationException::withMessages(['risk_level' => $original?->instrument_version === CompactScreening::SINGLE_VERSION
                        ? 'Original Seeker answers are read-only. Use the saved Seeker follow-up response when available.'
                        : 'The screening review confirms the saved classification. Original answers cannot be replaced.']);
                }
            }
            if ($data['risk_level'] === 'emergency' && $data['allow_peer_support']) {
                throw ValidationException::withMessages(['allow_peer_support' => 'Emergency routing cannot be approved for ordinary peer support.']);
            }
            if (mb_strlen(trim($data['reason'])) < 10) {
                throw ValidationException::withMessages(['reason' => 'Provide a documented review justification of at least 10 characters.']);
            }
            $screening = ScreeningResponse::create(['seeker_id' => $session->seeker_id, 'session_id' => $session->id,
                'responses' => $answers, 'risk_level' => $data['risk_level'], 'priority' => app(RiskClassificationService::class)->getQueuePriority($data['risk_level']),
                'action' => 'adviser_reassessment', 'reason' => $data['reason'], 'classified_at' => now(), 'classified_by' => 'adviser',
                'is_complete' => true, 'instrument_version' => $version, 'rule_code' => $rule,
                'review_status' => 'reviewed', 'actor_id' => $request->user()->id, 'evidence_source' => $data['evidence_source']]);
            $session->update(['risk_level' => $data['risk_level'], 'risk_updated_at' => now(), 'risk_update_reason' => $data['reason'], 'risk_updated_by' => $request->user()->id]);
            if ($data['risk_level'] === 'high' && ! $session->permitsPrioritySafetySupport() && ! Referral::where('session_id', $session->id)->exists()) {
                $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $session->helper_id, 'adviser_id' => $request->user()->adviser->id, 'priority_level' => 'high', 'help_seeker_consent' => false, 'referral_reason' => 'Adviser reassessment requires professional referral review.', 'referral_date' => now(), 'status' => 'pending_adviser']);
                SupportAudit::record('referral_proposed', $referral);
            }
            SupportAudit::record('risk_reassessed', $screening, ['original_screening_id' => $original?->id, 'evidence_source' => $data['evidence_source']]);
            Notification::create(['user_account_id' => $session->seeker->user_account_id,
                'title' => 'Screening reviewed', 'message' => 'Your adviser has reviewed your screening. Open your request to see the next step.',
                'notification_type' => 'system', 'link' => '/seeker/requests']);
            if ($session->queue_request_id) {
                QueueRequest::whereKey($session->queue_request_id)
                    ->where('request_status', 'waiting')->update(['priority_level' => $session->queuePriorityLevel()]);
            }

            if ($helperRequest) {
                $session->report->update(['reassessment_reviewed_at' => now()]);
                $session->update(['requires_adviser_review' => ! $data['allow_peer_support'], 'peer_support_approved_at' => $data['allow_peer_support'] ? now() : null]);
                if ($data['risk_level'] === 'emergency' && ! $session->isCompleted()) {
                    app(EmergencyEscalationService::class)->escalateEmergency($session, $session->seeker, ['reason' => $data['reason']]);
                }

                return;
            }
            if ($parallelSupport && $data['risk_level'] !== 'emergency' && $session->submitted_at) {
                $session->update(['requires_adviser_review' => !$data['allow_peer_support'], 'peer_support_approved_at' => $data['allow_peer_support'] ? now() : null]);

                return;
            }
            if ($data['risk_level'] === 'emergency') {
                app(EmergencyEscalationService::class)->escalateEmergency($session, $session->seeker, ['screening_id' => $screening->id, 'rule_code' => 'adviser_reassessment', 'reason' => $data['reason']]);
            } elseif ($data['allow_peer_support']) {
                $concernAlreadySelected = $original?->instrument_version === CompactScreening::SINGLE_VERSION
                    && ConcernCategory::active()->whereKey($session->concern_id)->exists();
                $session->update(['requires_adviser_review' => false, 'peer_support_approved_at' => now(), 'session_status' => 'screening_completed',
                    'workflow_state' => $concernAlreadySelected ? 'session_preferences_required' : 'concern_required']);
            }
        });

        return back()->with('success', $data['allow_peer_support']
            ? 'Peer support approved. The Seeker can continue through the existing request steps and matching. Original responses were retained.'
            : 'Review recorded without peer-support approval; original responses were retained.');
    }

    public function followUp(Request $request, Session $session)
    {
        abort_unless($request->user()->role === 'adviser' && $request->user()->is_active && $request->user()->adviser
            && (int) $session->review_adviser_id === (int) $request->user()->adviser->id, 403);
        $data = $request->validate(['follow_up_note' => 'nullable|string|max:300']);
        $created = app(ScreeningFollowUp::class)->request($request->user(), $session, $data['follow_up_note'] ?? null);

        return redirect()->to(route('adviser.screenings').'#screening-'.$session->id)
            ->with('success', $created ? 'Follow-up sent. Peer matching can continue while you review.' : 'This follow-up was already sent. No duplicate notification was created.');
    }
}
