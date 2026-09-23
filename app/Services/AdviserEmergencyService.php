<?php
namespace App\Services;
use App\Models\{EmergencyAlert,Notification};
use Illuminate\Support\Facades\DB;
class AdviserEmergencyService {
    public function record(EmergencyAlert $alert,string $action,string $notes): void {
        DB::transaction(function() use($alert,$action,$notes) {
            $alert=EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
            app(AdviserScope::class)->emergency($alert);
            abort_unless(in_array($action,['acknowledged','instruction','coordination','resolved'],true) && trim($notes)!=='' && mb_strlen($notes)<=2000,422);
            abort_if(in_array($alert->status,['resolved','closed']),409,'This emergency review is already terminal.');
            if(!$alert->acknowledged_at) {
                $alert->forceFill(['acknowledged_at'=>now(),'acknowledged_by'=>auth()->id()])->save();
                $this->append($alert,'acknowledged',$action==='acknowledged' ? $notes : 'Acknowledged when recording the first Adviser action.');
            } elseif($action==='acknowledged') abort(409,'Already acknowledged.');
            if($action!=='acknowledged') $this->append($alert,$action,$notes);
            if($action==='resolved') $alert->update(['status'=>'resolved','resolved_at'=>now(),'resolution_notes'=>$notes]);
            $recipient=$alert->session?->helper?->user_account_id;
            if($recipient) Notification::create(['user_account_id'=>$recipient,'title'=>'Emergency review updated','message'=>'Your Adviser recorded an emergency review action. Review the authorized case record.','notification_type'=>'emergency','link'=>'/helper/cases/'.$alert->session_id]);
        },3);
    }
    private function append(EmergencyAlert $alert,string $action,string $notes): void {
        DB::table('emergency_review_actions')->insert(['emergency_alert_id'=>$alert->id,'actor_id'=>auth()->id(),'action'=>$action,'notes'=>$notes,'created_at'=>now()]);
        SupportAudit::record('emergency_'.$action,$alert,['purpose'=>'emergency_review']);
    }
}
