<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdviserNotificationController extends Controller
{
    /**
     * Notification center for the adviser workspace.
     */
    public function index(Request $request): View
    {
        $typeFilter = $request->get('type');

        $notifications = Notification::where('user_account_id', Auth::id())
            ->ofType($typeFilter)
            ->latest()
            ->limit(100)
            ->get();

        $unreadCount = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        $types = Notification::where('user_account_id', Auth::id())
            ->select('notification_type')
            ->distinct()
            ->whereNotNull('notification_type')
            ->pluck('notification_type');

        return view('adviser.notifications', compact('notifications', 'unreadCount', 'types', 'typeFilter'));
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
     * Unread count for the adviser sidebar badge (AJAX polling).
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Notification::where('user_account_id', Auth::id())
                ->unread()
                ->count(),
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->findOrFail($id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', 'Notification deleted.');
    }
}