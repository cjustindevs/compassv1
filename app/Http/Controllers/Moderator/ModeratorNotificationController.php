<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ModeratorNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $typeFilter = $request->get('type');

        $notifications = Notification::where('user_account_id', Auth::id())
            ->ofType($typeFilter)
            ->latest()
            ->paginate(25);

        $unreadCount = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        $types = Notification::where('user_account_id', Auth::id())
            ->select('notification_type')
            ->distinct()
            ->whereNotNull('notification_type')
            ->pluck('notification_type');

        return view('moderator.notifications', compact('notifications', 'unreadCount', 'types', 'typeFilter'));
    }

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

    public function unreadCount(): JsonResponse
    {
        $count = Cache::remember('unread_count_' . auth()->id(), 30, function () {
            return Notification::where('user_account_id', Auth::id())
                ->unread()
                ->count();
        });

        return response()->json(['count' => $count]);
    }

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