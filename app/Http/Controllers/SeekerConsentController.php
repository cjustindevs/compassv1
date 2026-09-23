<?php
namespace App\Http\Controllers;
use App\Models\{ConsentRecord, Referral, Session};
use App\Services\{ConsentService, SeekerWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
class SeekerConsentController extends Controller {
    public function index(Request $request) {
        Gate::authorize('seeker-workflow');
        return view('seeker.privacy',['records'=>ConsentRecord::where('seeker_id',$request->user()->helpSeeker->id)->latest('id')->get()]);
    }
    public function decision(Request $request) {
        Gate::authorize('seeker-workflow');
        $data=$request->validate(['purpose'=>'required|in:privacy_policy,informed_consent,referral','decision'=>'required|in:declined,withdrawn','referral_id'=>'nullable|integer']);
        DB::transaction(function () use($request,$data) {
            if ($data['purpose']==='referral') {
                $referral=Referral::lockForUpdate()->findOrFail($data['referral_id'] ?? 0); Gate::authorize('update',$referral);
                app(ConsentService::class)->decide($request->user(),'referral',$data['decision'],$referral->session_id,$referral->id);
                $referral->update(['help_seeker_consent'=>false,'status'=>'closed','closed_date'=>now()]);
            } else {
                app(ConsentService::class)->decide($request->user(),$data['purpose'],$data['decision']);
                Session::where('seeker_id',$request->user()->helpSeeker->id)->whereNotIn('session_status',['completed','evaluated','cancelled','no_show'])->get()
                    ->each(fn($s)=>app(SeekerWorkflowService::class)->cancel($request->user(),$s));
            }
        });
        return redirect()->route('seeker.privacy')->with('success','Your decision was recorded. Past records are retained for authorized safety, referral and institutional recordkeeping; withdrawal does not erase past records.');
    }
    public function referrals(Request $request) {
        Gate::authorize('seeker-workflow');
        return view('seeker.referrals',['referrals'=>Referral::whereHas('session',fn($q)=>$q->where('seeker_id',$request->user()->helpSeeker->id))->latest('id')->get()]);
    }
}
