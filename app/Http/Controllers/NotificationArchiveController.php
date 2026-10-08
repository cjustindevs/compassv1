<?php
namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;
class NotificationArchiveController extends Controller {
 public function index(Request $request){abort_unless($request->user()?->is_active,403);$notifications=Notification::withoutGlobalScope('unarchived')->where('user_account_id',$request->user()->id)->whereNotNull('archived_at')->latest('archived_at')->paginate(15);$user_preference=$request->user()->auto_archive_read_days;return view('notifications.archive',compact('notifications','user_preference'));}

 public function restore(Request $request, int $id){
     $notification=Notification::withoutGlobalScope('unarchived')->where('user_account_id',$request->user()->id)->findOrFail($id);
     $notification->restoreToInbox();
     return back()->with('success','Notification restored to your inbox.');
 }

 public function updateAutoArchive(Request $request){
     $validated=$request->validate(['auto_archive_read_days'=>'nullable|integer|in:7,30,90,0']);
     $request->user()->update(['auto_archive_read_days'=>$validated['auto_archive_read_days'] ? intval($validated['auto_archive_read_days']) : null]);
     return back()->with('success','Notification archive preference saved.');
 }
}