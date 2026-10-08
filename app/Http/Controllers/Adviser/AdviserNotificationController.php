<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdviserNotificationController extends Controller
{
    /**
     * Notification center for the adviser workspace.
     */
    public function index(Request $request): View
    {
        $request->validate(['type'=>'nullable|string|max:60', 'history'=>'nullable|in:inbox,archived']);
        $typeFilter = $request->get('type');
        $history = $request->input('history','inbox');
        $query = Notification::where('user_account_id', Auth::id());
        if ($history==='archived') $query->withoutGlobalScope('unarchived')->whereNotNull('archived_at');

        $notifications = $query
            ->ofType($typeFilter)
            ->latest()
            ->paginate(15)->withQueryString();

        $unreadCount = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        $types = Notification::withoutGlobalScope('unarchived')->where('user_account_id', Auth::id())
            ->select('notification_type')
            ->distinct()
            ->whereNotNull('notification_type')
            ->pluck('notification_type');

        return view('adviser.notifications', compact('notifications', 'unreadCount', 'types', 'typeFilter', 'history'));
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $notification = Notification::where('user_account_id', Auth::id())
            ->findOrFail($id);

        $notification->markAsRead();
        Cache::forget('unread_count_'.Auth::id());

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

        Cache::forget('unread_count_'.Auth::id());

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
        $count = Cache::remember('unread_count_' . auth()->id(), 30, function () {
            return Notification::where('user_account_id', Auth::id())
                ->unread()
                ->count();
        });

        return response()->json(['count' => $count]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->findOrFail($id)
            ->archive();

        if ($request->expectsJson()) {
            return response()->json(['archived' => true]);
        }

        return back()->with('success', 'Notification archived.');
    }
    public function restore(int $id): RedirectResponse
    {
        Notification::withoutGlobalScope('unarchived')->where('user_account_id', Auth::id())
            ->whereNotNull('archived_at')->findOrFail($id)->restoreToInbox();
        return back()->with('success', 'Notification restored to the inbox.');
    }

}