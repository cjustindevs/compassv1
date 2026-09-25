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
        return view('request.screening',['concerns'=>ConcernCategory::all(),'hotlines'=>$hotlines,'selfHelp'=>$selfHelp]);
    }
    public function processScreening(Request $request) {
        Gate::authorize('seeker-workflow');
        if ($redirect = $this->requireConsentOrRedirect($request->user())) return $redirect;
        if ($request->has('immediate_intent')) {
            $answers=$request->validate(ScreeningInstrument::rules());
            return $this->next($this->workflow->screen($request->user(),$answers));
        }
        $data=$request->validate(\App\Services\CompactScreening::rules() + ['concern_id'=>'required|exists:concern_categories,id','description'=>'nullable|string|max:500','custom_concern'=>['nullable','string','max:255',Rule::requiredIf(function () use ($request) {
            $concern=ConcernCategory::find($request->input('concern_id'));
            return $concern && strtolower(trim($concern->concern_name)) === 'others';
        })]]);
        $answers=array_intersect_key($data,array_flip(\App\Services\CompactScreening::FIELDS));
        return $this->next($this->workflow->screen($request->user(),$answers,$data));
    }
    public function concern(Request $request) {
        app(ConsentService::class)->requireGeneral($request->user());
        $session=$this->workflow->current($request->user());
        if (!$session || $session->workflow_state!=='concern_required') return $session?$this->next($session):redirect()->route('request.screening');
        return view('request.concern',['concerns'=>ConcernCategory::whereIn('concern_name',['Stress','Family','Relationships','Academic','Health','Others'])->get(),'session'=>$session]);
    }
    public function processConcern(Request $request) {
        if ($redirect = $this->requireConsentOrRedirect($request->user())) return $redirect;
        $data=$request->validate(['concern_id'=>['required',\Illuminate\Validation\Rule::exists('concern_categories','id')->whereIn('concern_name',['Stress','Family','Relationships','Academic','Health','Others'])],'description'=>'nullable|string|max:500']);
        return $this->next($this->workflow->concern($request->user(),$data));
    }
    public function preferences(Request $request) {
        app(ConsentService::class)->requireGeneral($request->user());
        $session=$this->workflow->current($request->user());
        if (!$session || $session->workflow_state!=='session_preferences_required') return $session?$this->next($session):redirect()->route('request.screening');
        return view('request.preferences',['session'=>$session]);
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
        if (in_array($state,['queued','matching'])) {
            try {
                app(HelperMatchingService::class)->matchWaitingRequests();
                $session=$this->workflow->current($request->user());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('matching auto-match retry failed',['session_id'=>$session->id,'error'=>$e->getMessage()]);
            }
        }
        if (!$session) return redirect()->route('request.screening');

        if (in_array($session->workflow_state,['adviser_review_required','emergency_escalated'])) {
            return view('request.status',['session'=>$session,'events'=>\Illuminate\Support\Facades\DB::table('request_status_events')->where('session_id',$session->id)->orderBy('id')->get()]);
        }

        $resources=SelfHelpResource::published()->orderByDesc('is_featured')->orderByDesc('views_count')->limit(6)->get()->map(fn($r)=>[
            'link'=>route('selfhelp.show',$r->id),'title'=>$r->title,'description'=>$r->description,
            'icon'=>($r->icon ?: 'fa-book-open'),'duration'=>$r->duration ? $r->duration.' min' : 'Self-paced',
        ]);

        $availableHelperCount=app(HelperMatchingService::class)->countEligibleHelpers($session->risk_level ?? 'low');
        $matchingReason=null;
        if ($availableHelperCount === 0) {
            if (!config('app.relax_duty_hours', false) && !app(OperatingHoursService::class)->acceptsAssignments()) {
                $matchingReason='Peer-helper matching opens daily from 6:00 PM to 10:30 PM (Manila time). Your request stays safely in the queue and will be matched when the service reopens.';
            } elseif (!config('app.relax_duty_hours', false) && \App\Models\Helper::where('verification_status','!=','verified')->whereHas('user',fn($q)=>$q->where('is_active',true))->exists()) {
                $matchingReason='No verified peer helper is on duty right now. Helpers must complete adviser verification before they can accept requests, so this can take a little longer. Your place in the queue is saved.';
            } else {
                $matchingReason='No peer helper is currently available. Your place in the queue is saved and you will be notified as soon as one becomes available.';
            }
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
        $this->workflow->cancel($request->user(),$session); return redirect()->route('seeker.requests')->with('success','Request cancelled.');
    }
    public function history(Request $request) {
        Gate::authorize('seeker-workflow');
        return view('request.history',['requests'=>Session::where('seeker_id',$request->user()->helpSeeker->id)->latest('id')->get()]);
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
