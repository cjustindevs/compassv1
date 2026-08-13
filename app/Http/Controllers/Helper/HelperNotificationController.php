<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperNotificationController extends Controller
{
    /**
     * Show the logged-in user's notifications from the database.
     */
    public function index()
    {
        $user = Auth::user();

        $notifications = Notification::where('user_account_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = Notification::where('user_account_id', $user->id)
            ->where('status', 'unread')
            ->count();

        return view('helper.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id)
    {
        $notification = Notification::where('user_account_id', Auth::id())
            ->findOrFail($id);

        $notification->markAsRead();

        return redirect()->to($notification->link ?: route('helper.notifications'))
            ->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_account_id', Auth::id())
            ->where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Unread count for Ajax badge polling.
     */
    public function unreadCount()
    {
        $count = Notification::where('user_account_id', Auth::id())
            ->where('status', 'unread')
            ->count();

        return response()->json(['count' => $count]);
    }
}