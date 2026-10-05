<?php
namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;
class NotificationArchiveController extends Controller {
 public function index(Request $request){abort_unless($request->user()?->is_active,403);$notifications=Notification::withoutGlobalScope('unarchived')->where('user_account_id',$request->user()->id)->whereNotNull('archived_at')->latest('archived_at')->paginate(15);return view('notifications.archive',compact('notifications'));}
}
