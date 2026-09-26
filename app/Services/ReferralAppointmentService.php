<?php
namespace App\Services;
use App\Models\{Notification, PsychologyProfessional, Referral, ReferralAppointment};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Carbon;
class ReferralAppointmentService {
    public function schedule(Referral $referral, array $data): ReferralAppointment {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'professional',403);
        $professionalId = Auth::user()->psychologyProfessional?->id;
        abort_unless($professionalId && $referral->professional_id === $professionalId,403);
        return DB::transaction(function () use ($referral,$data,$professionalId) {
            // Serialize all calendar changes for this professional, including different referrals.
            PsychologyProfessional::whereKey($professionalId)->lockForUpdate()->firstOrFail();
            $referral = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();
            abort_unless($referral->professional_id === $professionalId && $referral->approved_at && $referral->help_seeker_consent && in_array($referral->status,['accepted','in_progress'],true),409);
            abort_unless(app(ConsentService::class)->valid($referral->session->seeker,'referral',$referral->id),409,'Current referral consent is required.');
            $start = Carbon::parse($data['starts_at'],'Asia/Manila')->utc();
            $end = Carbon::parse($data['ends_at'],'Asia/Manila')->utc();
            abort_unless($start->isFuture() && $end->greaterThan($start),422,'Choose a future start and an end after the start.');
            $current = ReferralAppointment::where('referral_id',$referral->id)->where('status','scheduled')->lockForUpdate()->first();
            abort_if($current && $current->starts_at->isPast(),409,'A started appointment cannot be rescheduled.');
            abort_if(ReferralAppointment::where('professional_id',$professionalId)->where('status','scheduled')
                ->where('referral_id','!=',$referral->id)->where('starts_at','<',$end)->where('ends_at','>',$start)->exists(),422,'This time overlaps another appointment.');
            if ($current) $current->update(['status'=>'rescheduled']);
            $appointment = ReferralAppointment::create(['referral_id'=>$referral->id,'professional_id'=>$professionalId,'created_by'=>Auth::id(),
                'starts_at'=>$start,'ends_at'=>$end,'meeting_details'=>$data['meeting_details'],'replaces_id'=>$current?->id]);
            SupportAudit::record('referral_appointment_scheduled',$appointment,['referral_id'=>$referral->id,'replaces_id'=>$current?->id]);
            // The helper raised the referral and coordinates with the seeker, so
            // they must be told when a professional books or moves a session.
            $seekerId = $referral->session->seeker->user_account_id;
            $helperId = $referral->session->helper?->user_account_id;
            $links = [
                $seekerId => '/seeker/referrals?appointment='.$appointment->id,
                $helperId => '/helper/chat/'.$referral->session_id,
                $referral->adviser?->user_account_id => '/adviser/referral/'.$referral->id,
                Auth::id() => '/professional/referral/'.$referral->id,
            ];
            foreach ($links as $recipient => $link) {
                if (! $recipient) continue;
                Notification::create(['user_account_id'=>$recipient,'title'=>'Referral appointment updated','message'=>'Open the referral to review the appointment details.','notification_type'=>'referral','type_icon'=>'fa-calendar','link'=>$link]);
            }
            return $appointment;
        },3);
    }
}
