<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HelperNotificationController extends Controller
{
    /**
     * Notification center for the helper workspace.
     */
    public function index(): View
    {
        $notifications = Notification::where('user_account_id', Auth::id())
            ->latest()
            ->paginate(25);

        $unreadCount = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        return view('helper.notifications', compact('notifications', 'unreadCount'));
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $notification = Notification::where('user_account_id', Auth::id())
            ->findOrFail($id);

        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['read' => true]);
        }

        return $notification->link
            ? redirect($notification->link)
            : back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->unread()
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        if (request()->expectsJson()) {
            return response()->json(['read_all' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Unread count for the helper sidebar badge (AJAX polling).
     */
    public function unreadCount(): JsonResponse
    {
        $count = Cache::remember('unread_count_' . auth()->id(), 30, function () {
            return Notification::where('user_account_id', Auth::id())
                ->unread()
                ->count();
        });

        return response()->json(['count' => $count]);
    }
}