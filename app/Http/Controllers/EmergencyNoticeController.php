<?php
namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
class EmergencyNoticeController extends Controller {
 private function authorizeStaff():void {abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role,['adviser','moderator'],true),403);}
 public function next(){ $this->authorizeStaff();$notice=Notification::where('user_account_id',auth()->id())->where('notification_type','emergency')->whereNull('popup_dismissed_at')->latest('id')->first();return response()->json(['notice'=>$notice ? ['reference'=>Crypt::encryptString((string)$notice->id),'title'=>'Emergency requires attention','message'=>'Open your emergency queue to review the case and coordinate support.','url'=>auth()->user()->role==='adviser'?route('adviser.emergencies'):route('moderator.emergency')] : null]); }
 public function dismiss(Request $request){$this->authorizeStaff();$data=$request->validate(['reference'=>'required|string|max:2000']);try{$id=Crypt::decryptString($data['reference']);}catch(\Throwable $e){abort(404);} $notice=Notification::where('user_account_id',auth()->id())->where('notification_type','emergency')->findOrFail($id);$notice->forceFill(['popup_dismissed_at'=>now()])->save();return response()->noContent();}
}
