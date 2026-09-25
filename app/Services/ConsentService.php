<?php
namespace App\Services;
use App\Models\{ConsentRecord, HelpSeeker, Session, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
class ConsentService {
    public const VERSION = 'compass-v4-2026-09';
    public function valid(HelpSeeker $seeker, string $purpose, ?int $referralId = null): bool {
        $query = ConsentRecord::where('seeker_id',$seeker->id)
            ->where(fn($q)=>$q->where('purpose',$purpose)->orWhere(fn($q)=>$q->whereNull('purpose')->where('document_type',$purpose)));
        if ($referralId !== null) $query->where('referral_id',$referralId);
        $last = $query->latest('id')->first();
        return $last && $last->version === self::VERSION && $last->consent_given && !$last->withdrawn && $last->decision !== 'withdrawn';
    }
    public function requireGeneral(User $user): void {
        Gate::authorize('seeker-workflow');
        abort_unless($user->helpSeeker && $this->valid($user->helpSeeker,'privacy_policy') && $this->valid($user->helpSeeker,'informed_consent'), 409, 'Please review and accept the current consent documents first.');
    }
    public function isFull(HelpSeeker $seeker): bool {
        return $this->valid($seeker, 'privacy_policy') && $this->valid($seeker, 'informed_consent');
    }
    public function decide(User $user, string $purpose, string $decision, ?int $sessionId = null, ?int $referralId = null, ?string $scope = null): ConsentRecord {
        Gate::authorize('seeker-workflow');
        abort_unless(in_array($purpose,['privacy_policy','informed_consent','referral','identity_disclosure','voice_participation','voice_recording','transcription'],true),422);
        abort_unless(in_array($decision,['accepted','declined','withdrawn'],true),422);
        if ($sessionId) Gate::authorize('view',Session::findOrFail($sessionId));
        if ($referralId) Gate::authorize('view',\App\Models\Referral::findOrFail($referralId));
        return DB::transaction(function () use ($user,$purpose,$decision,$sessionId,$referralId,$scope) {
            $seeker = HelpSeeker::whereKey($user->helpSeeker->id)->lockForUpdate()->firstOrFail();
            $record = ConsentRecord::create(['seeker_id'=>$seeker->id,
                'document_type'=>in_array($purpose,['privacy_policy','informed_consent','voice_recording']) ? $purpose : 'informed_consent',
                'purpose'=>$purpose,'scope'=>$scope,'version'=>self::VERSION,'decision'=>$decision,'consent_given'=>$decision==='accepted',
                'consent_date'=>now(),'withdrawn'=>$decision==='withdrawn','withdrawn_date'=>$decision==='withdrawn'?now():null,
                'session_id'=>$sessionId,'referral_id'=>$referralId,'ip_address'=>request()->ip(),'user_agent'=>substr((string)request()->userAgent(),0,1000)]);
            if ($purpose === 'referral' && $referralId && $decision === 'withdrawn') {
                $referral = \App\Models\Referral::lockForUpdate()->findOrFail($referralId);
                $referral->update(['help_seeker_consent'=>false,'status'=>\App\Models\Referral::STATUS_CLOSED,'closed_date'=>now(),'closure_notes'=>'Help Seeker withdrew referral consent.']);
                app(SupervisionVersions::class)->record($referral,'Help Seeker withdrew referral authorization');
                if ($referral->adviser?->user_account_id) \App\Models\Notification::create(['user_account_id'=>$referral->adviser->user_account_id,'title'=>'Referral consent withdrawn','message'=>'A referral authorization was withdrawn. Further professional access is blocked.','notification_type'=>'referral','link'=>'/adviser/referral/'.$referral->id]);
            }
            SupportAudit::record('consent_'.$decision,$record,['purpose'=>$purpose,'scope'=>$scope,'version'=>self::VERSION]);
            return $record;
        });
    }
}
