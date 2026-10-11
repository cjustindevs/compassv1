<?php
namespace App\Http\Controllers;
use App\Models\{ConcernCategory, EmergencyResource, SelfHelpResource, Session};
use App\Services\{ConsentService, HelperMatchingService, HelperWorkflowMaintenance, OperatingHoursService, ScreeningInstrument, SeekerWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
class RequestSupportController extends Controller {
    public function __construct(private SeekerWorkflowService $workflow) {}
    public function screening(Request $request) {
        Gate::authorize('seeker-workflow');
        $consents=app(ConsentService::class);
        // Required consent opens on this page; POST endpoints still enforce it.
        if ($session=$this->workflow->current($request->user())) return $this->next($session);
        $hotlines=EmergencyResource::published()->get();
        $selfHelp=SelfHelpResource::published()->orderByDesc('is_featured')->orderByDesc('views_count')->limit(4)->get();
        return view('request.screening',['concerns'=>ConcernCategory::active()->orderBy('concern_name')->get(),'hotlines'=>$hotlines,'selfHelp'=>$selfHelp,
            'draft'=>SeekerRequestDraftController::load($request->user(),'screening')]);
    }
    public function processScreening(Request $request) {
        Gate::authorize('seeker-workflow');
        if ($redirect = $this->requireConsentOrRedirect($request->user())) return $redirect;
        if ($request->has('immediate_intent') && !$request->has('screening_form_version')) {
            $answers=$request->validate(ScreeningInstrument::rules());
            return $this->next($this->workflow->screen($request->user(),$answers));
        }
        // The explicit form has categorical answers. Legacy complete boolean
        // clients remain supported, but the old ambiguous one-question form is rejected.
        $request->validate(['safety_check'=>'prohibited', 'screening_form_version'=>['sometimes', Rule::in([\App\Services\CompactScreening::FORM_VERSION])]]);
        $rules = $request->has('screening_form_version') ? \App\Services\CompactScreening::formRules() : \App\Services\CompactScreening::rules();
        $data=$request->validate($rules + ['screening_form_version'=>'sometimes|string','concern_id'=>['required',Rule::exists('concern_categories','id')->where('is_active',true)],'description'=>'nullable|string|max:500','custom_concern'=>['nullable','string','max:255',Rule::requiredIf(function () use ($request) {
            $concern=ConcernCategory::find($request->input('concern_id'));
            return $concern && in_array(strtolower(trim($concern->concern_name)), ['other','others'], true);
        })]]);
        $answers=array_intersect_key($data,array_flip(\App\Services\CompactScreening::FIELDS));
        return $this->next($this->workflow->screen($request->user(),$answers,$data));
    }
    public function concern(Request $request) {
        app(ConsentService::class)->requireGeneral($request->user());
        $session=$this->workflow->current($request->user());
        if (!$session || $session->workflow_state!=='concern_required') return $session?$this->next($session):redirect()->route('request.screening');
        return view('request.concern',['concerns'=>ConcernCategory::active()->get(),'session'=>$session]);
    }
    public function processConcern(Request $request) {
        if ($redirect = $this->requireConsentOrRedirect($request->user())) return $redirect;
        $data=$request->validate(['concern_id'=>['required',\Illuminate\Validation\Rule::exists('concern_categories','id')->where('is_active',true)],'description'=>['nullable','string','max:500',Rule::requiredIf(fn()=>in_array(strtolower(trim(ConcernCategory::find($request->input('concern_id'))?->concern_name ?? '')),['other','others'],true))]]);
        return $this->next($this->workflow->concern($request->user(),$data));
    }
    public function preferences(Request $request) {
        app(ConsentService::class)->requireGeneral($request->user());
        $session=$this->workflow->current($request->user());
        if (!$session || $session->workflow_state!=='session_preferences_required') return $session?$this->next($session):redirect()->route('request.screening');
        return view('request.preferences',['session'=>$session->load('concern'),
            'screening'=>$session->screeningResponses()->where('evidence_source','seeker_responses')->oldest('id')->first(),
            'draft'=>SeekerRequestDraftController::load($request->user(),'preferences',$session->id)]);
    }
    public function processPreferences(Request $request) {
        if ($redirect = $this->requireConsentOrRedirect($request->user())) return $redirect;
        $data=$request->validate(['support_mode'=>'required|in:chat','preferred_language'=>'required|in:English,Tagalog,English/Tagalog']);
        return $this->next($this->workflow->submit($request->user(),$data));
    }
    public function matching(Request $request) {
        Gate::authorize('seeker-workflow'); $session=$this->workflow->current($request->user());
        if (!$session) return redirect()->route('request.screening');
        $state=$session->workflow_state ?? match ($session->session_status) {
            Session::STATUS_ACTIVE => 'session_active',
            default => 'queued',
        };
        if (!in_array($state,['session_ready','queued','matching','helper_pending_acceptance','session_active','adviser_review_required','emergency_escalated'])) return $this->next($session);

        // Automated matching: retry the queue every time the seeker opens this
        // page, so waiting requests get a helper without a running scheduler.
        if (in_array($state,['queued','matching','emergency_escalated'])) {
            try {
                app(HelperMatchingService::class)->matchWaitingRequests();
                $session=$this->workflow->current($request->user());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('matching auto-match retry failed',['session_id'=>$session->id,'error'=>$e->getMessage()]);
            }
        }
        if (!$session) return redirect()->route('request.screening');

        if ($session->permitsEmergencySupport() || in_array($session->workflow_state,['adviser_review_required','emergency_escalated'])
            || ($session->isActive() && (!$session->helper_accepted_at || !$session->helper))) {
            return view('request.status',['session'=>$session,'events'=>\Illuminate\Support\Facades\DB::table('request_status_events')->where('session_id',$session->id)->orderBy('id')->get()]);
        }

        $resources=SelfHelpResource::published()->orderByDesc('is_featured')->orderByDesc('views_count')->limit(3)->get()->map(fn($r)=>[
            'link'=>route('selfhelp.show',$r->id),'title'=>$r->title,'description'=>$r->description,
            'icon'=>($r->icon ?: 'fa-book-open'),'duration'=>\App\Services\SeekerRequestPresentation::duration($r->duration),
        ]);

        $availableHelperCount=app(HelperMatchingService::class)->countEligibleHelpers($session->risk_level ?? 'low');
        $matchingReason = null;
        if (!app(ConsentService::class)->isFull($request->user()->helpSeeker)) {
            $matchingReason = 'Review the current consent documents in Privacy and consent to continue matching.';
        } elseif (!config('app.relax_duty_hours',false) && !app(OperatingHoursService::class)->acceptsAssignments()) {
            $matchingReason = 'Matching is outside operating hours. '.app(OperatingHoursService::class)->message();
        } elseif ($availableHelperCount === 0) {
            $matchingReason = 'Waiting for a ready helper on duty with free session capacity for this request. Your place in the queue is saved.';
        }
        if ($session->queue?->request_status === 'waiting') {
            $session->queue->queue_position = app(\App\Services\QueueManagementService::class)->position($session->queue);
        }

        return view('request.matching',[
            'session'=>$session,
            'resources'=>$resources,
            'availableHelper'=>$session->helper,
            'availableHelperCount'=>$availableHelperCount,
            'matchingReason'=>$matchingReason,
            'currentQueueRequest'=>$session->queue,
        ]);
    }
    public function cancel(Request $request, Session $session) {
        abort(403, 'Submitted support requests cannot be cancelled. Contact your support team for assistance.');
    }
    public function history(Request $request) {
        Gate::authorize('seeker-workflow');
        $filters=$request->validate(['q'=>'nullable|string|max:80','status'=>'nullable|in:waiting,active,completed,feedback_pending,review,emergency,cancelled,no_show,expired',
            'from'=>'nullable|required_with:to|date_format:Y-m-d','to'=>'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from','page'=>'nullable|integer|min:1']);
        $query=Session::with(['concern','evaluation'])->where('seeker_id',$request->user()->helpSeeker->id);
        if ($q=trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($q) {
                if (preg_match('/^(?:R-|#)?0*(\d+)$/i', $q, $match)) $query->whereKey((int)$match[1]);
                else $query->whereHas('concern',fn($concern)=>$concern->where('concern_name','like','%'.$q.'%'));
            });
        }
        $status=$filters['status'] ?? null;
        if ($status === 'expired') $query->whereNotNull('expired_at');
        elseif ($status === 'feedback_pending') $query->where('session_status','completed')->whereDoesntHave('evaluation');
        elseif ($status === 'completed') $query->whereIn('session_status',['completed','evaluated']);
        elseif ($status === 'waiting') $query->whereIn('session_status',['waiting','preferences_set','helper_assigned'])->whereNull('expired_at');
        elseif ($status === 'review') $query->where('workflow_state','adviser_review_required')->whereNotIn('session_status',['completed','evaluated','cancelled','no_show']);
        elseif ($status === 'emergency') $query->where('workflow_state','emergency_escalated')->whereNotIn('session_status',['completed','evaluated','cancelled','no_show']);
        elseif ($status) $query->where('session_status',$status)->whereNull('expired_at');
        foreach (['from','to'] as $bound) if (!empty($filters[$bound])) {
            $date=\Illuminate\Support\Carbon::createFromFormat('!Y-m-d',$filters[$bound],'Asia/Manila');
            $date=$bound==='from' ? $date->startOfDay() : $date->endOfDay();
            $query->whereRaw('COALESCE(submitted_at, created_date, created_at) '.($bound==='from'?'>=':'<=').' ?',[$date->utc()]);
        }
        return view('request.history',['requests'=>$query->latest('id')->paginate(15)->withQueryString(),
            'activeRequest'=>$this->workflow->current($request->user())]);
    }
    public function show(Request $request, Session $session) {
        Gate::authorize('seeker-workflow');
        Gate::authorize('view',$session);
        $session->load(['concern','helper','evaluation','queue']);
        $queuePosition=$session->queue?->request_status === 'waiting' ? app(\App\Services\QueueManagementService::class)->position($session->queue) : null;
        $screening=$session->screeningResponses()->where('evidence_source','seeker_responses')->oldest('id')->first();
        $narrative=$session->screeningResponses()->where('evidence_source','concern_description')->oldest('id')->first();
        // Only public state labels and timestamps; no staff notes, actors, or identity data.
        $events=\Illuminate\Support\Facades\DB::table('request_status_events')->where('session_id',$session->id)
            ->whereIn('to_state',array_keys(\App\Services\SeekerRequestPresentation::STATES))->orderBy('id')->get(['to_state','occurred_at']);
        return view('request.details',['session'=>$session,'events'=>$events,'queuePosition'=>$queuePosition,'screening'=>$screening,'narrative'=>$narrative,'activeRequest'=>$this->workflow->current($request->user())]);
    }
    public function declineHelper(Request $request) {
        Gate::authorize('seeker-workflow'); $session=$this->workflow->current($request->user());
        if (!$session) return redirect()->route('request.screening');
        if (!$session->isHelperAssigned()) return redirect()->route('request.matching');
        app(HelperWorkflowMaintenance::class)->releaseRecommendation($session,'declined_by_seeker');
        return redirect()->route('request.matching')->with('success','Helper declined. You stay in the queue and another available helper will be matched.');
    }
    public function voiceConsent() { abort(503,'Voice calls, recording and automatic transcription are unavailable. Please use chat.'); }
    public function processVoiceConsent() { return $this->voiceConsent(); }
    public function declineVoiceConsent() { return redirect()->route('request.matching'); }
    private function requireConsentOrRedirect(\App\Models\User $user): ?RedirectResponse {
        if ($user->helpSeeker && app(ConsentService::class)->isFull($user->helpSeeker)) return null;
        session(['open_consent' => true]);
        return redirect()->route('request.screening')->with('info', 'Please review and accept the current Terms and Condition and Privacy Notice before requesting support.');
    }
    private function next(Session $session) {
        return redirect()->route(match ($session->workflow_state) {
            'concern_required'=>'request.concern','session_preferences_required'=>'request.preferences','session_active'=>'session.chat',default=>'request.matching',
        });
    }
}
